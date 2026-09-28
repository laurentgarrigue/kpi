#!/usr/bin/env bash
#
# Gestion des médias uploadés (logos, photos, visuels…) stockés HORS du dépôt Git.
# Doc : DOC/developer/infrastructure/MEDIA_STORAGE.md
#
# Usage (depuis la racine du dépôt, normalement via make) :
#   scripts/media/media.sh init                    # migre sources/img/<dossiers> → HOST_MEDIA_PATH
#   scripts/media/media.sh status                  # état du stockage et des montages Docker
#   scripts/media/media.sh sync-from <chemin>      # copie un autre stockage (ex. prod → préprod)
#   scripts/media/media.sh backup                  # sauvegarde restic + rétention
#   scripts/media/media.sh snapshots               # liste des sauvegardes
#   scripts/media/media.sh check                   # vérifie l'intégrité du dépôt restic
#   scripts/media/media.sh restore <id> [chemin]   # restaure dans un dossier À CÔTÉ (jamais en place)
#
# Variables lues dans docker/.env (ou l'environnement) :
#   HOST_MEDIA_PATH             stockage des médias (relatif = relatif à docker/, comme compose)
#   MEDIA_BACKUP_REPO           dépôt restic (défaut : /data/backups/$APPLICATION_NAME/media-restic)
#   MEDIA_BACKUP_PASSWORD_FILE  fichier du mot de passe restic (défaut : <repo>/../.media-restic-password)
#   APPLICATION_NAME            préfixe des conteneurs (défaut : kpi)
#
# Le script est autonome (aucune dépendance au Makefile) : il peut être lancé sur un serveur
# AVANT d'y déployer le commit qui retire les médias de Git (cf. MEDIA_STORAGE.md §3).

set -euo pipefail

# Dossiers de sources/img/ alimentés par des uploads. DOIT rester aligné avec :
#   - les montages de docker/compose.{dev,preprod,prod}.yaml
#   - les règles de .gitignore
MEDIA_DIRS=(logo KIP Nations presentations schemas referees)

RESTIC_IMAGE="restic/restic:0.17.3"
KEEP_DAILY=7
KEEP_WEEKLY=4
KEEP_MONTHLY=12

log()  { printf '\033[1;34m▶\033[0m %s\n' "$*"; }
ok()   { printf '\033[1;32m✔\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m⚠\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31m✖\033[0m %s\n' "$*" >&2; exit 1; }

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$REPO_ROOT"
[ -d sources ] && [ -d docker ] || die "À lancer depuis la racine du dépôt KPI (sources/ et docker/ introuvables)."

# Lecture d'une variable de docker/.env sans sourcer le fichier (il n'est pas du shell strict).
env_get() {
  local key="$1" val=""
  if [ -n "${!key:-}" ]; then printf '%s' "${!key}"; return; fi
  if [ -f docker/.env ]; then
    val="$(grep -E "^${key}=" docker/.env | tail -1 | cut -d= -f2- | sed -e 's/^["'\'']//' -e 's/["'\'']$//')"
  fi
  printf '%s' "$val"
}

APPLICATION_NAME="$(env_get APPLICATION_NAME)"; APPLICATION_NAME="${APPLICATION_NAME:-kpi}"

