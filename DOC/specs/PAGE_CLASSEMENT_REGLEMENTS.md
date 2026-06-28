# Règlements de départage du classement (goal-average)

> Document compagnon de [PAGE_CLASSEMENT.md](PAGE_CLASSEMENT.md), section 6 (« Calcul du classement »).
> Il décrit les règlements officiels appliqués au départage des équipes à égalité de
> points, l'état réel de l'implémentation, et l'écart restant à combler.

## 1. Contexte

Le champ `goalaverage` (`kp_competition.goalaverage`, `varchar(4)` défaut `'gen'`)
détermine la méthode de départage à égalité de points :

- `gen` → **goal-average général** : règlement **ICF 2025**, art. 5.5.4 à 5.5.6.
- `part` → **goal-average particulier** : règlement sportif **FFCK 2023-2026**, art. RP KAP 65.

Le départage n'intervient qu'**à égalité de points**. Il s'applique à **deux endroits** :

1. **Championnat (CHPT)** — classement général.
2. **Tournoi (CP)** — départage **au sein de chaque poule** (déroulement par phase, type C).
   Le classement *général* d'une CP, lui, est piloté par les points pondérés `PtsNiveau`
   (`finalizeNiveauRanking`) et ne dépend pas de `goalaverage` ; mais l'ordre **dans la
   poule** en dépend, car c'est lui qui détermine quelle équipe est 1ʳᵉ/2ᵉ/… de poule.

Code concerné dans
[`AdminRankingsController.php`](../../sources/api2/src/Controller/AdminRankingsController.php) :

| Contexte | Fonction | `gen` | `part` |
|----------|----------|-------|--------|
| CHPT — général | `finalizeChptRanking()` | ✅ cascade #1→#4 (ICF) | ✅ cascade #1→#7 (FFCK) |
| CP — poules (type C) | `finalizeJourneeChptRanking()` | ✅ cascade #1→#4 (ICF) | ✅ cascade #1→#7 (FFCK), périmètre poule |

> ✅ **Écart CP comblé** : `finalizeChptRanking()` et `finalizeJourneeChptRanking()`
> partagent désormais le **même moteur de départage** (`resolveRankingOrder()` +
> `applyTieBreakCascade()`). En poule, le périmètre h2h/cartons est restreint au
> `Id_journee` de la poule (paramètre `$journeeId`). Le mode `part` n'attribue plus
> d'ex æquo arbitraire : la cascade FFCK (#1→#7) est appliquée par groupe d'égalité.

---

## 2. Goal-average général — ICF 2025 (art. 5.5.4 à 5.5.6)

Ordre de départage officiel entre équipes à égalité de points :

| # | Critère ICF | Pris en compte ? |
|---|-------------|------------------|
| 1 | **Goal difference** (différence générale de buts) | ✅ Oui (`diff_generale`) |
| 2 | **Total number of Goals scored** (buts marqués) | ✅ Oui (`buts_marques`) |
| 3 | **Results of game between the two teams** (confrontation directe) | ✅ Oui (`h2h_points`) |
| 4 | **Honourable Play** — cartons : rouge d'exclusion = 25 pts ; carton de progression (vert/jaune/rouge) = 5 pts chacun ; l'équipe au plus faible total est devant | ✅ Oui (`honourable_play`) |
| 5 | **Play Off** (match de barrage si possible) | ❌ Non (hors périmètre logiciel) |

**Règles complémentaires** :
- **5.5.5** — Goal difference = buts marqués − buts encaissés sur l'ensemble. ✅ Conforme (colonne `Diff`).
- **5.5.6** — Si une équipe a bénéficié d'un forfait d'une autre équipe, et qu'une
  3ᵉ équipe à égalité a réellement joué contre cette équipe forfait, alors **ces deux
  résultats sont neutralisés** pour le départage. ❌ Non implémenté.

### Implémentation actuelle (`finalizeChptRanking`, branche `gen`)

