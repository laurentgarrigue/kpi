# Pages éditoriales, menu éditorial et accueil éditorial

**Phase** : 4a — **Statut** : 📝 Brouillon (10/10/2026) — **Routes** : `/{slug}`, `/{parent}/{slug}` (+ `/en/…`)
— **Remplace** : pages WordPress, widgets d'accueil (Feature a Page Widget, Classic Widgets)
**Données** : [FEATURE_CMS.md](FEATURE_CMS.md) § 5.1 — **Fait évoluer** : [PAGE_HOME.md](PAGE_HOME.md),
[SITE_NAVIGATION.md](SITE_NAVIGATION.md)

## 1. Objectif

Publier les contenus permanents (présentation du kayak-polo, règlements, arbitrage, contacts, documents) dans
le gabarit du site, les rendre accessibles par un menu administrable, et rendre l'accueil éditorial.

## 2. Pages éditoriales

1. Route attrape-tout `/[...slug]` résolue **après** toutes les routes applicatives : `GET /pages/{slug}` (dernier
   segment, la hiérarchie parent / enfant doit correspondre au chemin, sinon 301 vers le bon chemin).
2. Gabarit : fil d'Ariane (pages parentes), `<h1>`, corps (mêmes styles que les articles, PAGE_NEWS.md § 2.2.3),
   **sommaire** automatique si la page a au moins 3 titres `h2` (ancres), liste des sous-pages s'il y en a.
3. Documents PDF de la médiathèque : lien avec type et taille (« Règlement 2026 (PDF, 1,2 Mo) »).
4. Inconnue ou non publiée → 404 du site ; slug réservé : jamais servi par cette route (FEATURE_CMS.md § 6).

## 3. Menu éditorial

- `MAIN_MENU` reste la source des entrées « données » ; une entrée **« Le kayak-polo »** (`id: 'editorial'`) est
  insérée entre « Équipes et clubs » et « En direct » ; ses sous-entrées viennent de `GET /menu` (au rendu
  serveur, mises en cache avec la page).
- Les règles de SITE_NAVIGATION.md s'appliquent (sous-menu au clic, Échap, mobile, entrée active).
- Menu vide ou api2 indisponible → l'entrée « Le kayak-polo » est masquée (NAV-05), le reste du menu est intact.

## 4. Accueil éditorial (évolution de PAGE_HOME.md)

Ordre : **bandeau d'alerte** (si actif) · **à la une** (1 à 3 articles mis en avant, grande carte) · **dernières
actualités** (3 cartes + lien « Toutes les actualités ») · prochains événements · événements récents (existants) ·
**partenaires** (logos avec liens, nouvel onglet). Chaque bloc est activable, ordonnable et masqué s'il est vide.

- Bandeau d'alerte : texte FR / EN, niveau (`info` bleu clair, `important` rouge), dates d'affichage ; rôle
  `status` (info) ou `alert` (important), non masquable en phase 4a.
- Les blocs viennent de `GET /home` ; si api2 échoue, l'accueil garde les blocs « événements » (dégradation
  identique à HOME-05).

## 5. Données et cache

`GET /pages/{slug}`, `GET /menu`, `GET /home`. `routeRules` : pages `cache: { maxAge: 300, swr: true }`, accueil
inchangé (300 s) ; tous purgés à la publication (FEATURE_CMS.md § 5.4). `sitemap.xml` d'app3 agrège
`GET /sitemap/content.xml`.

## 6. SEO et accessibilité

Titre et description SEO de la page (sinon titre / premier paragraphe) ; données structurées `WebPage`, fil
d'Ariane `BreadcrumbList` ; sommaire dans un `<nav aria-label="Sommaire">`.

## 7. Critères d'acceptation

- **CNT-01** — `/{slug}` affiche une page publiée dans le gabarit ; inconnue ou brouillon → 404 ; une route
  applicative (`/calendar`…) n'est jamais masquée par une page.
- **CNT-02** — Une page enfant n'est servie que sous le chemin de son parent ; un autre chemin redirige (301).
- **CNT-03** — Le sommaire apparaît à partir de 3 titres `h2`, avec des ancres fonctionnelles.
- **CNT-04** — L'entrée « Le kayak-polo » liste les entrées de `GET /menu` ; menu vide ou erreur → entrée masquée.
- **CNT-05** — L'accueil affiche les blocs actifs dans l'ordre configuré ; un bloc vide est masqué ; une erreur
  d'api2 laisse les blocs « événements ».
- **CNT-06** — Le bandeau d'alerte n'apparaît qu'entre ses dates, avec le rôle ARIA de son niveau.
- **CNT-07** — `sitemap.xml` contient les pages et articles publiés, pas les brouillons.

## 8. Hors périmètre

Constructeur de mise en page par blocs, pages protégées par mot de passe, gestion des documents hors
médiathèque (la page Documents d'app4 reste en place).
