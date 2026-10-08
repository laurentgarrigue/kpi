# api2 — endpoints publics des résultats (phase 2)

**Phase** : 2 — **Statut** : ✅ Validée (08/10/2026, retours intégrés) — **Consommé par** : app3 (pages compétitions, compétition,
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
En-tête `Cache-Control: public, max-age=60` (300 pour `/seasons`, `/group/…/competitions` et `…/info`).

| Endpoint | Réponse | Remplace |
|---|---|---|
| `GET /seasons` | `{ active: "2026", seasons: ["2026", "2025", …] }` (saisons > 1900 ayant au moins une compétition publiée, décroissantes ; `active` = `kp_saison.Etat = 'A'`) | combos saison |
| `GET /group/{season}/{code}/competitions` | `{ events, competitions }` : compétitions publiées du groupe, triées `Code_niveau, Code_tour DESC, GroupOrder, Code`, chacune avec son en-tête (§ 5.1) et son **classement compact** (§ 5.2) ; `events` = événements liés au groupe (§ 5.6) | `kpclassements.php` |
| `GET /competition/{season}/{code}` | en-tête de la compétition (§ 5.1) + `siblings` (compétitions publiées du même groupe, triées `GroupOrder`) + `events` (§ 5.6) | en-têtes `kpnavgroup.tpl` |
| `GET /competition/{season}/{code}/games` | matchs publiés de la compétition : **même format** que `/group/…/games` | `kpmatchs.php`, `kpterrains.php` |
| `GET /competition/{season}/{code}/charts` | tours / phases : **même format** qu'un élément de `/event/{id}/charts` (le plus riche : libellés d'attente résolus, `d_id` par phase, équipes des poules de CP déduites des matchs) | `kpchart.php`, `kpphases.php` |
| `GET /competition/{season}/{code}/ranking` | classement général (§ 5.3) | `kpclassement.php` |
| `GET /competition/{season}/{code}/stats` | statistiques disponibles : `{ kinds: ["scorers"] }` (§ 5.4) | — |
| `GET /competition/{season}/{code}/stats/{kind}?limit=20` | une statistique (§ 5.4) ; `limit` entre 1 et 100 ; `kind` inconnu → 404 | `kpstats.php` |
| `GET /competition/{season}/{code}/info` | journées, officiels, équipes engagées par poule, schéma (§ 5.5) | `kpdetails.php` |
| `GET /event/{id}/competitions` | `{ event: { id, libelle, place, logo, start, end }, competitions: [{ code, season, display_title, soustitre2 }] }` : tournoi publié (`kp_evenement`) et ses compétitions publiées ayant une journée publiée dans l'événement | `GetOtherCompetitions` (mode événement) |

- Compétition, groupe ou événement **inconnu ou non publié** → `404 {"error": "not_found"}`.
- `season` doit correspondre à `^\d{4}$`, `code` à `^[A-Za-z0-9_-]{1,12}$` ; sinon `400`.

## 5. Formats (DTO publics)

Les réponses sont construites par des **DTO dédiés** (jamais une ligne SQL brute) dont la liste de champs est
vérifiée par un test (stratégie § 11).

### 5.1 En-tête de compétition
`code`, `season`, `group` (`code`, `libelle`, `libelle_en`), `libelle`, `soustitre`, `soustitre2`,
`display_title` (règle legacy de `kpclassements.tpl` et des PDF : `Libelle` si `Titre_actif = 'O'`, sinon
`Soustitre`, et `Libelle` si `Soustitre` est vide),
`type` (`CHPT` | `CP` | `MULTI`), `status` (`ATT` | `ON` | `END`), `level` (`INT` | `NAT` | `REG`…),
`banner` (chemin sous `/img/` si `Bandeau_actif = 'O'`, sinon `null`), `logo` (idem avec `Logo_actif`),
`web` (URL ou `null`), `qualified`, `eliminated` (nombres), `has_games` (booléen),
`round` (`Code_tour`, 1 à 10), `final` (booléen : `Code_tour = 10`, tour final, affiché « F » dans app4).

