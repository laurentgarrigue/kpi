# Fix - Classement général et phases consolidées

**Date** : 2026-09-01 (partie 1) · 2026-09-04 → 2026-09-07 (partie 2)
**Type** : Bug fix, puis refonte de formule
**Fonctionnalité concernée** : [Consolidation des phases](../features/CONSOLIDATION_PHASES_CLASSEMENT.md)
**Fichiers corrigés** :
- `sources/api2/src/Controller/AdminRankingsController.php`
- `sources/admin/GestionClassement.php` (legacy)

**Spécification** : [PAGE_CLASSEMENT.md](../../../specs/PAGE_CLASSEMENT.md) §5 et §6

**Sommaire** :

| | Bug | Portée |
|---|---|---|
| **Partie 1** | **A** — contribution des phases consolidées perdue à la RAZ | api2 + legacy |
| | **B** — double comptage par niveau | api2 |
| **Partie 2** | **C** — `PtsNiveau` figé, sourd aux changements de `Niveau` et aux corrections manuelles de `Clt` | api2 + legacy |
| | **D** — divergence legacy/api2 sur le `Clt` de poule (cascade de départage + phases consolidées) | legacy |

---

## Symptômes

Sur une compétition CP dont les poules sont consolidées :

1. **Départage impossible dans le classement général** : les équipes éliminées en poule
   se retrouvent toutes ex æquo au même rang, sans critère pour les séparer.
2. **Nombre de matchs joués (`J`) erroné** : seuls les matchs des phases éliminatoires
   sont comptés, ceux des phases consolidées sont ignorés.

Exemple constaté sur `T-AVRD1` / 2026 : 7 équipes bloquées au rang 9 avec `J=0` et
`PtsNiveau=0`, alors qu'elles avaient joué 8 matchs chacune.

---

## Cause

### Bug A — Perte de la contribution des phases consolidées

Séquence de recalcul (api2 `compute()`, legacy `DoClassement()`) :

1. **RAZ** : `kp_competition_equipe` est remise à zéro **intégralement**, sans exemption
   pour les phases consolidées.
2. **Traitement des matchs** : les matchs des phases consolidées sont **exclus**
   (`AND j.Consolidation != 'O'`).

La contribution des phases consolidées est donc effacée puis jamais réinjectée. Seule
`kp_competition_equipe_journee` était correctement protégée de la RAZ — c'est pourquoi
le déroulement par phase restait juste alors que le classement général était faux.

Les équipes dont **tous** les matchs ont été joués en phases consolidées tombent à
`J = 0` / `PtsNiveau = 0` : plus aucune donnée ne permet de les départager.

### Bug B — Double comptage par niveau (api2 uniquement)

L'ancien `razNiveauRanking()` préservait de la suppression les lignes
`kp_competition_equipe_niveau` dont le `Niveau` apparaissait dans au moins une phase
consolidée.

Or un même `Niveau` peut contenir **à la fois** une phase consolidée et une phase non
consolidée. Dans ce cas la ligne était préservée, puis `processMatches()` la
ré-incrémentait avec les matchs de la phase non consolidée : **double comptage
cumulatif**, `J` augmentant à chaque recalcul sans jamais converger (mesuré :
20 → 24 → 28...).

Ce bug est resté invisible car `kp_competition_equipe_niveau` n'est jamais lue par la
page Classement (qui affiche `kp_competition_equipe` et `..._journee`). Elle n'alimente
que le classement publié et les PDF.

Le legacy n'avait pas ce bug : il remettait déjà cette table à zéro intégralement — mais
perdait donc bien les totaux consolidés (bug A).

---

## Correctif

### api2 — `AdminRankingsController.php`

**`applyConsolidatedPhases()`** (nouveau) : réinjecte les totaux figés des phases
consolidées, lus depuis `kp_competition_equipe_journee` (source de vérité, non modifiée
par la RAZ), dans `kp_competition_equipe` **et** `kp_competition_equipe_niveau`, groupés
par `(Id, Niveau)`.

Appelée en **étape 2b**, après `applyInitialValues()` et avant `processMatches()` :

```php
// 1. RAZ
$this->razRanking($competition, $season);
$this->razJourneeRanking($competition, $season);
$this->razNiveauRanking($competition, $season);

// 2. Valeurs initiales
$this->applyInitialValues($competition, $season);

// 2b. Réinjection des totaux figés des phases consolidées
$this->applyConsolidatedPhases($competition, $season);

// 3. Traitement des matchs (hors phases consolidées)
$this->processMatches($competition, $season, $includeUnlocked, $pointsStr);
```

**`razNiveauRanking()`** (modifié) : suppression désormais **totale**, sans exemption des
« niveaux consolidés ». Supprime le bug B et rend le recalcul idempotent, les totaux
figés étant reconstruits par `applyConsolidatedPhases()`.