```sql
ORDER BY Pts DESC, Diff DESC, Plus DESC
```

Le tri applique exactement les critères **1 (Diff)** et **2 (Plus / buts marqués)**.
Chaque équipe à égalité reçoit ensuite un rang séquentiel.

➡️ **Le calcul s'arrête au critère 2.** Les critères 3 (confrontation directe),
4 (Honourable Play / cartons) et la règle 5.5.6 (neutralisation des forfaits) ne sont
**pas** pris en compte.

---

## 3. Goal-average particulier — FFCK RP KAP 65

Ordre de départage officiel entre équipes à égalité de points :

| # | Critère FFCK RP KAP 65 | Pris en compte ? |
|---|------------------------|------------------|
| 1 | Points marqués **entre elles** (confrontation directe) | ✅ Oui (`h2h_points`) |
| 2 | **Différence particulière** de buts entre elles | ✅ Oui (`h2h_diff`) |
| 3 | **Différence générale** de buts | ✅ Oui (`diff_generale`) |
| 4 | Nombre de buts marqués (général) | ✅ Oui (`buts_marques`) |
| 5 | Moins de **cartons rouges** sur cette phase | ✅ Oui (`cartons_rouges`, R + exclusion D) |
| 6 | Moins de **cartons jaunes** sur cette phase | ✅ Oui (`cartons_jaunes`) |
| 7 | Moins de **cartons verts** sur cette phase | ✅ Oui (`cartons_verts`) |
| 8 | Moins de **cartons rouges** sur les phases précédentes | ❌ Non |
| 9 | Moins de **cartons jaunes** sur les phases précédentes | ❌ Non |
| 10 | (≥ 3 équipes) Moins de **cartons verts** sur les phases précédentes, sinon **tirage au sort** | ❌ Non |

### Implémentation actuelle (moteur de départage `resolveRankingOrder`)

Le départage est désormais un **moteur critère-par-critère récursif** (cf. §6.4).
Chaque groupe d'équipes à égalité de **points** est passé à `applyTieBreakCascade()`,
qui applique dans l'ordre la liste `TIEBREAK_CRITERIA['part']` :

```php
'part' => ['h2h_points', 'h2h_diff', 'diff_generale', 'buts_marques',
           'cartons_rouges', 'cartons_jaunes', 'cartons_verts'],
```

Chaque critère est une fonction autonome (`evaluateCriterion`) renvoyant une valeur
numérique par équipe (valeur élevée = mieux classé). Un sous-groupe resté strictement
à égalité après un critère est transmis **récursivement** au critère suivant ; à
épuisement de la cascade, le sous-groupe reste **ex æquo** (même `Clt`).

➡️ **Critères 1 à 7 pris en compte** : points de confrontation directe (selon le
**système de points de la compétition** `kp_competition.Points`, ex. 4-2-1-0 — pas un
barème universel), différence particulière, différence générale, buts généraux, puis
cartons rouges (R + exclusion D), jaunes et verts sur la phase.

**Écarts restants** :
- Critères **8 à 10** (cartons des phases précédentes, puis tirage au sort) : non
  implémentés. Un sous-groupe encore à égalité après le critère 7 reste **ex æquo**.

#### ✅ Cas ≥ 3 équipes à égalité — départage récursif

Le moteur gère le départage **récursif** réglementaire : `applyTieBreakCascade()`
partitionne le groupe par valeur du critère courant, et **ré-applique la cascade
sur chaque sous-groupe encore à égalité** avant de descendre au critère suivant.

Conséquences pour ≥ 3 équipes en `part` :
1. **Re-segmentation correcte** : si A se détache mais que B et C restent à égalité
   après h2h points, B et C sont ré-évalués sur le critère suivant (diff particulière,
   puis diff générale, etc.) sans toucher à A.
2. **Ex æquo préservé** : un sous-groupe strictement identique sur tous les critères
   implémentés conserve le **même `Clt`** au lieu d'être tranché arbitrairement.
