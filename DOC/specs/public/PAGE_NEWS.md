# Actualités : liste et article

**Phase** : 4a — **Statut** : ✅ Validée (11/10/2026) — **Routes** : `/news[?page=][&category=]`, `/news/{slug}`
(+ `/en/…`) — **Remplace** : articles WordPress, `?feed=rss2` — **Entrée de menu** : `news` (« Actualités »)
**Données** : [FEATURE_CMS.md](FEATURE_CMS.md) § 5.1

## 1. Objectif

Publier les actualités du kayak-polo (résultats marquants, annonces, sélections, vie fédérale) et les relier
aux pages de résultats, pour que les visiteurs passent naturellement de l'actualité aux compétitions.

## 2. Contenu et comportement

### 2.1 `/news`
1. `<h1>` « Actualités » ; **filtre par catégorie** (FEATURE_CMS.md § 3.1) : puces « Toutes » + les 4 catégories,
   liens GET `?category=`, puce courante marquée (`aria-current`).
2. **Liste éditoriale (maquettes du 22/10/2026)** : en page 1, la plus récente est **à la une** (image 3:2 à gauche,
   variante 1280 px chargée tout de suite, titre en Agency FB, chapô, date et catégories) ; les autres sont des
   **lignes** (vignette 3:2 de 200 px, carrée de 96 px sur mobile, `loading="lazy"`, décorative ; titre en Agency FB
   en lien ; chapô — masqué sur mobile ou s'il répète le titre ; date localisée et catégories). Sans image : un
   carré « KPI ».
3. **Pagination** par liens (`?page=`, 12 par page) « Plus récents » / « Plus anciens », sans JavaScript.
4. Aucun article → message « Aucune actualité pour le moment. ». Page hors bornes → 404.
5. Lien « S'abonner (RSS) » vers `/rss.xml` (+ `<link rel="alternate" type="application/rss+xml">`).

### 2.2 `/news/{slug}`
*Maquettes du 22/10/2026* : colonne de lecture de **720 px**, image de une plus large que le texte (débord de 60 px de
chaque côté sur grand écran), chapô en gras, partage sous le texte, « Actualité précédente » / « Actualité
suivante » en deux cartes.
1. Fil d'Ariane « Accueil › Actualités › {titre} ».
2. `<h1>` titre, date de publication (et « mis à jour le … » si modifié plus d'un jour après), **signature**, catégories (liens
   vers `/news?category=`).
3. Image de une (variantes `srcset` 768 / 1280 / 1920), chapô en exergue, corps (HTML assaini d'api2, styles
   typographiques du site : titres Agency FB, liens soulignés, tableaux avec en-têtes figés, images responsives,
   vidéos chargées au clic).
4. **Galeries** du corps : vignettes, visionneuse au clic (clavier : flèches, Échap ; focus piégé), sans
   bibliothèque tierce si possible.
5. **Liens vers les résultats** : encart « En lien avec cet article » → compétitions (`/competitions/{s}/{c}`),
   événements (`/events/{id}`), clubs (`/clubs/{code}`). Réciproquement, les pages de ces compétitions /
   événements / clubs affichent un bloc « Actualités » (3 derniers articles liés, NEWS-09).
6. **Partage** : bouton « Partager » → Web Share API si disponible (mobile), sinon liens Facebook, X, WhatsApp,
   e-mail et « Copier le lien » (aucun script tiers chargé, simples URL de partage).
7. Navigation « Article précédent / suivant ».
8. Brouillon / inconnu → 404 ; ancien permalien WordPress → 301 (middleware, FEATURE_CMS.md § 8).

## 3. Données et cache

`GET /news`, `GET /news/{slug}`, `GET /rss.xml` (api2). `routeRules` : `cache: { maxAge: 60, swr: true }` sur
`/news` et `/news/**` (+ `_payload.json`, `cachedPage`), purgé à la publication (FEATURE_CMS.md § 5.4).

## 4. SEO et accessibilité

- Titre « {titre} — kayak-polo.info » (ou titre SEO), description = description SEO sinon chapô.
- Open Graph / Twitter Card (image de une 1200 px, titre, description, `article:published_time`).
- Données structurées `NewsArticle` (titre, image, dates, auteur = organisation FFCK / CNA, signature en `author.name`).
- Liste : `<article>` par carte, titres `h2` ; dates en `<time datetime>`.
- `hreflang` FR / EN seulement si la version anglaise a un titre propre.

## 5. Critères d'acceptation

- **NEWS-01** — `/news` liste les articles publiés, 12 par page, du plus récent au plus ancien, pagination par liens
  utilisable sans JavaScript ; page hors bornes → 404.
- **NEWS-02** — Le filtre `?category=` ne garde que les articles de la catégorie ; catégorie inconnue → liste
  complète (filtre ignoré).
- **NEWS-03** — `/news/{slug}` affiche titre, date, signature, catégories, image, chapô et corps ; brouillon ou inconnu → 404.
- **NEWS-04** — L'encart « En lien avec cet article » mène aux pages internes des compétitions, événements et clubs.
- **NEWS-05** — Le partage utilise Web Share API si disponible, sinon des liens directs ; aucun script tiers.
- **NEWS-06** — Les balises Open Graph et `NewsArticle` sont présentes et cohérentes avec l'article.
- **NEWS-07** — La galerie s'ouvre au clic, se parcourt au clavier et se ferme avec Échap.
- **NEWS-08** — L'entrée de menu « Actualités » devient interne (`ready: true`) ; `/rss.xml` est annoncé.
- **NEWS-10** — `/news` page 1 : le plus récent à la une (grande image, titre, chapô, date et catégories), les autres en lignes éditoriales (vignette, titre, chapô, date, catégories) ; pages suivantes : lignes seulement.
- **NEWS-11** — Article : colonne de lecture de 720 px, image plus large que le texte, chapô en gras, ligne « date, par {signature} » et catégories en liens, partage, précédente / suivante en deux cartes.
- **NEWS-09** — Les pages compétition, événement et club affichent les 3 derniers articles liés, s'il y en a.

## 6. Hors périmètre

Commentaires, réactions, newsletter, archives par mois (la pagination et les catégories suffisent).