### Legacy — `GestionClassement.php`

**`ReportClassementPhasesConsolidees()`** (nouveau) : même logique, appelée dans
`DoClassement()` après les RAZ et avant `CalculClassement()`. Réinjecte dans les deux
tables (`kp_competition_equipe` et `kp_competition_equipe_niveau`), cette dernière étant
vidée intégralement par `RazClassementCompetitionEquipeNiveau()`.

Réutilise le helper existant `ExistCompetitionEquipeNiveau()` pour créer la ligne si
absente.

---

## Validation

- `php -l` OK sur les deux fichiers.
- Pipeline corrigé rejoué sur `T-AVRD1` / 2026 en transaction annulée : classement
  intégralement ordonné, 15 équipes, **aucun ex æquo**, `J` conformes
  (FRA Men 7, POR Men 9, Acigné I 8).
- **Idempotence** : deux passes consécutives donnent un résultat strictement identique.
  L'ancien code divergeait (`J` : 20 → 24).
- Résultats api2 et legacy identiques.

---

## Compétitions impactées

> Constats issus de la **base de dev locale** au 2026-09-01. **À rejouer sur préprod/prod**
> avant toute remédiation : les chiffres ci-dessous ne valent que pour la dev.

Périmètre : **saison 2026 uniquement** — la consolidation n'existait pas avant (vérifié :
83 phases consolidées, toutes en 2026, sur 17 compétitions).

**5 compétitions** ont au moins un niveau mélangeant phases consolidées et non consolidées :

| Code | Libellé | Statut | J affiché | J réel | Table niveau | Action |
|---|---|---|---|---|---|---|
| **N3O** | Nationale 3 - 1/2 Finales Ouest | `END` | 14 | 66 | sous-comptée | ⚠️ remédiation manuelle |
| **T-62D1** | Tournoi International du Pas de Calais | `END` | 50 | 102 | sous-comptée | ⚠️ remédiation manuelle |
| **N3E** | Nationale 3 - 1/2 Finales Est | `END` | 34 | 66 | sur-comptée (60 vs 40) | ⚠️ remédiation manuelle |
| **T-AVRD1** | Challenge International d'Avranches | `ON` | 116 ✓ | 116 | sur-comptée (120 vs 60) | ✅ simple recalcul |
| NEM | Championnat National Excellence Mixte | `ON` | 72 ✓ | 72 | ✓ saine | rien à faire |

**Précisions** :

- **N3O, T-62D1, N3E** : classement **visible** faux. Sur N3O, toutes les équipes
  affichent `J=1` ou `2` alors qu'elles ont joué 6 ou 7 matchs.
- **T-AVRD1** : le total 116 = 116 est une coïncidence — sur-évaluation niveau et
  sous-évaluation phases se compensent au total, mais les valeurs **par équipe** restent
  fausses (ex. Avranches I : `J=16` au niveau 1 au lieu de 4, avec lignes dupliquées).
- **NEM** : niveau mixte présent, mais jamais recalculée dans cet état → saine.
- Les 12 autres compétitions consolidées (ECA1, ECA1U21, ECA1U21W, ECA1W, N15, N18, N3,
  NPOF, NPOH, REG20B, T-62D2, T-AVRD2) n'ont aucun niveau mixte → non touchées par le
  bug B. Elles restent exposées au bug A si des phases consolidées portent des matchs ;
  à recalculer par précaution.

### ⚠️ Blocage : compétitions au statut END

`N3E`, `N3O` et `T-62D1` sont au statut **`END`**. Le recalcul y est **interdit**
(contrôle statut `ON`, spec §2 et §14.1). Elles **ne se répareront pas** au déploiement
du correctif.

Séquence de remédiation nécessaire, par compétition :

1. Sauvegarder `kp_competition_equipe`, `kp_competition_equipe_niveau`,
   `kp_competition_equipe_journee` et `kp_competition` (lignes de la compétition).
2. Passer le statut `END` → `ON` (profil ≤ 3).
3. Recalculer le classement.
4. Vérifier `J` par équipe contre `kp_competition_equipe_journee`.
5. Republier le classement.
6. Repasser le statut `ON` → `END`.

Ces compétitions sont **terminées et déjà publiées** — la manip modifie un classement
public. À faire sciemment, idéalement hors période de consultation.

`NEM` et `T-AVRD1` (statut `ON`) se corrigent au premier recalcul, sans manip.

---

## Requêtes de contrôle

Détection des niveaux mixtes (bug B) :

```sql
SELECT Code_competition, Code_saison, Niveau,
  SUM(Consolidation='O') AS phases_consolidees,
  SUM(Consolidation IS NULL OR Consolidation!='O') AS phases_non_consolidees
FROM kp_journee
WHERE Code_saison='2026' AND Niveau IS NOT NULL
GROUP BY Code_competition, Code_saison, Niveau
HAVING phases_consolidees>0 AND phases_non_consolidees>0
ORDER BY Code_competition, Niveau;
```

