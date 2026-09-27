#!/usr/bin/env bash
#
# pr-checks — suit la CI de la PR de la branche courante jusqu'au bout, SANS clic.
# Appelé par `make pr_checks`.
#
# Pourquoi pas un simple `gh pr checks --watch` :
#   le job `bump-version` de ci.yml pousse un commit « chore: bump versions » sur la
#   branche de la PR, avec le GITHUB_TOKEN (auteur github-actions[bot]). GitHub crée
#   bien un run CI pour ce push, mais le met en « Action required » : il attend une
#   approbation manuelle. Tant qu'il n'est pas approuvé, le nouveau HEAD de la PR n'a
#   aucun check → `gh pr checks` sort en erreur (« no checks reported ») et la PR reste
#   BLOCKED (ci-summary requis absent). Constaté sur toutes les PR depuis l'ajout du
#   job (run_attempt = 2 = approbation à la main).
#
# Ce script :
#   1. attend qu'un run existe pour le HEAD courant de la PR ;
#   2. approuve (API `actions/runs/{id}/approve`, équivalent du bouton « Approve and
#      run ») les runs en attente — UNIQUEMENT s'ils ont été déclenchés par
#      github-actions[bot] depuis CE dépôt (jamais un fork, jamais un tiers) ;
#   3. suit les runs jusqu'à la fin ; si le HEAD a bougé entre-temps (le bump vient
#      d'être poussé), recommence sur le nouveau HEAD ;
#   4. une fois le HEAD stable, `gh pr checks --watch` pour le verdict final.
#
# Sortie : 0 si CI verte, sinon le code de `gh pr checks` (pour `make pr_checks && …`).

set -uo pipefail

BOT='github-actions[bot]'
MAX_ROUNDS=5          # HEAD qui bouge plus de 5 fois = anormal, on arrête
WAIT_RUN_SECONDS=90   # délai max pour qu'un run apparaisse après un push

repo=$(gh repo view --json nameWithOwner --jq .nameWithOwner) || exit 1
branch=$(git rev-parse --abbrev-ref HEAD)

pr=$(gh pr view --json number,title,url 2>/dev/null) || {
  echo "Aucune PR ouverte pour la branche '$branch' (make pr_create pour en ouvrir une)."
  exit 1
}
num=$(jq -r .number <<<"$pr")
echo "PR #$num — $(jq -r .title <<<"$pr")"
echo "   branche : $branch"
echo "   URL     : $(jq -r .url <<<"$pr")"
echo "   début   : $(date '+%H:%M:%S')"
echo ""
start=$(date +%s)

head_sha() { gh pr view "$num" --json headRefOid --jq .headRefOid; }

# Runs du SHA donné, déclenchés par une PR de ce dépôt (pas un fork)
runs_json() {
  gh api "repos/$repo/actions/runs?head_sha=$1&event=pull_request&per_page=50" \
    --jq "[.workflow_runs[] | select(.head_repository.full_name == \"$repo\")
           | {id, name, status, conclusion, actor: .actor.login}]"
}

finish() {
  local rc=$1 elapsed verdict
  elapsed=$(( $(date +%s) - start ))
  echo ""
  if [ "$rc" -eq 0 ]; then verdict="✅ CI verte"; else verdict="❌ CI en échec (code $rc)"; fi
  printf '%s — PR #%s — durée totale : %dm%02ds\n' "$verdict" "$num" $(( elapsed / 60 )) $(( elapsed % 60 ))
  exit "$rc"
}

for round in $(seq 1 "$MAX_ROUNDS"); do
  sha=$(head_sha) || finish 1
  echo "▶ HEAD ${sha:0:8}"

  # 1. Attendre qu'au moins un run existe pour ce SHA
  waited=0
  while :; do
    runs=$(runs_json "$sha") || finish 1
    [ "$(jq length <<<"$runs")" -gt 0 ] && break
    if [ "$waited" -ge "$WAIT_RUN_SECONDS" ]; then
      echo "   aucun run CI après ${WAIT_RUN_SECONDS}s — on s'en remet à gh pr checks"
      break
    fi
    sleep 5; waited=$(( waited + 5 ))
  done

  # 2. Approuver les runs en attente déclenchés par le bot de CE dépôt
  for id in $(jq -r ".[] | select(.conclusion == \"action_required\" and .actor == \"$BOT\") | .id" <<<"$runs"); do
    name=$(jq -r ".[] | select(.id == $id) | .name" <<<"$runs")
    if gh api -X POST "repos/$repo/actions/runs/$id/approve" >/dev/null; then
      echo "   ✔ run « $name » ($id) approuvé automatiquement (commit du bot)"
    else
      echo "   ⚠ échec de l'approbation du run $id — à approuver à la main sur GitHub"
    fi
  done
  others=$(jq -r ".[] | select(.conclusion == \"action_required\" and .actor != \"$BOT\") | \"\(.name) (\(.actor))\"" <<<"$runs")
  [ -n "$others" ] && echo "   ⚠ runs en attente NON approuvés (acteur autre que le bot) : $others"

  # 3. Suivre les runs de ce SHA jusqu'à la fin
  for id in $(runs_json "$sha" | jq -r '.[].id'); do
    gh run watch "$id" --interval 10 >/dev/null 2>&1 || true
  done

  # 4. HEAD stable ? (le bump est poussé PENDANT le run : il est déjà là à la fin)
  sleep 5
  [ "$(head_sha)" = "$sha" ] && break
  echo "   HEAD a bougé (commit de bump poussé par la CI) — suivi du nouveau HEAD"
  [ "$round" -eq "$MAX_ROUNDS" ] && { echo "⛔ HEAD instable après $MAX_ROUNDS tours"; finish 1; }
done

echo ""
rc=0; gh pr checks "$num" --watch || rc=$?
finish "$rc"
