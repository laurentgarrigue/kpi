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
| CHPT — général | `finalizeChptRanking()` | ✅ tri Diff + buts | ✅ appelle `resolveHeadToHead()` |
| CP — poules (type C) | `finalizeJourneeChptRanking()` | ✅ tri Diff + buts | ⚠️ **ex æquo, aucun h2h** |

> ⚠️ **Écart spécifique CP** : `finalizeJourneeChptRanking()` reçoit bien `$goalaverage`,
> mais en mode `part` il se contente d'attribuer le **même rang** aux équipes à égalité
> de points (`$clt = $oldClt`) **sans jamais appeler `resolveHeadToHead()`**. Le départage
> par confrontation directe n'est donc **pas du tout** effectué dans les poules — contrairement
> au CHPT général où il l'est partiellement (cf. §3).

---

## 2. Goal-average général — ICF 2025 (art. 5.5.4 à 5.5.6)

Ordre de départage officiel entre équipes à égalité de points :

| # | Critère ICF | Pris en compte ? |
|---|-------------|------------------|
| 1 | **Goal difference** (différence générale de buts) | ✅ Oui |
| 2 | **Total number of Goals scored** (buts marqués) | ✅ Oui |
| 3 | **Results of game between the two teams** (confrontation directe) | ❌ Non |
| 4 | **Honourable Play** — cartons : rouge d'exclusion = 25 pts ; carton de progression (vert/jaune/rouge) = 5 pts chacun ; l'équipe au plus faible total est devant | ❌ Non |
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
| 1 | Points marqués **entre elles** (confrontation directe) | ❌ Non |
| 2 | **Différence particulière** de buts entre elles | ✅ Oui |
| 3 | **Différence générale** de buts | ❌ Non (voir note) |
| 4 | Nombre de buts marqués (général) | ❌ Non |
| 5 | Moins de **cartons rouges** sur cette phase | ❌ Non |
| 6 | Moins de **cartons jaunes** sur cette phase | ❌ Non |
| 7 | Moins de **cartons verts** sur cette phase | ❌ Non |
| 8 | Moins de **cartons rouges** sur les phases précédentes | ❌ Non |
| 9 | Moins de **cartons jaunes** sur les phases précédentes | ❌ Non |
| 10 | (≥ 3 équipes) Moins de **cartons verts** sur les phases précédentes, sinon **tirage au sort** | ❌ Non |

### Implémentation actuelle (`resolveHeadToHead`)

Pour chaque groupe d'équipes à égalité de points :
1. On récupère les matchs joués **entre les équipes du groupe** (`Validation = 'O'`).
2. On cumule, par équipe : `diff` (différence particulière de buts) et `plus`
   (buts marqués dans ces confrontations).
3. Tri : `h2h diff DESC, h2h plus DESC`.

```php
usort($ids, fn($a, $b) =>
    $h2h[$a]['diff'] !== $h2h[$b]['diff']
        ? $h2h[$b]['diff'] - $h2h[$a]['diff']     // différence particulière
        : $h2h[$b]['plus'] - $h2h[$a]['plus']);   // buts marqués entre elles
```

➡️ **Le calcul applique le critère 2 (différence particulière) puis, en cas
d'égalité, les buts marqués entre elles** (qui se rapproche du critère 1 mais sans
le formaliser comme « points » de confrontation directe).

**Écarts notables** :
- Le critère **1 (points marqués entre elles)** n'est **pas** appliqué en premier :
  l'implémentation départage d'abord sur la différence particulière de buts, alors
  que le règlement exige d'abord le nombre de **points** de la confrontation directe.
- Les critères **3 à 10** (différence générale, buts marqués généraux, puis toute la
  cascade cartons, puis tirage au sort) ne sont **pas** pris en compte.

#### ⚠️ Cas ≥ 3 équipes à égalité — départage non récursif

Le **regroupement** gère correctement N équipes : `GROUP BY Pts HAVING COUNT(*) > 1`
récupère les groupes de taille quelconque, et la requête h2h (`IN (...)`) prend bien tous
les matchs entre les N équipes. **Le problème n'est pas la taille du groupe, mais la
méthode.**

Pour un groupe de 3 équipes ou plus, le code calcule un cumul h2h **global** (somme des
diff/buts de chaque équipe contre *toutes* les autres du groupe), trie **une seule fois**
(`usort`), puis attribue des rangs **séquentiels distincts** (`startClt + $i`). Or le
règlement FFCK impose un départage **récursif** : on établit le sous-classement des seules
équipes concernées ; si un **sous-groupe** y reste à égalité, on ré-applique la cascade
**sur ce seul sous-groupe** (et l'on descend vers les critères suivants : diff générale,
cartons, tirage au sort).

