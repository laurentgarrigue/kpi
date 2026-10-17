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
puis lance `composer test-integration`. En local, tout passe par le Makefile —
la base de test `kpi_fixtures_test` est (re)créée et rechargée automatiquement :

```bash
make api2_test_fixtures       # (re)charge schema.sql puis data.sql
make api2_test_integration    # recharge les fixtures, puis joue la suite integration
```

Inutile de taper les `docker exec` à la main : les cibles gèrent la création de la
base, le chargement des deux fichiers et le `DATABASE_URL` de la suite.

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

## Jeu « scoring » (refonte live, lots 1 à 4)

Ces lignes servent `ScoringPublicEndpointsTest`, qui vérifie le **contrôle d'accès** du
chemin de lecture public des incrustations. Elles réutilisent l'événement `77` (publié) et
l'événement `78` (non publié) du jeu « résultats publics » ci-dessous : un seul jeu d'ids,
pas de tournoi dédié au scoring.

| Ligne | Cas couvert |
|---|---|
| `tsttok-valid-event-77` | jeton valide, portée événement → accès accordé sur tous les terrains de `77` |
| `tsttok-valid-pitch1` | valide mais restreint au terrain `1` → refusé sur un autre terrain |
| `tsttok-expired` | expiré → refusé |
| `tsttok-revoked` | révoqué (expiration lointaine : c'est bien la révocation qui tranche) |
| `tsttok-other-event` | valide pour l'événement `78` → n'ouvre rien sur `77` |

Les tables `scoring_display_settings` et `scoring_live_state` sont créées mais **vides** :
les tests d'accès doivent échouer **avant** toute lecture de données de match, le programme
doit savoir répondre avec les seuls réglages par défaut, et `/scoring/state/{id}` doit
renvoyer un 404 explicite sur un match sans état live.


## Ajouter un cas

1. Ajouter la ligne dans `data.sql` **avec un commentaire** disant quel test la
   consomme.
2. Si une nouvelle table est nécessaire, copier son `SHOW CREATE TABLE` dans
   `schema.sql` (en retirant les FK vers des tables absentes).
3. Ne jamais réutiliser un id existant : les tests asserted sur des ids précis.
