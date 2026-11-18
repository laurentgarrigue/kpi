# Catégorie d'âge — checklist de validation et de déploiement

> **✅ Close le 14/11/2026** : en production, migration `app:age-category:migrate` exécutée en préprod puis en prod.
> Lignes restées ☐ sans vérification tracée : § 1.3 n° 16 (statistiques) et 17 (anglais), § 1.5 n° 5 (import ICF XML)
> et 6 (dry-run après les essais legacy). La commande est relançable si un écart apparaît.

**Créée le** : 1er novembre 2026 · **Spec** : [CATEGORIE_AGE.md](../../../specs/CATEGORIE_AGE.md) · **ADR** :
[0001](../../../../docs/adr/0001-categorie-age-derivee.md) · **Tickets** : #367 (parent), #368 → #374 ·
**Branche** : `feature/categorie-age` · **Procédure de la commande** : [runbook § 1.4.1](../../infrastructure/DEPLOYMENT_RUNBOOK.md)

> **Convention** (comme le [runbook](../../infrastructure/DEPLOYMENT_RUNBOOK.md)) : ⌨️ poste de dev · 🖥 VPS (SSH) ·
> 🌐 interface GitHub. Cocher ☐ → ☑ au fil de l'eau ; une ligne qui échoue = on s'arrête et on corrige avant la suite.

**Règle générale** : on valide en local, puis en préprod, puis en production. Le code seul ne corrige **pas**
l'historique : il faut lancer la commande `app:age-category:migrate` dans **chaque** environnement (dry-run, relecture,
exécution). Elle réécrit `Categ` de toutes les compositions, **compétitions verrouillées comprises** : dump d'abord.

Aucune migration SQL de schéma : `kp_licence.Naissance` est déjà `date DEFAULT NULL`.

---

## 0. Préparation

### 0.1 Variables utilisées dans les commandes ci-dessous

```bash
# ⌨️ dev      : cd ~/Documents/dev/kpi       B=https://kpi.localhost/api2              A=https://kpi.localhost/admin2
# 🖥 préprod  : cd /data/kpi_preprod         B=https://preprod.kayak-polo.info/api2    A=https://preprod.kayak-polo.info/admin2
# 🖥 prod     : cd /data/kpi                 B=https://kayak-polo.info/api2            A=https://kayak-polo.info/admin2
set -a; . docker/.env; set +a                       # APPLICATION_NAME, DB_CONTAINER_NAME, DB_NAME, DB_ROOT_PASSWORD
API="${APPLICATION_NAME}_api2"
q() { docker exec -i "$DB_CONTAINER_NAME" mariadb -uroot -p"$DB_ROOT_PASSWORD" "$DB_NAME" -e "$1"; }
login() { curl -sk -X POST "$B/auth/login" -H 'Content-Type: application/json' \
  -d "{\"username\":\"$1\",\"password\":\"$2\"}" | jq -r .token; }
ADMIN=$(login <code_profil_1_ou_2> '<mot_de_passe>')
type q | head -1                                    # attendu : « q est une fonction »
```

> ⚠️ `q` et `login` sont des **fonctions shell**, pas des commandes installées : coller ce bloc dans
> **chaque** nouveau terminal (dev, puis session SSH préprod, puis prod) avant les commandes qui les utilisent.
> Sinon : `La commande « q » n'a pas été trouvée`. La console Symfony, elle, est toujours écrite en entier
> (`docker exec kpi_api2 php bin/console …` ; en préprod `kpi_preprod_api2`).

### 0.2 Photographie « avant » (à refaire dans chaque environnement)

```bash
q "SELECT SUM(Naissance='1901-01-01') AS d1901, SUM(CAST(Naissance AS CHAR)='0000-00-00') AS d0000,
          SUM(Naissance IS NULL) AS dnull FROM kp_licence WHERE Matric >= 2000000"
# Un joueur témoin : international, plusieurs saisons, catégories incohérentes (cf. Problem Statement)
q "SELECT l.Matric, l.Nom, l.Prenom, l.Naissance, ce.Code_saison, ce.Code_compet, cej.Categ
     FROM kp_competition_equipe_joueur cej
     JOIN kp_competition_equipe ce ON ce.Id = cej.Id_equipe
     JOIN kp_licence l ON l.Matric = cej.Matric
    WHERE l.Matric >= 2000000 AND l.Naissance > '1901-01-01'
    GROUP BY l.Matric, ce.Code_saison HAVING COUNT(DISTINCT cej.Categ) > 0
    ORDER BY l.Matric, ce.Code_saison LIMIT 30"
T=<matric du témoin choisi>        # un joueur né vers 1972 apparaissant V5 / V9 / V4 selon les saisons
T1901=<matric d'un international né le 01/01/1901>
```