resolve_media_path() {
  local p; p="$(env_get HOST_MEDIA_PATH)"
  [ -n "$p" ] || die "HOST_MEDIA_PATH absent de docker/.env (ex. HOST_MEDIA_PATH=/data/media/${APPLICATION_NAME})."
  # Même convention que docker compose : un chemin relatif l'est par rapport à docker/.
  case "$p" in /*) ;; *) p="$REPO_ROOT/docker/${p#./}" ;; esac
  printf '%s' "${p%/}"
}

# Copie récursive SANS écraser l'existant (le stockage fait foi s'il a déjà un fichier).
copy_noclobber() {
  local src="$1" dst="$2"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a --ignore-existing --exclude 'Thumbs.db' "$src/" "$dst/"
  elif cp --update=none /dev/null /dev/null 2>/dev/null; then
    cp -a --update=none "$src/." "$dst/"
  else
    cp -a -n "$src/." "$dst/"
  fi
}

# --------------------------------------------------------------------------- init
cmd_init() {
  local media; media="$(resolve_media_path)"
  log "Stockage médias : $media"
  mkdir -p "$media/img" "$media/content"

  for d in "${MEDIA_DIRS[@]}"; do
    local src="sources/img/$d" dst="$media/img/$d"
    mkdir -p "$dst"

    # 1. Arbre de travail : fichiers suivis ET non suivis (uploads de prod, jamais commités).
    #    --ignore-existing : un fichier déjà présent dans le stockage (upload plus récent) gagne.
    if [ -d "$src" ] && [ -n "$(ls -A "$src" 2>/dev/null)" ]; then
      copy_noclobber "$src" "$dst"
    fi

    # 2. Historique Git : si le commit qui retire le dossier a déjà été tiré, Git a supprimé
    #    du disque les fichiers suivis. On les récupère dans le commit précédant ce retrait.
    #    Uniquement si le dossier n'est plus suivi : sinon, le dernier commit « D » serait la
    #    suppression volontaire d'un fichier isolé, qu'on ressusciterait à tort.
    local rev=""
    if [ -z "$(git ls-files -- "$src" 2>/dev/null | head -1)" ]; then
      rev="$(git log -1 --format=%H --diff-filter=D -- "$src" 2>/dev/null || true)"
    fi
    if [ -n "$rev" ] && git cat-file -e "${rev}^:$src" 2>/dev/null; then
      git archive "${rev}^" "$src" | tar -x --skip-old-files --strip-components=3 -C "$dst" 2>/dev/null || true
    fi

    ok "$(printf '%-14s' "$d") $(find "$dst" -type f | wc -l | tr -d ' ') fichiers, $(du -sh "$dst" | cut -f1)"
  done

  # 3. Images par défaut (placeholders référencés par le code) pour un stockage neuf (dev).
  if [ -d scripts/media/defaults/img ]; then
    copy_noclobber scripts/media/defaults/img "$media/img"
  fi

  ok "Migration terminée. Les fichiers de sources/img/ n'ont PAS été supprimés (ils sont masqués par les montages)."
  echo "   Étape suivante : (re)créer les conteneurs pour activer les montages, puis 'make media_status'."
}

# --------------------------------------------------------------------------- status
cmd_status() {
  local media; media="$(resolve_media_path)"
  echo "Stockage : $media"
  [ -d "$media" ] || { warn "absent → lancer 'make media_init'"; return 1; }
  local rc=0
  for d in "${MEDIA_DIRS[@]}"; do
    if [ -d "$media/img/$d" ]; then
      printf '  %-14s %6s fichiers  %s\n' "$d" "$(find "$media/img/$d" -type f | wc -l | tr -d ' ')" "$(du -sh "$media/img/$d" | cut -f1)"
    else
      printf '  %-14s MANQUANT\n' "$d"; rc=1
    fi
  done

  command -v docker >/dev/null 2>&1 || return $rc
  local php; php="$(env_get PHP_CONTAINER_NAME)"; php="${php//\$\{APPLICATION_NAME\}/$APPLICATION_NAME}"
  echo "Montages :"
  for c in "${php:-${APPLICATION_NAME}_php}" "${APPLICATION_NAME}_api2" "${APPLICATION_NAME}_event_cache_worker"; do
    if ! docker inspect "$c" >/dev/null 2>&1; then printf '  %-36s (conteneur absent)\n' "$c"; continue; fi
    local mounts n=0
    mounts="$(docker inspect -f '{{range .Mounts}}{{.Destination}} {{end}}' "$c")"
    for d in "${MEDIA_DIRS[@]}"; do
      case " $mounts " in *" /var/www/html/img/$d "*) n=$((n+1)) ;; esac
    done
    if [ "$n" -eq "${#MEDIA_DIRS[@]}" ]; then
      printf '  %-36s ✔ %d/%d\n' "$c" "$n" "${#MEDIA_DIRS[@]}"
    else
      printf '  %-36s ✖ %d/%d → recréer le conteneur (make docker_<env>_up)\n' "$c" "$n" "${#MEDIA_DIRS[@]}"; rc=1
    fi
  done
  return $rc
}

# --------------------------------------------------------------------------- sync-from
cmd_sync_from() {
  local src="${1:-}" media; media="$(resolve_media_path)"
  [ -n "$src" ] || die "Usage : media.sh sync-from <stockage source> (ex. /data/media/kpi)"
  [ -d "$src/img" ] || die "$src/img introuvable."
  [ "$(cd "$src" && pwd)" != "$(mkdir -p "$media" && cd "$media" && pwd)" ] || die "Source et destination identiques."
  command -v rsync >/dev/null 2>&1 || die "rsync requis."
  log "Copie $src → $media (écrase les fichiers modifiés, ne supprime rien)"
  rsync -a "$src/" "$media/"
  ok "Synchronisation terminée."
}

# --------------------------------------------------------------------------- restic
backup_repo() {
  local r; r="$(env_get MEDIA_BACKUP_REPO)"
  printf '%s' "${r:-/data/backups/${APPLICATION_NAME}/media-restic}"
}
backup_password_file() {
  local f; f="$(env_get MEDIA_BACKUP_PASSWORD_FILE)"
  printf '%s' "${f:-$(dirname "$(backup_repo)")/.media-restic-password}"
}

# restic tourne dans un conteneur éphémère : rien à installer sur l'hôte.
restic_run() {
  local repo pw media; repo="$(backup_repo)"; pw="$(backup_password_file)"; media="$(resolve_media_path)"
  docker run --rm -i \
    --hostname "${APPLICATION_NAME}-media" \
    -e RESTIC_REPOSITORY=/repo -e RESTIC_PASSWORD_FILE=/run/restic-password \
    -v "$repo:/repo" -v "$pw:/run/restic-password:ro" -v "$media:/data/media:ro" \
    ${RESTIC_EXTRA_VOLUME:+-v "$RESTIC_EXTRA_VOLUME"} \
    "$RESTIC_IMAGE" "$@"
}

ensure_repo() {
  command -v docker >/dev/null 2>&1 || die "docker requis."
  local repo pw; repo="$(backup_repo)"; pw="$(backup_password_file)"
  mkdir -p "$repo" || die "Impossible de créer $repo (droits ?)."
  if [ ! -s "$pw" ]; then
    ( umask 077; head -c 48 /dev/urandom | base64 | tr -d '\n/+=' > "$pw" )
    warn "Mot de passe restic généré dans $pw."
    warn "COPIEZ-LE dans le gestionnaire de mots de passe : sans lui, les sauvegardes sont illisibles."
  fi
  if ! restic_run cat config >/dev/null 2>&1; then
    log "Initialisation du dépôt restic $repo"
    restic_run init
  fi
}

cmd_backup() {
  local media; media="$(resolve_media_path)"
  [ -d "$media/img" ] || die "$media/img introuvable : lancer 'make media_init' d'abord."
  ensure_repo
  log "Sauvegarde de $media → $(backup_repo)"
  restic_run backup /data/media --tag "$APPLICATION_NAME" --exclude 'Thumbs.db'
  log "Rétention : ${KEEP_DAILY} quotidiennes, ${KEEP_WEEKLY} hebdomadaires, ${KEEP_MONTHLY} mensuelles"
  restic_run forget --tag "$APPLICATION_NAME" --host "${APPLICATION_NAME}-media" \
    --keep-daily "$KEEP_DAILY" --keep-weekly "$KEEP_WEEKLY" --keep-monthly "$KEEP_MONTHLY" --prune
  ok "Sauvegarde terminée."
}

cmd_snapshots() { ensure_repo; restic_run snapshots --tag "$APPLICATION_NAME"; }

cmd_check() {
  ensure_repo
  # Lit 5 % des données à chaque contrôle : détecte une corruption sans tout relire.
  restic_run check --read-data-subset=5%
}

cmd_restore() {
  local snap="${1:-}" path="${2:-}"
  [ -n "$snap" ] || die "Usage : media.sh restore <snapshot|latest> [img/logo/fichier.png]"
  ensure_repo
  local media target; media="$(resolve_media_path)"
  target="${media}.restore-$(date +%Y%m%d-%H%M%S)"
  mkdir -p "$target"
  log "Restauration de $snap${path:+ ($path)} dans $target"
  RESTIC_EXTRA_VOLUME="$target:/restore" restic_run restore "$snap" --target /restore \
    ${path:+--include "/data/media/${path#/}"}
  ok "Restauré dans $target/data/media/ — rien n'a été écrasé."
  echo "   Pour remettre en place (après vérification) :"
  echo "   rsync -a \"$target/data/media/\" \"$media/\""
}

# --------------------------------------------------------------------------- main
case "${1:-}" in
  init)       cmd_init ;;
  status)     cmd_status ;;
  sync-from)  shift; cmd_sync_from "${1:-}" ;;
  backup)     cmd_backup ;;
  snapshots)  cmd_snapshots ;;
  check)      cmd_check ;;
  restore)    shift; cmd_restore "${1:-}" "${2:-}" ;;
  *) sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'; exit 1 ;;
esac
