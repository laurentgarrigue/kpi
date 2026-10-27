# Page compétition et ses onglets

**Phase** : 2 — **Statut** : ✅ Validée (08/10/2026, retours intégrés)
**Routes** : `/competitions/{season}/{code}` → `/competitions/{season}/{code}/{tab}` (+ `/en/…`),
`tab` ∈ `info` · `games` · `pitches` · `progress` · `ranking` · `stats`
**Remplace** : `kpmatchs.php`, `kpterrains.php`, `kpdetails.php`, `kpchart.php`, `kpphases.php`,
`kpclassement.php`, `kpstats.php` et la barre `kpnavgroup.tpl`

## 1. Objectif

Toute l'information publique d'une compétition sur une page à onglets, avec un passage direct vers les
compétitions sœurs (même groupe, ou même événement quand on vient d'un événement).

## 2. Structure commune

```
Fil d'Ariane : Accueil › Compétitions {saison} › {groupe} › {compétition}
┌─────────────────────────────────────────────────────────────┐
│ [bandeau ou logo]  {display_title}   {saison} [statut]        │  en-tête (CompetitionHeader)
│ Site web ↗   Suivre en direct (app2) ↗                       │
├─────────────────────────────────────────────────────────────┤
│ ★ Fait partie de {événement} — Voir l'événement →            │  encart événement (§ 2.3)
├─────────────────────────────────────────────────────────────┤
│ Sœurs : [Poule A] [Poule B] [Classement final] …             │  sélecteur de compétition
├─────────────────────────────────────────────────────────────┤
│ Infos | Matchs | Terrains | Déroulement | Classement | Stats  │  onglets (ResultsTabs)
├─────────────────────────────────────────────────────────────┤
│ contenu de l'onglet                                          │
└─────────────────────────────────────────────────────────────┘
```

- **`/competitions/{season}/{code}`** redirige (302) vers l'onglet par défaut : `ranking` si le statut est `END`,
  sinon `games`. Ainsi, chaque contenu a une seule URL.
- **En-tête** : données de `GET /competition/{season}/{code}`, **compact** (maquettes du 22/10/2026, § 2.5) : titre,
  saison, statut écrit, bouton **« Suivre en direct »** (rouge à point clignotant, en nouvel onglet, vers
  `{app2}/group/{season}/{groupe}`, page groupe existante d'app2). Pas de libellé « Compétition » en capitales ;
  le sous-titre identique au titre n'est pas répété.
- **Ordre des onglets** : Infos en premier (présentation avant les matchs, utile à qui découvre la compétition) ; la
  redirection par défaut est inchangée (`ranking` si `END`, sinon `games`).
- **Matchs** : le vainqueur d'un match terminé a son nom et son score en **gras**, le perdant en graisse normale
  (match nul ou en cours : aucun gras).
- **Sélecteur de compétitions sœurs** : `siblings` de l'en-tête (libellé `soustitre2`, sinon `display_title`) ;
  conserve l'onglet courant. Il est masqué s'il n'y a qu'une compétition.
- **Contexte événement** : avec `?event={id}`, le fil d'Ariane devient « Accueil › {événement} › {compétition} »,
  les sœurs sont celles de `GET /event/{id}/competitions`, et le paramètre est conservé dans tous les liens
  internes de la page (onglets, sœurs). Sans ce paramètre, les sœurs sont celles du groupe.
- **Onglets** : liens (pas de JavaScript requis), `aria-current="page"` sur l'onglet actif ; sur mobile, la barre
  défile horizontalement (une liste déroulante exigerait du JavaScript pour naviguer). Un onglet sans contenu
  reste accessible et affiche son état vide.

### 2.1 Liens vers des pages non encore livrées et PDF

Les noms d'équipes renvoient vers la fiche équipe (`/teams/{id}`, phase 3), les matchs vers la feuille de
match. Tant que ces pages ne sont pas livrées, on utilise la **même mécanique de repli que le menu**
(SITE_NAVIGATION.md § 3) : une table `PAGE_LINKS` (`team`, `game`…) indique pour chaque page cible si elle est
`ready` et, sinon, son URL de repli :

| Page cible | Livrée en | Repli |
|---|---|---|
| Fiche équipe | phase 3 | `{legacy}/kpequipes.php?Equipe={numero}&Compet={code}&lang={fr\|en}` |
| Feuille de match | — (reste dans app2) | `{app2}/game/{id}` (nouvel onglet) |

**PDF** (API_PUBLIC_RESULTS.md D-P2-2) : liens « PDF » (icône + texte, nouvel onglet) construits par une fonction
pure `pdfUrl(kind, params)` vers `{legacy}/Pdf….php` avec `S`, `Compet` et `lang` explicites :
classement (onglet Classement, fichier selon le type), liste des matchs (onglet Matchs), feuille de marque
(`PdfMatchMulti.php?listMatch={id}`) sur chaque match validé (`g_validation = 'O'`), comme le legacy.

### 2.3 Accès à l'événement

D'après `events` de l'en-tête (API_PUBLIC_RESULTS.md § 5.6) :
- **événement principal** = le premier, s'il couvre **au moins 75 %** des journées publiées de la compétition
  (`share ≥ 0,75`, « quasiment toutes les phases ») : encart sous l'en-tête « Fait partie de l'événement
  {libellé} ({lieu}, {dates}) » et lien **« Voir l'événement »** → `/events/{id}` ; le fil d'Ariane le propose
  aussi (« Accueil › {événement} › {compétition} ») même sans `?event=` ;
- autres événements liés : ligne discrète « Également dans : … » (liens) ;
- avec `?event={id}`, cet événement est l'événement principal quelle que soit sa part.

Le seuil est une constante nommée (`MAIN_EVENT_MIN_SHARE = 0.75`) d'une fonction pure (`mainEvent`).

### 2.3 bis Encart événement (maquettes du 22/10/2026)

L'événement principal est présenté comme une **carte cliquable** entière vers `/events/{id}` : vignette marine des
dates (« 3–5 » sur « juil. »), nom de l'événement, « {lieu}, {dates}. Les {N} compétitions, tous les matchs par
terrain et le direct. » (« Tous les matchs par terrain et le direct. » quand l'événement n'a qu'une compétition),
bouton « Voir l'événement → ». N vient de `GET /event/{id}/competitions`.

### 2.4 Rafraîchissement

Si au moins un match du jour est en cours (`ON`) ou à venir dans l'heure, les onglets **Matchs**, **Terrains**
et **Classement** se rafraîchissent côté navigateur toutes les **60 s** (sans rechargement de page ; arrêt
quand l'onglet du navigateur est masqué). L'abonnement Mercure remplacera ce mécanisme quand le chantier
scoring publiera les topics publics.

### 2.5 Présentation (maquettes du 22/10/2026)

Référence visuelle : [maquettes/](maquettes/README.md) (`matchs`, `infos`, `deroulement`, `classement`, `stats`).

- **Colonne des scores** : scores en Agency FB, blanc sur marine ; le score perdant est atténué ; la colonne est
  rouge pendant un match en direct. Une couleur n'est jamais seule à porter une information.
- **Or** réservé à la finale et aux médailles d'or ; **rouge** réservé au direct.
- **Statut** affiché seulement s'il informe : « en cours » (avec la période) et « provisoire » ; un score validé
  suffit à dire qu'un match est terminé.
- Mobile d'abord : aucun défilement horizontal de page à 390 px ; les tableaux larges défilent dans leur conteneur.

## 3. Onglets

### 3.1 Matchs (`games`) — remplace `kpmatchs.php`

- Données : `GET /competition/{season}/{code}/games`.
- Matchs **groupés par date** (titre de groupe : date longue localisée), puis triés par heure et terrain.
- Une ligne par match : heure, terrain (toujours visible), phase (`d_phase`), équipe A (nom + logo), **score**
  (`g_score_a – g_score_b`, coefficients s'ils diffèrent de 1), équipe B (logo + nom), arbitres (`r_1`, `r_2`,
  affichés à partir de `lg`), icône de la feuille de marque. Un arbitre valant « -1 » n'est pas affiché.
- Les scores d'une journée forment une **colonne continue** (§ 2.5) ; titre de journée en Agency FB avec le nombre
  de matchs. Sur mobile, les deux équipes et les deux scores sont **empilés** (scores dans la colonne, à droite).
- **Statut** : seuls `ON` (« 2e période », colonne rouge) et le score **provisoire** (`END`, `g_validation ≠ 'O'`)
  sont signalés, par une étiquette au-dessus du score ; un match à venir montre « – – ».
- **Codes de tirage** : pour un match à venir, une équipe non encore attribuée est lue d'après `g_code`
  avec la même lecture que `MatchLabelParser` d'api2 (un code par côté, A d'abord : `[1E-1F]` → « 1er poule E »,
  « 1er poule F » ; `[V11-P12]` → « Vainqueur n° 11 », « Perdant n° 12 » ; `T7` → « Équipe n° 7 ») ou d'après son
  libellé d'attente (« (Winner game #236) » → « Vainqueur n° 236 ») ; le code brut est affiché sous la phase. Une fois le match joué, le code n'est plus affiché.
- La **finale** (`d_phase` « Finale » / « Final ») est marquée en or (pastille + texte), seule touche dorée.
- Un match `ON` ou `END` mène à sa feuille de match (§ 2.1) ; un match validé propose en plus la feuille de
  marque PDF.
- Lien « Liste des matchs (PDF) » en tête d'onglet (§ 2.1).
- **Filtres** (paramètres d'URL, combinables) :
  - `gameday={id}` pour un championnat (CHPT) : sélecteur des journées (libellé, lieu, dates) ;
  - `day=YYYY-MM-DD` : **liens de jour** (« Tous », « sam. 4 »…, `aria-current`), sans JavaScript ; **pas pour un
    championnat** (jours sans mois, nombreux sur la saison) : le choix de la journée suffit, `day=` reste accepté ;
  - `upcoming=1` : « Prochains matchs » = matchs dont la date/heure (Europe/Paris) est postérieure à
    **maintenant − 35 min** (règle legacy), calcul par une fonction pure.
- Aucun match : « Aucun match publié. » ; aucun match après filtrage : « Aucun match pour ces critères. »

### 3.2 Terrains (`pitches`) — remplace `kpterrains.php`

- Même source que **Matchs**.
- Sélecteur de **jour** (`day=`) ; par défaut : aujourd'hui s'il y a des matchs, sinon le prochain jour avec des
  matchs, sinon le dernier. Sélecteur de **terrain** (`pitch=`) : « Tous les terrains » (défaut) ou un terrain.
- **Présentation (maquettes du 22/10/2026)** : une **ligne par horaire**, **une carte par match** dans la colonne de son
  terrain (ordre numérique), sans quadrillage ; en-têtes « Terrain n » au-dessus des colonnes. Jours en liens à
  gauche, terrains en liens alignés à droite (ils passent à la ligne sans se chevaucher).
- **Carte** : en haut la **catégorie** (`soustitre2`, « libellé 2 »), suivie pour une **coupe** (`CP`) de la
  **phase** de la journée (« PlayOff H. Poule A ») ; à droite « n° {numéro} » ; puis les deux équipes empilées avec
  le **bloc de scores étroit** (§ 2.5 : score perdant atténué, rouge et période en cours, « Prov. » provisoire). La
  finale est bordée d'or.
- **Un seul terrain choisi, ou écran < 820 px** : une seule colonne, chaque carte porte son terrain (« Terrain 2 »).
  Sous 600 px, jour et terrain deviennent **deux listes déroulantes** d'un formulaire GET (appliqué au changement
  avec JavaScript, par son bouton sans).

### 3.3 Infos (`info`) — remplace `kpdetails.php`

- Données : `GET /competition/{season}/{code}/info`.
- **Journées** : dates (début – fin), lieu et département, organisateur, officiels (RC, R1, délégué, chef des
  arbitres) quand ils sont renseignés. Les journées qui ont **exactement les mêmes paramètres** (les phases
  d'une coupe) sont regroupées en **une seule fiche, sans titre ni liste de phases** ; si les paramètres
  diffèrent (championnat, coupe sur plusieurs dates), une fiche par groupe, titrée par ses phases. Le lien
  « Ajouter à mon agenda » n'est proposé que pour une journée seule (l'abonnement à la compétition couvre le reste).
- **Équipes engagées** par poule (logo, nom), seulement si la compétition est `ON` ou `END` (règle legacy).
- **Schéma** de la compétition (image) s'il existe, avec un texte alternatif.
- Lien vers le site web de la compétition s'il est renseigné. L'abonnement ICS arrive en phase 3.
- **Présentation (maquettes du 22/10/2026)** : dates en très grand (Agency FB) et lieu en tête quand toutes les
  journées partagent les mêmes paramètres ; officiels **en grille** (libellé au-dessus du nom) ; équipes par poule
  **avec logos** ; à côté (dessous sur mobile, au-dessus des équipes) : **podium** si la compétition est terminée
  (`GET …/ranking`, médailles, lien « Classement complet ») et bloc **« Dans votre agenda »** (bouton d'abonnement,
  copier le lien, .ics, aide repliée).

### 3.4 Déroulement (`progress`) — remplace `kpchart.php` **et** `kpphases.php`

`kpchart.php` et `kpphases.php` présentent les **mêmes données** (tours, phases, équipes, classements de poule
et matchs), l'une par étape en colonnes, l'autre par niveau en liste : un seul onglet, avec une **bascule
« Horizontal / Vertical »** (paramètre `view=horizontal|vertical`, lien sans JavaScript, `aria-pressed`).

- Données : `GET /competition/{season}/{code}/charts`.
- **Horizontal** (défaut) — remplace `kpchart.php` : une colonne par **tour** (`rounds`, par étape), une carte par
  **phase** (poule ou match à élimination), avec équipes et résultats, comme le composant « charts » d'app2. Sa
  **logique** (tri des poules, équipes d'attente, vainqueur) est réécrite en fonctions pures dans `kpi-layer`
  (`utils/results/charts.ts`), réutilisables par app2 en phase 6 ; les composants restent dans app3 jusque-là
  (le layer n'a pas encore d'i18n). Duplication temporaire avec app2 tracée dans le README du layer.
  Défilement horizontal sur petit écran.
- **Vertical** — remplace `kpphases.php` : par phase, en ordre de niveau décroissant :
  - **poule** (`type` = `C`) : tableau de classement de la phase (rang, équipe, Pts, J, G, N, P, F, +, −, Diff)
    puis la liste de ses matchs ;
  - **élimination** (`type` = `E`) : la liste des matchs avec vainqueur mis en évidence.
- Les deux vues partagent les mêmes sous-composants (carte d'équipe, ligne de match) ; seule la disposition
  change.
- **Présentation horizontale (maquettes du 22/10/2026)** : une colonne par tour défini par l'organisateur (2 à 5),
  **sans titre visible** (« Tour n » reste pour les lecteurs d'écran), contenu **centré verticalement** dans chaque
  colonne ; poules en tableaux (Pts, J, +/−), phases éliminatoires en matchs au bloc de scores étroit (§ 2.5) ; la
  finale est titrée en or. **Toucher une équipe surligne tout son parcours** (JavaScript ; sans lui, le nom reste un
  lien vers l'équipe) ; « Ne plus suivre {équipe} » l'annule. Sur mobile (< 600 px), les tours s'empilent.
- **Présentation verticale d'une coupe** : par phase, tableau de classement **au-dessus** de ses matchs (deux
  colonnes de matchs à partir de `sm`). Le tableau occupe toute la largeur et **répartit** ses colonnes : rang et
  logo étroits, équipe extensible, Pts, J et +/− en colonnes de largeur proportionnelle, centrées. Toucher une
  équipe surligne son parcours, comme dans la vue horizontale.
- **Championnat (`CHPT`)** : pas de bascule horizontal / vertical — les journées d'un championnat portent souvent
  la même phase, que les deux vues fusionnaient en un seul tableau. Une seule présentation **par journée**
  (`GET …/info` pour la liste, les dates et le lieu ; `GET …/charts` pour le classement de chaque journée, lignes
  rattachées par `d_id`) : titre « Journée n », dates et lieu, tableau de classement de la journée (ou « Classement
  à venir »), lien « Matchs de la journée » vers l'onglet Matchs filtré (`games?gameday={id}`). Les journées sont
  disposées en grille de colonnes de largeur bornée. Toucher une équipe surligne sa **progression au fil des
  journées** (sa ligne dans chaque classement), « Ne plus suivre {équipe} » l'annule.

### 3.6 Classement (`ranking`) — remplace `kpclassement.php`

- Données : `GET /competition/{season}/{code}/ranking`.
- Badge **« Classement provisoire »** si le statut est `ON`, **« Classement final »** si `END` ; pas de badge
  sans classement à afficher.
- Colonnes selon le type (API_PUBLIC_RESULTS.md § 5.3) : CHPT et CP → rang, équipe, Pts, J, G, N, P, F, +, −,
  Diff ; MULTI → rang, équipe, Pts, J. Sur mobile, colonnes réduites à rang, équipe, Pts, J, Diff.
- **Médailles** : compétition terminée du tour final → médaille or / argent / bronze (visuelle et textuelle)
  pour les rangs 1 à 3 (`medal` d'api2), à la place de la marque « qualifié ».
- Sinon, les `qualified` premiers et `eliminated` derniers sont marqués (couleur **et** texte).
- Lien « Classement (PDF) » (§ 2.1).
- Pas de classement disponible → « Le classement sera publié après les premiers matchs. »
- **Coupe en cours** (`CP`, `ON`) : api2 ne renvoie pas son classement (API_PUBLIC_RESULTS.md § 5.3), car un
  classement provisoire de coupe trompe : une équipe encore qualifiée, finaliste compris, peut y figurer derrière
  une équipe éliminée parce qu'il lui reste des matchs à jouer. Le message le dit : « Le classement général sera
  publié à l'issue de la compétition. » suivi de « En cours de compétition à élimination, un classement provisoire
  serait trompeur : les équipes encore qualifiées, finalistes compris, ont encore des matchs à jouer. », et un lien
  **« Suivre le déroulement de la compétition »**
  vers l'onglet Déroulement (même contexte : compétition ou événement).
- **Présentation (maquettes du 22/10/2026)** : « Classement final » / « Classement provisoire » écrit (sans badge
  coloré) ; quand les trois médailles sont attribuées, **podium en marches** (2–1–3, marche la plus haute au
  centre) avec logo, nom, points et victoires ; puis le tableau complet avec logos, différence signée (« +5 ») ;
  colonnes secondaires (N, F, +, −) masquées sur mobile.

### 3.7 Stats (`stats`) — remplace `kpstats.php`

- Conçu pour **plusieurs statistiques** (API_PUBLIC_RESULTS.md § 5.4) : `GET /competition/{season}/{code}/stats`
  donne la liste des statistiques disponibles ; un sélecteur (paramètre `stat=`, liens) les propose, masqué tant
  qu'il n'y en a qu'une. Une statistique = `GET …/stats/{kind}?limit=20`, affichée par **un tableau générique**
  piloté par `columns` ; seuls le titre et les en-têtes de colonnes sont propres à chaque statistique (i18n
  `stats.<kind>.*`). Ajouter une statistique côté app3 = ajouter ses libellés.
- Phase 2 : **meilleurs buteurs** (`scorers`, par défaut) : rang (égalités partagées), « NOM Prénom #numéro »,
  équipe (lien § 2.1), buts. Bouton « Voir plus » → `limit=100`.
- Aucune donnée → « Aucune statistique disponible. »
- **Présentation des buteurs (maquettes du 22/10/2026)** : le **meilleur buteur** mis en avant (bloc marine, buts
  en très grand) ; puis une ligne par joueur avec une **barre** de buts sur une échelle commune (le maximum de la
  liste) ; **filtre par équipe** (liens `team={numéro}`, sans JavaScript, « Toutes ») parmi les équipes de la liste ;
  « Voir plus » inchangé. Une statistique sans joueurs garde le tableau générique.

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
- **CMP-02** — L'en-tête affiche le bandeau (sinon le logo), le titre `display_title`, la saison, le badge statut et les liens web / app2. Le type de compétition (Championnat / Coupe / Multi) n'est **pas** affiché : c'est une notion de gestion, sans intérêt pour le public.
- **CMP-03** — Le sélecteur de sœurs liste les compétitions du groupe (ou de l'événement avec `?event=`), conserve l'onglet et le paramètre `event`, et est masqué s'il n'y a qu'une compétition.
- **CMP-04** — Les onglets portent `aria-current` sur l'onglet actif et fonctionnent sans JavaScript.
- **CMP-05** — Matchs groupés par date, triés par heure puis terrain ; statut et score provisoire affichés selon `g_status` / `g_validation`.
- **CMP-06** — Les filtres `gameday`, `day` et `upcoming` se combinent ; `upcoming` applique « maintenant − 35 min » en heure de Paris (fonction pure testée avec une date injectée).
- **CMP-07** — Terrains : une ligne par horaire, une carte par match placée dans la colonne de son terrain ; le jour par défaut suit la règle § 3.2 ; `pitch=` limite à un terrain (une colonne, terrain écrit sur chaque carte) ; jours et terrains en liens, et en deux listes d'un formulaire GET sur petit écran.
- **CMP-17** — Carte de terrain : catégorie (suivie de la phase pour une coupe, « PlayOff H. Poule A »), « n° {numéro} », équipes et bloc de scores (statut « en cours » / « provisoire » porté par ce bloc, CMP-19) ; la finale est bordée d'or.
- **CMP-08** — Infos : journées avec officiels ; équipes par poule seulement si `ON`/`END` ; schéma s'il existe.
- **CMP-09** — Déroulement : vue horizontale (par tour) par défaut, vue verticale (par phase, niveau décroissant) avec `view=vertical`, bascule sans JavaScript ; poules avec tableau, éliminations avec vainqueur.
- **CMP-10** — Classement : colonnes selon le type, badge provisoire/final, médailles (END + tour final, rangs 1 à 3) sinon qualifiés/éliminés marqués, état vide ; coupe en cours : classement publié à son issue, raison et lien vers le déroulement, sans badge.
- **CMP-11** — Stats : sélecteur des statistiques disponibles (masqué s'il n'y en a qu'une), tableau générique ; buteurs : 20 puis « Voir plus » (100), égalités partagées, état vide.
- **CMP-12** — Liens équipe et match résolus par `PAGE_LINKS` (repli legacy / app2 tant que non livrés) ; liens PDF construits par `pdfUrl` avec `S`, `Compet`, `lang`.
- **CMP-13** — Rafraîchissement toutes les 60 s seulement si un match est en cours ou commence dans l'heure, suspendu quand l'onglet est masqué.
- **CMP-14** — Compétition inconnue ou non publiée → page 404 du site.
- **CMP-15** — Grille de parité (§ 7) validée sur trois compétitions réelles en préprod.
- **CMP-18** — En-tête compact : titre, saison, statut en texte, « Suivre en direct » ; pas de libellé « Compétition » ; `soustitre2` masqué s'il est égal au titre. Encart événement = carte cliquable (vignette des dates, nom, « Les N compétitions… », « Voir l'événement »).
- **CMP-19** — Matchs : colonne des scores par journée, score perdant atténué, colonne rouge et période pour un match en cours, étiquette « Provisoire » ; logos des équipes ; jours en liens avec `aria-current`, sauf pour un championnat qui ne propose que le choix de la journée (ses jours, sans mois et nombreux sur la saison, ne sont pas des raccourcis utiles) ; finale marquée en or (texte « Finale » conservé).
- **CMP-20** — Codes de tirage traduits pour les équipes non attribuées d'un match à venir (« 1er poule B », « Vainqueur n° 222 »), code brut masqué une fois le match joué (fonction pure testée).
- **CMP-21** — Classement : statut écrit ; podium 2–1–3 (logos, points, victoires) quand les rangs 1 à 3 ont une médaille ; tableau avec logos et différence signée ; colonnes secondaires masquées sur mobile.
- **CMP-22** — Stats (buteurs) : meilleur buteur mis en avant, barres sur une échelle commune, filtre par équipe en liens (`team=`), sans le bloc mis en avant quand une équipe est choisie.
- **CMP-23** — Infos : dates et lieu en tête, officiels en grille, équipes avec logos, podium (lien « Classement complet ») si `END`, bloc « Dans votre agenda ».
- **CMP-24** — Déroulement horizontal : une colonne par tour sans titre visible (titre pour lecteurs d'écran), contenu centré ; toucher une équipe surligne ses apparitions (`data-followed`) et propose « Ne plus suivre ».
- **CMP-25** — Déroulement d'un championnat : aucune bascule de vue ; une carte par journée (dans l'ordre d'api2) avec « Journée n », dates, lieu, classement de cette journée seule (rangs non répétés d'une journée à l'autre) ou « Classement à venir », et lien vers `games?gameday={id}` ; toucher une équipe surligne sa ligne dans chaque journée (`data-followed`) et propose « Ne plus suivre ».
- **CMP-26** — Déroulement vertical d'une coupe : tableau de poule au-dessus de ses matchs, colonnes Pts, J, +/− réparties sur la largeur (`data-spread`) ; toucher une équipe surligne son parcours (tableaux et matchs) et propose « Ne plus suivre ».
- **CMP-16** — Un événement couvrant au moins 75 % des journées (ou celui du paramètre `event`) est mis en avant avec un lien vers `/events/{id}` ; les autres sont listés discrètement.

## 7. Grille de parité legacy ↔ app3 (recette)

À dérouler en préprod sur **une compétition CHPT, une CP et une MULTI** de la saison en cours :

| Onglet | Vérification |
|---|---|
| Matchs | même nombre de matchs, mêmes scores et statuts que `kpmatchs.php` (filtres « Tous », une journée, prochains matchs) |
| Terrains | même répartition que `kpterrains.php` pour un jour donné |
| Infos | mêmes journées, officiels et équipes que `kpdetails.php` |
| Déroulement (horizontal / vertical) | mêmes poules, équipes et matchs que `kpchart.php` / `kpphases.php` |
| Classement | mêmes rangs, points et colonnes que `kpclassement.php` |
| Stats | mêmes 20 premiers buteurs que `kpstats.php` |

## 8. Correspondance des anciennes URL (pour la phase 5)

| Ancienne URL | Nouvelle URL |
|---|---|
| `kpmatchs.php?Saison=S&Compet=C[&J=id][&filtreJour=d][&next=next]` | `/competitions/S/C/games[?gameday=id][&day=d][&upcoming=1]` |
| `kpterrains.php?Saison=S&Compet=C[&filtreJour=d]` | `/competitions/S/C/pitches[?day=d]` |
| `kpdetails.php?Saison=S&Compet=C` | `/competitions/S/C/info` |
| `kpchart.php?…` / `kpphases.php?…` | `…/progress` / `…/progress?view=vertical` |
| `kpclassement.php?…` / `kpstats.php?…` | `…/ranking` / `…/stats` |
| `…&event=E` | `…?event=E` |
| `…&Compet=*&Group=G` (matchs, terrains) | `/groups/S/G/games` / `/groups/S/G/pitches` |
| `…&lang=en` | préfixe `/en` |

## 9. Hors périmètre / questions ouvertes

- Fiche équipe et historique : phase 3.
- Abonnement ICS de la compétition : phase 3 (lien ajouté dans l'onglet Infos à ce moment-là).