3. **Sous-classement réel** : les valeurs h2h sont recalculées sur le **seul
   sous-groupe** passé au critère (`headToHeadStats` ne compte que les matchs entre
   les équipes encore en lice), ce qui correspond au mini-championnat réglementaire.

---

## 4. Synthèse : jusqu'où va le calcul

| Contexte | Goal-average | Dernier critère pris en compte | Premier critère **non** pris en compte |
|----------|--------------|--------------------------------|----------------------------------------|
| CHPT général | **Général (ICF)** | #4 — Honourable Play (cartons) | #5 — Play Off (hors logiciel) ; règle 5.5.6 (forfaits) |
| CHPT général | **Particulier (FFCK)** | #7 — Cartons verts (phase) | #8 — Cartons des phases précédentes, puis #9-10 (tirage au sort) |
| CP — poules | **Général (ICF)** | #4 — Honourable Play (cartons), périmètre poule | #5 — Play Off ; règle 5.5.6 |
| CP — poules | **Particulier (FFCK)** | #7 — Cartons verts (poule), périmètre poule | #8 — Cartons des phases précédentes, puis #9-10 |

---

## 5. Ce qu'impliquerait la mise en conformité (non appliqué)

> ⚠️ Analyse uniquement — aucune de ces modifications n'est implémentée à ce jour.

### 5.1 Pré-requis commun : compter les cartons par équipe

