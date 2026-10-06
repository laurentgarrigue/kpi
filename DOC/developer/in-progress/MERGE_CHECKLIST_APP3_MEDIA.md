# Checklist de merge — suppression de l'ancien app3 et médias hors Git

**Branche** : `claude/public_site_redesign_strategy`
**Commits concernés** : `cd4ef770` (Chore: remove legacy app3 match-sheet prototype) et `2033ca0d`
(Feat: move uploaded media out of Git with restic backups)
**Contexte** : phases 0a et 0b de [PUBLIC_SITE_REDESIGN_STRATEGY.md](plans/PUBLIC_SITE_REDESIGN_STRATEGY.md) ;
procédure détaillée des médias dans [MEDIA_STORAGE.md](../infrastructure/MEDIA_STORAGE.md)

> **Convention** (comme le [runbook](../infrastructure/DEPLOYMENT_RUNBOOK.md)) : ⌨️ = poste de dev,
> 🖥 = sur le VPS (en SSH, en tant que `laurent`), 🌐 = interface GitHub.

---

## Pourquoi l'ordre compte

Il n'y a plus de branche `develop` : tout passe par `main` (cf. [GIT_WORKFLOW.md](../guides/GIT_WORKFLOW.md)).
Le merge de la PR sur `main` **déploie automatiquement la préprod**. Toute release taguée ensuite
(`vX.Y.Z`) contiendra ce changement : la prod doit être préparée **avant** le premier
« Deploy production » sur un tag postérieur au merge. Le déploiement (`git reset --hard`)
**supprime du disque** les images qui étaient suivies par Git. Les fichiers jamais commités ne sont
pas touchés. De plus, dès ce déploiement, `docker compose` **refuse de démarrer** si `HOST_MEDIA_PATH`
est absent de `docker/.env`.

On prépare donc chaque serveur **avant** que le code n'y arrive.

`make media_init` sait récupérer dans l'historique Git les fichiers supprimés par un déploiement
trop précoce. Il ne peut en revanche rien pour une image **remplacée sur place** par un upload
(même nom) : seule la version sur le disque est à jour.

---

## Étape 0 — Avant d'ouvrir / merger la PR

### 0.1 ⌨️ Publier le tag d'archive de l'ancien app3

✅ **Fait le 29/09/2026** : le tag pointe sur `cdb2081014`. Commande utilisée, pour mémoire :

```bash
git fetch origin
git tag -a archive/app3-matchsheet cdb2081014 -m "Archive: former app3 match-sheet prototype (Nuxt)"
git push origin archive/app3-matchsheet
```

`cdb2081014` est le dernier commit de `main` contenant l'ancien `sources/app3/`.

- [x] Tag publié : `git ls-remote --tags origin archive/app3-matchsheet` renvoie une ligne

### 0.2 ⌨️ Coordination avec la branche scoring

✅ **Fait le 29/09/2026** : la branche `claude/scoring-refactoring-strategy-3d43ac` référence désormais le
tag dans `DOC/specs/PAGE_SCORING.md` et `DOC/developer/reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md`.
Ces fichiers **ne sont pas modifiés ici**, pour éviter les conflits : c'est la version de la branche scoring
qui fait foi.

- [x] Signaler à cette branche de remplacer les chemins par le tag, par exemple :
  `git show archive/app3-matchsheet:sources/app3/composables/useBroadcast.ts`
- [ ] Conflits attendus au merge : `Makefile` et `docker/compose.dev.yaml` sont modifiés des deux
  côtés, mais dans des zones différentes. Résolution : **garder la suppression des blocs app3** et
  les ajouts scoring.

### 0.3 ⌨️ Tests api2

Le lot corrige la résolution des chemins legacy dans api2 (paramètre `legacy_document_root`). Seul
un `php -l` a pu être lancé pendant le développement : le conteneur Symfony n'a pas été compilé et
les tests n'ont pas tourné.

```bash
make api2_test                 # unit + integration
make api2_cache_clear          # le conteneur Symfony doit compiler sans erreur (nouveau bind)
```

- [x] `make api2_test` vert (29/09/2026)
- [x] Aucune erreur de compilation du conteneur (`legacy_document_root`, bind `$legacyDocumentRoot`)