### 5.2 Classement compact (liste des compétitions)
Pour chaque compétition : `rank` (`Clt_publi`, ou `CltNiveau_publi` pour une CP), `team` (`id`, `number`,
`label`, `logo`), `points` (`Pts_publi / 100`), `played` (`J_publi`), `medal` (§ 5.7). Équipes au rang 0 exclues.

### 5.3 Classement général
`{ status, type, qualified, eliminated, rows: [...] }` avec, selon le type :
- **CHPT** : `rank, team, points, played, won, drawn, lost, forfeits, goals_for, goals_against, goal_diff` —
  tri `Clt_publi ASC, Diff_publi DESC`, rangs > 0 ;
- **CP** : idem, rang = `CltNiveau_publi`, tri `CltNiveau_publi ASC, Diff_publi DESC` ;
- **MULTI** : `rank, team, points, played` uniquement.
- Chaque ligne porte `medal` (§ 5.7).
- Si une équipe classée a un rang 0, `qualified` et `eliminated` valent 0 (règle legacy).
- Le classement n'est renvoyé (`rows` non vide) que si `(CHPT et status ≠ ATT) ou status = END ou MULTI`.

### 5.4 Statistiques (extensibles)
Chaque statistique est un **fournisseur** indépendant (`CompetitionStatInterface` : `kind()`, `compute(scope, limit)`),
enregistré par étiquette de service Symfony. Ajouter une statistique (meilleure attaque, meilleure défense,
cartons, fair-play…) = ajouter une classe et sa spec, **sans modifier** le contrôleur ni les autres statistiques
(principe ouvert/fermé). Réponse commune : `{ kind, columns: [...], rows: [...] }`, où `columns` décrit les
valeurs propres à la statistique (clé + type), pour que l'affichage d'app3 soit générique.

Livrée en phase 2 — **`scorers`** (meilleurs buteurs) :
`rows: [{ rank, first_name, last_name, number, team: { id, number, label }, goals }]`, `columns: [{ key: "goals", type: "integer" }]` :
buts (`Id_evt_match = 'B'`) des matchs **validés et publiés**, tri `goals DESC, last_name, first_name`.
**Aucun numéro de licence.** Le `rank` est partagé en cas d'égalité (1, 2, 2, 4).

Pressenties (specs à écrire le moment venu) : `attack` (buts marqués par équipe), `defense` (buts encaissés),
`cards` (cartons verts, jaunes, rouges par joueur ou équipe).

### 5.5 Informations
- `gamedays: [{ id, name, phase, start, end, place, department, organizer, officials: { rc, r1, delegate, chief_referee } }]` :
  journées publiées hors pauses, par date ; noms d'officiels publiés aujourd'hui par `kpdetails.php`, **sans le
  numéro de licence** stocké entre parenthèses (« NOM Prénom (123456) » → « NOM Prénom », comme `utyGetNomPrenom`) ;
- `teams_by_pool: [{ pool, teams: [{ id, number, label, logo }] }]`, seulement si le statut est `ON` ou `END` ;
- `schema`: chemin `/img/schemas/schema_{season}_{code}.png` s'il existe dans `legacy_document_root`, sinon `null`.

### 5.6 Événements liés
Un événement (`kp_evenement`, publié) est lié à une portée (compétition ou groupe) quand il contient au moins une
de ses journées publiées (`kp_evenement_journee`). `events: [{ id, libelle, place, start, end, logo, share }]`,
triés par `share` décroissant puis date de début, où `share` (0 à 1) = journées publiées de la portée incluses
dans l'événement / journées publiées de la portée. Les pages s'en servent pour mettre en avant l'événement
principal (PAGE_COMPETITIONS.md, PAGE_COMPETITION.md).

### 5.7 Médailles
`medal` vaut `1`, `2` ou `3` quand la compétition est **terminée** (`status = END`), du **tour final**
(`final = true`) et que le rang de l'équipe (`rank`, donc `CltNiveau_publi` pour une CP) est 1, 2 ou 3 ; sinon
`null`. Règle reprise de `kpclassement.tpl` / `kpclassements.tpl`, calculée **une seule fois** dans le service.