Écart entre `J` général stocké et réel (bug A) :

```sql
SELECT ce.Code_compet, c.Statut, SUM(ce.J) AS J_stocke,
 (SELECT COUNT(*) FROM kp_match m JOIN kp_journee j ON j.Id=m.Id_journee
   WHERE j.Code_competition=ce.Code_compet AND j.Code_saison=ce.Code_saison
     AND m.ScoreA REGEXP '^[0-9]+$' AND m.ScoreB REGEXP '^[0-9]+$')*2 AS J_attendu
FROM kp_competition_equipe ce
JOIN kp_competition c ON c.Code=ce.Code_compet AND c.Code_saison=ce.Code_saison
WHERE ce.Code_saison='2026'
GROUP BY ce.Code_compet
HAVING J_stocke <> J_attendu;
```

---

## Réserves

- Chiffres issus de la **dev** ; la prod peut différer.
- Seule la colonne `J` a été vérifiée (la plus lisible). `Pts`, `Plus`, `Moins`, `Diff`
  et `PtsNiveau` suivent la même mécanique et sont faux de la même façon.
- Aucune donnée modifiée lors du diagnostic : tous les tests de validation ont été joués
  en transaction annulée (`ROLLBACK`).

---
---

# Partie 2 — Refonte : `PtsNiveau` basé sur le rang de poule

> Couvre le **bug C** (`PtsNiveau` figé) et le **bug D** (divergence legacy/api2 sur `Clt`).

**Date** : 2026-09-04
**Type** : Refonte de formule (suite du fix ci-dessus)

## Symptôme

Sur `T-AVRD2` / 2026, les rangs 12 à 14 du classement général sont faux, **de façon
identique en dev, en prod et via l'admin legacy**, et **aucun recalcul n'y change rien** :

| Équipe | Poule finale | Rang obtenu | Rang attendu |
|---|---|---|---|
| FRA U21 Women | 14th place, 1ʳᵉ | 12 ❌ | 14 |
| Château-Thébaud I | 12th place, 1ᵉʳ | 13 | 12 |
| Les alligators-Landerneau I | 12th place, 1ᵉʳ | 14 ❌ | 13 |

Corriger le `Niveau` de la phase « 12th place » (3 → 4) n'a **aucun effet**.

## Cause

### Le déclencheur : deux poules de placement au même `Niveau`

`PtsNiveau = pow(64, Niveau) × n` (n ∈ {4,3,2,1} selon V/N/P/F) est le **seul canal**
par lequel une phase alimente le classement général : `kp_competition_equipe.PtsNiveau`
détermine `CltNiveau`, qui est le rang affiché pour une compétition CP.

Le facteur 64 sert de séparateur : tant que les `Niveau` diffèrent, une poule supérieure
écrase toujours une poule inférieure. Sur T-AVRD2, « 14th place » et « 12th place »
partageaient le `Niveau` 3 — le séparateur disparaît, et les équipes sont comparées sur
leurs **points bruts**, toutes poules confondues. FRA U21 Women (7 pts en « 14th place »)
passe devant les équipes de « 12th place » (6 pts).

### La cause de fond : `PtsNiveau` est un cache jamais invalidé

Corriger le `Niveau` ne suffit pas parce qu'une phase consolidée est **gelée** :

- `razJourneeRanking()` ne remet pas ses lignes à zéro ;
- `processMatches()` exclut ses matchs ;
- `applyConsolidatedPhases()` se contente de **relire** `kp_competition_equipe_journee`.

Le `PtsNiveau` stocké a donc été figé avec le `Niveau` de l'époque, et **rien ne le
régénère** — ni api2 ni le legacy. C'est un cache d'un calcul dont une entrée
(`j.Niveau`) reste modifiable.

### Le corollaire : les corrections manuelles ne remontaient pas

`cej.Clt` n'est lu par **aucun** calcul global (vérifié : ses seules lectures sont
l'affichage et la justification de départage). Conséquence : corriger à la main le rang
d'une poule n'a **jamais** eu d'effet sur le classement général.

Les deux défauts ont la même racine : `PtsNiveau` dérive du *résultat des matchs* au lieu
de dériver du *rang de poule*.

## Correctif — `PtsNiveau` basé sur le rang

```
phases Type 'C' :  PtsNiveau = pow(64, Niveau) × (nb_équipes_phase − Clt + 1)
phases Type 'E' :  formule inchangée (pow(64, Niveau) × n)
```

**Pourquoi dissocier `C` et `E`** : aucune phase éliminatoire n'est consolidée (0 sur 194
en 2026), et 90 d'entre elles sont des matchs secs à 2 équipes — il n'y a pas de
« classement de poule » à respecter, ni rien à y préserver.

