# Page compétition et ses onglets

**Phase** : 2 — **Statut** : 📝 Proposée
**Routes** : `/competitions/{season}/{code}` → `/competitions/{season}/{code}/{tab}` (+ `/en/…`),
`tab` ∈ `games` · `pitches` · `info` · `progress` · `phases` · `ranking` · `stats`
**Remplace** : `kpmatchs.php`, `kpterrains.php`, `kpdetails.php`, `kpchart.php`, `kpphases.php`,
`kpclassement.php`, `kpstats.php` et la barre `kpnavgroup.tpl`

## 1. Objectif

Toute l'information publique d'une compétition sur une page à onglets, avec un passage direct vers les
compétitions sœurs (même groupe, ou même événement quand on vient d'un événement).

## 2. Structure commune

```
Fil d'Ariane : Accueil › Compétitions {saison} › {groupe} › {compétition}
┌─────────────────────────────────────────────────────────────┐
│ [bandeau ou logo]  {display_title}   {saison} [type] [statut] │  en-tête (CompetitionHeader)
│ Site web ↗   Suivre en direct (app2) ↗                       │
├─────────────────────────────────────────────────────────────┤
│ Sœurs : [Poule A] [Poule B] [Classement final] …             │  sélecteur de compétition
├─────────────────────────────────────────────────────────────┤
│ Matchs | Terrains | Infos | Déroulement | Phases | Classement | Stats │  onglets (ResultsTabs)
├─────────────────────────────────────────────────────────────┤
│ contenu de l'onglet                                          │
└─────────────────────────────────────────────────────────────┘
```

- **`/competitions/{season}/{code}`** redirige (302) vers l'onglet par défaut : `ranking` si le statut est `END`,
  sinon `games`. Ainsi, chaque contenu a une seule URL.
- **En-tête** : données de `GET /competition/{season}/{code}`. Le lien « Suivre en direct » mène à
  `{app2}/group/{season}/{groupe}` (page groupe existante d'app2).
- **Sélecteur de compétitions sœurs** : `siblings` de l'en-tête (libellé `soustitre2`, sinon `display_title`) ;
  conserve l'onglet courant. Il est masqué s'il n'y a qu'une compétition.
- **Contexte événement** : avec `?event={id}`, le fil d'Ariane devient « Accueil › {événement} › {compétition} »,
  les sœurs sont celles de `GET /event/{id}/competitions`, et le paramètre est conservé dans tous les liens
  internes de la page (onglets, sœurs). Sans ce paramètre, les sœurs sont celles du groupe.
- **Onglets** : liens (pas de JavaScript requis), `aria-current="page"` sur l'onglet actif ; sur mobile, une
  liste déroulante remplace la barre. Un onglet sans contenu reste accessible et affiche son état vide.

### 2.1 Liens vers des pages non encore livrées

Les noms d'équipes renvoient vers la fiche équipe (`/teams/{id}`, phase 3), les matchs vers la feuille de
match. Tant que ces pages ne sont pas livrées, on utilise la **même mécanique de repli que le menu**
(SITE_NAVIGATION.md § 3) : une table `PAGE_LINKS` (`team`, `game`…) indique pour chaque page cible si elle est
`ready` et, sinon, son URL de repli :

| Page cible | Livrée en | Repli |
|---|---|---|
| Fiche équipe | phase 3 | `{legacy}/kpequipes.php?Equipe={numero}&Compet={code}&lang={fr\|en}` |
| Feuille de match | — (reste dans app2) | `{app2}/game/{id}` |

### 2.2 Rafraîchissement

Si au moins un match du jour est en cours (`ON`) ou à venir dans l'heure, les onglets **Matchs**, **Terrains**
et **Classement** se rafraîchissent côté navigateur toutes les **60 s** (sans rechargement de page ; arrêt
quand l'onglet du navigateur est masqué). L'abonnement Mercure remplacera ce mécanisme quand le chantier
scoring publiera les topics publics.

## 3. Onglets

### 3.1 Matchs (`games`) — remplace `kpmatchs.php`

- Données : `GET /competition/{season}/{code}/games`.
- Matchs **groupés par date** (titre de groupe : date longue localisée), puis triés par heure et terrain.
- Une ligne par match : numéro, heure, terrain, phase / libellé (`d_phase`, `g_code`), équipe A (logo + nom),
  **score** (`g_score_a – g_score_b`, coefficients s'ils diffèrent de 1), équipe B, arbitres (`r_1`, `r_2`,
  affichés à partir de `lg`), statut.
- **Statut** : `ATT` « À venir » ; `ON` « En cours » (+ période `g_period`) en rouge accent ; `END` « Terminé » ;
  score **provisoire** (italique + mention « provisoire ») tant que `g_validation ≠ 'O'`.
- Un match `ON` ou `END` mène à sa feuille de match (§ 2.1).
- **Filtres** (paramètres d'URL, combinables) :
  - `gameday={id}` pour un championnat (CHPT) : sélecteur des journées (libellé, lieu, dates) ;
  - `day=YYYY-MM-DD` : sélecteur des dates présentes ;
  - `upcoming=1` : « Prochains matchs » = matchs dont la date/heure (Europe/Paris) est postérieure à
    **maintenant − 35 min** (règle legacy), calcul par une fonction pure.
- Aucun match : « Aucun match publié. » ; aucun match après filtrage : « Aucun match pour ces critères. »

### 3.2 Terrains (`pitches`) — remplace `kpterrains.php`

- Même source que **Matchs**.
- Sélecteur de **jour** (`day=`) ; par défaut : aujourd'hui s'il y a des matchs, sinon le prochain jour avec des
  matchs, sinon le dernier.
- **Grille** : une colonne par terrain (ordre numérique), une ligne par horaire ; cellule = match compact
  (équipes, score, statut). Sur mobile (< `md`) : une section par terrain, matchs dans l'ordre horaire.

### 3.3 Infos (`info`) — remplace `kpdetails.php`

- Données : `GET /competition/{season}/{code}/info`.
- **Journées** : libellé, dates (début – fin), lieu et département, organisateur, officiels (RC, R1, délégué,
  chef des arbitres) quand ils sont renseignés.
- **Équipes engagées** par poule (logo, nom), seulement si la compétition est `ON` ou `END` (règle legacy).
- **Schéma** de la compétition (image) s'il existe, avec un texte alternatif.
- Lien vers le site web de la compétition s'il est renseigné. L'abonnement ICS arrive en phase 3.

### 3.4 Déroulement (`progress`) — remplace `kpchart.php`

- Données : `GET /competition/{season}/{code}/charts`.
- Représentation graphique du parcours : une colonne par **tour** (`rounds`), une carte par **phase** (poule ou
  match à élimination), avec équipes et résultats, comme le composant « charts » d'app2, **porté dans
  `kpi-layer`** (duplication temporaire avec app2 tracée, cf. stratégie § 2.2).

### 3.5 Phases (`phases`) — remplace `kpphases.php`

- Même source que **Déroulement**.
- Par tour puis par phase (ordre de niveau décroissant, comme api2) :
  - **poule** (`type` = `C`) : tableau de classement de la phase (rang, équipe, Pts, J, G, N, P, F, +, −, Diff)
    puis la liste de ses matchs ;
  - **élimination** (`type` = `E`) : la liste des matchs avec vainqueur mis en évidence.

### 3.6 Classement (`ranking`) — remplace `kpclassement.php`

- Données : `GET /competition/{season}/{code}/ranking`.
- Badge **« Classement provisoire »** si le statut est `ON`, **« Classement final »** si `END`.
- Colonnes selon le type (API_PUBLIC_RESULTS.md § 5.3) : CHPT et CP → rang, équipe, Pts, J, G, N, P, F, +, −,
  Diff ; MULTI → rang, équipe, Pts, J. Sur mobile, colonnes réduites à rang, équipe, Pts, J, Diff.
- Les `qualified` premiers et `eliminated` derniers sont marqués (couleur **et** texte).
- Pas de classement disponible → « Le classement sera publié après les premiers matchs. »

### 3.7 Stats (`stats`) — remplace `kpstats.php`

- Données : `GET /competition/{season}/{code}/scorers?limit=20`.
- Tableau des **meilleurs buteurs** : rang (égalités partagées), « NOM Prénom #numéro », équipe (lien § 2.1),
  buts. Bouton « Voir plus » → `limit=100`.
- Aucun but enregistré → « Aucune statistique disponible. »

## 4. Données et cache

- Endpoints : API_PUBLIC_RESULTS.md § 4.
- `routeRules` : `cache: { maxAge: 60, swr: true }` sur `/competitions/*/*/**`.
- Les données sont chargées **par onglet** (seul l'en-tête est commun), pour des pages légères.

## 5. SEO et accessibilité

- Titre : « {onglet} — {display_title} {saison} — kayak-polo.info » ; description propre à l'onglet.
- Données structurées `SportsEvent` (nom, dates de début/fin, lieu de la première journée) sur l'onglet Infos.
- Tableaux avec `<caption>` et en-têtes `scope="col"` ; scores lisibles par lecteur d'écran (« 5 à 3 »).
- Statuts et marques qualifié/éliminé non portés par la seule couleur.

## 6. Critères d'acceptation

- **CMP-01** — `/competitions/{s}/{c}` redirige vers `ranking` si le statut est `END`, sinon vers `games`.
- **CMP-02** — L'en-tête affiche le bandeau (sinon le logo), le titre `display_title`, la saison, les badges type et statut, et les liens web / app2.
- **CMP-03** — Le sélecteur de sœurs liste les compétitions du groupe (ou de l'événement avec `?event=`), conserve l'onglet et le paramètre `event`, et est masqué s'il n'y a qu'une compétition.
- **CMP-04** — Les onglets portent `aria-current` sur l'onglet actif et fonctionnent sans JavaScript.
- **CMP-05** — Matchs groupés par date, triés par heure puis terrain ; statut et score provisoire affichés selon `g_status` / `g_validation`.
- **CMP-06** — Les filtres `gameday`, `day` et `upcoming` se combinent ; `upcoming` applique « maintenant − 35 min » en heure de Paris (fonction pure testée avec une date injectée).
- **CMP-07** — La grille des terrains a une colonne par terrain et une ligne par horaire ; le jour par défaut suit la règle § 3.2.
- **CMP-08** — Infos : journées avec officiels ; équipes par poule seulement si `ON`/`END` ; schéma s'il existe.
- **CMP-09** — Déroulement et Phases affichent tours et phases dans l'ordre d'api2 ; poules avec tableau, éliminations avec vainqueur.
- **CMP-10** — Classement : colonnes selon le type, badge provisoire/final, qualifiés/éliminés marqués, état vide.
- **CMP-11** — Stats : 20 buteurs puis « Voir plus » (100), égalités de rang partagées, état vide.
- **CMP-12** — Liens équipe et match résolus par `PAGE_LINKS` (repli legacy / app2 tant que non livrés).
- **CMP-13** — Rafraîchissement toutes les 60 s seulement si un match est en cours ou commence dans l'heure, suspendu quand l'onglet est masqué.
- **CMP-14** — Compétition inconnue ou non publiée → page 404 du site.
- **CMP-15** — Grille de parité (§ 7) validée sur trois compétitions réelles en préprod.

## 7. Grille de parité legacy ↔ app3 (recette)

À dérouler en préprod sur **une compétition CHPT, une CP et une MULTI** de la saison en cours :

| Onglet | Vérification |
|---|---|
| Matchs | même nombre de matchs, mêmes scores et statuts que `kpmatchs.php` (filtres « Tous », une journée, prochains matchs) |
| Terrains | même répartition que `kpterrains.php` pour un jour donné |
| Infos | mêmes journées, officiels et équipes que `kpdetails.php` |
| Déroulement / Phases | mêmes poules, équipes et matchs que `kpchart.php` / `kpphases.php` |
| Classement | mêmes rangs, points et colonnes que `kpclassement.php` |
| Stats | mêmes 20 premiers buteurs que `kpstats.php` |

## 8. Correspondance des anciennes URL (pour la phase 5)

| Ancienne URL | Nouvelle URL |
|---|---|
| `kpmatchs.php?Saison=S&Compet=C[&J=id][&filtreJour=d][&next=next]` | `/competitions/S/C/games[?gameday=id][&day=d][&upcoming=1]` |
| `kpterrains.php?Saison=S&Compet=C[&filtreJour=d]` | `/competitions/S/C/pitches[?day=d]` |
| `kpdetails.php?Saison=S&Compet=C` | `/competitions/S/C/info` |
| `kpchart.php?…` / `kpphases.php?…` / `kpclassement.php?…` / `kpstats.php?…` | `…/progress` / `…/phases` / `…/ranking` / `…/stats` |
| `…&event=E` | `…?event=E` |
| `…&Compet=*&Group=G` (matchs, terrains) | `/groups/S/G/games` / `/groups/S/G/pitches` |
| `…&lang=en` | préfixe `/en` |

## 9. Hors périmètre / questions ouvertes

- Liens PDF (classements, listes de matchs, feuilles de marque) : API_PUBLIC_RESULTS.md Q-P2-2.
- Fiche équipe et historique : phase 3.
- Abonnement ICS de la compétition : phase 3 (lien ajouté dans l'onglet Infos à ce moment-là).