> **Bug trouvé au contrôle d'upload (29/09/2026)** : « Warning: mkdir(): Permission denied ».
> `legacy_document_root` valait le littéral `'/var/www/html'`. Quand le cache est compilé depuis
> `event-cache-worker` ou le conteneur Apache (projet dans `/var/www/html/api2`), ce chemin est un
> ancêtre de `var/cache/` : le dumper Symfony le réécrit en `dirname(__DIR__, 5)`, soit `/` dans
> api2 (projet dans `/app`). Corrigé : paramètre lu depuis la variable d'env `LEGACY_DOCUMENT_ROOT`,
> définie dans `docker/compose.*.yaml` (api2 + worker) et `sources/api2/.env.test`. L'ancien
> `live_document_root` avait le même défaut (cause probable de « Cache directory does not exist »).
>
> **Second bug au même contrôle** : 500 « undefined function imagecreatefromjpeg ». L'image api2 était
> construite **sans gd** (choix de l'analyse FrankenPHP §8quinquies, qui n'avait pas vu
> `ImageOperationsService`). Corrigé : gd (JPEG + PNG) ajoutée dans `docker/config/Dockerfile.api2`.
> **L'image api2 doit être reconstruite** sur chaque serveur ; vérifier après déploiement :
> `docker exec <app>_api2 php -r 'var_dump(gd_info()["JPEG Support"]);'` → `bool(true)`.
> Sinon : `make docker_<env>_rebuild`, ou plus ciblé `docker compose -f docker/compose.<env>.yaml build api2<_preprod>` puis `up -d`.

### 0.4 ⌨️ Poste de dev

```bash
echo "HOST_MEDIA_PATH=$HOME/kpi-media" >> docker/.env     # chemin ABSOLU (worktrees)
make media_init          # copie sources/img/<dossiers> + historique Git + images par défaut
make docker_dev_up       # recrée kpi / api2 / worker avec les montages
make media_status        # ✔ 6/6 pour les trois conteneurs
```