Recalculée à chaque passe, la valeur devient **reconstructible** : un changement de
`Niveau` est pris en compte sans déconsolider, et une correction manuelle de `Clt`
remonte enfin au classement général.

### Inversion de dépendance dans le pipeline

`PtsNiveau` dérivant désormais de `Clt`, la chaîne devient
`Pts/Diff → Clt de poule → PtsNiveau → CltNiveau général`, ce qui impose de réordonner
`compute()` : `finalizeJourneeChptRanking()` (qui fixe `Clt`) passe **avant** les
finalisations globales.

```php
// 4. Classement par phase d'abord : PtsNiveau en dérive.
$this->finalizeJourneeChptRanking($competition, $season, $goalaverage);

// 4b. PtsNiveau depuis le rang, puis reconstruction des totaux.
$this->applyRankBasedPtsNiveau($competition, $season);
$this->rebuildPtsNiveauTotals($competition, $season);

// 5. Finalisations qui consomment ces totaux.
$this->finalizeChptRanking(...);
$this->finalizeNiveauRanking(...);
$this->finalizeNiveauNiveauRanking(...);
$this->finalizeJourneeNiveauRanking(...);
```

`applyRankBasedPtsNiveau()` traite les phases consolidées **comme les autres** : leur
`Clt` figé (corrections manuelles incluses) est respecté, seul `PtsNiveau` est reconstruit.
`rebuildPtsNiveauTotals()` ne réécrit **que** `PtsNiveau` ; `Pts`, `J`, `G`, `N`, `P`,
`F`, `Plus`, `Moins`, `Diff` gardent les valeurs produites par les étapes précédentes.

**Fichiers** :
- api2 : `applyRankBasedPtsNiveau()` + `rebuildPtsNiveauTotals()`
- legacy : `AppliquePtsNiveauSurRang()` + `ReconstruitTotauxPtsNiveau()` (SQL identique,
  vérifié par comparaison normalisée)

## Bug D — Divergence legacy / api2 sur le `Clt` de poule

Révélé par `T-3RIV` : classement général inversé entre les rangs 8 et 9 selon l'admin
utilisé (Les alligators / Montargis I).

**Ce n'est pas** un effet du départage `PtsNiveau DESC, Diff DESC` de
`finalizeNiveauRanking()` : les deux équipes ont des `PtsNiveau` distincts. La divergence
naît **en amont**, sur le `Clt` de la poule AF.

Le match Vannes – Montargis n'était pas encore joué : les deux équipes sont à égalité de
points, et la confrontation directe (1ᵉʳ critère en `goalaverage = part`) ne peut pas les
départager.

| | Vannes | Montargis I |
|---|---|---|
| legacy (avant) | `Clt=1` | `Clt=1` (ex æquo) |
| api2 | `Clt=1` | `Clt=2` |

api2 poursuit la cascade FFCK (`h2h_points → h2h_diff → diff_generale → buts_marques → …`)
et sépare sur la différence générale (+7 contre +2). Le legacy s'arrêtait au h2h :
`GestionEgalitesClassementJourneeChpt()` n'agrège que les matchs **entre équipes à
égalité** ; sans match joué, toutes les valeurs valent 0 et l'ex æquo subsistait.

Répercuté par la nouvelle formule : Montargis vaut `4096×3 = 12 288` (legacy) contre
`4096×2 = 8 192` (api2) — un rang d'écart dans une poule de 3 pèse 4096 points de niveau,
assez pour renverser le général.

> Cette divergence **préexistait** à la refonte : `PtsNiveau` dérivant alors du résultat
> des matchs, le désaccord sur `Clt` restait invisible au classement général. La refonte
> l'expose, elle ne le crée pas.

**Décision** : api2 fait référence (cascade complète).

**Correctif legacy** — `GestionClassement.php` :

1. `GestionEgalitesClassementJourneeChpt()` : ajout des critères de repli `DiffGen` puis
   `PlusGen` (statistiques générales de la poule, lues depuis
   `kp_competition_equipe_journee`) après les critères h2h. L'ordre de tri devient
   `Pts h2h → Diff h2h → Plus h2h → Diff générale → Buts marqués`, soit la cascade `part`
   d'api2.
2. Attribution des rangs : l'ancienne boucle incrémentait **systématiquement** le rang à
   partir du 2ᵉ, produisant un ordre arbitraire entre équipes indépartageables. Elle
   conserve désormais l'ex æquo quand tous les critères sont épuisés — comme api2, dont
   la cascade se termine sur `non_departage`.
3. `FinalisationClassementJourneeChpt()` : ajout du filtre
   `AND (j.Consolidation IS NULL OR j.Consolidation != 'O')`, absent côté legacy. Sans
   lui le legacy **recalculait le `Clt` des phases consolidées**, écrasant les
   corrections manuelles — et ce `Clt` alimente désormais `PtsNiveau`, donc le général.

