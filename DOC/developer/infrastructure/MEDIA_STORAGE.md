# Stockage et sauvegarde des médias (hors Git)

**Date** : 28 septembre 2026
**Statut** : ✅ Implémenté dans le dépôt, ⏳ **migration à exécuter sur chaque serveur** (§3)
**Contexte** : phase 0b de [PUBLIC_SITE_REDESIGN_STRATEGY.md](../in-progress/plans/PUBLIC_SITE_REDESIGN_STRATEGY.md) (§8)

> **Convention** (comme le [runbook](DEPLOYMENT_RUNBOOK.md)) : ⌨️ = poste de dev, 🖥 = sur le VPS.

---

## 1. Ce qui change

Les images **uploadées** ne sont plus versionnées. Elles vivent dans un **stockage hors du dépôt**
(`HOST_MEDIA_PATH`), **monté à leur emplacement historique** dans les conteneurs :

| Dossier (URL `/img/...` inchangée) | Contenu |
|---|---|
| `sources/img/logo/` | Logos et bandeaux de compétitions |
| `sources/img/KIP/` | Logos de clubs, photos d'équipes et de joueurs, couleurs, casques… |
| `sources/img/Nations/` | Logos d'équipes nationales |
| `sources/img/presentations/` | Visuels de présentation (écrans/TV) |
| `sources/img/schemas/` | Schémas |
| `sources/img/referees/` | Photos d'arbitres |

```
${HOST_MEDIA_PATH}/
├── img/
│   ├── logo/  KIP/  Nations/  presentations/  schemas/  referees/
└── content/            (réservé : médias du futur module éditorial)
```

- **Montages** : conteneurs `kpi` (Apache), `api2` (FrankenPHP) et `event-cache-worker`, dans
  `docker/compose.{dev,preprod,prod}.yaml` → `${HOST_MEDIA_PATH}/img/<dossier>:/var/www/html/img/<dossier>`.
  Si `HOST_MEDIA_PATH` manque dans `docker/.env`, `docker compose` **refuse de démarrer**. C'est
  volontaire : sans cette garde, Docker créerait des dossiers vides à la racine de l'hôte.
- **Git** : ces dossiers sont dans `.gitignore`. Les fichiers restent dans l'historique, qui n'a
  **pas été réécrit** : aucune branche ni aucun clone n'est invalidé (cf. stratégie §8.4).
- **Les autres fichiers de `sources/img/`** (drapeaux `Pays/`, icônes, `calendar/`, `admin-choice/`,
  logos KPI…) sont des ressources de l'application : ils restent versionnés.
- **api2** : les chemins vers l'arborescence legacy passent désormais par le paramètre
  `legacy_document_root` (`/var/www/html`, `config/services.yaml`). Avant cette modification, sous
  FrankenPHP, ils étaient déduits de `$_SERVER['DOCUMENT_ROOT']` (`/app/public`) ou de `__DIR__`
  (`/app/...`). Les uploads d'images faits depuis app4 (`ImageOperationsService`) atterrissaient
  donc dans `sources/api2/public/img/`, et non dans `sources/img/` (cf. §3.4).

La liste des dossiers est définie à **trois endroits, à garder alignés** : `MEDIA_DIRS` dans
`scripts/media/media.sh`, les montages des trois fichiers compose, et `.gitignore`.

## 2. Configuration (`docker/.env`)

```ini
HOST_MEDIA_PATH=/data/media/kpi                              # préprod : /data/media/kpi_preprod
MEDIA_BACKUP_REPO=/data/backups/kpi/media-restic             # préprod : /data/backups/kpi_preprod/media-restic
MEDIA_BACKUP_PASSWORD_FILE=/data/backups/kpi/.media-restic-password
```

- **Chemin absolu** recommandé, et obligatoire avec les worktrees (`scripts/git-wt.sh` copie
  `docker/.env` : un chemin relatif pointerait vers un stockage vide propre à chaque worktree).
- En dev, `HOST_MEDIA_PATH=./media/` (→ `docker/media/`, ignoré par Git) convient pour un clone unique.

## 3. Migration — ordre des opérations (IMPORTANT)

