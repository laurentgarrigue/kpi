# Médailles de match et statistiques de timeline

**Phase** : transverse (après la phase 4, avant ou pendant la phase 5) — **Statut** : 📝 Brouillon
**Surfaces** : app3 (site public) et app2 (PWA résultats) — **Données** : api2, sans saisie supplémentaire
**Remplace** : — (nouveauté)

## 1. Objectif

Raconter chaque match autrement que par le score : à partir de la **timeline** déjà saisie (buts et cartons
horodatés), attribuer des **médailles** aux équipes et aux joueurs (premier but, but en supériorité numérique,
remontée au score…) et en tirer des **statistiques originales** par compétition et par événement (taux de
conversion en supériorité, part des matchs gagnés par l'équipe qui ouvre le score…).

Public visé : joueurs, clubs, spectateurs et commentateurs. Rien n'est saisi en plus : tout est **déduit** des
faits de match existants.

**Décisions de cadrage (08/11/2026)** :

| Question | Décision |
|---|---|
| Surfaces du premier lot | **app3** (stats compétition / événement, fiche équipe) et **app2** (feuille de match, stats). Incrustations TV et fiche joueur : plus tard (§ 9). |
| Matchs éligibles | **Matchs validés uniquement** (`kp_match.Validation = 'O'`), publiés. |
| Terminologie | **Anglicismes sportifs assumés** (vocabulaire courant des clubs : *Powerplay*, *Comeback*…), jamais guerriers. Mêmes noms en français et en anglais, description traduite. |
| Tirs (`T`) et arrêts (`A`) | **Exclus** du premier lot : buts et cartons seulement (§ 9). |

## 2. Vocabulaire

| Terme | Définition |
|---|---|
| **Timeline** | Suite des faits de match d'un match (`kp_match_detail`) : buts (`B`) et cartons (`V`, `J`, `R`, `D`), chacun avec une période et un temps. |
| **Temps de jeu écoulé** | Position d'un fait sur un axe continu depuis le coup d'envoi, en secondes (§ 4.2). Le champ `Temps` stocke le **temps restant** au chrono de la période (décompte). |
| **Exclusion** | Infériorité numérique provoquée par un carton (§ 4.3). |
| **Supériorité / infériorité** | Une équipe est en **supériorité** quand l'adversaire a au moins une exclusion en cours, et qu'elle-même en a moins ; en **infériorité** dans le cas inverse. |
| **Médaille** | Distinction attribuée par un match à une équipe ou à un joueur, selon une règle de § 3. Un match peut attribuer la même médaille plusieurs fois (ex. deux buts en supériorité). |
| **Médaille d'équipe / de joueur** | Selon son bénéficiaire. Une médaille de joueur rapporte aussi la médaille à son équipe dans les agrégats. |
| **Timeline fiable** | Timeline qui permet les médailles « chronologiques » (§ 4.1). |

> Les termes *Timeline*, *Médaille* et *Exclusion* seront ajoutés à `GLOSSARY.md` à la validation de la spec.

## 3. Catalogue des médailles

Chaque médaille a un **code** stable (clé technique et i18n), un **nom** (identique FR/EN), une **description**
traduite, une **famille** (pour l'affichage) et un **niveau de données** requis :

- **S (score)** : n'a besoin que du score et des faits de match, sans ordre ni temps ;
- **T (timeline)** : a besoin d'une timeline fiable (§ 4.1).

Les seuils (en *italique*) sont des **paramètres** (§ 4.4), pas des valeurs codées en dur.

### 3.1 Médailles d'équipe

| Code | Nom | Règle d'attribution | Famille | Données |
|---|---|---|---|---|
| `opener` | **Opener** | Marque le premier but du match. | Momentum | T |
| `wire_to_wire` | **Wire to Wire** | Ouvre le score, n'est jamais rejointe ni dépassée, et gagne. | Momentum | T |
| `scoring_run` | **Scoring Run** | Marque au moins *3* buts consécutifs sans en encaisser. Une seule médaille par série ; plusieurs séries = plusieurs médailles. | Momentum | T |
| `bounce_back` | **Bounce Back** | Marque moins de *60 s* de temps de jeu après avoir encaissé un but. Une médaille par but concerné. | Réaction | T |
| `comeback` | **Comeback** | Menée d'au moins *3* buts à un moment du match, finit par gagner. | Réaction | T |
| `comeback_draw` | **Fightback** | Menée d'au moins *3* buts, obtient le match nul (matchs de classement, type `C`). | Réaction | T |
| `late_winner` | **Late Winner** | Marque le but décisif (§ 4.5) dans les *60* dernières secondes du temps réglementaire. | Réaction | T |
| `lifeline` | **Lifeline** | Égalise dans les *60* dernières secondes de `M2` et arrache la prolongation (match éliminatoire, type `E`). | Réaction | T |
| `extra_time` | **Extra Time** | Le match va en prolongation (les deux équipes). | Endurance | S |
| `marathon` | **Marathon** | Le match atteint au moins une *2ᵉ* prolongation (`P2`) (les deux équipes). | Endurance | S |
| `golden_goal` | **Golden Goal** | Gagne en prolongation (but en or). | Endurance | S |
| `powerplay` | **Powerplay** | Marque en supériorité numérique (§ 4.3). Une médaille par but. | Supériorité | T |
| `shorthanded` | **Shorthanded** | Marque en infériorité numérique. Une médaille par but. | Supériorité | T |
| `penalty_kill` | **Penalty Kill** | Traverse au moins *2* exclusions dans le match sans encaisser de but pendant ces exclusions. | Supériorité | T |
| `clean_sheet` | **Clean Sheet** | N'encaisse aucun but (match terminé, adversaire présent). | Défense | S |
| `big_win` | **Big Win** | Gagne avec au moins *5* buts d'écart. | Défense | S |
| `fair_play` | **Fair Play** | Ne reçoit aucun carton dans le match. | Esprit sportif | S |

### 3.2 Médailles de joueur

| Code | Nom | Règle d'attribution | Famille | Données |
|---|---|---|---|---|
| `first_goal` | **First Goal** | Marque le premier but du match. | Momentum | T |
| `fast_start` | **Fast Start** | Marque dans les *30* premières secondes d'une période réglementaire (`M1`, `M2`). | Momentum | T |
| `hat_trick` | **Hat-trick** | Marque au moins *3* buts dans le match. | Finition | S |
| `natural_hat_trick` | **Natural Hat-trick** | Marque *3* buts consécutifs du match (aucun autre but, des deux équipes, entre eux). | Finition | T |
| `game_winner` | **Game Winner** | Marque le but décisif (§ 4.5). | Réaction | T |
| `bounce_back` | **Bounce Back** | Marque le but de la médaille d'équipe `bounce_back`. | Réaction | T |
| `late_winner` | **Late Winner** | Marque le but de la médaille d'équipe `late_winner`. | Réaction | T |
| `lifeline` | **Lifeline** | Marque le but de la médaille d'équipe `lifeline`. | Réaction | T |
| `golden_goal` | **Golden Goal** | Marque le but en or. | Endurance | S |
| `powerplay` | **Powerplay** | Marque en supériorité numérique. | Supériorité | T |
| `shorthanded` | **Shorthanded** | Marque en infériorité numérique. | Supériorité | T |

**Exclusions communes** : les faits de la période `TB` (tirs au but) ne comptent pour **aucune** médaille ; les buts
d'un membre marqué entraîneur (`kp_match_joueur.Capitaine = 'E'`) ne rapportent pas de médaille de joueur (même
règle que les statistiques existantes) ; un but sans joueur identifié rapporte la médaille d'équipe, pas celle de
joueur.

### 3.3 Étiquettes de match

En plus des médailles, un match reçoit des **étiquettes** qui le qualifient dans les listes (« matchs à revoir ») :

| Code | Nom | Règle | Données |
|---|---|---|---|
| `thriller` | **Thriller** | Au moins *3* changements de leader (§ 4.5). | T |
| `seesaw` | **Seesaw** | Au moins *4* égalisations dans le match. | T |
| `goal_fest` | **Goal Fest** | Total de buts ≥ *percentile 90* des matchs de la compétition (au moins *10* matchs éligibles). | S |
| `lockdown` | **Lockdown** | Total de buts ≤ *2*. | S |

## 4. Règles de calcul

### 4.1 Éligibilité et fiabilité

Un match **attribue des médailles** si :

1. il est **validé** (`Validation = 'O'`) et **publié** (match, journée et compétition publiés) ;
2. son score est numérique des deux côtés (pas de forfait ni de score non saisi) ;
3. il a deux équipes.

Sa **timeline est fiable** (médailles **T** et étiquettes **T** autorisées) si, en plus :

4. le nombre de buts `B` hors `TB` de chaque équipe est égal à son score (`ScoreA`, `ScoreB`) ;
5. chaque but et chaque carton hors `TB` a une période et un temps renseignés ;
6. aucun temps n'est hors de sa période (temps restant > durée de la période, § 4.2).

Un match éligible dont la timeline n'est pas fiable n'attribue **que les médailles S**. L'API expose ce niveau
(`timeline_reliable: false`) pour que l'affichage l'explique (« médailles chronologiques indisponibles : feuille
incomplète »), sans jamais l'imputer à une équipe.

### 4.2 Axe de temps

- Ordre des périodes : `M1`, `M2`, `P1`, `P2`, … `Pn` (non borné, cf. PAGE_SCORING.md § 0.6). `TB` est hors axe.
- La **durée d'une période** n'est pas stockée. Durée retenue : la durée par défaut (`M*` = *600 s*, `P*` = *300 s*),
  **allongée** à la minute pleine supérieure au plus grand temps restant saisi dans cette période — même règle que
  la frise d'app2 (`sources/app2/utils/gameTimeline.ts`), à **mutualiser** plutôt qu'à dupliquer.
- Temps écoulé d'un fait = somme des durées des périodes précédentes + (durée de sa période − temps restant).
- À temps égal, l'ordre est : carton avant but (un carton et un but à la même seconde : le but est réputé marqué
  après le carton), puis ordre de saisie (`date_insert`, puis `Id`).

