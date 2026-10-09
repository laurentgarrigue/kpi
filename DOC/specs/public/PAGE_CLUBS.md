# Clubs : liste, carte et fiche club

**Phase** : 3 — **Statut** : ✅ Validée (09/10/2026) — 🛠 En cours — **Routes** : `/clubs[?q=][&view=map]`, `/clubs/{code}` (+ `/en`)
— **Remplace** : `kpclubs.php`, `kplogos.php`, `json-clubs.php`, `searchClubs.php` — **Entrée de menu** : `clubs`

## 1. Objectif

Trouver un club (par nom, numéro ou sur une carte), ses coordonnées et ses équipes.

## 2. Contenu et comportement

### 2.1 `/clubs`
- `<h1>` « Clubs » ; recherche (formulaire GET `q` : nom ou code, filtrage côté serveur).
- **Liste** (par défaut) : grille de cartes logo + nom + département, triées par nom ; remplace aussi la page des
  logos (`kplogos.php`).
- **Carte** (`view=map`, bascule « Liste / Carte ») : marqueurs des clubs ayant une position ; clic → bulle avec
  nom et lien vers la fiche. La carte n'est **chargée qu'à la demande** (Q-P3-5) : la vue liste n'appelle aucun
  service tiers.

### 2.2 `/clubs/{code}`
- Nom, logo, comité départemental et régional, site web (nouvel onglet), e-mail du club (Q-P3-2), adresse postale.
- Petite carte de situation (même règle de chargement) si la position est connue.
- Équipes du club : liens vers `/teams/{number}`.
- Club inconnu → 404.

## 3. Données et cache

`GET /clubs[?q=]`, `GET /club/{code}` (API_PUBLIC_TRANSVERSE.md § 3.5). Cache `maxAge: 3600, swr: true`
(données quasi statiques). Pas de géocodage d'adresse (Nominatim n'est plus appelé) : seules les positions
enregistrées sont affichées.

## 4. SEO et accessibilité

Titre « {club} — kayak-polo.info » ; données structurées `SportsOrganization` (nom, adresse, site). La carte
n'est jamais le seul accès à l'information : la liste reste disponible.

## 5. Critères d'acceptation

- **CLB-01** — `/clubs` liste les clubs avec logo et département ; la recherche fonctionne sans JavaScript.
- **CLB-02** — La vue carte affiche un marqueur par club positionné, avec lien vers sa fiche ; aucune requête vers
  un service de cartographie tant que la carte n'est pas demandée.
- **CLB-03** — La fiche affiche comités, site, e-mail, adresse, position et équipes (liens vers les fiches
  équipe) ; club inconnu → 404.
- **CLB-04** — `kplogos.php` n'a plus d'équivalent séparé : la liste des clubs en tient lieu.
- **CLB-05** — L'entrée de menu « Clubs » devient interne.

## 6. Décisions (09/10/2026)

- **Q-P3-5 — Fond de carte.** Les tuiles OpenStreetMap sont un service tiers (adresse IP du visiteur transmise).
  ✅ Retenu : Leaflet (auto-hébergé, déjà dans le legacy) avec tuiles OSM **chargées seulement après un clic**
  sur « Afficher la carte », et mention de la source.

## 7. Correspondance des anciennes URL (phase 5)

`kpclubs.php?clubId=C` → `/clubs/C` ; `kpclubs.php` et `kplogos.php` → `/clubs`.
