# api2 — endpoints publics des résultats (phase 2)

**Phase** : 2 — **Statut** : 📝 Proposée — **Consommé par** : app3 (pages compétitions, compétition,
événement, groupe) ; endpoints existants également consommés par **app2**
**Remplace** : les requêtes SQL embarquées dans `kpclassements.php`, `kpmatchs.php`, `kpterrains.php`,
`kpdetails.php`, `kpchart.php`, `kpphases.php`, `kpclassement.php`, `kpstats.php`

## 1. Objectif

Fournir à app3 toutes les données publiques des résultats, **sans modifier le comportement des endpoints
qu'app2 consomme déjà**, et en supprimant la duplication actuelle des requêtes « matchs » et « tableaux »
entre événements et groupes.

## 2. Existant

| Endpoint | Contrôleur | Utilisé par | Contenu |
|---|---|---|---|
| `GET /groups/{season}` | `GroupController` | app2 | groupes ayant des compétitions publiées, par section |
| `GET /group/{season}/{code}/games` | `GroupController` | app2 | matchs publiés du groupe |
| `GET /group/{season}/{code}/charts` | `GroupController` | app2 | tours / phases / équipes / classements du groupe |
| `GET /group/{season}/{code}/teams`, `…/team/{id}/stats` | `GroupController` | app2 | équipes, stats d'une équipe |
| `GET /event/{id}/games` | `GamesController` | app2 | matchs publiés de l'événement |
| `GET /event/{id}/charts` | `ChartsController` | app2 | tours / phases / équipes / classements de l'événement |
| `GET /events/{mode}`, `GET /event/{id}` | `EventController` | app2, app3 (accueil) | événements |
| `GET /game-sheet/{id}` | `PublicController` | app2 | feuille de match publique |

**Constat** : les requêtes « matchs » et « tableaux » de `GamesController`/`ChartsController` et de
`GroupController` sont **identiques à la clause `WHERE` près** (mêmes colonnes, mêmes jointures, même
construction de l'arbre tours → phases). Ajouter une troisième copie pour « une compétition » est exclu (DRY).

## 3. Architecture cible

```
Controller (HTTP : validation des paramètres, cache, JSON)
   └── PublicResultsService (règles : publication, construction de l'arbre, classements)
          └── PublicResultsRepository (SQL, une requête par besoin, paramétrée par un ResultsScope)

ResultsScope = Event(id) | Group(season, code) | Competition(season, code)
```

- `ResultsScope` (value object) fournit la clause de filtrage et ses paramètres : c'est le **seul** élément
  qui varie entre les trois portées.
- `GamesController::eventGames`, `ChartsController::eventCharts`, `GroupController::groupGames` et
  `GroupController::groupCharts` délèguent au service. **Leur JSON reste identique, octet pour octet**
  (ordre des clés, types, tri), y compris l'ordre de tri propre à chacun : le tri fait partie du scope.
- Les nouveaux endpoints vivent dans un `PublicCompetitionController`, avec le même service.

### 3.1 Garde-fou : tests de caractérisation avant la refactorisation

1. **Avant toute modification**, ajouter aux fixtures (`SQL/fixtures/`) un jeu couvrant : un groupe avec une
   compétition CHPT (2 journées), une CP (poules + phase finale), une MULTI, un événement regroupant deux
   journées, des matchs publiés / non publiés, des journées `Break`/`Pause`, des statuts `ATT`/`ON`/`END`.
2. Écrire des tests d'intégration qui **figent la réponse actuelle** des quatre endpoints (fichiers de
   référence JSON sous `tests/Integration/__snapshots__/`).
3. Refactoriser ; les tests de caractérisation doivent rester verts sans modifier les fichiers de référence.

## 4. Nouveaux endpoints

Tous en **GET, publics, en lecture seule**. Ils ne renvoient que le **publié** (`Publication = 'O'` sur la
compétition, la journée et le match ; journées `Break`/`Pause` exclues), comme les endpoints existants.
En-tête `Cache-Control: public, max-age=60` (300 pour `/seasons` et `/group/…/competitions`).

