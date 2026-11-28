# Points d'entrée legacy archivés : `connector/` et `live/`

**Date** : 28 novembre 2026
**Statut** : ✅ En place (fermeture des points d'entrée HTTP)
**Origine** : revue de sécurité du 28/11/2026 : écriture en base sans authentification
(`connector/read_evenement.php`, `live/ajax_change_voie.php`, `live/ajax_change_tv.php`, `live/api_worker.php`)
et injection SQL (`connector/get_evenement.php`), tous joignables en production.

## 1. Décision

Les scripts de `sources/connector/` (import/export d'événements entre serveurs) et de `sources/live/`
(incrustations TV legacy, pilotage des voies, worker de cache legacy) **ne sont plus utilisés** d'ici la migration
complète vers app2 / app3 / app4 + Scoring + api2. Ils sont **conservés pour archive** et **fermés en HTTP**.
Leur remplacement éventuel, ou leur abandon, relève de la stratégie Scoring
([LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md](../in-progress/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md),
[PAGE_INCRUSTATION.md](../../specs/PAGE_INCRUSTATION.md)) :

| Legacy archivé | Équivalent cible |
|---|---|
| `live/tv2.php`, `score*.php`, `teams*.php`, `next_game*.php`, `multi_score*.php`… | page d'incrustation paramétrée app4 `/live/overlay` (lot 4 Scoring) |
| `live/ajax_change_tv.php`, `ajax_change_voie.php` (pilotage des voies `kp_tv`) | api2 `AdminTvController` (`/admin/tv/*`, JWT) |
| `live/api_worker.php`, `event_worker.php` (worker de cache) | api2 `app:event-cache-worker` + app4 `/live/cache-manager` |
| `connector/*` (import/export d'événements entre serveurs) | aucun : à reprendre ou non |

## 2. Mécanisme (deux couches indépendantes)

1. **Apache** : `connector/.htaccess` refuse tout, et `live/.htaccess` refuse tout fichier `*.php`. Les fichiers
   statiques de `live/` (`cache/*.json`, `css/`, `js/`, `img/`) restent servis.
2. **PHP** : chaque script PHP à la racine de ces deux dossiers commence par
   `require_once __DIR__ . '/../commun/ArchivedEndpoint.php'; archivedEndpointGuard(__FILE__);`.
   Appelé directement en HTTP, il répond `410 Gone` **avant toute inclusion ni accès base**. La garde laisse passer :
   - la **CLI** (`php live/event_worker.php`) ;
   - un script **inclus par un autre** : `live/create_cache_match.php` est inclus par la feuille de marque
     legacy (`admin/v2/*`, `admin/GestionJournee.php`), `connector/replace_evenement.php` par `admin/GestionOperations.php`.

La seconde couche protège même si le `.htaccess` est ignoré (`AllowOverride None`, autre serveur, copie du dossier).

Les failles du code conservé sont aussi corrigées, car `replace_evenement.php` reste joignable via
`GestionOperations` (import par l'admin) :
- `Replace_Table` utilise des requêtes préparées ;
- les identifiants de `Delete_Evenement` et de `get_evenement.php` sont des entiers ;
- `get_evenement.php` vérifie que **chaque** événement demandé est autorisé (avant : seul le dernier l'était).

## 3. Critères d'acceptation

| ID | Critère | Validation |
|---|---|---|
| ARCH-01 | Chaque `*.php` à la racine de `connector/` et `live/` appelle la garde en première instruction | `scripts/ci/legacy-archived-endpoints.test.mjs` |
| ARCH-02 | Appelé directement en HTTP, chaque script répond `410` sans rien exécuter d'autre (aucune inclusion, aucun accès base) | idem, via `php -S` |
| ARCH-03 | Un script archivé **inclus** par un autre script s'exécute normalement | idem (fixture qui inclut `live/create_cache_match.php` et `connector/replace_evenement.php`) |
| ARCH-04 | En CLI, la garde laisse passer | idem |
| ARCH-05 | `connector/.htaccess` refuse tout, `live/.htaccess` refuse `*.php` | idem (contrôle statique) |
| ARCH-06 | Aucune requête du code conservé n'interpole de valeur venant de la requête ou du JSON importé | relecture + `grep` (le legacy n'a pas de banc de test base) |

## 4. Sources Vue CLI legacy supprimées

Les sources `sources/app_dev/`, `app_live_dev/` et `app_wsm_dev/` (Vue CLI 5) sont **supprimées** du dépôt le
28/11/2026 et conservées au tag git **`archive/vue-cli-sources`**, avec les cibles `make app_wsm_generate_*` et
`app_live_generate_*`, le service de dev `node_wsm` et `vetur.config.js`. Leurs vulnérabilités sans correctif
(`elliptic`, `webpack-dev-server` figé en v4 par `@vue/cli-service`) faisaient échouer le groupe de sécurité npm de
Dependabot, qui ignore `exclude-paths`. Les builds `sources/app/`, `app_live/` et `app_wsm/` ne sont pas versionnés :
ils restent servis sur les serveurs, mais ne peuvent plus être recompilés qu'à partir du tag
(`git worktree add ../vue-cli archive/vue-cli-sources`).

## 5. Réactiver un script (dépannage ponctuel)

À éviter : préférer l'équivalent api2/app4. En cas de besoin, retirer la garde du **seul** script concerné et ajouter
une exception dans le `.htaccess` du dossier, après lui avoir ajouté un contrôle de session et de profil.

## 6. Impact connu

- **app4, module TV** (`composables/useTvUrl.ts`, `components/admin/tv/*`, `pages/tv/index.vue`) : il génère encore
  des liens vers `live/tv2.php`, `live/score*.php`, `live/spliturl.php`, `live/scenario.php`,
  `live/force_cache_match.php`. Ces liens répondent maintenant 403/410. Ils seront remplacés par `/live/overlay`
  (Scoring lot 4).
- **Legacy `admin/GestionOperations.php`** : l'import/export d'événements entre serveurs appelle
  `connector/get_evenement.php`, `ajax_md5.php` et `ajax_okevent.php` en HTTP. Ces appels échouent désormais. L'import d'un
  JSON collé dans le formulaire fonctionne toujours, avec des requêtes préparées.
