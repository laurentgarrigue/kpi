#!/usr/bin/env bash
#
# pr-checks — suit la CI de la PR de la branche courante jusqu'au bout, SANS clic.
# Appelé par `make pr_checks`. Relançable à tout moment (idempotent).
#
# Pourquoi pas un simple `gh pr checks --watch` :
#   le job `bump-version` de ci.yml pousse un commit « chore: bump versions » sur la
#   branche de la PR, avec le GITHUB_TOKEN (auteur github-actions[bot]). GitHub crée
#   bien un run CI pour ce push, mais le met en « Action required » : il attend une
#   approbation. Tant qu'il n'est pas approuvé, le nouveau HEAD n'a aucun check et la
#   PR reste BLOCKED (ci-summary requis absent).
#
# Une seule boucle de suivi (toutes les POLL secondes) :
#   1. suit le HEAD courant de la PR — s'il bouge (commit de bump), bascule dessus
#      AUSSITÔT, sans attendre la fin du run du SHA périmé ;
#   2. approuve immédiatement (API `actions/runs/{id}/approve`) les runs en attente
#      du HEAD — UNIQUEMENT ceux déclenchés par github-actions[bot] depuis CE dépôt
#      (jamais un fork, jamais un tiers) ;
#   3. affiche la progression job par job (démarrage, verdict + durée, et un point
#      d'étape par minute de silence) ;
#   4. job coincé « queued » sans runner au-delà de STUCK_SECONDS (incident GitHub,
#      vu sur la PR #336) : annule le run et relance ses jobs ; run du HEAD trouvé
#      annulé (concurrence, clic dans l'UI) : relancé — MAX_RETRIES fois par run ;
#   5. quand tous les runs du HEAD sont finis et que le HEAD n'a plus bougé,
#      `gh pr checks` donne le verdict final.
#
# Ne JAMAIS approuver / relancer à la main dans l'UI pendant que ce script tourne :
# chaque run qui démarre annule l'autre via le groupe `concurrency` de ci.yml.
#
# Sortie : 0 si CI verte, sinon non-nul (pour `make pr_checks && make pr_merge`).

set -uo pipefail

BOT='github-actions[bot]'
POLL=10               # secondes entre deux tours
WAIT_RUN_SECONDS=120  # délai max pour qu'un run apparaisse sur un nouveau HEAD
STUCK_SECONDS=300     # job « queued » au-delà → bloqué côté GitHub
MAX_RETRIES=1         # relances automatiques d'un run bloqué
HEARTBEAT=60          # point d'étape si rien n'a bougé depuis N secondes

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

now() { date +%s; }
say() { printf '   %s  %s\n' "$(date '+%H:%M:%S')" "$*"; last_out=$(now); }

finish() {
  local rc=$1 elapsed verdict
  elapsed=$(( $(now) - start ))
  echo ""
  if [ "$rc" -eq 0 ]; then verdict="✅ CI verte"; else verdict="❌ CI en échec (code $rc)"; fi
  printf '%s — PR #%s — durée totale : %dm%02ds\n' "$verdict" "$num" $(( elapsed / 60 )) $(( elapsed % 60 ))
  exit "$rc"
}

head_sha() { gh pr view "$num" --json headRefOid --jq .headRefOid; }

# Runs du SHA donné, déclenchés par une PR de ce dépôt (pas un fork) — le plus
# récent par workflow (un run remplacé ne compte plus)
runs_json() {
  gh api "repos/$repo/actions/runs?head_sha=$1&event=pull_request&per_page=50" \
    --jq "[.workflow_runs[] | select(.head_repository.full_name == \"$repo\")
           | {id, name, status, conclusion, actor: .actor.login}]
          | group_by(.name) | map(max_by(.id))"
}