| # | Vérification | Attendu | ☐ |
|---|---|---|---|
| 1 | Comptes `d1901 / d0000 / dnull` notés | dev : ≈ 695 / 961 / 81 (chiffres de la spec) | ☑ |
| 2 | Témoin `T` choisi, catégories « avant » notées par saison | au moins une incohérence visible | ☑ |
| 3 | Témoin `T1901` choisi, catégorie « avant » notée | `V9` ou `SEN` | ☑ |

---

## 1. ⌨️ Local (dev)

### 1.1 Code et tests

```bash
git checkout feature/categorie-age
make docker_dev_up && make api2_composer_install && make api2_restart
make api2_test                                            # unit + integration (ce que fait la CI)
make app4_lint
docker exec kpi_node_app4 npx vitest run tests/unit       # tests unitaires app4
docker exec kpi_api2 php bin/console list app:age-category
```

| # | Commande / action | Attendu | ☑ |
|---|---|---|---|
| 1 | `make api2_test` | unit 248 (3 skipped), integration 244 (2 dépréciations connues dans `AdminStatsController`), vertes | ☑ |
| 2 | `make app4_lint` | aucune erreur | ☑ |
| 3 | `vitest run tests/unit` | vert (60 tests) | ☑ |
| 4 | `docker exec kpi_api2 php bin/console list app:age-category` | `app:age-category:migrate` listée | ☑ |

> Dépannage : integration « Errors: 244 » avec `Unable to write in the "cache" directory (/app/var/cache/test)` =
> cache de test créé par root (conteneur lancé en root). Le supprimer puis relancer :
> `docker run --rm -v $PWD/sources/api2:/app --entrypoint rm kpi-api2 -rf /app/var/cache/test && make api2_test`.

### 1.2 Migration sur la base de dev

```bash
mkdir -p docker/backups && docker exec "$DB_CONTAINER_NAME" sh -c "mariadb-dump -uroot -p'$DB_ROOT_PASSWORD' $DB_NAME" \
  | gzip > docker/backups/kpi_avant-categorie-age_$(date +%F_%H%M%S).sql.gz && ls -lh docker/backups | tail -1
docker exec kpi_api2 php bin/console app:age-category:migrate --dry-run | tee /tmp/categorie-age-dry-run.txt
# relire, puis :
docker exec kpi_api2 php bin/console app:age-category:migrate | tee /tmp/categorie-age-run.txt
docker exec kpi_api2 php bin/console app:age-category:migrate --dry-run
```

| # | Commande / action | Attendu | ☑ |
|---|---|---|---|
| 1 | Dump | fichier de taille comparable aux dumps habituels | ☑ |
| 2 | Dry-run | `Simulation (rien n'est écrit)`, dates converties ≈ `d1901 + d0000` du § 0.2, détail par saison `avant → après` (`∅` = pas de catégorie) | ☑ |
| 3 | Relecture | surtout `V9 → ∅` / `SEN → ∅` et des glissements entre catégories voisines ; pas de changement massif entre catégories éloignées sur une saison récente | ☑ |
| 4 | Re-lancer la requête de comptes du § 0.2 **après le dry-run** | comptes inchangés (rien écrit) | ☑ |
| 5 | Exécution | `Migration exécutée`, mêmes comptes que le dry-run | ☑ |
| 6 | Second dry-run | `… → inconnue : 0` et `Catégories d'âge à changer : 0` | ☑ |
| 7 | Comptes du § 0.2 | `d1901 = 0`, `d0000 = 0`, `dnull` = ancien total des trois | ☑ |
| 8 | Requête témoin du § 0.2 pour `T` | une catégorie par saison, qui suit l'âge (ex. V3 en 2020, V4 en 2025) | ☑ |
| 9 | `q "SELECT COUNT(*) FROM kp_surclassement"` avant / après | identique (surclassements intacts) | ☑ |

### 1.3 app4 (`make app4_dev`, puis `https://kpi.localhost/admin2`)