---

## Périmètre d'impact : quelles vues et quels PDF ?

**Aucune vue publique ne change tant que le classement n'est pas republié.**

Tous les consommateurs lisent les colonnes **`_publi`**, jamais les colonnes calculées
(vérifié : chaque requête SQL de ces fichiers porte sur `CltNiveau_publi` /
`PtsNiveau_publi`) :

| Consommateur | Colonne lue |
|---|---|
| `kpclassement.php`, `kpclassements.php`, `kpphases.php`, `kpchart.php`, `kphistorique.php` | `CltNiveau_publi`, `PtsNiveau_publi` |
| `frame_classement.php`, `frame_phases.php`, `frame_chart.php` | `CltNiveau_publi` |
| PDF : `PdfCltNiveau`, `…Niveau`, `…Phase`, `…Journee`, `…Detail`, `PdfCltChpt`, `…Detail` | `CltNiveau_publi`, `PtsNiveau_publi` |
| api2 `GroupController`, `ChartsController` ; api legacy `publicControllers` | `CltNiveau_publi` |

Chaîne d'impact :

1. **Au recalcul** — seule la page **Classement de l'admin** (admin2 et legacy), bloc
   « calculé », reflète les nouvelles valeurs.
2. **À la republication** — tout le reste bascule d'un coup : classements publics, phases,
   graphiques, historique et les **7 PDF**. `publish()` recopie `CltNiveau → CltNiveau_publi`.

Deux conséquences :

- Le **déploiement seul ne change rien** pour le public : il faut recalculer **et**
  republier.
- C'est ce qui rend gérable le cas des compétitions `END` : leur classement publié reste
  intact jusqu'à décision explicite.

> `PtsNiveau_publi` est *affiché* dans `PdfCltNiveau` et `PdfCltNiveauDetail` : les valeurs
> numériques y changeront visiblement après republication, pas seulement l'ordre.

---

## Prise en compte du goal-average (`gen` / `part`)

La nouvelle formule ne contient **aucun** critère de départage : elle ne lit que `Clt`.
C'est `finalizeJourneeChptRanking($competition, $season, $goalaverage)`, exécutée juste
avant, qui produit ce `Clt` en appliquant **la cascade complète** propre à la compétition,
poule par poule :

| `goalaverage` | Cascade |
|---|---|
| `gen` (ICF) | diff générale → buts marqués → confrontation directe → cartons |
| `part` (FFCK) | **points h2h → diff particulière** → diff générale → buts marqués → cartons rouges/jaunes/verts |

C'est précisément le gain de la refonte : `PtsNiveau` dérivait auparavant du seul résultat
de match (V/N/P/F), **sans aucune notion de goal-average** — le départage fin appliqué
dans la poule était perdu en remontant au général. Il est désormais intégralement transmis.

**Vérifié sur N3** (`goalaverage = part`), poule NE : Le Havre 1ᵉʳ (1200 pts), puis Cestas,
Orsay et Montpellier **à égalité de points** (600), départagés par la cascade `part`. Le
nouveau `PtsNiveau` reprend ces rangs 2/3/4 — d'où Cestas 12→10 et Montpellier 10→12 au
général. L'ancien code les traitait comme équivalentes et laissait la `Diff` **générale**
trancher, contredisant le règlement FFCK de la compétition.

Sur les 7 compétitions impactées, **4 sont en `part`** (N3, N18, NPOH, REG20A) et 3 en
`gen` (T-62D1, T-62D2, REG07U12) : la correction opère dans les deux modes.

> **Réserve** : `finalizeNiveauRanking()`, qui transforme `PtsNiveau` en `CltNiveau`, trie
> en dur sur `PtsNiveau DESC, Diff DESC` et **ne reçoit pas `$goalaverage`** — ce `Diff`
> est la différence **générale**, quel que soit le mode. Cela ne joue que pour départager
> deux équipes de `PtsNiveau` strictement égal (donc même rang, mêmes poules, mêmes
> niveaux), cas devenu rare avec la nouvelle formule. Comportement **inchangé** par rapport
> à l'existant, mais non conforme au règlement `part` pour ces ex æquo résiduels. Non
> traité ici.

---

## Compétitions et poules à vérifier

> Base de dev au 2026-09-04, 32 compétitions CP portant des équipes (sur 58 déclarées) / 259 équipes. **À rejouer sur prod.**

### Impact mesuré : 12 équipes sur 259 (4,6 %), 7 compétitions

Comparaison **ancien pipeline vs nouveau**, tous deux rejoués à neuf (et non contre les
valeurs stockées, qui contiennent des restes périmés).