# Jobs d'un run (dernière tentative), une ligne TSV par job :
#   id  nom  status  conclusion  durée(s)  âge-en-file(s)
# (conclusion vide → « - » : `read` fusionne les tabulations consécutives)
jobs_tsv() {
  gh api "repos/$repo/actions/runs/$1/jobs?per_page=100" --jq '
    .jobs[] | [
      .id, .name, .status, (.conclusion // "-"),
      (if .started_at and .completed_at
         then ([(.completed_at|fromdateiso8601) - (.started_at|fromdateiso8601), 0] | max)
         else 0 end),
      (if .status == "queued" or .status == "waiting"
         then (now - ((.created_at // .started_at) | fromdateiso8601) | floor)
         else 0 end)
    ] | @tsv'
}

# Relance les jobs échoués/annulés d'un run terminé (à défaut, le run entier)
rerun_run() {
  gh api -X POST "repos/$repo/actions/runs/$1/rerun-failed-jobs" >/dev/null 2>&1 \
    || gh api -X POST "repos/$repo/actions/runs/$1/rerun" >/dev/null 2>&1
}

# Annule un run bloqué, attend qu'il soit terminé, puis le relance
retry_stuck_run() {
  local id=$1
  gh api -X POST "repos/$repo/actions/runs/$id/cancel" >/dev/null 2>&1 || true
  for _ in $(seq 1 18); do
    [ "$(gh api "repos/$repo/actions/runs/$id" --jq .status 2>/dev/null)" = "completed" ] && break
    sleep 5
  done
  rerun_run "$id"
}

declare -A job_state=()   # job id → dernier état affiché
declare -A handled=()     # run id → approbation / avertissement déjà traité
declare -A retries=()     # run id → nb de relances (blocage ou annulation)
cur=""
head_since=0
last_out=$(now)

while :; do
  sha=$(head_sha 2>/dev/null) || { sleep "$POLL"; continue; }
  if [ "$sha" != "$cur" ]; then
    [ -n "$cur" ] && echo "" && echo "   HEAD a bougé (commit poussé par la CI, ex. bump de version) — suivi du nouveau HEAD"
    echo "▶ HEAD ${sha:0:8} — $(gh api "repos/$repo/commits/$sha" --jq '.commit.message | split("\n")[0]' 2>/dev/null)"
    cur=$sha; head_since=$(now); last_out=$(now)
  fi

  runs=$(runs_json "$sha" 2>/dev/null) || { sleep "$POLL"; continue; }
  if [ "$(jq length <<<"$runs")" -eq 0 ]; then
    if [ $(( $(now) - head_since )) -ge "$WAIT_RUN_SECONDS" ]; then
      say "aucun run CI après ${WAIT_RUN_SECONDS}s — on s'en remet à gh pr checks"
      break
    fi
    sleep "$POLL"; continue
  fi

  all_done=1
  # mapfile plutôt que `while read < <(…)` : les appels gh dans la boucle ne
  # doivent pas pouvoir avaler l'entrée de la boucle.
  mapfile -t run_lines < <(jq -r '.[] | [.id, .name, .status, (.conclusion // "-"), .actor] | @tsv' <<<"$runs")
  for run_line in "${run_lines[@]}"; do
    IFS=$'\t' read -r rid rname rstatus rconcl ractor <<<"$run_line"
    # Run en attente d'approbation
    if [ "$rconcl" = "action_required" ]; then
      if [ "$ractor" = "$BOT" ]; then
        all_done=0
        if [ -z "${handled[$rid]:-}" ]; then
          if gh api -X POST "repos/$repo/actions/runs/$rid/approve" >/dev/null 2>&1 </dev/null; then
            say "✔ run « $rname » approuvé automatiquement (commit du bot)"
            handled[$rid]=1
          else
            say "⚠ échec de l'approbation du run « $rname » ($rid) — nouvel essai au prochain tour"
          fi
        fi
      elif [ -z "${handled[$rid]:-}" ]; then
        say "⚠ run « $rname » en attente d'approbation, acteur $ractor (pas le bot) — NON approuvé"
        handled[$rid]=1
      fi
      continue
    fi

    # Run du HEAD annulé (concurrence entre runs, annulation dans l'UI…) : sans
    # relance, la PR resterait sans ci-summary vert → on le relance une fois.
    if [ "$rconcl" = "cancelled" ] && [ "${retries[$rid]:-0}" -lt "$MAX_RETRIES" ]; then
      retries[$rid]=$(( ${retries[$rid]:-0} + 1 ))
      if rerun_run "$rid" </dev/null; then
        say "↻ run « $rname » annulé — relancé automatiquement"
        all_done=0; continue
      fi
      say "⚠ échec de la relance du run « $rname » ($rid)"
    fi

    [ "$rstatus" != "completed" ] && all_done=0

    stuck=""
    mapfile -t job_lines < <(jobs_tsv "$rid" 2>/dev/null </dev/null)
    for job_line in "${job_lines[@]}"; do
      IFS=$'\t' read -r jid jname jstatus jconcl jdur jage <<<"$job_line"
      state="$jstatus/$jconcl"
      [ "${job_state[$jid]:-}" = "$state" ] && { [ "$jage" -ge "$STUCK_SECONDS" ] && stuck=$jname; continue; }
      job_state[$jid]=$state
      case "$jstatus/$jconcl" in
        in_progress/*)      say "⟳ $jname" ;;
        completed/success)  say "✓ $jname ($(( jdur / 60 ))m$(printf '%02d' $(( jdur % 60 )))s)" ;;
        completed/failure)  say "✗ $jname — ÉCHEC" ;;
        completed/cancelled) say "⊘ $jname (annulé)" ;;
        completed/skipped)  ;;   # brique non touchée ou SHA périmé : silencieux
        completed/*)        say "· $jname ($jconcl)" ;;
      esac
      [ "$jage" -ge "$STUCK_SECONDS" ] && stuck=$jname
    done

    if [ -n "$stuck" ] && [ "$rstatus" != "completed" ]; then
      n=${retries[$rid]:-0}
      if [ "$n" -lt "$MAX_RETRIES" ]; then
        say "⚠ « $stuck » attend un runner depuis plus de $(( STUCK_SECONDS / 60 )) min (incident GitHub ?) — annulation + relance du run"
        retry_stuck_run "$rid" </dev/null
        retries[$rid]=$(( n + 1 ))
      else
        say "⛔ « $stuck » toujours sans runner après relance — souci côté GitHub (https://www.githubstatus.com)"
        say "   relance plus tard : make pr_checks"
        finish 1
      fi
    fi
  done

  if [ "$all_done" -eq 1 ]; then
    # Tous les runs du HEAD sont finis : HEAD stable ? (un push tardif relancerait tout)
    sleep "$POLL"
    [ "$(head_sha 2>/dev/null)" = "$sha" ] && break
    continue
  fi

  # Point d'étape si rien n'a été affiché depuis un moment
  if [ $(( $(now) - last_out )) -ge "$HEARTBEAT" ]; then
    e=$(( $(now) - start ))
    say "… toujours en cours ($(( e / 60 ))m$(printf '%02d' $(( e % 60 )))s écoulées)"
  fi
  sleep "$POLL"
done

echo ""
rc=0; gh pr checks "$num" --watch --interval 10 || rc=$?
finish "$rc"