| Endpoint | Réponse | Remplace |
|---|---|---|
| `GET /seasons` | `{ active: "2026", seasons: ["2026", "2025", …] }` (saisons > 1900 ayant au moins une compétition publiée, décroissantes ; `active` = `kp_saison.Etat = 'A'`) | combos saison |
| `GET /group/{season}/{code}/competitions` | compétitions publiées du groupe, triées `Code_niveau, Code_tour DESC, GroupOrder, Code`, chacune avec son **classement compact** (§ 5.2) | `kpclassements.php` |
| `GET /competition/{season}/{code}` | en-tête de la compétition (§ 5.1) + `siblings` (compétitions publiées du même groupe, triées `GroupOrder`) | en-têtes `kpnavgroup.tpl` |
| `GET /competition/{season}/{code}/games` | matchs publiés de la compétition : **même format** que `/group/…/games` | `kpmatchs.php`, `kpterrains.php` |
| `GET /competition/{season}/{code}/charts` | tours / phases : **même format** qu'un élément de `/group/…/charts` | `kpchart.php`, `kpphases.php` |
| `GET /competition/{season}/{code}/ranking` | classement général (§ 5.3) | `kpclassement.php` |
| `GET /competition/{season}/{code}/scorers?limit=20` | meilleurs buteurs (§ 5.4) ; `limit` entre 1 et 100 | `kpstats.php` |
| `GET /competition/{season}/{code}/info` | journées, officiels, équipes engagées par poule, schéma (§ 5.5) | `kpdetails.php` |
| `GET /event/{id}/competitions` | compétitions publiées de l'événement (même forme que `siblings`) + en-tête de l'événement (`id`, `libelle`, `place`, `logo`, dates) | `GetOtherCompetitions` (mode événement) |

- Compétition, groupe ou événement **inconnu ou non publié** → `404 {"error": "not_found"}`.
- `season` doit correspondre à `^\d{4}$`, `code` à `^[A-Za-z0-9_-]{1,12}$` ; sinon `400`.

## 5. Formats (DTO publics)

Les réponses sont construites par des **DTO dédiés** (jamais une ligne SQL brute) dont la liste de champs est
vérifiée par un test (stratégie § 11).

### 5.1 En-tête de compétition
`code`, `season`, `group` (`code`, `libelle`, `libelle_en`), `libelle`, `soustitre`, `soustitre2`,
`display_title` (règle legacy : `Soustitre` si `Titre_actif != 'O'` et `Soustitre2` non vide, sinon `Libelle`),
`type` (`CHPT` | `CP` | `MULTI`), `status` (`ATT` | `ON` | `END`), `level` (`INT` | `NAT` | `REG`…),
`banner` (chemin sous `/img/` si `Bandeau_actif = 'O'`, sinon `null`), `logo` (idem avec `Logo_actif`),
`web` (URL ou `null`), `qualified`, `eliminated` (nombres), `has_games` (booléen).

### 5.2 Classement compact (liste des compétitions)
Pour chaque compétition : `rank` (`Clt_publi`, ou `CltNiveau_publi` pour une CP), `team` (`id`, `number`,
`label`, `logo`), `points` (`Pts_publi / 100`), `played` (`J_publi`). Équipes au rang 0 exclues.

### 5.3 Classement général
`{ status, type, qualified, eliminated, rows: [...] }` avec, selon le type :
- **CHPT** : `rank, team, points, played, won, drawn, lost, forfeits, goals_for, goals_against, goal_diff` —
  tri `Clt_publi ASC, Diff_publi DESC`, rangs > 0 ;