> **Limite connue** : pour une compétition jouée en périodes plus courtes (jeunes, tournois), la durée par défaut
> fausse les écarts **entre deux périodes**. Les écarts **dans une même période** restent exacts. Évolution : lire
> la durée dans la configuration de la compétition dès qu'elle existera (PAGE_SCORING.md § 6.4). Voir § 9, Q1.

### 4.3 Exclusions et supériorité numérique

Règle de jeu reprise de PAGE_SCORING.md § 7.4 :

- chaque carton (`V`, `J`, `R`, `D`) ouvre une **exclusion** du joueur pendant *120 s* de temps de jeu (durée
  paramétrable **par couleur**, § 4.4), qui se poursuit d'une période à l'autre ;
- une exclusion se **termine plus tôt** au premier but encaissé par l'équipe exclue, **sauf** pour le rouge
  définitif (`D`), qui va à son terme ;
- si l'équipe a deux exclusions en cours, le but adverse lève **la plus ancienne** (hors `D`) ;
- l'équipe ne descend jamais sous 3 joueurs : au plus 2 exclusions comptées par équipe à un instant donné.

Un but est **en supériorité** si, à l'instant du but, l'adversaire a strictement plus d'exclusions en cours que
l'équipe qui marque ; **en infériorité** dans le cas inverse. À égalité d'exclusions (ex. une de chaque côté), le
but n'est ni l'un ni l'autre.