## 6. Décisions (validées le 08/10/2026)

- **D-P2-1 — Numéros de licence d'arbitres retirés.** `/group/…/games` et `/event/{id}/games` exposaient
  `r_1_id` / `r_2_id` (`Matric_arbitre_*`), exclus par la stratégie § 11 et non lus par app2 (seuls `r_1`/`r_2`
  le sont). Ils sont retirés lors de la refactorisation : seule exception assumée au « JSON identique »
  (fichiers de référence mis à jour en conséquence, dans un commit distinct).
- **D-P2-3 — Rien de non publié dans les tableaux** (constat des tests de caractérisation, 08/10/2026).
  `/group/…/charts` listait aussi les phases des journées **non publiées** (sans leurs matchs) et
  `/event/{id}/charts` ne filtrait ni la compétition ni la journée. Les tableaux ne retiennent désormais que
  les compétitions et journées publiées (API-04) ; fichiers de référence mis à jour dans le même commit
  (suppressions seulement).
- **D-P2-2 — Liens PDF.** Les PDF publics doivent accepter leurs paramètres en GET, comme ceux appelés par
  app4 ([LEGACY_PDF_STANDALONE_ACCESS.md](../../developer/in-progress/LEGACY_PDF_STANDALONE_ACCESS.md)).
  Vérification faite : les PDF publics utiles à app3 les acceptent déjà, la session n'étant qu'une valeur par
  défaut. app3 les appelle **toujours** avec `S` et `Compet` explicites, et `lang` :

  | PDF | Paramètres GET | Utilisé par |
  |---|---|---|
  | `PdfCltChpt.php` (CHPT) / `PdfCltNiveauPhase.php` (CP) / `PdfCltMulti.php` (MULTI) | `S`, `Compet`, `lang` | onglet Classement |
  | `PdfListeMatchs.php` / `PdfListeMatchsEN.php` | `S`, `Compet` (liste séparée par des virgules possible) ou `idEvenement` | onglets Matchs (compétition, groupe, événement) |
  | `PdfMatchMulti.php` | `listMatch`, `lang` | feuille de marque d'un match validé |

  Tout PDF public qui ne lirait pas l'un de ces paramètres en GET est corrigé selon le motif de
  LEGACY_PDF_STANDALONE_ACCESS.md § B (`utyGetGet` après la valeur de session), sans changer le comportement
  de l'administration legacy. Les liens sont construits par **une** fonction pure d'app3 (`pdfUrl`).

## 7. Critères d'acceptation

- **API-01** — Les réponses de `/group/{s}/{c}/games|charts` et `/event/{id}/games|charts` sur les fixtures sont
  identiques aux fichiers de référence capturés **avant** la refactorisation (hors décisions D-P2-1 et D-P2-3).
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
- **API-08** — `/competition/{s}/{c}/stats/scorers` ne compte que les matchs validés et publiés, gère les égalités
  et borne `limit` ; aucun champ de licence ; `/stats` liste les statistiques disponibles, un `kind` inconnu → 404.
- **API-09** — Compétition inconnue ou non publiée → 404 ; paramètres invalides → 400.
- **API-10** — Chaque DTO public a un test listant exactement ses champs.
- **API-11** — Les nouveaux endpoints apparaissent dans `/api2/doc` (tag « 7. Site public », les numéros 1 à 6
  étant pris).
- **API-12** — `medal` vaut 1/2/3 seulement pour une compétition `END` du tour final (`Code_tour = 10`), selon
  le rang propre au type ; `null` sinon (classement compact et classement général).
- **API-13** — `events` liste les événements publiés contenant des journées publiées de la portée, avec leur
  `share`, triés par `share` décroissant.
- **API-14** — `r_1_id` / `r_2_id` n'apparaissent plus dans aucune réponse de matchs (D-P2-1).
