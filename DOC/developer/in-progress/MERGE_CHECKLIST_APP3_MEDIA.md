# Checklist de merge — suppression de l'ancien app3 et médias hors Git

**Branche** : `claude/keen-feynman-meumrx`
**Commits concernés** : `cd4ef770` (Chore: remove legacy app3 match-sheet prototype) et `2033ca0d`
(Feat: move uploaded media out of Git with restic backups)
**Contexte** : phases 0a et 0b de [PUBLIC_SITE_REDESIGN_STRATEGY.md](plans/PUBLIC_SITE_REDESIGN_STRATEGY.md) ;
procédure détaillée des médias dans [MEDIA_STORAGE.md](../infrastructure/MEDIA_STORAGE.md)

> **Convention** (comme le [runbook](../infrastructure/DEPLOYMENT_RUNBOOK.md)) : ⌨️ = poste de dev,
> 🖥 = sur le VPS (en SSH, en tant que `laurent`), 🌐 = interface GitHub.

---

## Pourquoi l'ordre compte

Le merge sur `develop` **déploie automatiquement la préprod**. Le déploiement (`git reset --hard`)
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

L'environnement de travail n'a pas pu pousser le tag. Commande à lancer une seule fois :

```bash
git fetch origin
git tag -a archive/app3-matchsheet cdb2081014 -m "Archive: former app3 match-sheet prototype (Nuxt)"
git push origin archive/app3-matchsheet
```

`cdb2081014` est le dernier commit de `main` contenant l'ancien `sources/app3/`.

- [ ] Tag publié : `git ls-remote --tags origin archive/app3-matchsheet` renvoie une ligne

### 0.2 ⌨️ Coordination avec la branche scoring

La branche `claude/scoring-refactoring-strategy-3d43ac` porte encore des références à `sources/app3/...`
dans `DOC/specs/PAGE_SCORING.md` et `DOC/developer/reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md`.
Ces fichiers **n'ont pas été modifiés ici**, pour éviter les conflits.

- [ ] Signaler à cette branche de remplacer les chemins par le tag, par exemple :
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

- [ ] `make api2_test` vert
- [ ] Aucune erreur de compilation du conteneur (`legacy_document_root`, bind `$legacyDocumentRoot`)

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

### 1.1 🖥 AVANT le merge sur `develop`

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
git fetch origin claude/keen-feynman-meumrx
git show origin/claude/keen-feynman-meumrx:scripts/media/media.sh > /tmp/media.sh
bash /tmp/media.sh init
```

- [ ] `docker/.env` contient les 3 variables
- [ ] `bash /tmp/media.sh init` affiche un nombre de fichiers non nul pour chaque dossier
- [ ] Taille cohérente : `du -sh /data/media/kpi_preprod` ≈ `du -sh sources/img/{logo,KIP,Nations,presentations,schemas,referees}`

### 1.2 🌐 Merger la PR sur `develop`

Le déploiement préprod est automatique.

- [ ] Workflow de déploiement préprod vert

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

git fetch origin main
git show origin/main:scripts/media/media.sh > /tmp/media.sh     # une fois la release mergée sur main
bash /tmp/media.sh init
```

- [ ] Variables présentes dans `docker/.env`
- [ ] `init` OK. En prod, le volume attendu est d'environ **437 Mo** au total pour `sources/img/`,
  dont la plus grande partie dans ces six dossiers.

### 2.2 🌐 Déployer

Actions → « Deploy production » → Run workflow depuis `main` → approuver.

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
- [ ] Premier backup OK, mot de passe copié
- [ ] `sources/api2/public/img/` vérifié et rapatrié si besoin (cf. 1.3)
- [ ] Contrôles fonctionnels

### 2.4 🖥 Planifier la sauvegarde (dépôt privé `vps-manager`)

À ajouter aux crons, **après** le dump SQL nocturne :

```cron
30 3 * * *  cd /data/kpi && make media_backup >> /var/log/kpi-media-backup.log 2>&1 || mail -s "KPI: media_backup en échec" <admin> < /var/log/kpi-media-backup.log
0  5 * * 0  cd /data/kpi && make media_backup_check >> /var/log/kpi-media-backup.log 2>&1
```

- [ ] Cron ajouté dans `vps-manager` et déployé
- [ ] Lendemain : `make media_backup_list` montre un nouvel instantané
- [ ] Test de restauration d'un fichier :
  `make media_restore snapshot=latest path=img/logo/<un-fichier>`, puis `cmp` avec l'original

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
| Images manquantes après déploiement | `make media_init` (récupère depuis l'historique Git), puis `make media_status` |
| Image écrasée ou supprimée par erreur | `make media_restore snapshot=latest path=img/...`, puis `rsync` (commande affichée) |
| Retour au code précédent (`make preprod_rollback sha=…`) | Sans risque pour les médias : ils restent dans le stockage. L'ancien code recrée les fichiers suivis dans `sources/img/`, et les anciens compose ne montent plus le stockage. Les uploads faits entre-temps restent dans `HOST_MEDIA_PATH` : à recopier dans `sources/img/` si le retour arrière dure (`rsync -a --ignore-existing /data/media/<env>/img/ sources/img/`). |

---

## Récapitulatif

| # | Où | Quoi | Fait |
|---|---|---|---|
| 0.1 | ⌨️ | Publier le tag `archive/app3-matchsheet` | ☐ |
| 0.2 | ⌨️ | Prévenir la branche scoring (chemins app3 → tag) | ☐ |
| 0.3 | ⌨️ | `make api2_test` + compilation du conteneur | ☐ |
| 0.4 | ⌨️ | Dev : `HOST_MEDIA_PATH`, `media_init`, `docker_dev_up`, contrôles | ☐ |
| 1.1 | 🖥 | Préprod : variables, dossiers, `media.sh init` **avant merge** | ☐ |
| 1.2 | 🌐 | Merge sur `develop` | ☐ |
| 1.3 | 🖥 | Préprod : `docker_preprod_up`, `media_status`, 1er backup, contrôles | ☐ |
| 2.1 | 🖥 | Prod : variables, dossiers, `media.sh init` **avant déploiement** | ☐ |
| 2.2 | 🌐 | Deploy production | ☐ |
| 2.3 | 🖥 | Prod : `docker_prod_up`, `media_status`, 1er backup, contrôles | ☐ |
| 2.4 | 🖥 | Cron de sauvegarde dans `vps-manager` + test de restauration | ☐ |