| # | Action | Attendu | ☑ |
|---|---|---|---|
| 1 | Athlètes → ouvrir `T1901` | catégorie « ? », infobulle « Date de naissance inconnue » ; participations avec « ? » | ☑ |
| 2 | Modifier `T1901` : cocher « Date de naissance inconnue » | le champ date se grise ; enregistrement accepté | ☑ |
| 3 | Modifier `T1901` : saisir `1901-01-01`, puis une date donnant > 100 ans, puis < 5 ans | refus avec message explicite (bornes 5 et 100 ans), rien n'est modifié | ☑ |
| 4 | Modifier `T1901` : saisir sa vraie date (ou une date plausible de test) | accepté ; ses catégories sont recalculées dans **toutes** ses compositions, y compris une compétition verrouillée (vérifier avec la requête témoin) | ☑ |
| 5 | Remettre « inconnue » sur ce joueur | ses `Categ` repassent à `NULL` partout | ☑ |
| 6 | Athlètes → « Liste des athlètes » (`/admin2/athletes/list`) | filtre « date inconnue » coché par défaut, total affiché | ☑ |
| 7 | Combiner « date inconnue » + « joueur international » + « engagé cette saison » | total plus petit, cohérent avec la requête SQL équivalente | ☑ |
| 8 | Clic sur une ligne → corriger la date → revenir à la liste | le joueur a quitté le filtre « date inconnue » | ☑ |
| 9 | Liste : mobile (DevTools) et mode sombre | lisible, pas de texte sombre sur fond marine | ☑ |
| 10 | Présence équipe (`/presence/team/<id>`) d'une compétition avec `Age_min` ou `Age_max` renseigné : ajouter un joueur sans date | ajout accepté, toast d'avertissement « date de naissance inconnue, catégorie d'âge non vérifiable », pastille orange « ? » | ☑ |
| 11 | Même ajout dans une compétition **sans** bornes d'âge | « ? » sans avertissement | ☑ |
| 12 | Même ajout dans une compétition à surclassement (N1H, N2…, CFH…) en mode national | **bloqué** (erreur Surclassement) — décision validée | ☑ |
| 13 | Ajouter un joueur à date connue dans une équipe d'une saison **passée** | catégorie de la saison de l'équipe, pas de la saison courante | ☑ |
| 14 | Copier une composition d'une saison vers la suivante | catégories recalculées pour la saison de destination (un joueur change de catégorie) | ☑ |
| 15 | Présence match d'un match de cette équipe | « ? » pour le joueur sans date | ☑ |
| 16 | Statistiques → liste des surclassements / catégories | « ? » pour les joueurs sans date | ☐ |
| 17 | Basculer la langue en anglais sur les écrans 1, 3, 6, 10 | libellés traduits (« Unknown birth date »…) | ☐ |

### 1.4 PDF et exports

| # | Action | Attendu | ☑ |
|---|---|---|---|
| 1 | app4 Équipes → PDF feuille de présence (Gotenberg) de l'équipe du § 1.3-10 | « ? » dans la colonne catégorie | ☑ |
| 2 | Même PDF, vue « par catégorie » | groupe « ? - Date de naissance inconnue » | ☑ |
| 3 | Admin legacy : `FeuillePresence.php`, `FeuillePresenceCat.php`, `FeuillePresenceVisa.php` de la même compétition | « ? » pour le joueur sans date | ☑ |
| 4 | app4 Statistiques → export XLSX ; legacy `export_stats_csv.php` | cellule catégorie **vide** (ni `null` ni `?`) | ☑ |

### 1.5 Legacy (aucun test automatique)