### 4.4 Paramètres

Les seuils sont regroupés dans un objet de configuration unique côté api2 (`MedalRules`), versionné (§ 5.2). Valeurs
initiales :

| Paramètre | Valeur | Utilisé par |
|---|---|---|
| `bounceBackSeconds` | 60 | `bounce_back` |
| `comebackDeficit` | 3 | `comeback`, `comeback_draw` |
| `scoringRunGoals` | 3 | `scoring_run` |
| `lateWindowSeconds` | 60 | `late_winner`, `lifeline` |
| `fastStartSeconds` | 30 | `fast_start` |
| `hatTrickGoals` | 3 | `hat_trick`, `natural_hat_trick` |
| `bigWinMargin` | 5 | `big_win` |
| `penaltyKillExclusions` | 2 | `penalty_kill` |
| `exclusionSeconds` | `{ V: 120, J: 120, R: 120, D: 120 }` | § 4.3 |
| `regularPeriodSeconds` / `extraPeriodSeconds` | 600 / 300 | § 4.2 |
| `thrillerLeadChanges` / `seesawEqualizers` | 3 / 4 | étiquettes |
| `goalFestPercentile` / `goalFestMinGames` / `lockdownMaxGoals` | 90 / 10 / 2 | étiquettes |

### 4.5 Définitions dérivées