Conséquences pour ≥ 3 équipes en `part` :
1. **Pas de re-segmentation** : si A se détache mais que B et C restent à égalité après
   h2h, le code les sépare quand même arbitrairement au lieu de descendre au critère
   suivant.
2. **Égalités tranchées arbitrairement** : `usort` donne des rangs distincts même à stats
   h2h strictement identiques, au lieu de laisser ex æquo puis d'appliquer le critère
   suivant.
3. **Cumul ≠ sous-classement** : sommer les écarts contre tout le groupe n'équivaut pas au
   mini-championnat réglementaire (un gros écart contre une seule équipe peut masquer une
   défaite contre une autre).

> **Note critère 3** : pour deux équipes restées à égalité après h2h, l'implémentation
> conserve l'ordre antérieur (qui provenait du tri `Pts, Diff, Plus` global), donc la
> différence générale joue *de facto* comme garde-fou ; mais ce n'est pas garanti pour
> un groupe de ≥ 3 équipes et ce n'est pas explicitement codé comme critère 3.

---

## 4. Synthèse : jusqu'où va le calcul

| Contexte | Goal-average | Dernier critère pris en compte | Premier critère **non** pris en compte |
|----------|--------------|--------------------------------|----------------------------------------|
| CHPT général | **Général (ICF)** | #2 — Total des buts marqués | #3 — Confrontation directe (puis #4 Honourable Play, règle 5.5.6) |
| CHPT général | **Particulier (FFCK)** | #2 — Différence particulière de buts | #1 — Points de la confrontation directe (mal ordonné), puis #3 → #10. **≥ 3 équipes : départage non récursif** (cf. §3) |
| CP — poules | **Général (ICF)** | #2 — Total des buts marqués | #3 — Confrontation directe (puis #4, 5.5.6) |
| CP — poules | **Particulier (FFCK)** | *(aucun)* — équipes laissées **ex æquo** | #1 dès la première égalité : aucun h2h n'est calculé |

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

### 6.5 Endpoint API2 proposé

```
GET /admin/rankings/justification
```

**Query Parameters** : `season` (req.), `competition` (req.), `type` (opt., CHPT/CP),
`format` (opt. : `json` par défaut, ou `pdf`).

**Profil** : ≤ 10 (lecture ; aligné sur la consultation du classement).

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

### 6.6 Rendu PDF

Deux pistes, à arbitrer au moment de l'implémentation :

- **Réutiliser l'infra legacy mPDF** (`MyPDF.php`), comme les autres `PdfClt*.php` : créer un
  `PdfCltJustif.php` qui **consomme le JSON de l'endpoint api2** (il ne recalcule rien), pour
  rester homogène avec les liens PDF existants (ouverts en `target="_blank"` depuis app4).
- **Générer le PDF directement dans api2** (mPDF y est déjà utilisé, cf.
  `AdminStatsController`) via `format=pdf`, et ouvrir l'URL api2 depuis app4.

La première limite l'introduction de nouveaux patterns ; la seconde garde tout le périmètre
« classement » dans api2. Le choix n'impacte pas le calcul (qui reste dans api2 dans les deux
cas).

**Contenu du PDF** : en-tête compétition + mode goal-average ; puis, par groupe à égalité,
la liste des équipes, le critère décisif appliqué à chaque étape avec les valeurs chiffrées,
et la mention explicite des cas **non départagés automatiquement** (à trancher manuellement /
tirage au sort).

### 6.7 Point d'entrée UI

Bouton/lien « Justification du départage » dans la page Classement (section PDFs admin),
visible uniquement s'il existe au moins un groupe d'équipes à égalité de points (sinon le
document serait vide). À ajouter à la spec [PAGE_CLASSEMENT.md](PAGE_CLASSEMENT.md) §2.6.

---

## 7. Références

- **ICF 2025** — art. 5.5.4 à 5.5.6 (goal-average général).
- **FFCK Règlement sportif 2023-2026** — art. RP KAP 65 (goal-average particulier).
- Code : [`AdminRankingsController.php`](../../sources/api2/src/Controller/AdminRankingsController.php)
  → `finalizeChptRanking()`, `resolveHeadToHead()`, `finalizeJourneeChptRanking()`.

---

**Document créé le** : 2026-06-25
**Statut** : 📋 Analyse + spec.
Départage partiellement implémenté — CHPT général : critères 1-2 (`gen` et `part`) ;
poules CP : critères 1-2 en `gen`, mais **aucun départage h2h en `part`** (équipes ex æquo).
Restent à compléter : confrontation directe en points, cartons, neutralisation des forfaits
(ICF 5.5.6), tirage au sort, **départage récursif ≥ 3 équipes**.
À implémenter également (§6) : **export PDF de justification du départage** (à la volée via api2,
respectant la consolidation, périmètre CHPT + poules CP, groupes à égalité uniquement).