| Compétition | Statut | Équipes | Nature du changement |
|---|---|---|---|
| **T-62D1** | `END` | GBR U21 Men 10↔11, SG Liblar/Hamburg 11↔10 | inversion réelle |
| **T-62D2** | `END` | Michiel de Ruyter 2 15↔16, Meridian X 16↔15 | inversion réelle |
| **N3** | `END` | Montpellier I 10↔12, Cestas I 12↔10 | inversion réelle |
| **N18** | `END` | Ploërmel-Vern 8↔7, Pont d'Ouilly 7↔8 | inversion réelle |
| **NPOH** | `END` | Condé-sur-Vire I 7↔8, Saint-Grégoire I 8↔7 | inversion réelle |
| **REG07U12** | `ON` | Saint-Domineuc 12 I 3→4 | ex æquo résolu |
| **REG20A** | `END` | Thury-Harcourt Rég I 2→3 | ex æquo résolu |

Les 25 autres compétitions sont **strictement inchangées**, dont T-AVRD1, N3E, N3O,
ECA1, NEM.

### Poules décisives à contrôler

Dans **tous** les cas d'inversion, les deux équipes sont dans la **même poule décisive**,
où l'ancienne formule leur donnait un `PtsNiveau` identique — le rang de poule était donc
ignoré et `Diff` tranchait, plaçant parfois la 2ᵉ devant la 1ʳᵉ.

| Compétition | Poule à contrôler | Situation |
|---|---|---|
| T-62D1 | `Classifying 10th-12th` (niv. 4) | SG Liblar 1ᵉʳ / GBR U21 2ᵉ — même `PN` = 117 440 512 |
| T-62D2 | `15th place` (niv. 4, Type E) | Meridian X 1ᵉʳ / Michiel 2ᵉ — `PN` = 0 des deux côtés |
| N3 | `Poule NE` (niv. 2) | Cestas 2ᵉ / Montpellier 4ᵉ |
| N18 | `Classement 5-8` (niv. 2) | Ploërmel 3ᵉ / Pont d'Ouilly 4ᵉ |
| NPOH | `Classement 5-8` (niv. 2) | Saint-Grégoire 3ᵉ / Condé 4ᵉ |
| REG07U12 | `Poule A` (niv. 1) | Saint-Grégoire 3ᵉ / Saint-Domineuc 4ᵉ, `Diff` égales |
| REG20A | `Poule A` (niv. 1) | Le Havre 2ᵉ / Thury-Harcourt 3ᵉ, `Diff` égales |
| T-AVRD2 | `12th place` (niv. 4) | **ex æquo légitime** : les 2 équipes sont à `Clt=1` |

**Après implémentation, le rang général doit respecter le rang de poule dans chacune.**

### ⚠️ 6 des 7 compétitions sont au statut `END`

Seule REG07U12 (`ON`) se corrigera au premier recalcul. Les 6 autres sont **terminées et
publiées** : elles nécessitent la séquence de remédiation décrite plus haut
(sauvegarde → `END`→`ON` → recalcul → vérification → republication → `ON`→`END`).

Le classement **publié** n'est pas modifié par un simple recalcul : sur T-AVRD2 en prod,
il était déjà correct (corrigé à la main avant publication).

---

## Validation

Pipeline réel exécuté via le **vrai contrôleur** (réflexion sur `AdminRankingsController`),
sur les 32 compétitions CP de 2026 portant des équipes, en transaction annulée.

- `php -l` OK sur les deux fichiers.
- **T-AVRD2 corrigé** : 12 = Château-Thébaud I, 13 = Les alligators, 14 = FRA U21 Women,
  15 = Saint-Grégoire I — **sans avoir eu à toucher au `Niveau`**.
- **Idempotence** : deux passes consécutives donnent un résultat strictement identique
  (`diff` vide sur les 259 lignes).
- **Impact circonscrit** : 12 équipes / 259, toutes vérifiées une par une comme étant des
  corrections (le rang général respecte désormais le rang de poule).
- **api2 ≡ legacy** : formule SQL identique au caractère près après normalisation.
- **Bug D corrigé (T-3RIV)** : la logique de départage legacy modifiée, rejouée sur la
  poule AF, donne Vannes 1 / Montargis 2 — identique à api2. Le classement général qui en
  découle (Les alligators 8ᵉ = 12 352, Montargis 9ᵉ = 8 320) concorde entre les deux
  moteurs.
- **Non-régression** : le pipeline api2 rejoué après les modifications legacy donne un
  résultat identique sur les 31 autres compétitions CP ; seul T-3RIV diffère du relevé
  précédent, des matchs y ayant été saisis entre-temps.
- Idempotence revérifiée sur l'état courant : deux passes identiques.
- Aucune donnée modifiée : `ROLLBACK` sur tous les tests, scripts supprimés.

### Réserves