- [ ] `make media_status` : 6/6 montages sur `kpi_php`, `kpi_api2`, `kpi_event_cache_worker`
- [ ] Contrôles fonctionnels en dev (voir [§ Contrôles](#contrôles-fonctionnels))
- [ ] Worktrees existants : ajouter la même ligne `HOST_MEDIA_PATH` dans leur `docker/.env`

---

## Étape 1 — Préprod (`/data/kpi_preprod`)

### 1.1 🖥 AVANT le merge sur `main`

```bash
cd /data/kpi_preprod

# a. Déclarer le stockage et la sauvegarde
cat >> docker/.env <<'EOF'
HOST_MEDIA_PATH=/data/media/kpi_preprod
MEDIA_BACKUP_REPO=/data/backups/kpi_preprod/media-restic
MEDIA_BACKUP_PASSWORD_FILE=/data/backups/kpi_preprod/.media-restic-password
EOF

# b. Créer les dossiers (droits alignés sur les volumes existants : 1000:33 + ACL deploy)
sudo mkdir -p /data/media/kpi_preprod /data/backups/kpi_preprod
sudo chown 1000:33 /data/media/kpi_preprod
sudo setfacl -R -m u:deploy:rwX /data/media/kpi_preprod /data/backups/kpi_preprod
sudo setfacl -R -d -m u:deploy:rwX /data/media/kpi_preprod /data/backups/kpi_preprod

# c. Copier les médias avec le script de la branche, sans toucher à l'arbre de travail
# FETCH_HEAD et non origin/<branche> : .git/logs/refs/remotes/ appartient à `deploy` (CI),
# `laurent` ne peut pas y écrire → « cannot update the ref … Permission non accordée ».
# Le fetch réussit quand même (objets + FETCH_HEAD) ; NE PAS toucher aux droits de .git.
git fetch origin claude/public_site_redesign_strategy
git show FETCH_HEAD:scripts/media/media.sh > /tmp/media.sh
bash /tmp/media.sh init
```

- [x] `docker/.env` contient les 3 variables
- [x] `bash /tmp/media.sh init` affiche un nombre de fichiers non nul pour chaque dossier
- [x] Taille cohérente : `du -sh /data/media/kpi_preprod` ≈ `du -sh sources/img/{logo,KIP,Nations,presentations,schemas,referees}`

### 1.2 ⌨️ Merger la PR sur `main`

```bash
make pr_checks && make pr_merge     # squash-merge sur main
```

Le push sur `main` déclenche « Deploy preprod » automatiquement.

- [ ] Workflow de déploiement préprod vert

> **Piège rencontré en préprod (04/10/2026)** : « Deploy preprod » a échoué dès `git reset --hard` avec
> `Entry 'sources/img/KIP/…' not uptodate. Cannot merge.` Cause : ~1 200 images de `sources/img/`
> portaient le bit **`skip-worktree`** (ancienne astuce pour que les uploads ne gênent pas les
> déploiements). `reset --hard` refuse de supprimer un fichier marqué ainsi. Corrigé en levant le bit
> (cf. commande en 2.1), puis en relançant le job. **La prod a le même piège (1 203 fichiers)**.
>
> **Second échec (même jour)** : api2 en boucle de redémarrage, sans aucun message dans ses logs. Le
> rebuild a tiré `dunglas/frankenphp:php8.4` en **FrankenPHP 1.13.0** (1.12.4 en dev), dont le module
> Mercure refuse `anonymous 0` : `anonymous` y est un flag sans argument. Visible seulement via
> `frankenphp validate --config /etc/frankenphp/Caddyfile` dans l'image. Corrigé : directive injectée par
> `MERCURE_EXTRA_DIRECTIVES` (`anonymous` en dev, non définie ailleurs). `MERCURE_ANONYMOUS` disparaît.
> Le rollback automatique a lui aussi échoué (cf. Retour arrière) : la préprod est restée sur le nouveau
> code, api2 hors service. La PR de revert ouverte par le workflow (#331) est à **fermer sans merger**.
>
> **Troisième échec (05/10)** : (1) le wrapper lançait les étapes api2 (`docker exec`) AVANT le rebuild
> de la stack, donc dans le conteneur cassé → corrigé dans vps-manager (`a7c6450`, ordre stack → apps
> → composer → api2 ; le rollback rebuild aussi). (2) Un 2ᵉ changement cassant de Mercure en 1.13 :
> `publisher_jwt`/`subscriber_jwt` exigent `protocol_version_compatibility 8` (1.12 n'acceptait que 7).
> **FrankenPHP est désormais épinglé dans `Dockerfile.api2`** (`1.13.0-php8.4`) ; `BASE_IMAGE_FRANKENPHP`
> de `docker/.env` n'est plus lue (on peut la retirer des `.env`). Validé en dev : api2 `healthy`,
> publish JWT valide 200 / invalide 401, abonnement anonyme 200.

### 1.3 🖥 Après le déploiement

```bash
cd /data/kpi_preprod
make docker_preprod_up        # recrée les conteneurs si le déploiement ne l'a pas fait
make api2_restart
make media_init               # idempotent : complète depuis l'historique Git si besoin
make media_status             # ✔ 6/6 pour les trois conteneurs
make media_backup             # 1er backup : génère le mot de passe restic
cat /data/backups/kpi_preprod/.media-restic-password   # → gestionnaire de mots de passe
make media_backup_list
```

- [ ] `make media_status` : 6/6 sur les trois conteneurs
- [ ] api2 démarre (`docker ps` : `healthy`, pas `Restarting`) — sinon `docker exec`/`docker run … frankenphp validate --config /etc/frankenphp/Caddyfile`
- [ ] gd présente dans api2 (image reconstruite) : `docker exec kpi_preprod_api2 php -r 'var_dump(gd_info()["JPEG Support"]);'`
- [ ] Premier backup OK, **mot de passe copié en lieu sûr**
- [ ] Uploads égarés (bug corrigé) : `ls -R sources/api2/public/img 2>/dev/null | head`
  - vide → rien à faire
  - sinon → `rsync -a --ignore-existing sources/api2/public/img/ /data/media/kpi_preprod/img/` (vérifier d'abord les doublons)
- [ ] Contrôles fonctionnels (voir [§ Contrôles](#contrôles-fonctionnels))

---

## Étape 2 — Production (`/data/kpi`)

### 2.1 🖥 AVANT « Deploy production »

Même préparation qu'en préprod, avec les chemins de prod :

```bash
cd /data/kpi

cat >> docker/.env <<'EOF'
HOST_MEDIA_PATH=/data/media/kpi
MEDIA_BACKUP_REPO=/data/backups/kpi/media-restic
MEDIA_BACKUP_PASSWORD_FILE=/data/backups/kpi/.media-restic-password
EOF

sudo mkdir -p /data/media/kpi
sudo chown 1000:33 /data/media/kpi
sudo setfacl -R -m u:deploy:rwX /data/media/kpi /data/backups/kpi
sudo setfacl -R -d -m u:deploy:rwX /data/media/kpi /data/backups/kpi

git fetch origin main                                            # la PR est mergée sur main depuis l'étape 1.2
git show FETCH_HEAD:scripts/media/media.sh > /tmp/media.sh      # FETCH_HEAD : cf. 1.1 (droits .git)
bash /tmp/media.sh init
```

**Puis lever le bit `skip-worktree`** (sinon « Deploy production » échoue sur `reset --hard`, cf. 1.2).
D'abord vérifier qu'aucun fichier marqué ne diffère de sa version Git **hors des 6 dossiers** (ex.
`sources/img/Pays/`, qui reste suivi : un fichier modifié y serait écrasé par le déploiement) :

```bash
cd /data/kpi
git ls-files -v | grep '^S ' | cut -c3- | while IFS= read -r f; do
  [ -e "$f" ] && [ "$(git rev-parse ":$f")" != "$(git hash-object -- "$f")" ] && echo "DIFF $f"
done
# → DIFF hors des 6 dossiers : copier le fichier de côté avant de continuer
git ls-files -v -z | tr '\0' '\n' | grep '^S ' | cut -c3- | tr '\n' '\0' | xargs -0 git update-index --no-skip-worktree --
git ls-files -v | grep -c '^S '     # → 0
git status --short                  # → vide (sinon : ce sont des modifs locales réelles, à examiner)
```

- [ ] Fichiers non suivis (`git status --short`, `??`) : ceux des 6 dossiers doivent être dans le
  stockage (`cmp sources/img/<f> /data/media/kpi/img/<f>`) ; ceux d'autres dossiers versionnés (ex.
  `sources/img/Pays/`) sont à **committer** ; ceux de `sources/api2/public/img/` (uploads égarés) sont
  à copier dans `/data/media/kpi/img/` **et** dans `sources/img/` (visibles tout de suite en prod).
  Fait le 05/10 : 7 images déjà dans le stockage, `Pays/WAL.png` committée, `logo/L-WCM-2026.png` rapatriée.
- [ ] Variables présentes dans `docker/.env`
- [ ] Plus aucun fichier `skip-worktree` (`git ls-files -v | grep -c '^S '` → 0)
- [ ] `init` OK. En prod, le volume attendu est d'environ **437 Mo** au total pour `sources/img/`,
  dont la plus grande partie dans ces six dossiers.

### 2.2 ⌨️ 🌐 Taguer puis déployer

⌨️ Poser le tag de release sur `main` à jour (s'il n'existe pas déjà un tag postérieur au merge) :

```bash
git checkout main && git pull
make release_tag version=X.Y.Z
```

🌐 Actions → « Deploy production » → Run workflow depuis `main`, input `ref` = `vX.Y.Z` → approuver.

- [ ] Workflow vert

### 2.3 🖥 Après le déploiement

```bash
cd /data/kpi
make docker_prod_up
make api2_restart
make media_init
make media_status
make media_backup
cat /data/backups/kpi/.media-restic-password     # → gestionnaire de mots de passe
```

- [ ] 6/6 montages sur les trois conteneurs
- [ ] gd présente dans api2 : `docker exec kpi_api2 php -r 'var_dump(gd_info()["JPEG Support"]);'`
- [ ] Premier backup OK, mot de passe copié
- [ ] `sources/api2/public/img/` vérifié et rapatrié si besoin (cf. 1.3)
- [ ] Contrôles fonctionnels

### 2.4 🖥 Planifier la sauvegarde (dépôt privé `vps-manager`)

Script `media-backup.sh` + cibles make dans `vps-manager`. Il appelle `make media_backup` /
`media_backup_check` / `media_restore` du dépôt KPI de chaque instance (`MEDIA_BACKUP_TARGETS`),
journalise dans `$LOGS_BASE_DIR/media-backup/` et alerte par email (variables `HEALTH_CHECK_*`).

- Sauvegarde : tous les jours à **3h30**, après le dump SQL de 2h
- Contrôle : chaque **dimanche à 5h**, avec trois vérifications : `restic check` (5 % des données),
  restauration d'un fichier tiré au hasard comparée à l'original, et alerte si la dernière sauvegarde
  réussie a plus de `MEDIA_BACKUP_MAX_AGE_HOURS` (26 h)

```bash
cd <vps-manager>
# .env : ajouter MEDIA_BACKUP_TARGETS et MEDIA_BACKUP_MAX_AGE_HOURS (cf. .env.dist)
make media-backup            # 1re exécution manuelle (préprod + prod)
make media-backup-check      # doit être vert (restauration test comprise)
make install-cron-media-backup
make media-backup-status
```

> L'utilisateur du crontab doit être dans le groupe `docker` (restic tourne en conteneur, en root :
> c'est lui qui lit le mot de passe et écrit le dépôt) et pouvoir lire `/data/kpi` (Makefile, `docker/.env`).

- [ ] `.env` de `vps-manager` complété, `make media-backup-check` vert
- [ ] `make install-cron-media-backup` : deux lignes `media-backup.sh` dans `crontab -l`
- [ ] Lendemain : `make media-backup-status` affiche ✅ et un nouvel instantané pour chaque instance

---

## Contrôles fonctionnels

À faire en dev, puis en préprod, puis en prod :

- [ ] **app4** : un logo de compétition s'affiche (Compétitions / Documents)
- [ ] **app4** : upload d'un logo ou d'une photo d'équipe (Opérations → images) → le fichier
  apparaît dans `HOST_MEDIA_PATH/img/...`, **pas** dans `sources/api2/public/img/`
- [ ] **app4** : fiche club → la photo d'équipe la plus récente s'affiche
- [ ] **app4** : PDF de classement et PDF de stats → le logo KPI apparaît en en-tête
- [ ] **app4** : Opérations → purge du cache live → ne renvoie plus « Cache directory does not exist »
- [ ] **Legacy public** : `kpequipes.php` / `kpclubs.php` affichent les logos de clubs (`/img/KIP/logo/...`)
- [ ] **Legacy public** : `kpclassement.php` avec bandeau/logo de compétition (`/img/logo/...`)
- [ ] **Legacy admin** : upload d'un logo via l'ancienne interface → le fichier arrive dans le stockage
- [ ] **Incrustations / TV** : une page `live/` ou `frame_*.php` avec logos s'affiche

---

## Retour arrière

| Problème | Action |
|---|---|
| Conteneurs qui ne démarrent pas (« HOST_MEDIA_PATH absent ») | Ajouter la variable dans `docker/.env`, puis `make docker_<env>_up` |
| « mkdir(): Permission denied » à l'upload, ou live/cache introuvable | `LEGACY_DOCUMENT_ROOT` absent de l'environnement d'api2 ou du worker : `docker exec <app>_api2 printenv LEGACY_DOCUMENT_ROOT`, puis `make docker_<env>_up` et `make api2_cache_clear` |
| 404 sur `/img/...` alors que le fichier est dans `HOST_MEDIA_PATH` | Montage perdu dans un conteneur en marche : le point de montage `sources/img/<d>` a été supprimé sur l'hôte (changement de branche, `git pull`/`reset` qui retire les images suivies), le noyau a retiré le montage. `make media_status` (lit `/proc/mounts`) → `docker restart <conteneur>` |
| Images manquantes après déploiement | `make media_init` (récupère depuis l'historique Git), puis `make media_status` |
| Image écrasée ou supprimée par erreur | `make media_restore snapshot=latest path=img/...`, puis `rsync` (commande affichée) |
| Retour au code précédent (`make preprod_rollback sha=…`, ou rollback auto du wrapper) | ⚠️ **Échoue tel quel** : l'ancien code veut recréer les images suivies dans `sources/img/{logo,Nations,…}`, mais Docker y a créé les points de montage, possédés par `root` → « unable to create file … Permission non accordée », `reset --hard` avorte. Il faut d'abord vider ces dossiers (root/sudo, conteneurs arrêtés) **ou** préférer un correctif en avant. Les médias, eux, ne risquent rien : ils restent dans `HOST_MEDIA_PATH`. Les uploads faits entre-temps sont à recopier dans `sources/img/` si le retour arrière dure (`rsync -a --ignore-existing /data/media/<env>/img/ sources/img/`). |

---

## Récapitulatif

| # | Où | Quoi | Fait |
|---|---|---|---|
| 0.1 | ⌨️ | Publier le tag `archive/app3-matchsheet` | ☑ |
| 0.2 | ⌨️ | Prévenir la branche scoring (chemins app3 → tag) | ☑ |
| 0.3 | ⌨️ | `make api2_test` + compilation du conteneur | ☑ |
| 0.4 | ⌨️ | Dev : `HOST_MEDIA_PATH`, `media_init`, `docker_dev_up`, contrôles | ☐ |
| 1.1 | 🖥 | Préprod : variables, dossiers, `media.sh init` **avant merge** | ☑ |
| 1.2 | ⌨️ | Merge de la PR sur `main` (→ préprod auto) | ☐ |
| 1.3 | 🖥 | Préprod : `docker_preprod_up`, `media_status`, 1er backup, contrôles | ☐ |
| 2.1 | 🖥 | Prod : variables, dossiers, `media.sh init`, lever `skip-worktree` **avant déploiement** | ☐ |
| 2.2 | ⌨️ 🌐 | `make release_tag` puis Deploy production sur le tag | ☐ |
| 2.3 | 🖥 | Prod : `docker_prod_up`, `media_status`, 1er backup, contrôles | ☐ |
| 2.4 | 🖥 | Cron de sauvegarde dans `vps-manager` + test de restauration | ☐ |
