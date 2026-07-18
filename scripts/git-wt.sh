#!/usr/bin/env bash
#
# git-wt — gestion des git worktrees pour le développement parallèle de features.
#
# Contexte KPI : le stack Docker monte `../sources` relatif au dossier courant, et
# plusieurs fichiers requis (docker/.env, sources/api2/.env, MyParams.php, .env.*)
# sont gitignorés. Un worktree fraîchement créé ne les a donc PAS. Ce script les
# recopie/relie depuis le repo principal pour que `make docker_dev_up` fonctionne
# dans le worktree.
#
# Règle d'or : UN SEUL stack Docker à la fois. Arrête le stack courant avant de
# `make docker_dev_up` depuis un autre worktree (conflits de ports/noms sinon).
#
# Usage :
#   scripts/git-wt.sh new <feature-name> [base-branch]   # crée worktree + branche feature/<name>
#   scripts/git-wt.sh list                               # liste les worktrees
#   scripts/git-wt.sh rm <feature-name>                  # supprime le worktree (garde la branche)
#   scripts/git-wt.sh sync <feature-name>                # re-copie les fichiers non-versionnés
#
set -euo pipefail

# Répertoire du repo principal (celui qui contient le .git réel).
MAIN_REPO="$(git rev-parse --show-toplevel)"
# Les worktrees vivent à côté du repo principal, dans un dossier frère.
WT_ROOT="$(dirname "$MAIN_REPO")/$(basename "$MAIN_REPO")-worktrees"
DEFAULT_BASE="develop"

# Fichiers non-versionnés à propager du repo principal vers chaque worktree.
# Copie (pas lien) pour docker/.env : on peut vouloir l'ajuster par worktree.
COPY_FILES=(
  "docker/.env"
  "docker/MyParams.php"
  "docker/MyConfig.php"
  "sources/api2/.env"
  "sources/app2/.env.development"
  "sources/app3/.env.development"
  "sources/app4/.env.development"
)

# Dossiers lourds à partager par symlink plutôt que réinstaller (node_modules).
# Symlink = gain de temps ET d'espace ; sûr tant qu'on ne fait pas tourner deux
# `npm install` concurrents (on ne fait qu'un stack à la fois de toute façon).
LINK_DIRS=(
  "sources/app2/node_modules"
  "sources/app3/node_modules"
  "sources/app4/node_modules"
  "sources/api2/vendor"
)

log()  { printf '\033[1;34m▶\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m⚠\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31m✖\033[0m %s\n' "$*" >&2; exit 1; }

propagate_files() {
  local dest="$1"
  log "Propagation des fichiers non-versionnés vers $dest"
  for rel in "${COPY_FILES[@]}"; do
    if [ -e "$MAIN_REPO/$rel" ]; then
      mkdir -p "$dest/$(dirname "$rel")"
      cp -a "$MAIN_REPO/$rel" "$dest/$rel"
      echo "  copié : $rel"
    else
      warn "  absent dans le repo principal, ignoré : $rel"
    fi
  done
  for rel in "${LINK_DIRS[@]}"; do
    if [ -d "$MAIN_REPO/$rel" ] && [ ! -e "$dest/$rel" ]; then
      mkdir -p "$dest/$(dirname "$rel")"
      ln -s "$MAIN_REPO/$rel" "$dest/$rel"
      echo "  lié   : $rel -> repo principal"
    fi
  done
}

cmd_new() {
  local name="${1:?usage: git-wt new <feature-name> [base-branch]}"
  local base="${2:-$DEFAULT_BASE}"
  local branch="feature/$name"
  local dest="$WT_ROOT/$name"

  [ -e "$dest" ] && die "Le worktree existe déjà : $dest"

  log "Mise à jour de $base depuis origin"
  git -C "$MAIN_REPO" fetch origin "$base" --quiet || warn "fetch $base échoué (offline ?)"

  mkdir -p "$WT_ROOT"
  if git -C "$MAIN_REPO" show-ref --verify --quiet "refs/heads/$branch"; then
    log "Branche $branch existante → worktree dessus"
    git -C "$MAIN_REPO" worktree add "$dest" "$branch"
  else
    log "Création de $branch à partir de origin/$base"
    git -C "$MAIN_REPO" worktree add -b "$branch" "$dest" "origin/$base"
  fi

  propagate_files "$dest"

  log "Worktree prêt : $dest (branche $branch)"
  echo
  echo "  Ouvrir dans VS Code :   code \"$dest\""
  echo "  Démarrer le stack   :   cd \"$dest\" && make docker_dev_up"
  echo "  (arrête d'abord tout autre stack : make docker_dev_down)"
}

cmd_list() {
  git -C "$MAIN_REPO" worktree list
}

cmd_rm() {
  local name="${1:?usage: git-wt rm <feature-name>}"
  local dest="$WT_ROOT/$name"
  [ -e "$dest" ] || die "Worktree introuvable : $dest"
  log "Suppression du worktree $dest (la branche est conservée)"
  git -C "$MAIN_REPO" worktree remove "$dest" || die "Échec ; commit/stash tes changements d'abord, ou --force manuellement"
  log "Fait. Pour supprimer aussi la branche : git branch -d feature/$name"
}

cmd_sync() {
  local name="${1:?usage: git-wt sync <feature-name>}"
  local dest="$WT_ROOT/$name"
  [ -e "$dest" ] || die "Worktree introuvable : $dest"
  propagate_files "$dest"
}

case "${1:-}" in
  new)  shift; cmd_new  "$@" ;;
  list) shift; cmd_list "$@" ;;
  rm)   shift; cmd_rm   "$@" ;;
  sync) shift; cmd_sync "$@" ;;
  *)
    cat <<EOF
git-wt — worktrees pour développer plusieurs features en parallèle.

  scripts/git-wt.sh new <feature-name> [base-branch]   crée le worktree + branche feature/<name> (base: $DEFAULT_BASE)
  scripts/git-wt.sh list                               liste les worktrees
  scripts/git-wt.sh rm  <feature-name>                 supprime le worktree (garde la branche)
  scripts/git-wt.sh sync <feature-name>                re-copie les fichiers non-versionnés (.env, etc.)

Les worktrees sont créés dans : $WT_ROOT
EOF
    ;;
esac