| # | Action | Attendu | ☑ |
|---|---|---|---|
| 1 | Admin legacy → équipe d'une saison **passée** → ajout depuis la recherche de licence : un joueur à date connue et `T1901`-like | catégorie de la saison de l'équipe ; vide pour le joueur sans date | ☑ |
| 2 | Même ajout dans le pool d'arbitres | catégorie calculée sur la saison active | ☑ |
| 3 | Admin legacy → Opérations → import ICF CSV, **sans** « MAJ BDD » | aperçu sans écriture | ☑ |
| 4 | Import ICF CSV avec « MAJ BDD », fichier contenant : un joueur sans date, un joueur déjà en base avec date connue mais sans date dans le fichier, un joueur à date inconnue en base avec une vraie date dans le fichier, une date `1901-01-01` | 1ᵉʳ → `NULL` ; 2ᵉ → date conservée ; 3ᵉ → date prise et catégories recalculées ; 4ᵉ → `NULL` | ☑ |
| 5 | Même essai avec l'import ICF XML | mêmes résultats | ☐ |
| 6 | Re-lancer `docker exec kpi_api2 php bin/console app:age-category:migrate --dry-run` | `0` / `0` (le legacy n'a rien désaligné) | ☐ |

> Écart connu, hors périmètre : l'ajout **manuel** legacy (`GestionEquipeJoueur::Add()`) calcule encore sur la saison
> de session et donne V9 pour une date 1901. La commande de migration, relançable, le rattrape.

### 1.6 Fusion de joueurs et import d'événement

| # | Action | Attendu | ☑ |
|---|---|---|---|
| 1 | Fusionner deux doublons : l'un né le 01/01/1901, l'autre avec la vraie date | la fiche conservée porte la vraie date ; ses compositions ont la catégorie correspondante | ☑ |
| 2 | Exporter un événement puis le réimporter | `Categ` recalculées (pas reprises du fichier), `NULL` pour les dates inconnues | ☑ |

---

## 2. 🌐 PR et merge sur `main`

```bash
git push -u origin feature/categorie-age
make pr_create            # titre : « Catégorie d'âge dérivée, date de naissance inconnue (#367) »
make pr_checks
make pr_merge
```

| # | Action | Attendu | ☑ |
|---|---|---|---|
| 1 | Corps de PR | `Closes #367`, `Closes #368` … `Closes #374` ; lien vers cette checklist | ☑ |
| 2 | CI (`make pr_checks`) | tous les jobs verts (tests-api2, app4, legacy lint) | ☑ |
| 3 | Merge | déploiement préprod automatique lancé | ☑ |

---

## 3. 🖥 Préprod (automatique après merge)

```bash
ssh -i ~/.ssh/kpi-deploy/kpi_deploy_ed25519 -p 22 deploy@93.127.163.83
cd /data/kpi_preprod && cat .last-deploy-sha && git rev-parse HEAD
# variables du § 0.1 (préprod), puis § 0.2
docker exec kpi_preprod_api2 php bin/console list app:age-category
```

| # | Vérification | Attendu | ☑ |
|---|---|---|---|
| 1 | SHA déployé | celui du merge | ☑ |
| 2 | `docker exec kpi_preprod_api2 php bin/console list app:age-category` | commande listée (sinon `make api2_cache_clear && make api2_restart`) | ☑ |
| 3 | § 0.2 sur la base préprod | comptes et témoins notés | ☑ |
| 4 | Migration : lignes 1 à 9 du § 1.2, avec `kpi_preprod_api2` au lieu de `kpi_api2` (dump vers `/data/backups/kpi_preprod/pre-migration/`) | mêmes attendus | ☑ |
| 5 | Échantillon fonctionnel : § 1.3 lignes 1, 3, 4, 6, 10, 13, 14 ; § 1.4 lignes 1 et 3 ; § 1.5 lignes 1 et 4 | mêmes attendus | ☑ |
| 6 | `make api2_logs_errors lines=100` | aucune erreur `App\AgeCategory` / `AdminAthletes` / `AdminPresence` | ☑ |

---

## 4. 🌐🖥 Production

```bash
make release_tag version=X.Y.Z        # ⌨️ sur main à jour
```

🌐 Actions → « Deploy production » → `ref` = `vX.Y.Z` → approuver. Le wrapper fait `composer install`, `cache_clear`
et `api2_restart` (api2 modifié). Puis 🖥 :

| # | Vérification | Attendu | ☑ |
|---|---|---|---|
| 1 | `cd /data/kpi && cat .last-deploy-sha` | SHA du tag | ☑ |
| 2 | `docker exec kpi_api2 php bin/console list app:age-category` | commande listée | ☑ |
| 3 | § 0.2 sur la base de prod | comptes et témoins notés | ☑ |
| 4 | **Runbook § 1.4.1**, étapes 1 à 6 (dump → dry-run `tee` → relecture → exécution `tee` → second dry-run) | `0` / `0` au second dry-run | ☑ |
| 5 | Conserver `/tmp/categorie-age-dry-run.txt` et `/tmp/categorie-age-run.txt` (les copier hors `/tmp`) | fichiers archivés | ☑ |
| 6 | § 0.2 après exécution | `d1901 = 0`, `d0000 = 0` ; témoin cohérent par saison | ☑ |
| 7 | Échantillon fonctionnel du § 3 ligne 5 (sans écritures de test sur de vrais joueurs : corriger une vraie date manquante si possible) | mêmes attendus | ☑ |
| 8 | `make api2_logs_errors lines=100` le lendemain | rien de nouveau | ☑ |
| 9 | Prévenir les gestionnaires : `/admin2/athletes/list` pour compléter les dates ; un PDF ou une stat d'une saison close peut changer (voulu, ADR 0001) | message envoyé | ☑ |

**Rollback**
- Code : 🌐 PR de revert du merge → CI → merge (préprod) → release ([runbook § 4](../../infrastructure/DEPLOYMENT_RUNBOOK.md)).
- Données : restaurer le dump pris juste avant l'exécution ([runbook § 5.2](../../infrastructure/DEPLOYMENT_RUNBOOK.md)).
  ⚠️ Les dates saisies **après** la migration seraient perdues : préférer, si possible, corriger puis relancer la
  commande (idempotente) plutôt que restaurer.

---

## 5. Clôture

| # | Action | Attendu | ☑ |
|---|---|---|---|
| 1 | Tickets #367 → #374 | fermés par la PR | ☑ |
| 2 | `DOC/specs/CATEGORIE_AGE.md` | statut « LIVRÉ » | ☑ |
| 3 | `git mv` de cette checklist vers `DOC/developer/archive/features/`, mise à jour des références (`grep -rn CATEGORIE_AGE_ROLLOUT_CHECKLIST`) et de [archive/README.md](../README.md) | fait | ☑ |