- Les 32 poules `C` (sur 108 au 2026-09-04 ; 115 au 2026-09-07, des matchs ayant été
  saisis depuis) contenant des ex æquo sur `PtsNiveau` voient leur
  comportement changer : le rang de poule les départage désormais au général. C'est
  l'effet recherché, mais il touche un tiers des poules — à faire valider
  fonctionnellement.
- L'ex æquo de `12th place` sur T-AVRD2 (deux équipes à `Clt=1`) est **conservé** : la
  refonte propage fidèlement la donnée source au lieu d'inventer un ordre.
- **`CHPT` vérifié, sans impact visible** : `applyRankBasedPtsNiveau()` ne filtre pas sur
  le type de compétition, et 365 lignes de phase appartiennent à des CHPT. Mesuré sur les
  155 équipes CHPT de 2026 : `PtsNiveau` change sur 141 d'entre elles, mais le rang
  affiché (`Clt`, seul lu en CHPT) est **inchangé sur les 155**. `MULTI` n'entre pas dans
  cette branche du pipeline.
- **Bug D — portée plus large que T-3RIV, non mesurée** : le repli `DiffGen`/`PlusGen`
  change le départage de **toutes** les poules en `goalaverage = part` où des équipes sont
  à égalité de points sans confrontation directe décisive. 12 compétitions sont en `part`
  (ECCM, N15, N18, N3, N3E, NEM, NPOF, NPOH, OPEND, REG20A, REG20B, T-3RIV). L'effet n'a
  été mesuré que sur T-3RIV : la classe legacy étend `MyPageSecure` et exige une session
  HTTP, donc `DoClassement()` n'est pas exécutable en CLI — la logique modifiée a été
  testée isolément sur la poule AF. **Un recalcul comparatif via l'interface sur une ou
  deux compétitions `part` terminées reste à faire avant déploiement.**
- **Bug D correctif 3 — ne restaure pas le passé** : le filtre de consolidation empêche le
  legacy d'écraser à l'avenir le `Clt` des phases consolidées, mais si des recalculs
  passés l'ont déjà fait, les valeurs actuelles en base sont issues de cet écrasement et
  ne sont pas restaurées.
- Impact prod non mesuré : les chiffres ci-dessus valent pour la dev.

---
---

# Runbook de remédiation post-déploiement

> Consolide les séquences décrites en Partie 1 et Partie 2. À exécuter **après** déploiement
> du correctif (code) en prod, compétition par compétition. Les chiffres de compétitions et
> de poules cités proviennent de la dev (2026-09-01 / 2026-09-04 / 2026-09-07) — **à
> revérifier en prod avant toute action**, via les requêtes de contrôle du document.

## 0. Préalable — rejouer les requêtes de détection en prod

Avant toute remédiation, rejouer sur la prod :

- la requête « niveaux mixtes » (bug B, §Requêtes de contrôle) ;
- la requête « écart J stocké / J réel » (bug A, §Requêtes de contrôle) ;
- un recalcul en transaction annulée sur chaque compétition CP 2026 pour mesurer l'écart
  réel avant/après (comme fait en dev), afin de confirmer que la liste ci-dessous est toujours
  valable et n'a pas besoin d'être complétée.

## 1. Compétitions concernées

8 compétitions distinctes, toutes `Code_saison = 2026`, cumulant les deux bugs :

| Code | Libellé | Bug(s) | Statut (dev) | Nature de l'écart |
|---|---|---|---|---|
| **N3O** | Nationale 3 - 1/2 Finales Ouest | A | `END` | J sous-compté |
| **N3E** | Nationale 3 - 1/2 Finales Est | A + B | `END` | J sur-compté (double comptage niveau) |
| **T-62D1** | Tournoi International du Pas de Calais | A + C/D | `END` | J sous-compté + inversion GBR U21/SG Liblar |
| **T-62D2** | Tournoi International du Pas de Calais (2) | C/D | `END` | inversion Michiel de Ruyter 2 / Meridian X |
| **N3** | Nationale 3 | C/D | `END` | inversion Montpellier I / Cestas I |
| **N18** | Nationale 18 | C/D | `END` | inversion Ploërmel-Vern / Pont d'Ouilly |
| **NPOH** | Nationale Pôles Hommes | C/D | `END` | inversion Condé-sur-Vire I / Saint-Grégoire I |
| **REG20A** | Régional 20 A | C/D | `END` | ex æquo Thury-Harcourt à résoudre |

Compétitions **`ON`** touchées, corrigées **sans manip particulière** au premier recalcul
normal (pas de bascule de statut) :

- **T-AVRD1** (bug A/B)
- **REG07U12** (bug C/D)
- **T-AVRD2** (bug C, déjà corrigée en dev, cf. §Validation partie 2)