- **CP** : idem, rang = `CltNiveau_publi`, tri `CltNiveau_publi ASC, Diff_publi DESC` ;
- **MULTI** : `rank, team, points, played` uniquement.
- Si une équipe classée a un rang 0, `qualified` et `eliminated` valent 0 (règle legacy).
- Le classement n'est renvoyé (`rows` non vide) que si `(CHPT et status ≠ ATT) ou status = END ou MULTI`.

### 5.4 Buteurs
`rows: [{ rank, first_name, last_name, number, team: { id, number, label }, goals }]` : buts (`Id_evt_match = 'B'`)
des matchs **validés et publiés**, tri `goals DESC, last_name, first_name`. **Aucun numéro de licence.**
Le `rank` est partagé en cas d'égalité (1, 2, 2, 4).

### 5.5 Informations
- `gamedays: [{ id, label, start, end, place, department, organizer, officials: { rc, r1, delegate, chief_referee } }]`
  (les noms d'officiels sont publiés aujourd'hui par `kpdetails.php`, et repris à l'identique) ;
- `teams_by_pool: [{ pool, teams: [{ id, number, label, logo }] }]`, seulement si le statut est `ON` ou `END` ;
- `schema`: chemin `/img/schemas/schema_{season}_{code}.png` s'il existe dans `legacy_document_root`, sinon `null`.

## 6. Points à valider

- **Q-P2-1 — Numéros de licence d'arbitres.** `/group/…/games` et `/event/{id}/games` exposent aujourd'hui
  `r_1_id` / `r_2_id` (`Matric_arbitre_*`, des numéros de licence, exclus par la stratégie § 11). app2 ne les
  utilise pas (vérifié : seuls `r_1`/`r_2` sont lus). **Proposition** : les retirer lors de la refactorisation,
  seule exception assumée au « JSON identique » (fichiers de référence mis à jour en conséquence).
- **Q-P2-2 — Liens PDF.** Les PDF legacy (`PdfCltChpt.php`, `PdfListeMatchs.php`…) lisent encore la session
  PHP pour certains paramètres ([LEGACY_PDF_STANDALONE_ACCESS.md](../../developer/in-progress/LEGACY_PDF_STANDALONE_ACCESS.md)).
  **Proposition** : pas de liens PDF en phase 2 ; ajoutés quand les PDF acceptent leurs paramètres en GET.

## 7. Critères d'acceptation

- **API-01** — Les réponses de `/group/{s}/{c}/games|charts` et `/event/{id}/games|charts` sur les fixtures sont
  identiques aux fichiers de référence capturés **avant** la refactorisation (hors décision Q-P2-1).
- **API-02** — Les quatre endpoints historiques et les nouveaux endpoints `games`/`charts` passent par le même
  service ; aucune requête de matchs ou de tableaux n'est dupliquée dans un contrôleur.
- **API-03** — `/competition/{s}/{c}/games` renvoie exactement les matchs de `/group/{s}/{groupe}/games` dont
  `c_code = c` (même format, même ordre).
- **API-04** — Rien de non publié n'est renvoyé (compétition, journée ou match `Publication ≠ 'O'`), ni les
  journées `Break`/`Pause`.
- **API-05** — `/seasons` renvoie la saison active et les saisons ayant des compétitions publiées, décroissantes.
- **API-06** — `/group/{s}/{c}/competitions` respecte l'ordre legacy et le classement compact (rangs 0 exclus,
  rang CP = `CltNiveau_publi`).
- **API-07** — `/competition/{s}/{c}/ranking` respecte, par type, colonnes, tri, condition d'affichage et règle
  qualifiés/éliminés (§ 5.3).
- **API-08** — `/competition/{s}/{c}/scorers` ne compte que les matchs validés et publiés, gère les égalités et
  borne `limit` ; aucun champ de licence.
- **API-09** — Compétition inconnue ou non publiée → 404 ; paramètres invalides → 400.
- **API-10** — Chaque DTO public a un test listant exactement ses champs.
- **API-11** — Les nouveaux endpoints apparaissent dans `/api2/doc` (tag « 3. Site public »).