- **Leader** : équipe qui mène au score. Un **changement de leader** est un but qui fait passer l'avantage d'une
  équipe à l'autre (une égalisation n'en est pas un ; le but suivant qui redonne l'avantage à l'autre équipe en est
  un).
- **But décisif** : pour le vainqueur, le but qui porte son score à (score final du perdant + 1). Pas de but décisif
  pour un match nul.

## 5. Données (api2)

### 5.1 Calcul

- Un **service de domaine pur** (`src/MatchMedals/`) prend un match (score, type, période finale) et sa timeline, et
  renvoie ses médailles et étiquettes. Sans accès base, sans horloge : il est testé unitairement règle par règle.
- Chaque médaille est une **règle** indépendante (`MedalRuleInterface` : `code()`, `scope()` équipe/joueur,
  `dataLevel()` S/T, `award(MatchTimeline)`), enregistrée par étiquette de service Symfony — même principe
  ouvert/fermé que les statistiques (API_PUBLIC_RESULTS.md § 5.4). Ajouter une médaille = une classe + sa ligne dans
  cette spec.

### 5.2 Persistance

Les matchs validés ne bougent plus : les médailles sont **calculées une fois et stockées**, ce qui rend les
agrégats (compétition, événement, saison) simples et rapides.

- Table `kp_match_medal` : `id`, `match_id`, `code`, `scope` (`team`/`player`), `team_side` (`A`/`B`),
  `team_id`, `player_matric` (NULL pour une médaille d'équipe), `event_detail_id` (but à l'origine, si applicable),
  `game_seconds` (temps écoulé du but, si applicable), `rules_version`. Index sur `match_id`, `team_id`,
  `player_matric`, `code`.
- Table `kp_match_insight` (une ligne par match calculé) : `match_id`, `timeline_reliable`, `labels` (JSON), `lead_changes`,
  `equalizers`, `first_goal_seconds`, `max_deficit_A`, `max_deficit_B`, `exclusions_A`, `exclusions_B`,
  `powerplay_goals_A`, `powerplay_goals_B`, `rules_version`, `source_hash`, `computed_at`. Elle sert aussi les
  statistiques de taux (§ 6.2) et le suivi du recalcul.
- Migration Doctrine dans api2 ; noms de tables préfixés `kp_` comme le reste du schéma.

**Recalcul** :

- **À la validation / dévalidation** d'un match dans api2 (`AdminGamesController`) : calcul immédiat, ou
  suppression des médailles à la dévalidation.
- **Commande** `app:match-medals:refresh [--season=] [--competition=] [--event=] [--all]`, lancée **par cron**
  (toutes les 10 min) : traite les matchs validés sans calcul, ceux dont `source_hash` (hash du score et de la
  timeline) a changé, ceux calculés avec une `rules_version` antérieure, et supprime ceux qui ne sont plus validés.
  Elle couvre la validation faite dans le legacy et le **rattrapage de l'historique** (toutes saisons).
- Changer une règle ou un seuil = incrémenter `rules_version` ; la commande recalcule tout au passage suivant.

### 5.3 Endpoints publics

Lecture seule, sans authentification, mêmes règles de publication que les résultats. **Aucun numéro de licence**
exposé (identifiant joueur public opaque, comme `scorers`).

| Endpoint | Contenu | Consommateurs |
|---|---|---|
| `GET /game-sheet/{id}` (existant) | **ajoute** `medals: { reliable, labels: [...], team: [{ code, side, count }], player: [{ code, side, number, first_name, last_name, event_id }] }` ; vide si le match n'est pas validé | app2 feuille de match |
| `GET /competition/{season}/{code}/stats` (existant) | `kinds` **s'enrichit** des statistiques de § 6 | app3 onglet Stats |
| `GET /competition/{season}/{code}/stats/{kind}` | une statistique de § 6, format commun `{ kind, columns, rows }` | app3, app2 |
| `GET /event/{id}/stats` et `…/stats/{kind}` | mêmes statistiques sur le périmètre d'un événement (à créer si l'onglet Stats événement n'a pas encore son endpoint) | app3, app2 |
| `GET /team/{id}/medals?season=` | collection de médailles de l'équipe sur la saison, par code, avec les matchs concernés | app3 fiche équipe |