Compétitions consolidées **sans niveau mixte** (12 : ECA1, ECA1U21, ECA1U21W, ECA1W, N15,
N18¹, NPOF, NPOH¹, REG20B, T-62D2¹, T-AVRD2¹ — ¹déjà listées ci-dessus pour un autre bug) :
non exposées au bug B, mais à recalculer par précaution si elles portent des phases
consolidées (bug A potentiel).

> **NEM** : niveau mixte présent mais jamais recalculée depuis l'introduction du bug → saine,
> aucune action requise, sauf recalcul de routine.

## 2. Séquence par compétition `END`

À répéter **individuellement** pour chacune des 8 compétitions du tableau ci-dessus. Ne pas
grouper les bascules de statut : traiter une compétition de bout en bout avant de passer à
la suivante, pour limiter la fenêtre où un classement publié est temporairement rouvert.

1. **Sauvegarder** (dump SQL ciblé, pas juste un export) :
   - `kp_competition_equipe` (lignes de la compétition/saison)
   - `kp_competition_equipe_niveau` (idem)
   - `kp_competition_equipe_journee` (idem)
   - `kp_competition` (la ligne de la compétition — pour restaurer le `Statut` en cas
     d'interruption)
2. **Basculer le statut** `END` → `ON` (nécessite un profil ≤ 3).
3. **Recalculer le classement** (admin api2 ou legacy — les deux moteurs donnent désormais
   un résultat identique, cf. Validation).
4. **Vérifier avant republication** :
   - `J` par équipe contre `kp_competition_equipe_journee` (somme des matchs réellement
     joués, phases consolidées incluses) ;
   - pour les compétitions listées bug C/D : le rang général respecte le rang de poule dans
     la **poule décisive** indiquée au tableau « Poules décisives à contrôler » ;
   - pour T-62D1, N3E, N3O : re-rejouer la requête « écart J » (§0) pour confirmer J_stocké
     = J_attendu ;
   - si `goalaverage = part` sur la compétition (N3, N18, NPOH, N3E) : vérifier à la main que
     le classement de la poule décisive respecte la cascade FFCK (points h2h → diff
     particulière → diff générale → buts marqués → cartons) — cf. §4 ci-dessous, cette
     vérification n'a jamais été faite via l'interface réelle pour ces compétitions.
5. **Republier** le classement (bascule `CltNiveau` → `CltNiveau_publi`, etc. — c'est ce qui
   rend le changement visible au public).
6. **Rebasculer le statut** `ON` → `END`.
7. **Consigner** : compétition traitée, date, écart constaté avant/après, personne ayant
   validé la republication (ces compétitions sont publiques et déjà terminées — la
   republication modifie un résultat officiel consulté).

## 3. Compétitions `ON` (T-AVRD1, REG07U12)

Pas de bascule de statut nécessaire. Un recalcul + republication normaux suffisent. Vérifier
tout de même le point 4 ci-dessous en priorité pour ces deux-là : ce sont les seules où le
correctif peut être validé en conditions réelles sans manip de statut, donc les meilleures
candidates pour combler la réserve du §4 avant de s'attaquer aux compétitions `END`.

## 4. Combler la réserve bug D avant de traiter les compétitions `part`

Le document signale que la portée du bug D (repli `DiffGen`/`PlusGen`) n'a été testée
qu'isolément (poule AF de T-3RIV), `DoClassement()` legacy exigeant une session HTTP
(`MyPageSecure`) non disponible en CLI. Avant de traiter **N3, N18, NPOH, N3E** (toutes en
`goalaverage = part`, toutes `END`) :

1. Choisir 1 à 2 compétitions `part` déjà terminées (T-3RIV a déjà servi de cas de test ;
   prendre une deuxième pour croiser) et rejouer un recalcul comparatif **via l'interface
   admin réelle** (pas en CLI), legacy et api2, en environnement de dev ou preprod.
2. Comparer les classements obtenus poule par poule sur les cas d'égalité de points sans
   confrontation directe décisive.
3. Ne traiter N3 / N18 / NPOH / N3E (§2) qu'une fois ce point validé — ce sont exactement les
   compétitions où cette réserve peut se matérialiser en prod.

## 5. Ordre de traitement recommandé

1. **REG07U12** et **T-AVRD1** (`ON`, aucune manip de statut, risque le plus faible) —
   valider le pipeline de bout en bout en conditions réelles.
2. **§4** — combler la réserve bug D sur 1-2 compétitions `part` terminées.
3. **N3O** (bug A seul, pas de dépendance au goal-average `part` a priori — à vérifier).
4. **T-62D2, REG20A** (bug C/D, hors `part` ou impact limité à un ex æquo).
5. **N3, N18, NPOH, N3E, T-62D1** (bug C/D en `goalaverage = part`, ou cumul A+B+C/D pour
   T-62D1 et N3E) — en dernier, une fois le §4 validé.