> Version « à cocher » pour le premier merge (y compris le tag app3 et les tests api2) :
> [MERGE_CHECKLIST_APP3_MEDIA.md](../in-progress/MERGE_CHECKLIST_APP3_MEDIA.md)

**Pourquoi l'ordre compte** : dès qu'un serveur tire le commit qui retire ces dossiers de Git
(`git pull`, ou `git reset --hard` du déploiement automatique), **Git supprime du disque les
fichiers qui étaient suivis**. Les fichiers jamais commités (la majorité en prod, ≈ 437 Mo au
total) ne sont pas touchés. `make media_init` sait récupérer les fichiers suivis dans l'historique.

La règle reste pourtant de **migrer avant de déployer**. En prod, des fichiers suivis ont pu être
**remplacés sur place** par un upload (même nom). Seule leur version sur disque est à jour ;
l'historique Git n'en a que l'ancienne.

### 3.1 Préprod — AVANT de merger la PR sur `develop`

Le merge sur `develop` déploie automatiquement la préprod. On prépare donc le terrain avant.

🖥 En tant que `laurent`, dans `/data/kpi_preprod` :

```bash
# 1. Déclarer le stockage (et la sauvegarde) dans docker/.env
cat >> docker/.env <<'EOF'
HOST_MEDIA_PATH=/data/media/kpi_preprod
MEDIA_BACKUP_REPO=/data/backups/kpi_preprod/media-restic
MEDIA_BACKUP_PASSWORD_FILE=/data/backups/kpi_preprod/.media-restic-password
EOF
sudo mkdir -p /data/media/kpi_preprod && sudo chown 1000:33 /data/media/kpi_preprod
sudo setfacl -R -m u:deploy:rwX /data/media/kpi_preprod && sudo setfacl -R -d -m u:deploy:rwX /data/media/kpi_preprod

# 2. Copier les médias AVANT que le déploiement ne retire les fichiers suivis.
#    Le script est lu depuis la branche, sans rien changer à l'arbre de travail :
BRANCH=origin/<branche-de-la-PR>
git fetch origin
git show "$BRANCH:scripts/media/media.sh" > /tmp/media.sh && bash /tmp/media.sh init
```

Puis ⌨️ merger la PR → déploiement automatique. Ensuite 🖥 :

```bash
make docker_preprod_up        # recrée kpi / api2 / worker avec les nouveaux montages (si le déploiement ne l'a pas fait)
make media_init               # idempotent : complète depuis l'historique Git si besoin
make media_status             # doit afficher ✔ 6/6 pour les trois conteneurs
make media_backup             # premier backup : GÉNÈRE le mot de passe → le copier en lieu sûr
```

Contrôle visuel : un logo de compétition dans app4, un logo de club (`/img/KIP/logo/...`),
une page publique legacy avec logos, un PDF de classement (logo KPI).

### 3.2 Production — AVANT « Deploy production »

Même procédure dans `/data/kpi`, avec `HOST_MEDIA_PATH=/data/media/kpi` et
`MEDIA_BACKUP_REPO=/data/backups/kpi/media-restic`. Le `media.sh init` de l'étape 2 est lancé
depuis `main` (ou la branche de release) **avant** de déclencher le workflow, puis
`make docker_prod_up`, `make api2_restart`, `make media_status`, `make media_backup`.

### 3.3 Poste de dev

⌨️ Après `git pull` :

```bash
echo "HOST_MEDIA_PATH=$HOME/kpi-media" >> docker/.env   # chemin absolu conseillé
make media_init        # récupère les fichiers depuis sources/img/ ET l'historique Git
make docker_dev_up
make media_status
```

Pour disposer des médias de prod : copier le stockage (rsync depuis le VPS), puis
`make media_sync_from src=<dossier copié>`.

### 3.4 Uploads égarés dans `sources/api2/public/img/`

À cause du bug de chemin corrigé dans ce lot (§1), des images uploadées depuis app4 ont pu être
écrites sous `sources/api2/public/img/`. Après la migration 🖥 :