Cache HTTP : celui des statistiques existantes (les données ne changent qu'au recalcul).

## 6. Statistiques globales (compétition, événement)

Chaque statistique est un fournisseur `CompetitionStatInterface` (kind) ; le périmètre est la compétition ou
l'événement. Ne comptent que les matchs éligibles ; les taux qui reposent sur des médailles **T** ne comptent que les
matchs à timeline fiable, et l'affichage indique sur combien de matchs le taux est calculé.

### 6.1 Classements de médailles

| Kind | Contenu |
|---|---|
| `medals_teams` | Par équipe : nombre de médailles par code, total, tri par total puis par nom. |
| `medals_players` | Par joueur : idem (top 20 par défaut, `limit` comme `scorers`). |
| `medals_{code}` | Classement d'une médaille donnée (ex. `medals_powerplay` = meilleurs buteurs en supériorité). |

### 6.2 Indicateurs de compétition

| Kind | Indicateur | Calcul |
|---|---|---|
| `opener_wins` | **First goal wins** | % des matchs gagnés par l'équipe qui ouvre le score (nuls à part). |
| `powerplay_rate` | **Powerplay %** par équipe | buts en supériorité / exclusions adverses subies. |
| `penalty_kill_rate` | **Penalty Kill %** par équipe | exclusions sans but encaissé / exclusions. |
| `goal_timing` | **Goal timing** | répartition des buts par tranche de *2 min* de temps de jeu (M1, M2, prolongations) : histogramme. |
| `first_goal_time` | **First goal time** | délai moyen et médian avant le premier but ; but le plus rapide (joueur, match). |
| `comebacks` | **Comebacks** | nombre de `comeback`, plus grosse remontée (écart, match). |
| `extra_time` | **Extra Time** | matchs en prolongation, nombre maximal de prolongations d'un match, buts en or. |
| `discipline` | **Discipline** | cartons par match et par équipe, minutes d'infériorité subies (complète la statistique `cards` pressentie). |
| `highlights` | **Highlights** | liste des matchs étiquetés `thriller`, `seesaw`, `goal_fest`, triés par date. |

Les noms de kinds suivent les libellés i18n `stats.<kind>.*` d'app3 ; app3 n'ajoute que des libellés et, pour
`goal_timing`, un histogramme (règles de `dataviz` / charte FFCK).

## 7. Affichage

### 7.1 Composant commun

- Une médaille s'affiche en **pastille** : pictogramme (jeu d'icônes déjà utilisé par l'application, un par famille
  et par médaille), nom, compteur si > 1 (« Powerplay ×2 »), **info-bulle** avec la description traduite et le temps
  du but (« M2 · 03:12 »). Couleur par **famille**, issue des jetons de la charte, jamais seule porteuse du sens
  (nom toujours écrit).
- Pas de médaille « négative » : on ne distingue jamais une équipe pour ce qu'elle a subi (pas de « a encaissé
  une remontée »).

### 7.2 app2 (PWA résultats)

