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

## Jeu « scoring » (refonte live, lot 4)

Ces lignes servent `ScoringPublicEndpointsTest`, qui vérifie le **contrôle d'accès** du
chemin de lecture public des incrustations. Chaque jeton couvre un motif de refus distinct :

| Ligne | Cas couvert |
|---|---|
| `kp_evenement_journee` (9001 → 9101) | rattache la journée à l'événement : c'est ce qui donne sa portée à un jeton |
| `kp_match` 99001 (journée 9101, terrain `2`) | match cible de `/scoring/state/{id}` ; **aucun** état live associé, pour vérifier le 404 explicite |
| `tsttok-valid-event-9001` | jeton valide, portée événement → accès accordé |
| `tsttok-valid-pitch2` | valide mais restreint au terrain `2` → refusé sur un autre terrain |
| `tsttok-expired` | expiré → refusé |
| `tsttok-revoked` | révoqué (expiration lointaine : c'est bien la révocation qui tranche) |
| `tsttok-other-event` | valide pour l'événement 9002 → n'ouvre rien sur 9001 |

Les tables `scoring_display_settings` et `scoring_live_state` sont créées mais **vides** :
les tests d'accès doivent échouer **avant** toute lecture de données de match, et le
programme doit savoir répondre avec les seuls réglages par défaut.

## Ajouter un cas

1. Ajouter la ligne dans `data.sql` **avec un commentaire** disant quel test la
   consomme.
2. Si une nouvelle table est nécessaire, copier son `SHOW CREATE TABLE` dans
   `schema.sql` (en retirant les FK vers des tables absentes).
3. Ne jamais réutiliser un id existant : les tests asserted sur des ids précis.