Les critères « Honourable Play » (ICF #4) et « cartons » (FFCK #5 à #10) supposent de
disposer du **nombre de cartons (vert / jaune / rouge / rouge d'exclusion) par équipe**,
sur la phase courante **et** sur les phases précédentes.

- Vérifier que les cartons sont stockés au niveau match/joueur (table à confirmer :
  événements de match `kp_match` / feuille de marque), et qu'on peut les agréger par
  équipe et par phase.
- Si la donnée n'existe pas de façon fiable et historisée, ces critères ne peuvent pas
  être calculés automatiquement → ils resteraient à départager manuellement (édition
  inline) ou par tirage au sort.

### 5.2 Goal-average général (ICF) — travail à prévoir

1. **Critère #3 — confrontation directe** : pour chaque groupe d'équipes à égalité
   après Diff + buts marqués, calculer le résultat des matchs entre elles et réordonner.
   (La brique existe déjà dans `resolveHeadToHead`, à généraliser au mode `gen`.)
2. **Critère #4 — Honourable Play** : agréger les cartons par équipe avec le barème
   ICF (rouge d'exclusion = 25 pts, vert/jaune/rouge de progression = 5 pts), classer
   par total croissant.
3. **Règle 5.5.6 — neutralisation des forfaits** : détecter les matchs gagnés par
   forfait impliquant une équipe du groupe à égalité, et exclure du calcul de départage
   les résultats correspondants quand une autre équipe du groupe a réellement joué
   l'équipe forfait. Logique spécifique à ajouter au pré-traitement des matchs.

### 5.3 Goal-average particulier (FFCK) — travail à prévoir

1. **Réordonner `resolveHeadToHead`** pour appliquer d'abord le critère #1
   (**points** de la confrontation directe, selon le barème de la compétition), puis #2
   (différence particulière), puis #4 (buts marqués entre elles).
   ⚠️ Attention : à 3 équipes ou plus, le règlement FFCK calcule ces critères sur le
   **sous-classement** des seules équipes concernées, ce qui peut casser un groupe en
   sous-groupes successifs (départage récursif).
2. **Critère #3 — différence générale** : ajouter explicitement `Diff` (général) comme
   critère de repli après la confrontation directe.
3. **Critère #4 — buts marqués généraux** : ajouter `Plus` (général).
4. **Critères #5 à #10 — cascade cartons** : nécessite l'agrégation des cartons
   (cf. 5.1), par phase courante puis phases précédentes.
5. **Critère #10 — tirage au sort** : si les équipes restent à égalité, prévoir un
   départage non déterministe (ou une intervention manuelle), à tracer.

### 5.4 Poules de Tournoi (CP) — travail spécifique

Le départage intra-poule (`finalizeJourneeChptRanking`) doit suivre **la même cascade**
que le CHPT général (§5.2 pour `gen`, §5.3 pour `part`), mais en se restreignant au
**périmètre de la poule** :

1. **Mode `part` — appeler un départage h2h par poule** : aujourd'hui inexistant.
   `resolveHeadToHead` n'est pas réutilisable tel quel : il regroupe par `Pts` **global**
   sur `kp_competition_equipe` et ne connaît pas la notion de `Id_journee`/poule. Il faut
   une variante qui :
   - travaille sur `kp_competition_equipe_journee` (points/diff **de la poule**) ;
   - restreigne les matchs de confrontation directe à ceux de la poule
     (`kp_match.Id_journee = Id_journee` de la poule) ;
   - itère **groupe d'égalité par groupe d'égalité, au sein de chaque poule**.
2. **Critères généraux (#3 différence générale, #4 buts généraux pour FFCK ; #1-2 déjà
   présents)** : décider, dans une poule, si « différence générale » signifie la diff
   *cumulée toutes phases* (`kp_competition_equipe`) ou la diff *de la poule* — à clarifier
   avec le règlement appliqué en interne.
3. **Cartons / tirage au sort** : mêmes pré-requis que §5.1, à l'échelle de la poule.

> Conséquence pratique : tant que le mode `part` n'effectue aucun départage en poule, deux
> équipes à égalité de points dans une poule sortent **ex æquo** (même `Clt` de poule), ce
> qui peut fausser la qualification (qui est 1ʳᵉ / 2ᵉ de poule) et donc le tableau final.

### 5.5 Impact transverse

- Le départage doit produire le **même ordre** dans le classement général CHPT
  (`finalizeChptRanking`) **et** dans le déroulement par phase / poules CP
  (`finalizeJourneeChptRanking`), qui partagent aujourd'hui la même logique simplifiée en
  `gen` mais **divergent** en `part` (le CP ne fait aucun h2h).
- Idéalement, factoriser la cascade de départage dans une brique commune paramétrée par
  périmètre (compétition entière vs. poule) plutôt que de dupliquer la logique. Cette brique
  doit suivre le principe **critères autonomes + ordre configurable** détaillé en §6.4
  (chaque critère = fonction pure indépendante ; l'ordre = liste paramétrable par règlement),
  afin d'anticiper les futures évolutions réglementaires sans réécriture.
- Les PDF legacy (`FeuilleCltChpt.php`, `PdfCltChpt.php`, `FeuilleCltNiveau.php`,
  `FeuilleCltNiveauPhase.php`, …) recalculent ou relisent le classement : vérifier la
  cohérence si la logique de départage évolue côté api2.
- Penser à documenter, à terme, le critère atteint pour chaque égalité (traçabilité),
  notamment pour les cas tranchés par tirage au sort.

---

## 6. Justification du départage (export PDF à la demande)

### 6.1 Objectif

Permettre de **générer à la demande un document justifiant les départages** appliqués aux
équipes à égalité de points — au classement général (CHPT) ou au sein d'une poule (CP) —
afin de rendre les décisions défendables vis-à-vis des clubs (« pourquoi l'équipe A est-elle
classée devant l'équipe B ? »).

### 6.2 Décisions d'architecture retenues

| Décision | Choix | Justification |
|----------|-------|---------------|
| **Source du calcul** | **api2** — la trace est produite par le code de départage lui-même | Source unique de vérité : pas de seconde implémentation à maintenir. Le PDF **ne recalcule pas** le départage de façon parallèle. |
| **Moment** | **À la volée** à la génération | Pas de stockage. La justification est dérivée des matchs au moment de la demande. |
| **Cohérence consolidation** | La justification **respecte la consolidation** (cf. 6.3) | Une poule consolidée ne doit pas être « recalculée à neuf » : sa justification doit refléter l'état figé qui a servi aux tours suivants. |
| **Périmètre** | CHPT général **et** poules CP | Le `goalaverage` s'applique aux deux. |
| **Contenu** | **Uniquement les groupes réellement à égalité de points** | Un PDF qui listerait toutes les équipes serait inutile ; on ne documente que les départages effectifs. |

### 6.3 Cohérence avec la consolidation (point central)

Le calcul « à la volée » est **sûr** précisément parce que les données sous-jacentes d'un
départage déjà acté ne bougent plus :

- Une poule **consolidée** (`kp_journee.Consolidation = 'O'`) est **exclue du recalcul** :
  ses `Clt`/`Pts`/`Diff` dans `kp_competition_equipe_journee` sont figés, et ses matchs
  ne sont plus modifiés. Re-dériver la justification depuis ces matchs redonne donc
  **toujours le même résultat** que celui qui a départagé les équipes au moment de la
  consolidation — et qui a servi de base aux tours suivants.
- Le PDF doit donc :
  1. lire les **`Clt`/`Pts` tels quels en base** (qui respectent déjà la consolidation),
     **sans relancer `finalize…`** ;
  2. n'**expliquer** le départage que pour les groupes à égalité, en relisant les matchs
     **du périmètre concerné** (toute la compétition pour CHPT ; la poule pour CP).

> ⚠️ Conséquence : la justification ne doit **jamais** déclencher un recalcul implicite des
> poules consolidées. Sinon on réintroduirait le différentiel que la consolidation sert
> justement à éviter entre « égalités déjà traitées » et « recalculs des tours suivants ».

### 6.4 Principe de conception : critères autonomes et ordre configurable

> Exigence structurante : **chaque critère de départage doit être implémenté comme une unité
> autonome**, et **l'ordre d'application doit être une donnée de configuration**, pas du code
> en dur. Les règlements évoluent (l'ICF et la FFCK n'appliquent déjà pas les mêmes critères
> dans le même ordre) ; un futur règlement peut réordonner, ajouter ou retirer un critère.
> On doit pouvoir absorber ce changement **sans réécrire la logique de calcul**, juste en
> modifiant la séquence.

Conséquences concrètes :

1. **Un critère = une fonction pure**, isolée et testable indépendamment :
   ```text
   Critere {
     code   : 'h2h_points' | 'h2h_diff' | 'h2h_buts' | 'diff_generale'
            | 'buts_marques' | 'cartons_rouges' | 'cartons_jaunes'
            | 'cartons_verts' | 'cartons_rouges_phases_prec' | ... | 'tirage_au_sort'
     // Reçoit le sous-groupe d'équipes encore à égalité + le périmètre (compétition / poule),
     // renvoie un ordre partiel : groupes d'équipes départagées, et sous-groupes restés à
     // égalité (à passer au critère suivant). Ne connaît PAS les autres critères.
     evaluer(equipes[], contexte) -> [ sousGroupesOrdonnes ]
   }
   ```
   - Un critère ne suppose **jamais** qu'un autre critère a tourné avant lui (pas de
     dépendance d'ordre cachée). Il calcule ses propres valeurs (diff, points h2h, cartons…)
     à partir des données brutes (matchs, événements), pour le sous-groupe qu'on lui passe.
   - Il renvoie le **résultat de son seul critère** : ce qu'il a réussi à départager, et ce
     qui reste à égalité (transmis tel quel au critère suivant — d'où la récursivité §5.3).

2. **L'ordre est une liste ordonnée de `code`**, idéalement **paramétrable par règlement** :
   ```text
   REGLEMENTS = {
     'gen'  : ['diff_generale', 'buts_marques', 'h2h_points', 'honourable_play', 'tirage_au_sort'],   // ICF 2025
     'part' : ['h2h_points', 'h2h_diff', 'diff_generale', 'buts_marques',
               'cartons_rouges', 'cartons_jaunes', 'cartons_verts',
               'cartons_rouges_phases_prec', 'cartons_jaunes_phases_prec', 'tirage_au_sort'], // FFCK RP KAP 65
   }
   ```
   Le moteur de départage est alors un **simple itérateur** : pour chaque critère de la
   séquence, l'appliquer aux sous-groupes encore à égalité, jusqu'à épuisement des critères.
   Changer de règlement = changer la liste ; ré-ordonner = permuter la liste. **Aucune
   modification du code des critères.**

3. **Avantages** :
   - Anticipe les évolutions réglementaires (réordonnancement, nouveau critère) sans refonte.
   - Permet d'exposer la séquence en configuration (à terme : par compétition, si un règlement
     spécifique s'applique).
   - Chaque critère est testable en isolation (jeux de données ciblés).
   - La **trace** (§6.4-bis ci-dessous) tombe naturellement : chaque appel de critère produit
     une étape de justification.

> Cette conception est le pré-requis idéal des corrections de §5 : plutôt que de « rajouter
> des critères » dans le `usort` actuel, factoriser d'abord ce moteur critère-par-critère,
> puis y brancher CHPT général et poules CP (mêmes critères, périmètre différent).

### 6.4-ter Faire évoluer un règlement (procédure)

Tout est centralisé dans
[`AdminRankingsController.php`](../../sources/api2/src/Controller/AdminRankingsController.php).
On **ne touche jamais** au moteur (`applyTieBreakCascade`), ni à
`resolveRankingOrder` / `finalizeChptRanking` / `finalizeJourneeChptRanking`, ni au rendu
PDF / endpoint / frontend : ils consomment la séquence de critères et les étapes produites.

**Cas 1 — réordonner / activer / retirer des critères existants** (le plus fréquent) :
un seul endroit, la constante `TIEBREAK_CRITERIA`.

```php
private const TIEBREAK_CRITERIA = [
    'gen'  => ['diff_generale', 'buts_marques', 'h2h_points', 'honourable_play'],
    'part' => ['h2h_points', 'h2h_diff', 'diff_generale', 'buts_marques',
               'cartons_rouges', 'cartons_jaunes', 'cartons_verts'],
];
```

Permuter / ajouter / retirer un code dans la liste suffit. Le départage **et** la
justification suivent automatiquement.

**Cas 2 — ajouter un critère inédit** (logique non encore codée) : 4 points, tous
adjacents dans le fichier.

| # | Quoi | Méthode / constante |
|---|------|---------------------|
| 1 | **Calcul** : fonction pure `teamId => valeur` (valeur haute = mieux classé) | `evaluateCriterion()` — nouveau `case` |
| 2 | **Affichage** : reconvertir la valeur interne en chiffre lisible (si on inverse le signe, comme pour les cartons) | `displayValues()` — `case` |
| 3 | **Libellés** FR + EN | `CRITERE_LABELS` (entrée dans `fr` **et** `en`) |
| 4 | **Activer** : insérer le code dans la séquence | `TIEBREAK_CRITERIA` |

Selon la nature du critère, ajuster aussi le **tri d'affichage des valeurs** (dans
`traceTiedGroups`) :
- « plus = mieux » → rien (cas par défaut, `arsort`) ;
- « moins = mieux » (cartons-like) → ajouter le code à `$cardCriteria` ;
- confrontation directe avec détail des matchs → ajouter le code à `$h2hCriteria`.

> **Règle d'or** (point 1) : un critère est **autonome**. Il reçoit le sous-groupe encore à
> égalité + le `$context`, recalcule ses propres valeurs depuis les données brutes, et ne
> suppose **jamais** qu'un autre critère a tourné avant. C'est ce qui rend la récursivité et
> le réordonnancement sûrs.

Si le critère exige une **donnée nouvelle** (ex. cartons des phases précédentes,
FFCK #8-10), ajouter une méthode de chargement *lazy* sur le modèle de
`cardStats()` / `loadH2hMatches()` (nouvelle clé dans `$context`, ex. `'cards_prev' => null`).

**Cas 3 — nouveau règlement** (3ᵉ mode de goal-average) : ajouter une **clé** dans
`TIEBREAK_CRITERIA` (ex. `'icf2027' => [...]`), s'assurer que `kp_competition.goalaverage`
peut porter cette valeur, puis traiter les éventuels critères inédits via le Cas 2. Le
fallback `?? TIEBREAK_CRITERIA['gen']` protège déjà contre un mode inconnu.

### 6.4-bis Trace de départage

La justification réutilise la **même cascade de critères** que le départage (la brique
moteur ci-dessus), mais en **mode trace** : au lieu de seulement réordonner, elle
**enregistre, pour chaque groupe à égalité, le critère décisif** qui sépare chaque
paire/sous-groupe. Chaque appel de critère du moteur (§6.4-1) produit directement une
`JustificationEtape`.

Structure de trace proposée (en mémoire, non persistée) :

```text
JustificationGroupe {
  perimetre   : 'CHPT' | 'POULE'
  pouleLabel  : string|null      // ex. "Poule A (Final, Enschede)"
  points      : int              // points communs aux équipes du groupe
  goalaverage : 'gen' | 'part'
  equipes     : [ { id, libelle, cltFinal } ]   // dans l'ordre final retenu
  etapes      : [ JustificationEtape ]           // cascade appliquée
}

JustificationEtape {
  critere     : 'diff_generale' | 'buts_marques' | 'h2h_points'
              | 'h2h_diff' | 'h2h_buts' | 'cartons_rouges' | ...
              | 'tirage_au_sort'
  perimetre   : 'groupe' | 'sous-groupe'
  equipesConcernees : [ id, ... ]
  valeurs     : { id => valeurNumerique }   // ex. diff par équipe
  resultat    : 'departage' | 'toujours_egalite'  // si égalité → on passe à l'étape suivante
}
```

> Tant que la cascade complète (§5.2/§5.3) n'est pas implémentée, la trace ne couvre que les
> critères réellement calculés (diff générale, buts, h2h diff/buts). Les critères non encore
> implémentés (h2h points, cartons, tirage au sort) apparaîtront comme **« non départagé par
> le logiciel »** dans le PDF, ce qui est honnête et signale les cas à trancher manuellement.

### 6.5 Endpoint API2 ✅ implémenté

```
GET /admin/rankings/justification
```

**Query Parameters** : `season` (req.), `competition` (req.), `type` (opt., CHPT/CP),
`format` (opt. : `json` par défaut, ou `pdf`), `timezone` (opt., pour la date du PDF).

**Profil** : ≤ 10 (lecture ; aligné sur la consultation du classement).

**Implémentation** (`AdminRankingsController::justification()`) :
- lit `Clt`/`Pts` **tels quels en base** (respecte la consolidation, **n'appelle jamais**
  `finalize…`) ;
- ne documente que les **groupes réellement à égalité de points** (`buildJustificationChpt`
  pour le CHPT ; `buildJustificationPoules` par poule de type C pour la CP) ;
- rejoue la **même cascade de critères** que le départage en **mode trace**
  (`applyTieBreakCascade($…, $trace)`), produisant une `JustificationEtape` par critère.

**Réponse `json`** :
```json
{
  "competition": { "code": "N1H", "libelle": "...", "goalaverage": "gen" },
  "groupes": [
    {
      "perimetre": "CHPT",
      "pouleLabel": null,
      "points": 65,
      "goalaverage": "gen",
      "equipes": [
        { "id": 1, "libelle": "Acigné I", "cltFinal": 4 },
        { "id": 2, "libelle": "Saint-Omer I", "cltFinal": 5 }
      ],
      "etapes": [
        { "critere": "diff_generale", "valeurs": { "1": 42, "2": 18 }, "resultat": "departage" }
      ]
    }
  ]
}
```

> **Étapes h2h détaillées** : les critères de confrontation directe (`h2h_points`,
> `h2h_diff`, `h2h_buts`) portent en plus un tableau `matchs` listant les rencontres
> **réellement prises en compte** (entre les seules équipes du sous-groupe, dans le
> périmètre), avec libellés et scores :
> ```json
> { "critere": "h2h_points", "valeurs": { "5": 8, "6": 6, "7": 2 }, "resultat": "departage",
>   "matchs": [ { "numero": 123, "idA": 5, "idB": 6, "equipeA": "Corbeil-Essonnes I",
>                 "equipeB": "Acigné I", "scoreA": 9, "scoreB": 7 } ] }
> ```
> Dans le PDF : une équipe par ligne pour les valeurs, puis un match par ligne préfixé
> du **numéro de match** (`#123`, `Numero_ordre` avec repli sur `Id`) et **score en gras**.

### 6.6 Rendu PDF ✅ implémenté (api2)

Choix retenu : **générer le PDF directement dans api2** via `format=pdf`
(`renderJustificationPdf()`, mPDF, même style que `AdminStatsController`). Tout le
périmètre « classement » reste dans api2 ; le calcul n'est pas dupliqué. Depuis app4,
le PDF est récupéré en blob authentifié (`useApi().getBlob`) puis ouvert dans un nouvel
onglet (le endpoint exige le Bearer token, un simple `target="_blank"` ne conviendrait pas).

**Contenu du PDF** : en-tête compétition + mode goal-average ; puis, par groupe à égalité,
la liste des équipes, le critère décisif appliqué à chaque étape avec les valeurs chiffrées,
le **détail des matchs** (libellés + scores) pour les étapes de confrontation directe, et la
mention explicite des cas **non départagés automatiquement** (à trancher manuellement /
tirage au sort). Le PDF et le JSON suivent la **langue active** (`locale=fr|en`).

### 6.7 Point d'entrée UI ✅ implémenté

Bouton « Justification du départage » dans la page Classement
([`pages/rankings/index.vue`](../../sources/app4/pages/rankings/index.vue)), à côté des
extractions PDF. **Visible uniquement** s'il existe au moins un groupe d'équipes à égalité
de points (`hasTies` : CHPT sur tout le classement, CP au sein d'une poule de type C).
Clé i18n `rankings.justification.*` (fr/en).

---

## 7. Références

- **ICF 2025** — art. 5.5.4 à 5.5.6 (goal-average général).
- **FFCK Règlement sportif 2023-2026** — art. RP KAP 65 (goal-average particulier).
- Code : [`AdminRankingsController.php`](../../sources/api2/src/Controller/AdminRankingsController.php)
  → `finalizeChptRanking()`, `finalizeJourneeChptRanking()`,
  `resolveRankingOrder()`, `applyTieBreakCascade()`, `evaluateCriterion()`.

---

**Document créé le** : 2026-06-25
**Mis à jour le** : 2026-06-25
**Statut** : ✅ Départage implémenté via moteur critère-par-critère récursif (CHPT + poules CP).
- `gen` (ICF) : critères **1 → 4** (diff générale, buts, confrontation directe, Honourable Play).
- `part` (FFCK) : critères **1 → 7** (h2h points, diff particulière, diff générale, buts généraux,
  cartons rouges, jaunes, verts de la phase/poule).
Export PDF de justification du départage (§6) : ✅ implémenté (api2 `format=pdf`,
mode trace, respect de la consolidation, périmètre CHPT + poules CP, bouton app4 conditionnel).
Restent à compléter : critères #8-10 FFCK (cartons des phases précédentes, tirage au sort),
règle ICF 5.5.6 (neutralisation des forfaits).
