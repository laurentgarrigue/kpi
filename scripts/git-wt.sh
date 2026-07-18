#!/usr/bin/env bash
#
# git-wt — gestion des git worktrees pour le développement parallèle de features.
#
# Contexte KPI : le stack Docker monte `../sources` relatif au dossier courant, et
# plusieurs fichiers requis (docker/.env, sources/api2/.env, MyParams.php, .env.*)
# sont gitignorés. Un worktree fraîchement créé ne les a donc PAS. Ce script les
# recopie depuis le repo principal pour que `make docker_dev_up` fonctionne dans
# le worktree. Idem pour node_modules/ et api2/vendor (cf. COPY_DIRS : la copie
# est délibérée, les symlinks cassent Docker).
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
# ⚠️ PAS `--show-toplevel` : lancé depuis un worktree il retourne le worktree,
# et les nouveaux worktrees seraient créés imbriqués dedans. `--git-common-dir`
# pointe toujours vers le .git du repo principal, worktree ou non.
MAIN_REPO="$(dirname "$(git rev-parse --path-format=absolute --git-common-dir)")"
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
  # app4 utilise .env (pas .env.development comme app2/app3).
  "sources/app4/.env"
  # Tests Playwright en dev local (cf. tests/playwright/README.md).
  "sources/app4/.env.test.local"
  # Clés Lexik JWT : sans elles, /auth/login renvoie 500 (« unable to encode
  # the JWT token »). On copie plutôt que régénérer, sinon la passphrase de
  # sources/api2/.env ne correspond plus à la clé privée.
  "sources/api2/config/jwt/private.pem"
  "sources/api2/config/jwt/public.pem"
)

# Dossiers lourds recopiés du repo principal (~1,8 Go, ~10 s) plutôt que
# réinstallés via npm/composer (plusieurs minutes, et `npm install` app4 est
# fragile : OOM/crash-loop).
#
# ⚠️ NE PAS remplacer par des symlinks (ce fut le cas, ça cassait le stack) :
#   1. Docker ne suit pas un symlink dont la cible sort de ses montages. Le
#      conteneur ne voit qu'un lien pendant → « autoload_runtime.php not found »
#      (api2), « nuxt: not found » (app2/app4), et boucle de redémarrage.
#   2. Même en montant le repo principal dans le conteneur, ça casse : Vite et
#      Symfony ÉCRIVENT dans ces dossiers (node_modules/.cache, var/cache via le
#      projectDir résolu). En lecture seule → EROFS / « Unable to write in the
#      cache directory » ; en écriture → deux worktrees se disputent les mêmes
#      caches, avec des chemins absolus pointant vers le mauvais dépôt.
# Le coût est ~1,8 Go par worktree. C'est le prix de l'isolation.
COPY_DIRS=(
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
  for rel in "${COPY_DIRS[@]}"; do
    # Un symlink hérité d'une ancienne version du script : on le remplace.
    if [ -L "$dest/$rel" ]; then
      warn "  symlink obsolète remplacé par une copie : $rel"
      rm "$dest/$rel"
    fi
    if [ -d "$MAIN_REPO/$rel" ] && [ ! -e "$dest/$rel" ]; then
      mkdir -p "$dest/$(dirname "$rel")"
      cp -a "$MAIN_REPO/$rel" "$dest/$rel"
      # Caches du repo principal : chemins absolus vers le MAUVAIS dossier.
      rm -rf "$dest/$rel/.cache" "$dest/$rel/.vite"
      echo "  copié : $rel ($(du -sh "$dest/$rel" 2>/dev/null | cut -f1))"
    fi
  done
  # Cache Symfony compilé du repo principal : mêmes chemins en dur.
  rm -rf "$dest/sources/api2/var/cache"
  point_db_to_main_repo "$dest"
}

# HOST_DB_PATH/HOST_DBWP_PATH sont relatifs au dossier docker/ : tel quel, chaque
# worktree initialiserait une base MariaDB VIDE (« Table kp_user doesn't exist »
# au login). On les réécrit en absolu vers le repo principal pour partager les
# données de dev.
#
# Sans danger tant qu'UN SEUL stack tourne à la fois (règle du projet) : deux
# MariaDB sur le même datadir corrompraient InnoDB.
# ⚠️ Contrepartie : une migration Doctrine jouée depuis un worktree affecte la
# base de TOUTES les branches.
point_db_to_main_repo() {
  local dest="$1"
  local env_file="$dest/docker/.env"
  [ -f "$env_file" ] || return 0

  local changed=0
  for var in HOST_DB_PATH HOST_DBWP_PATH; do
    local cur
    cur="$(grep -E "^${var}=" "$env_file" | head -1 | cut -d= -f2-)"
    # Déjà absolu (worktree resynchronisé, ou choix délibéré) : on n'y touche pas.
    case "$cur" in
      /*) continue ;;
      '') continue ;;
    esac
    # ./db/mysql/ -> <repo principal>/docker/db/mysql/
    local abs="$MAIN_REPO/docker/${cur#./}"
    sed -i "s|^${var}=.*|${var}=${abs}|" "$env_file"
    echo "  base partagée : $var -> $abs"
    changed=1
  done
  [ "$changed" = 1 ] && warn "  ⚠ base de dev PARTAGÉE avec le repo principal : un seul stack Docker à la fois."
  return 0
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
