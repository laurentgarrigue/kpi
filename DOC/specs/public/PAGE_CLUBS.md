# Clubs : liste, carte et fiche club

**Phase** : 3 — **Statut** : ✅ Validée (09/10/2026) — 🛠 Implémentée (à livrer) — **Routes** : `/clubs[?q=][&club=]` (+ `/en`) ; `/clubs/{code}` redirige (301) vers `/clubs?club={code}`
— **Remplace** : `kpclubs.php`, `kplogos.php`, `json-clubs.php`, `searchClubs.php` — **Entrée de menu** : `clubs`

## 1. Objectif

Trouver un club (par nom, numéro ou sur une carte), ses coordonnées et ses équipes.

## 2. Contenu et comportement

### 2.1 `/clubs` — Clubs et équipes (maquettes du 22/10/2026)

Entrée de menu « Clubs et équipes » ; remplace aussi `/teams` (301, PAGE_TEAM.md § 2.1). Plus de liste complète des
clubs : la carte et la recherche en tiennent lieu.
- **Panneau** (à gauche, au-dessus de la carte sur mobile) : `<h1>` « Clubs et équipes » ; **recherche** (formulaire
  GET `q`, 2 caractères minimum) des clubs par nom ou code et des équipes par nom (filtres d'api2) ; résultats
  groupés « Clubs » / « Équipes » (logo, nom, département ou club) : un club ouvre sa fiche à côté de la carte
  (`?club={code}`, adresse partageable), une équipe mène à sa page.
- **Fiche du club** (`?club={code}`) dans le panneau — **seule présentation d'un club** (adresse partagée par la
  recherche globale, la page équipe et les actualités) : logo (ou code), nom, ville ou département ; boutons
  « Site web » et « Itinéraire » (OpenStreetMap) avec la flèche de lien sortant et « (nouvel onglet) » ; adresse, e-mail (texte, Q-P3-2), département, région, code ; équipes du club, sans les
  équipes d'attente créées par les tournois (« (1er Poule A) », « 2ème poule haute »…). Sans club choisi : nombre de
  clubs sur la carte et mode d'emploi. Sous les équipes : actualités liées au club (NEWS-09).
- **Carte** (à droite) : un point par club positionné ; toucher un point ouvre sa fiche ; le club choisi est mis en
  évidence. Décision Q-P3-5 maintenue : les tuiles ne sont chargées **qu'après le clic** « Afficher la carte » (la
  maquette les charge directement : à valider au regard du consentement, cf. maquettes/README.md). Une fois la carte
  affichée, « Clubs près de moi » la centre sur la position du visiteur (géolocalisation du navigateur).
  Le choix est **retenu** sur l'appareil (`localStorage`, aucun cookie) : aux visites suivantes la carte s'affiche
  sans nouveau clic. « Masquer la carte » la retire et oublie ce choix (retrait aussi simple que l'accord).
- Les noms de clubs et de comités stockés en capitales sont affichés en casse de titre (« Canoe Club d'Avranches »).

### 2.2 `/clubs/{code}` — redirection

L'ancienne fiche séparée n'existe plus : `/clubs/{code}` redirige (301) vers `/clubs?club={code}`, qui montre le club
sur la carte avec sa fiche. Club inconnu → la page « Clubs et équipes » avec son mode d'emploi.

## 3. Données et cache

`GET /clubs[?q=]`, `GET /club/{code}` (API_PUBLIC_TRANSVERSE.md § 3.5). Cache `maxAge: 3600, swr: true`
(données quasi statiques). Pas de géocodage d'adresse (Nominatim n'est plus appelé) : seules les positions
enregistrées sont affichées.

## 4. SEO et accessibilité

Titre « {club} — kayak-polo.info » quand un club est choisi ; données structurées `SportsOrganization` (nom, adresse, site) de ce club. La carte
n'est jamais le seul accès à l'information : la liste reste disponible.

## 5. Critères d'acceptation

- **CLB-01** — `/clubs` : recherche de clubs et d'équipes (GET `q`, sans JavaScript, 2 caractères minimum), résultats groupés (club → `?club={code}`, équipe → `/teams/{number}`) ; `/teams` redirige vers `/clubs`.
- **CLB-02** — La carte affiche un marqueur par club positionné, qui ouvre sa fiche (`?club={code}`) ; aucune requête vers
  un service de cartographie tant que la carte n'est pas demandée.
- **CLB-09** — Après le clic « Afficher la carte », le choix est mémorisé (`localStorage`, clé `kpi.clubs.map`) et
  la carte s'affiche d'elle-même aux visites suivantes ; « Masquer la carte » la retire et efface ce choix. Sans
  choix mémorisé (ou stockage indisponible), CLB-02 s'applique.
- **CLB-03** — La fiche affiche comités, site, e-mail, adresse, position et équipes (liens vers les fiches
  équipe) ; club inconnu → 404.
- **CLB-04** — `kplogos.php` n'a plus d'équivalent séparé : la carte et la recherche en tiennent lieu.
- **CLB-06** — `?club={code}` affiche la fiche du club dans le panneau : identité, site web et itinéraire (lien sortant signalé), coordonnées, comités, équipes sans les équipes d'attente ; club inconnu → mode d'emploi.
- **CLB-08** — `/clubs/{code}` redirige (301) vers `/clubs?club={code}` ; aucun lien du site ne mène plus à `/clubs/{code}` ; la fiche affiche les actualités liées au club et le titre de la page est le nom du club.
- **CLB-07** — Noms de clubs et de comités en casse de titre (« CANOE CLUB D'AVRANCHES » → « Canoe Club d'Avranches », « COMITE DEPARTEMENTAL CK DE LA MANCHE » → « Manche »), fonction pure testée.
- **CLB-05** — L'entrée de menu « Clubs » devient interne.

## 6. Décisions (09/10/2026)

- **Q-P3-5 — Fond de carte.** Les tuiles OpenStreetMap sont un service tiers (adresse IP du visiteur transmise).
  ✅ Retenu : Leaflet (auto-hébergé, déjà dans le legacy) avec tuiles OSM **chargées seulement après un clic**
  sur « Afficher la carte », et mention de la source.

## 7. Correspondance des anciennes URL (phase 5)

`kpclubs.php?clubId=C` → `/clubs?club=C` ; `kpclubs.php` et `kplogos.php` → `/clubs`.