- **Feuille de match** (`/game/[id]`) : bloc « Médailles » sous le score, une colonne par équipe (médailles
  d'équipe puis de joueurs), étiquettes du match en tête. Sur la frise (`GameTimelineBar`), les buts médaillés
  portent un repère discret (ex. contour pour *Powerplay*).
- Match non validé : bloc absent (pas de message). Timeline non fiable : mention « Médailles chronologiques
  indisponibles (feuille incomplète) ».
- Statistiques (`/team`, groupe) : les kinds de § 6 s'ajoutent à la liste existante.

### 7.3 app3 (site public)

- **Onglet Stats** de la compétition et de l'événement : les nouveaux kinds dans le sélecteur générique ;
  `medals_teams` et `highlights` en tête.
- **Liste des matchs** : une icône discrète à côté du score pour un match étiqueté (`thriller`…), avec info-bulle.
- **Fiche équipe** : section « Médailles de la saison » (compteurs par médaille, lien vers les matchs).
- SEO : rien de spécifique ; les médailles sont du texte rendu côté serveur, lisible par les lecteurs d'écran
  (`aria-label` = nom + description + compteur).

## 8. Critères d'acceptation

**Éligibilité**

- **MED-01** — Étant donné un match terminé non validé, quand on demande sa feuille, alors `medals` est vide et
  aucune ligne n'existe dans `kp_match_medal`.
- **MED-02** — Étant donné un match validé dont le total des buts de A diffère de `ScoreA`, quand on calcule, alors
  `timeline_reliable = false` et seules les médailles **S** sont attribuées.
- **MED-03** — Étant donné un match validé dont un but n'a pas de temps, quand on calcule, alors
  `timeline_reliable = false`.
- **MED-04** — Étant donné un match dont un score est un forfait (non numérique), quand on calcule, alors aucune
  médaille n'est attribuée.
- **MED-05** — Étant donné des buts en période `TB`, quand on calcule, alors ils ne comptent pour aucune médaille ni
  pour la fiabilité.

**Axe de temps et exclusions**

- **MED-06** — Étant donné un but en `M2` avec 9:10 restant et des périodes par défaut, alors son temps écoulé est
  650 s ; avec un temps restant de 11:30 en `M2`, la durée retenue de `M2` est 720 s.
- **MED-07** — Étant donné un carton jaune de B à 05:00 restant en `M1` et un but de A à 04:00 restant en `M1`,
  alors le but de A est `powerplay` et le but de B qui suit à 03:30 n'est plus en infériorité pour B (exclusion
  levée).
