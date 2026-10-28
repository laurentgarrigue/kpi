# Fixtures de test — api2

Jeu de données **minimal** et **synthétique** pour la suite `integration` d'api2
(Phase 4 du plan CI/CD).

## Ce que ce n'est pas

Ce n'est **pas** un extrait de la base réelle : aucune donnée de production,
aucun nom de licencié, aucun club existant. Tout est inventé (saisons `2999`/
`2998`, compétitions `TST*`, événements à partir de l'`Id` 9000). C'est
volontaire :

- pas de données personnelles dans le repo (RGPD),
- des jeux stables : un test ne casse pas parce qu'un vrai match a été saisi,
- lisible : on voit d'un coup d'œil quel cas chaque ligne couvre.

## Fichiers

| Fichier | Rôle |
|---|---|
| `schema.sql` | `CREATE TABLE` des seules tables utilisées par les tests |
| `data.sql` | Les lignes de test (chaque ligne commentée avec le cas couvert) |

Le schéma est un **sous-ensemble** de la vraie base, copié depuis `SHOW CREATE
TABLE`, sans les clés étrangères vers les tables non incluses (sinon il faudrait
recopier la moitié du schéma). Les types et les valeurs par défaut sont
identiques à la production — c'est ce qui compte pour que le SQL des contrôleurs
se comporte pareil (`char(1)`, `char(4)` pour les saisons, `Publication` à `''`
par défaut, etc.).

## Utilisation

En CI, le job `tests-api2` crée un service MariaDB, charge ces deux fichiers,
puis lance `composer test-integration`. En local :

```bash
# base de test dédiée, dans le conteneur MariaDB existant
docker exec -i kpi_db mariadb -uroot -p<pass> -e "CREATE DATABASE IF NOT EXISTS kpi_test"
docker exec -i kpi_db mariadb -uroot -p<pass> kpi_test < SQL/fixtures/schema.sql
docker exec -i kpi_db mariadb -uroot -p<pass> kpi_test < SQL/fixtures/data.sql

# puis, en pointant api2 sur cette base :
docker exec -e API2_TEST_DB=1 \
  -e DATABASE_URL='mysql://root:<pass>@kpi_db:3306/kpi_test?serverVersion=11.5.2-MariaDB&charset=utf8mb4' \
  kpi_api2 sh -lc 'cd /app && composer test-integration'
```

⚠️ **Ne jamais pointer la suite `integration` sur la base de dev/préprod/prod** :
les tests supposent ces fixtures et n'ont aucune raison d'y trouver leurs ids.
Ils ne font que des `SELECT` aujourd'hui, mais la garde reste la règle.

## Jeu « résultats publics » (groupe `TSTRES`)

Consommé par les tests de caractérisation (`tests/Integration/PublicResultsCharacterizationTest.php`) et les
endpoints du site public ([API_PUBLIC_RESULTS.md](../../DOC/specs/public/API_PUBLIC_RESULTS.md)) :

| Élément | Cas couverts |
|---|---|
| `RCH` (CHPT, `ON`) | journées publiées / non publiée / `Pause`, matchs terminés, en cours, à venir, non publié, buteurs |
| `RCP` (CP, `END`, tour final) | poule classée, poule sans classement (équipes déduites des matchs), finale, placeholders `[V11-P12]`, médailles |
| `RMU` (MULTI, `END`, tour final) | classement sans match |
| `RAT` (CHPT, `ATT`) / `RNP` (non publiée) | filtres de statut et de publication |
| Événement `77` (publié) / `78` (non publié) | ids < 3000 = tournoi `kp_evenement` ; journées liées par `kp_evenement_journee` |

## Jeu « site public, phase 3 » (transverse)

Consommé par `tests/Integration/PublicSiteEndpointsTest.php`
([API_PUBLIC_TRANSVERSE.md](../../DOC/specs/public/API_PUBLIC_TRANSVERSE.md)) :

| Élément | Cas couverts |
|---|---|
| `kp_equipe` 101–131 | équipes engagées ci-dessus ; 105 « Alpha Deux » : deuxième équipe du club C001 |
| `kp_club` C001–C012, C099 | position valide / absente / illisible, nom à tiret (« Saint-Malo »), club **sans équipe** (C099, jamais publié) |
| `kp_cd` / `kp_cr` | comités départementaux et régional de la fiche club |
| `RCP` 2998 (CP, `END`, finale) / `RQL` 2998 (CHPT `END`, tour 1) | historique sur deux saisons ; palmarès d'équipe avec et sans médaille |
| Joueurs 9505 (`E`) et 9506 (`X`) de l'équipe 9301 | composition : entraîneur après les joueurs, joueur `X` exclu |

Les fichiers de référence JSON sont dans `sources/api2/tests/Integration/__snapshots__/` ; un changement voulu
se régénère avec `UPDATE_SNAPSHOTS=1` et se relit dans le diff.

## Ajouter un cas

1. Ajouter la ligne dans `data.sql` **avec un commentaire** disant quel test la
   consomme.
2. Si une nouvelle table est nécessaire, copier son `SHOW CREATE TABLE` dans
   `schema.sql` (en retirant les FK vers des tables absentes).
3. Ne jamais réutiliser un id existant : les tests asserted sur des ids précis.

## Jeu « scrutineering et profil 10 » (saison `2997`)

Consommé par `tests/Integration/StaffScrutineeringTest.php`, `PresenceScrutineerTest.php` et
`ScrutineerProfileAssignmentTest.php` ([PAGE_SCRUTINEERING.md](../../DOC/specs/PAGE_SCRUTINEERING.md)) :

| Élément | Cas couverts |
|---|---|
| Événement `80` (non publié, hors app2) | journées 9271 (`SCA`) et 9272 (`SCB`) |
| `SCA` (non verrouillée) | 9381 Scrut Alpha (C001), 9382 Scrut Bravo (C002), 9383 Germany Men (`GER`) |
| `SCB` (verrouillée) | 9384 Scrut Alpha Deux (C001, même équipe de club que 9381 : copie), 9385 Germany Women (`GER-W`) |
| `SCC` (hors événement) | journée 9273 rattachée à aucun événement ; 9386 Scrut Charlie (C001), joueur 9517 |
| `kp_scrutineering` | 9381 : un joueur complet et un partiel → « partiel » ; 9382 : ligne remise à 0 → « non contrôlé » |
| Comptes | `TSCRUT` (profil 10, SCA + SCB + SCC, limité à l'événement 80 donc sans SCC, club C002 ignoré), `TSCRUTA` (profil 10, SCA + SCC, sans limite d'événement), `TDIV` (profil 3), `TCLUB` (profil 7, club C002) |

`kp_licence` reprend la définition de production (colonnes lues par la feuille de présence), avec `kp_arbitre`,
`kp_surclassement` et `kp_categorie` (vides).

## Jeu « documents PDF »

Consommé par `tests/Integration/PdfEndpointsTest.php` ([PDF_GOTENBERG.md](../../DOC/specs/PDF_GOTENBERG.md)), en plus des
comptes du jeu scrutineering (`TADMIN`, `TVIEW`, `TDIV`, `TCLUB`, `TSCRUT`) et des compétitions `SCA`/`SCC` (2997) et `RCH`/`RNP` (2999) :

| Élément | Cas couverts |
|---|---|
| `TSCORE` (profil 9) | feuille de marque permise, équipes engagées refusées (`ROLE_SCORER` sans `ROLE_VIEWER`) |
| Mandat `9701` de `TDIV` (profil 3, `SCC`) | périmètre du mandat actif : `SCC` permis, `SCA` refusé (PDF-AUTH-03) |