```bash
ls -R sources/api2/public/img 2>/dev/null | head      # rien = rien à faire
# sinon, les rapatrier sans écraser (vérifier d'abord les doublons) :
rsync -a --ignore-existing sources/api2/public/img/ "$(grep ^HOST_MEDIA_PATH docker/.env | cut -d= -f2)/img/"
```

## 4. Sauvegarde

| Élément | Choix |
|---|---|
| Outil | **restic** dans un conteneur éphémère (`restic/restic:0.17.3`) : rien à installer sur l'hôte |
| Périmètre | `HOST_MEDIA_PATH` complet |
| Destination | **Sur le VPS** (`MEDIA_BACKUP_REPO`), hors de `HOST_MEDIA_PATH` et du dépôt |
| Rétention | 7 quotidiennes, 4 hebdomadaires, 12 mensuelles (`forget --prune` à chaque backup) |
| Chiffrement | Mot de passe généré au premier `make media_backup` dans `MEDIA_BACKUP_PASSWORD_FILE` (mode 600) |

> ⚠️ **Le mot de passe est indispensable à toute restauration** : copiez-le dans le gestionnaire
> de mots de passe dès le premier backup.
>
> ⚠️ Une sauvegarde sur le même VPS protège contre les erreurs (suppression, écrasement), **pas
> contre la perte du serveur**. L'externalisation est un chantier ultérieur. Avec restic, elle
> consistera à ajouter un dépôt distant et à y lancer `restic copy`.

### 4.1 Planification (cron)

Les crons du VPS vivent dans le dépôt privé `vps-manager`. Ligne à y ajouter, **après** le dump SQL
nocturne, pour que base et médias restent cohérents :

```cron
# Médias KPI : backup restic quotidien + contrôle hebdomadaire
30 3 * * *  cd /data/kpi && make media_backup >> /var/log/kpi-media-backup.log 2>&1 || mail -s "KPI: media_backup en échec" <admin> < /var/log/kpi-media-backup.log
0  5 * * 0  cd /data/kpi && make media_backup_check >> /var/log/kpi-media-backup.log 2>&1
```

(idem pour `/data/kpi_preprod` si l'on veut sauvegarder la préprod, ce qui n'est pas indispensable :
elle se resynchronise depuis la prod avec `make media_sync_from src=/data/media/kpi`.)

### 4.2 Restaurer

La restauration ne se fait **jamais en place** : elle écrit à côté du stockage, dans
`${HOST_MEDIA_PATH}.restore-<date>/`.

```bash
make media_backup_list                                   # repérer l'instantané
make media_restore snapshot=latest path=img/logo/2026-N1.png   # un fichier
make media_restore snapshot=1a2b3c4d                     # tout un instantané
# vérifier, puis remettre en place (commande affichée par la cible) :
rsync -a "<stockage>.restore-<date>/data/media/" "<stockage>/"
```

**Test de restauration** : à faire au moins une fois après la mise en place, puis chaque trimestre
(restaurer un fichier, le comparer avec `cmp`).

## 5. Référence des commandes

| Commande | Rôle |
|---|---|
| `make media_init` | Migre `sources/img/<dossiers>` → stockage (arbre de travail puis historique Git ; n'écrase ni ne supprime rien ; ajoute les images par défaut de `scripts/media/defaults/`) |
| `make media_status` | Nombre de fichiers par dossier + vérification des montages dans `kpi`, `api2`, `event-cache-worker` |
| `make media_sync_from src=…` | Copie un autre stockage dans celui-ci (ex. prod → préprod) |
| `make media_backup` | Backup restic + rétention |
| `make media_backup_list` | Liste des instantanés |
| `make media_backup_check` | Vérification d'intégrité (5 % des données relues) |
| `make media_restore snapshot=… [path=…]` | Restauration à côté du stockage |

## 6. Ajouter un dossier de médias

1. Ajouter le nom à `MEDIA_DIRS` dans `scripts/media/media.sh`.
2. Ajouter le montage dans les **trois** services (`kpi`, `api2`, `event-cache-worker`) des **trois**
   fichiers compose.
3. Ajouter la règle `.gitignore` et, s'il était suivi, `git rm -r --cached` le dossier.
4. Sur chaque serveur : `make media_init` puis `make docker_<env>_up`.