- **MED-08** — Étant donné un carton `D` de B puis deux buts de A dans les 120 s, alors les deux buts sont
  `powerplay` (le rouge définitif n'est pas levé par un but).
- **MED-09** — Étant donné un carton de B à 00:30 restant en `M1` et un but de A à 09:20 restant en `M2`, alors le
  but est `powerplay` (l'exclusion se poursuit sur la période suivante : 30 s + 40 s < 120 s).
- **MED-10** — Étant donné une exclusion de chaque côté en cours, quand A marque, alors le but n'est ni
  `powerplay` ni `shorthanded`.
- **MED-11** — Étant donné un carton de A et un but de A à la même seconde, alors le but est `shorthanded`
  (carton avant but à temps égal).

**Médailles d'équipe**

- **MED-12** — `opener` / `first_goal` vont à l'équipe et au joueur du premier but sur l'axe de temps.
- **MED-13** — Étant donné A mène 1-0, B égalise 1-1, puis A gagne 3-1, alors A n'a pas `wire_to_wire`.
- **MED-14** — Étant donné B mène 4-1 puis A gagne 5-4, alors A reçoit `comeback` ; avec un final 4-4 en match de
  type `C`, A reçoit `comeback_draw`.
- **MED-15** — Étant donné A encaisse à 06:00 restant et marque à 05:01 restant dans la même période, alors A et le
  buteur reçoivent `bounce_back` ; à 05:00 restant (60 s), non (strictement moins de 60 s).
- **MED-16** — Étant donné A marque 4 buts consécutifs, alors A reçoit une seule `scoring_run` ; deux séries de 3
  séparées par un but de B donnent deux médailles.
- **MED-17** — Étant donné un match de type `E` décidé en `P2`, alors les deux équipes reçoivent `extra_time` et
  `marathon`, le vainqueur `golden_goal`, le buteur `golden_goal`.
- **MED-18** — Étant donné un match terminé 0-0, alors les deux équipes reçoivent `clean_sheet`.
- **MED-19** — Étant donné une équipe sans carton, alors elle reçoit `fair_play`.
- **MED-20** — Étant donné B exclue deux fois sans encaisser pendant ces exclusions, alors B reçoit
  `penalty_kill`.

**Médailles de joueur**

- **MED-21** — Étant donné un joueur auteur de 3 buts, alors il reçoit `hat_trick` (médaille S, même sans timeline
  fiable) ; si ces 3 buts sont consécutifs dans le match, il reçoit aussi `natural_hat_trick`.
- **MED-22** — Étant donné A gagne 4-2, alors le 3ᵉ but de A porte `game_winner`.
- **MED-23** — Étant donné le but décisif marqué à 00:45 restant en `M2`, alors il porte `late_winner` ; un but
  égalisateur dans la même fenêtre d'un match `E` allant en prolongation porte `lifeline`.
- **MED-24** — Étant donné un but marqué par un membre `Capitaine = 'E'` ou sans joueur identifié, alors l'équipe
  reçoit la médaille d'équipe mais aucun joueur ne reçoit la médaille de joueur.

**Persistance et API**

- **MED-25** — Étant donné un match validé, quand il est dévalidé dans api2, alors ses médailles sont supprimées ;
  revalidé après correction, elles sont recalculées.
- **MED-26** — Étant donné un match validé dans le legacy, quand la commande de recalcul passe, alors ses médailles
  existent ; repassée sans changement, elle ne réécrit rien (`source_hash` identique).
- **MED-27** — Étant donné une `rules_version` incrémentée, quand la commande passe, alors tous les matchs sont
  recalculés.
- **MED-28** — Aucune réponse publique ne contient de numéro de licence.
- **MED-29** — `GET /competition/{season}/{code}/stats` liste les nouveaux kinds ; `…/stats/powerplay_rate` renvoie
  le format commun `{ kind, columns, rows }`, et indique le nombre de matchs à timeline fiable pris en compte.

**Affichage**

- **MED-30** — Étant donné un match validé médaillé, la feuille de match app2 affiche le bloc « Médailles » ; chaque
  pastille a un nom écrit et une description accessible (`aria-label`).
- **MED-31** — Étant donné une timeline non fiable, la feuille de match app2 affiche les médailles S et la mention
  « Médailles chronologiques indisponibles (feuille incomplète) ».
- **MED-32** — L'onglet Stats d'app3 (compétition et événement) affiche `medals_teams` et `highlights` sans code
  spécifique autre que les libellés i18n, et `goal_timing` en histogramme.
- **MED-33** — La fiche équipe d'app3 affiche les médailles de la saison, chaque compteur menant aux matchs
  concernés.

## 9. Hors périmètre et questions ouvertes

**Hors périmètre (lots suivants)** :

- Incrustations TV / live (« Opener ! », « Powerplay » en direct) : demanderait le calcul sur matchs non validés.
- Fiche joueur publique et collection de carrière (la fiche n'existe pas encore).
- Tirs (`T`) et arrêts (`A`) : médailles gardien (*Big Save*, *Wall*) et efficacité (*Sniper*) dès que ces faits
  seront saisis de façon régulière.
- Agrégats de saison toutes compétitions et palmarès historique des médailles.
- Badges partageables (image pour réseaux sociaux).

**Questions ouvertes** :

- **Q1 — Durée des périodes** : faut-il, dès ce lot, une table de durées par compétition (ou par catégorie d'âge)
  pour fiabiliser les écarts entre périodes, ou accepte-t-on l'approximation de § 4.2 ?
- **Q2 — Durée d'exclusion par carton** : PAGE_SCORING.md § 7.4 fixe 2 min pour **tout** carton. Le rouge (`R`)
  et le rouge définitif (`D`) ont-ils une autre durée d'infériorité dans les règlements FFCK / ICF (ex. 4 min) ?
  Valeurs à confirmer dans `exclusionSeconds`.
- **Q3 — Seuils** : 60 s pour *Bounce Back*, 3 buts pour *Comeback*, 5 buts pour *Big Win*, 30 s pour
  *Fast Start* : à valider (on pourra les ajuster après un premier calcul sur l'historique).
- **Q4 — Noms** : la liste des noms anglais (§ 3) convient-elle ? En particulier *Fightback* (nul après 3 buts de
  retard), *Lifeline* (égalisation qui arrache la prolongation) et *Penalty Kill*.
- **Q5 — Matchs de classement à faible enjeu** (matchs de placement, coefficient nul) : attribuent-ils des médailles
  comme les autres ?
- **Q6 — Rattrapage** : recalcul de **toutes** les saisons dès la mise en production, ou seulement la saison en
  cours (les feuilles anciennes sont moins souvent horodatées) ?
