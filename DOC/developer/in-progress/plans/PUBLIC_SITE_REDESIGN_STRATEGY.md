# Stratégie de refonte de la partie publique (kayak-polo.info)

**Date** : 27 septembre 2026
**Statut** : 📋 Proposition — à valider (voir [§ 11 Questions ouvertes](#11-questions-ouvertes))
**Périmètre** : page d'accueil WordPress, pages publiques `kp*.php`, affichages `frame_*.php`, exports publics (PDF, ICS), articulation avec app2 / app4 / api2

---

## 0. Résumé en une page

| Question | Recommandation |
|---|---|
| **Faire évoluer app2 ou une brique distincte ?** | **Brique distincte** (`sources/app5/`, « site public »), mais **pas une réécriture parallèle** : on extrait un **Nuxt Layer partagé** (`sources/kpi-layer/`) consommé par app2, app4 et le nouveau site (design system, client api2, types, composants matchs / classements / tableaux / carte des clubs, i18n commun). app2 reste l'appli compagnon « suivre un événement / un groupe » (PWA hors-ligne, QR codes imprimés). |
| **Mode de rendu** | **Nuxt 4 en rendu hybride SSR** (serveur Nitro Node dans un nouveau conteneur), avec `routeRules` : pré-rendu des pages éditoriales, cache SWR court sur les résultats, rafraîchissement client (+ Mercure) pour le live. C'est la différence majeure avec app2/app4 (SPA `ssr: false`) et elle est **indispensable au référencement** d'un site public. |
| **CMS** | **Remplacer WordPress par un module éditorial natif** (actualités, pages, menu, médias) : édition dans **app4**, stockage/lecture dans **api2**, rendu dans le site. Une seule stack, une seule authentification (JWT + profils existants), une seule base, plus de maintenance WordPress. WordPress en *headless* (API REST) n'est retenu que comme **pont de transition** si la migration du contenu doit attendre. |
| **Couverture fonctionnelle** | Toutes les pages publiques `kp*.php` sont couvertes, **à condition d'ajouter ~10 endpoints publics dans api2** (calendrier, saisons/compétitions, historique, équipes, clubs, buteurs…). **Ne sont pas couverts par ce chantier** et doivent être planifiés à part ou explicitement conservés : affichages écrans `frame_*.php` (et leurs intégrations en iframe chez des tiers via `?Css=`), incrustations vidéo `live/`, PDF publics `Pdf*.php` (conservés en l'état et liés depuis le nouveau site). |
| **Migration** | *Strangler fig* : nouveau site d'abord sur un sous-domaine de prévisualisation, puis bascule du domaine principal avec **redirections 301** depuis les anciennes URL `kp*.php?...` (liens partagés, Google, QR codes). |

---

## 1. État des lieux

### 1.1 Ce qui compose la « partie publique » aujourd'hui

| Brique | Techno | Rôle | Emplacement |
|---|---|---|---|
| **Accueil / actualités / pages éditoriales** | WordPress (thème « Material »), base `dbwp` | Page d'accueil, articles, pages statiques, flux RSS, médias | `sources/index.php` → `wordpress/wp-blog-header.php` ; contenu dans `docker/wordpress/` (hors Git) |
| **Pages de consultation** | PHP + Smarty + Bootstrap 5 + jQuery/DataTables | Calendrier, matchs, classements, historique, équipes, clubs, stats… | `sources/kp*.php`, `smarty/templates/kp*.tpl` |
| **Affichages écrans / iframes** | PHP + Smarty | Écrans de gymnase, rotation (`voie`, `intervalle`), **intégration chez des tiers** avec thème (`?Css=simply`, `ckca`, `saintomer2022`…) | `sources/frame_*.php` |
| **Exports publics** | mPDF, ICS | Classements, listes de matchs, feuilles de marque ; export calendrier d'une journée | `sources/Pdf*.php`, `upload_ics.php` |
| **Flux JSON legacy** | PHP | Alimentation FullCalendar / carte Leaflet | `json-events.php`, `json-clubs.php`, `searchClubs.php` |
| **app2** | Nuxt 4 SPA/PWA (`ssr: false`), `app.kayak-polo.info` | Suivi d'un **événement** ou d'un **groupe de compétitions** : matchs, classements, graphes, équipe, feuille de match, contrôle (scrutineering) | `sources/app2/` |
| **app4** | Nuxt 4 SPA (`ssr: false`), `/admin2` | Administration complète | `sources/app4/` |
| **api2** | Symfony 7.4 + API Platform 4.3 sur FrankenPHP, `/api2` | API publique (événements, groupes) + staff + admin + WSM | `sources/api2/` |

### 1.2 Comment WordPress et les pages KPI « se ressemblent » aujourd'hui

La cohérence visuelle est obtenue **par copie**, pas par partage :

- `kppage.tpl` charge `css/wordpress_material_stylesheets_styles.css` et `css/wordpress_material_style.css` : des copies des feuilles du thème WordPress.
- Le menu public est **défini en dur** dans `commun/MyPage.php` (`Accueil`, `Calendrier`, `Matchs`, `Classements`, `Historique`, `Equipes`, `Clubs`, `Administration`) et **redéfini** côté WordPress.
- `kpfooter.tpl`, les balises Open Graph et le flux RSS pointent vers `wordpress/wp-content/...` et `?feed=rss2`.

Conséquence : toute évolution de design ou de menu doit être faite **deux fois**, dans deux technologies. C'est le principal argument pour unifier.

### 1.3 Ce qui existe déjà et qu'on peut réutiliser

- **api2 public** : `/events/{mode}`, `/event/{id}`, `/event/{id}/games`, `/event/{id}/charts`, `/groups/{season}`, `/group/{season}/{code}/games|charts|teams|team/{id}/stats`, `/event/{id}/team/{id}/stats`, `/game-sheet/{id}`.
- **Logique métier déjà portée dans api2 pour l'admin** : classements (`AdminRankingsController`), statistiques (`AdminStatsController`), clubs (`AdminClubsController`), compétitions, journées… → à **factoriser en services** pour les exposer en lecture publique.
- **Composants app2** : `GameList`, `GameSheet`, `Charts`, `ChartGroup`, `ChartChptRanking`, `ChartCpRanking`, `TeamName`, `EventQrCode`, `LanguageSwitcher`.
- **Composants app4** : `ClubMap.client.vue` (carte des clubs), `Pagination`, `Card`, `PageHeader`, sélecteurs de compétition.
- **Mercure** intégré à FrankenPHP pour le temps réel.
- **Matomo** déjà câblé dans app2/app4.

> ⚠️ **Limite de cette analyse** : le site https://www.kayak-polo.info n'était pas accessible depuis l'environnement d'analyse (sortie réseau bloquée). L'inventaire du **contenu WordPress** (nombre d'articles, pages, extensions, formulaires…) reste donc à faire — voir questions Q1 à Q3.

---

## 2. Question 1 — Faire évoluer app2 ou créer une brique distincte ?

### 2.1 Ce qui distingue les deux usages

| Critère | app2 (suivi événement) | Site public |
|---|---|---|
| Point d'entrée | QR code, lien direct vers **un** événement | Google, réseaux sociaux, navigation libre |
| Axe de navigation | Un événement / un groupe, la saison en cours | Toutes saisons, toutes compétitions, clubs, historique, actualités |
| Référencement | Sans importance | **Critique** (résultats, clubs, palmarès) |
| Rendu | SPA statique, `ssr: false` | Doit produire du HTML indexable (SSR/SSG) |
| Hors-ligne / PWA | **Essentiel** (bord de bassin) | Accessoire |
| Support principal | Mobile, installé | Desktop + mobile, navigateur |
| Contenu éditorial | Aucun | Actualités, pages, médias |
| Authentification | Oui (scrutineering) | Non (hors lien vers l'admin) |

### 2.2 Option A — Étendre app2

- ➕ Une application de moins à déployer.
- ➖ Il faudrait passer app2 en SSR : cela remet en cause sa stratégie PWA/offline-first (Dexie, service worker `navigateFallback`, `globPatterns: []`) et son déploiement statique Nginx.
- ➖ Le site public ajouterait CMS, historique multi-saisons, clubs… à une application aujourd'hui volontairement ciblée, et **tout le public paierait le poids du scrutineering** (et inversement).
- ➖ `app.kayak-polo.info` est imprimé sur des QR codes et installé sur des téléphones : un changement de nature de l'app est risqué.

### 2.3 Option B — Brique distincte + socle partagé ✅ recommandé

- **`sources/app5/`** (nom de travail « site ») : Nuxt 4, SSR hybride, servi sur `www.kayak-polo.info`.
- **`sources/kpi-layer/`** : [Nuxt Layer](https://nuxt.com/docs/getting-started/layers) consommé via `extends: ['../kpi-layer']` par app2, app4 et app5 :
  - thème Tailwind / `app.config.ts` Nuxt UI (couleurs, typographie, dark mode déjà spécifié dans `DOC/specs/DARK_MODE.md`) ;
  - client api2 typé (`useApi2`) + types TypeScript des réponses publiques ;
  - composants d'affichage **en lecture seule** : liste de matchs, feuille de match, classement, tableau de phases finales, carte des clubs, nom/logo d'équipe, sélecteur de langue ;
  - clés i18n communes (noms de phases, statuts de match, libellés de classement).
- **Extraction progressive** : on ne déplace un composant d'app2/app4 vers le layer qu'au moment où app5 en a besoin, pour ne pas bloquer les autres chantiers.
- **Passerelles** : chaque page compétition/événement du site propose « 📱 Suivre dans l'app » (vers app2) ; app2 renvoie vers le site pour l'historique, les clubs et les actualités.

> **Porte laissée ouverte** : si, à terme, app5 couvre aussi bien le suivi d'événement (grâce au layer commun), app2 pourra se réduire à la coque PWA/scrutineering. Cette décision n'a pas à être prise maintenant.

**Contrainte technique à traiter** : les commandes `make app2_generate_*` montent uniquement `sources/app2` dans le conteneur Node temporaire. Avec un layer hors du dossier de l'app, il faudra monter `sources/` (ou `sources/kpi-layer` en plus) — idem pour la CI.

---

## 3. Mode de rendu et hébergement

### 3.1 Options

| Mode | SEO | Fraîcheur des résultats | Infra | Verdict |
|---|---|---|---|---|
| SPA (`ssr: false`, comme app2/app4) | ❌ faible | ✅ | Nginx statique (existant) | Non adapté à un site public |
| SSG complet (`nuxt generate`) | ✅ | ❌ il faut regénérer à chaque score ; volume = saisons × compétitions × équipes × clubs | Nginx statique | Non viable seul |
| **SSR hybride (Nitro Node)** | ✅ | ✅ via cache SWR court + refresh client | **Nouveau conteneur Node** | ✅ **Recommandé** |

### 3.2 Principe proposé

```ts
// app5/nuxt.config.ts (esquisse)
routeRules: {
  '/':                          { swr: 300 },   // accueil : actus + prochains événements
  '/actualites/**':             { swr: 600 },
  '/pages/**':                  { swr: 3600 },
  '/clubs/**':                  { swr: 3600 },
  '/historique/**':             { swr: 86400 }, // saisons closes : quasi immuable
  '/competitions/**':           { swr: 60 },    // résultats : 1 min, + refresh client
  '/calendrier/**':             { swr: 600 },
}
```

- Les pages « live » (compétition en cours) se rafraîchissent côté client (polling comme app2, puis **abonnement Mercure** `/api2/.well-known/mercure` quand les topics publics existeront).
- Nouveau service Docker `${APPLICATION_NAME}_site` : `node:22-alpine` exécutant `.output/server/index.mjs`, sur `network_${APPLICATION_NAME}` pour appeler api2 **en interne** (sans repasser par Traefik) côté serveur, et via l'URL publique côté navigateur.
- SEO : `useSeoMeta`, `sitemap.xml` dynamique (saisons/compétitions/clubs), données structurées schema.org `SportsEvent` / `SportsTeam`, balises `hreflang` FR/EN, images Open Graph par compétition (logo/bandeau déjà gérés dans app4).

> Si l'ajout d'un conteneur Node en production n'est **pas** souhaitable (question Q5), le repli est : pré-rendu des pages éditoriales et des saisons closes + SPA pour le reste. Le SEO des résultats en cours serait alors dégradé.

---

## 4. Question 2 — Comment intégrer le CMS ?

### 4.1 Options comparées

| Option | Principe | ➕ | ➖ |
|---|---|---|---|
| **A. Garder WordPress en frontal** | Refaire le thème WP pour imiter le nouveau design | Zéro migration de contenu | Deux stacks, design dupliqué (la situation actuelle), mises à jour de sécurité WP, `dbwp` |
| **B. WordPress *headless*** | Nuxt lit `/wordpress/wp-json/wp/v2/posts|pages|menus` | Les rédacteurs gardent leur outil | WP + `dbwp` + extensions à maintenir ; rendu des blocs Gutenberg/shortcodes à réinterpréter ; deuxième système de comptes |
| **C. Module éditorial natif KPI** ✅ | Tables `kp_article`, `kp_page`, `kp_menu`, `kp_media` ; CRUD dans **app4** ; lecture publique dans **api2** ; rendu dans app5 | Une stack, une base, une authentification (JWT + profils existants), sauvegardes communes, design unique, contenu éditorial **relié aux données sportives** (ex. un article lié à une compétition s'affiche sur sa page) | ~3–4 semaines de développement ; migration des contenus WP ; pas d'écosystème d'extensions |
| **D. CMS headless tiers** (Directus, Strapi, Payload…) | Nouveau service dédié | Interface d'édition riche prête à l'emploi | Un service, une base et un silo de comptes de plus ; sur-dimensionné pour des actualités et quelques pages |
| **E. Nuxt Content (Markdown dans Git)** | Pages en fichiers `.md` | Gratuit, versionné | Réservé aux développeurs : ne convient pas à des rédacteurs bénévoles |

### 4.2 Recommandation : option C, avec B en pont si nécessaire

Le besoin éditorial d'un site fédéral de discipline est a priori modeste (actualités, quelques pages : règlements, contacts, présentation, partenaires). Le coût de maintenance de WordPress (mises à jour, extensions, second serveur de base, surface d'attaque) dépasse alors celui d'un petit module maison, **d'autant qu'app4 fournit déjà l'authentification, les profils, l'upload d'images** (cf. `DOC/user/IMAGE_UPLOAD_MANAGEMENT.md`) **et les patterns CRUD**.

Contenu du module natif (MVP) :

| Élément | Détail |
|---|---|
| **Articles** | titre FR/EN, chapô, corps riche (éditeur TipTap/ProseMirror, sortie HTML assainie côté api2), image de une, date de publication programmable, statut brouillon/publié, **liens optionnels vers compétition / événement / club** |
| **Pages** | slug, titre, corps, position dans le menu |
| **Menu** | arborescence éditable (les entrées « données » — Calendrier, Compétitions… — sont fixes, les entrées éditoriales sont libres) |
| **Médias** | upload images/PDF réutilisant le mécanisme existant, redimensionnement |
| **Blocs d'accueil** | bandeau/alerte, « à la une », partenaires |
| **Droits** | nouveau droit « rédacteur » dans le modèle de profils (`DOC/specs/DROITS_PAR_PROFIL.md`) |
| **Sortie** | flux RSS (remplace `?feed=rss2`), sitemap |

Migration : script ponctuel (commande Symfony `app:import-wordpress`) lisant la base `dbwp` ou `wp-json`, convertissant les articles/pages/médias, avec **redirections 301** des anciennes URL WordPress (`/?p=123`, permaliens).

**Pont de transition (option B)** : si le site doit ouvrir avant que le module soit prêt, la page d'accueil d'app5 peut lire temporairement les derniers articles via `wp-json` (WordPress restant en place, non exposé comme frontal). À n'utiliser que si le calendrier l'impose.

---

## 5. Architecture cible

```
                        Traefik (TLS)
                             │
  www.kayak-polo.info ───────┼──────────────────────────────────────────────┐
                             │                                              │
    /api2/*  ──► FrankenPHP api2 (Symfony, Mercure)                          │
    /admin2/*──► Nginx app4 (SPA admin + module éditorial)                  │
    /admin/*, /Pdf*.php, /frame_*.php, /live/*, /api/*, /img/*, ... ──► Apache legacy (liste explicite)
    /*  (tout le reste) ──► Node app5 (SSR Nuxt) ── appels internes ──► api2
                             │
  app.kayak-polo.info ───────┴──► Nginx app2 (PWA, inchangée)
```

- **Inversion du routeur par défaut** au moment de la bascule : aujourd'hui Apache est le *catch-all* (`!PathPrefix('/api2') && !PathPrefix('/admin2')`) ; demain c'est app5, et Apache ne reçoit plus qu'une **liste explicite** de préfixes legacy. Cette liste est la checklist de décommissionnement.
- **WordPress** : retiré (conteneur `dbwp` compris) après migration et période de sécurité (archive SQL + fichiers conservée).
- **Design** : un seul design system dans `kpi-layer`, appliqué aux trois apps Nuxt ; les pages legacy restantes (PDF, écrans) n'en ont pas besoin.

### 5.1 Plan d'URL (proposition)

| Nouvelle URL | Remplace |
|---|---|
| `/` | WordPress (accueil) |
| `/actualites`, `/actualites/{slug}` | Articles WordPress |
| `/{slug-page}` | Pages WordPress |
| `/calendrier` | `kpcalendrier.php` |
| `/competitions/{saison}` (filtres niveau / type) | `kpclassements.php` (sélecteur) |
| `/competitions/{saison}/{groupe}/{compet}/matchs` | `kpmatchs.php` |
| `…/terrains` | `kpterrains.php` |
| `…/infos` | `kpdetails.php` |
| `…/deroulement` | `kpchart.php` |
| `…/phases` | `kpphases.php` |
| `…/classement` | `kpclassement.php` |
| `…/stats` | `kpstats.php` |
| `/evenements/{id}/…` | `kp*.php?event=…` |
| `/historique/{groupe}` | `kphistorique.php` |
| `/equipes/{id}` | `kpequipes.php` |
| `/clubs`, `/clubs/{code}` | `kpclubs.php`, `kplogos.php` |
| `/en/...` | `?lang=en` |

Les anciennes URL (`kp*.php?Compet=N1&Saison=2026&Group=N&J=…`) sont redirigées en **301** par un middleware serveur Nitro (table de correspondance des paramètres), ce qui préserve les liens partagés, les favoris et le référencement.

---

## 6. Question 3 — Toutes les fonctionnalités publiques seront-elles couvertes ?

### 6.1 Matrice de couverture

Légende api2 : ✅ existe · 🟡 logique présente dans un contrôleur admin, à exposer en public · ❌ à créer

| Fonctionnalité | Legacy | Déjà dans app2 ? | api2 | Cible app5 |
|---|---|---|---|---|
| Accueil, actualités, pages | WordPress | — | ❌ (module éditorial) | ✅ |
| Calendrier (FullCalendar, couleurs par niveau) | `kpcalendrier.php` + `json-events.php` | — | ❌ `/calendar?start&end` | ✅ |
| Export ICS d'une journée | `upload_ics.php` | — | ❌ (ou lien legacy) | ✅ (+ abonnement ICS par compétition en bonus) |
| Liste des compétitions d'une saison / sélecteur | `kpclassements.php` | Partiel (`/groups/{season}`) | 🟡 | ✅ |
| Matchs d'une compétition / « prochains matchs » | `kpmatchs.php` | ✅ (groupe/événement) | ✅ groupe · ❌ compétition seule | ✅ |
| Matchs par terrain | `kpterrains.php` | — | ✅ (dérivable des matchs) | ✅ |
| Infos compétition / journées (lieu, dates, officiels) | `kpdetails.php` | — | 🟡 | ✅ |
| Déroulement / graphe | `kpchart.php` | ✅ `charts` | ✅ | ✅ |
| Phases / poules / tableaux | `kpphases.php` | ✅ | ✅ | ✅ |
| Classement (CHPT, CP, multi, par journée) | `kpclassement.php` | ✅ | ✅ / 🟡 (par journée, consolidation) | ✅ |
| Buteurs / stats compétition | `kpstats.php` | Partiel (stats équipe) | 🟡 `AdminStatsController` | ✅ |
| Historique / palmarès multi-saisons | `kphistorique.php` | — | ❌ | ✅ |
| Fiche équipe (palmarès, composition, cartons, buts) | `kpequipes.php` | Partiel (`/team`) | 🟡 | ✅ |
| Clubs : liste, recherche, carte, équipes | `kpclubs.php`, `json-clubs.php`, `searchClubs.php` | — | 🟡 `AdminClubsController` | ✅ (`ClubMap` app4) |
| Logos des clubs | `kplogos.php` | — | 🟡 | ✅ |
| Feuille de match publique | app2 `/game/[id]` | ✅ | ✅ `/game-sheet/{id}` | ✅ (lien ou composant partagé) |
| Bandeau / logo / lien web de compétition | `kpnavgroup.tpl` | — | 🟡 | ✅ |
| Partage (bouton, Open Graph) | `share_btn` | — | — | ✅ (Web Share API + OG) |
| QR codes vers app2 | `kpqr.php`, `frame_qr*.php` | ✅ `EventQrCode` | — | ✅ |
| FR / EN | `?lang=`, `MyLang.ini` | ✅ i18n | — | ✅ (préfixe `/en`) |
| Flux RSS | WordPress | — | ❌ | ✅ |
| PDF publics (classements, listes de matchs, feuilles) | `Pdf*.php` | — | Partiel (mPDF dans 2 contrôleurs admin) | 🔗 **liens vers le legacy conservé** (migration PDF = chantier distinct, cf. `DOCUMENTS_MIGRATION.md`) |
| Contrôle TV / scénarios | `kptv.php`, `kptvscenario.php` | — | ✅ `AdminTvController` | ➖ déjà dans app4 (`/tv`) — **pas public** |
| Affichages écrans (`frame_*.php`, rotation `voie`/`intervalle`) | `frame_*.php` | — | ✅ données | ⚠️ **hors périmètre initial** — voir 6.2 |
| Intégrations iframe chez des tiers (`?Css=…`) | `frame_*.php`, `kp*.php?Css=` | — | — | ⚠️ voir 6.2 |
| Incrustations vidéo (OBS…) | `live/*.php` | — | ✅ cache événements | ➖ chantier « Live Overlay » de la roadmap |

### 6.2 Ce qui n'est **pas** couvert automatiquement — décisions nécessaires

1. **Affichages écrans et iframes tiers** (`frame_*.php`, paramètre `Css`) : ils sont utilisés sur des écrans de gymnase et **intégrés dans des sites de clubs/organisateurs** avec des thèmes dédiés (`simply`, `ckca`, `saintomer2022`, `deqing2024`, `thury2014`…). Proposition : une section **`/embed/...`** dans app5 (layout sans menu, paramètre `?theme=`, rotation paramétrable), puis redirection des `frame_*.php`. En attendant : **conservés tels quels sur Apache**.
2. **PDF publics** : conservés en legacy et liés depuis le site (les rendre autonomes est déjà traité dans `LEGACY_PDF_STANDALONE_ACCESS.md`).
3. **Incrustations `live/`** : relèvent du chantier « Refonte du Live Overlay » ; aucune dépendance avec le site.
4. **Fonctionnalités WordPress inconnues** (formulaires de contact, newsletter, galeries, agenda, commentaires, pages protégées…) : à recenser (Q2) avant de garantir la couverture.

---

## 7. Travaux api2

### 7.1 Principes

- Endpoints **publics en lecture seule**, regroupés dans des contrôleurs `Public*Controller`, sans authentification.
- **Factoriser** la logique aujourd'hui dans les contrôleurs admin (classements, stats, clubs, compétitions) dans des services `src/Service/...` appelés par les deux côtés — pas de copier-coller.
- Ne renvoyer que ce qui est **publié** (`Publication = 'O'` sur compétition, journée, match), comme le fait déjà `/game-sheet`.
- En-têtes `Cache-Control` / `ETag` pour soulager FrankenPHP (le cache SWR de Nitro fait le reste).
- Données personnelles : même règles que les pages legacy (nom, prénom, numéro) — pas de date de naissance ni de licence ; vérifier les éventuels refus de diffusion.

### 7.2 Endpoints à créer (proposition)

| Endpoint | Usage |
|---|---|
| `GET /seasons` | Sélecteur de saison |
| `GET /season/{s}/competitions?level=&type=` | Liste/sélecteur des compétitions |
| `GET /season/{s}/competition/{code}` | En-tête, visuels, journées, officiels (`kpdetails`) |
| `GET /season/{s}/competition/{code}/games` | Matchs d'une compétition seule |
| `GET /season/{s}/competition/{code}/ranking?gameday=` | Classement, y compris par journée (CHPT) |
| `GET /season/{s}/competition/{code}/scorers` | Buteurs / cartons |
| `GET /calendar?start=&end=` | Remplace `json-events.php` |
| `GET /gameday/{id}.ics`, `GET /competition/{s}/{code}.ics` | Exports ICS |
| `GET /history/{groupCode}` | Palmarès multi-saisons |
| `GET /team/{numero}` | Palmarès, compositions par saison |
| `GET /clubs`, `GET /club/{code}` | Liste, géolocalisation, équipes |
| `GET /news`, `GET /news/{slug}`, `GET /pages/{slug}`, `GET /menu` | Module éditorial |
| `GET /rss.xml`, `GET /sitemap` (données) | Syndication, SEO |

Et côté admin (app4) : CRUD `/admin/news`, `/admin/pages`, `/admin/menu`, `/admin/media`.

---

## 8. Phasage proposé

| Phase | Contenu | Livrable | Charge indicative |
|---|---|---|---|
| **0. Cadrage** | Réponses aux questions § 11, inventaire WordPress, maquettes (accueil, page compétition, club), plan d'URL définitif, table de redirections | Doc validé + maquettes | 1–2 sem. |
| **1. Socle** | `kpi-layer` (thème, client api2, types), squelette app5 SSR (layout, menu, i18n, SEO de base), conteneur `site` dans les 3 compose, cibles Makefile (`app5_dev`, `app5_build`, `app5_restart`…), CI, déploiement sur **sous-domaine de prévisualisation** | Site vide navigable en préprod | 2 sem. |
| **2. Résultats** | Pages compétition (matchs, terrains, infos, déroulement, phases, classement, stats) en réutilisant les composants d'app2 via le layer ; endpoints `season/competition/*` | Parité avec `kpmatchs`/`kpclassement`/… | 3–4 sem. |
| **3. Transverse** | Calendrier, historique, équipes, clubs (+ carte), logos, ICS | Parité avec le reste des `kp*.php` | 3 sem. |
| **4. Éditorial** | Module articles/pages/menu/médias (api2 + app4), import WordPress, accueil, RSS | CMS natif opérationnel | 3–4 sem. |
| **5. Bascule** | Inversion du routage Traefik, redirections 301 (legacy + WordPress), sitemap, Search Console, suivi Matomo des 404 | `www.kayak-polo.info` servi par app5 | 1 sem. + suivi |
| **6. Décommissionnement** | WordPress en lecture seule puis arrêt (`dbwp`), suppression des `kp*.php` et templates associés, du CSS « material » copié | Legacy public retiré | 1 sem. |
| **7. Écrans/embeds** *(optionnel, peut suivre)* | `/embed/...` + thèmes, redirection des `frame_*.php` | Fin de la dépendance Apache pour l'affichage | 2–3 sem. |

Chaque phase est déployable indépendamment ; tant que la phase 5 n'a pas eu lieu, le site actuel reste intact.

---

## 9. Risques et points d'attention

| Risque | Mitigation |
|---|---|
| Perte de référencement / liens cassés (QR codes, sites de clubs, fédération) | Table de redirections 301 exhaustive testée automatiquement ; conserver `frame_*.php` et `Pdf*.php` ; suivi des 404 |
| Nouveau runtime Node en prod (mémoire, redémarrage, logs) | Conteneur dédié avec `restart: unless-stopped`, healthcheck, cible `make app5_logs` ; charge limitée grâce au cache SWR |
| Divergence entre app2 et app5 | Composants d'affichage dans le layer commun ; règle : un composant de lecture n'existe qu'une fois |
| Pics de charge pendant les grands événements | Cache SWR Nitro + `Cache-Control` api2 ; le live passe par Mercure plutôt que par du polling massif |
| Contenu WordPress plus riche que prévu | Inventaire en phase 0 ; pont *headless* possible |
| Charge de travail sous-estimée (bénévolat) | Phasage livrable par tranche ; périmètre « écrans/embeds » explicitement optionnel |
| Symfony doit rester en 7.4 LTS | Aucun nouveau bundle ne doit tirer Symfony 8 (ne pas faire de `composer update` global) |

---

## 10. Ce que je ferais en premier

1. Répondre aux questions ci-dessous (Q1–Q5 sont bloquantes).
2. Faire l'inventaire WordPress (export de la liste des articles/pages/extensions actives).
3. Prototyper **une** page compétition complète dans app5 en SSR, avec le layer commun extrait d'app2 : c'est elle qui valide l'architecture (SSR + api2 + composants partagés + cache).

---

## 11. Questions ouvertes

Pour chacune : **mon hypothèse par défaut** si vous n'avez pas de préférence.

### Contenu et CMS

- **Q1 — Volume et nature du contenu WordPress** : combien d'articles/pages, publiés à quel rythme ? Faut-il **migrer tout l'historique** des actualités ou seulement les pages utiles + N dernières années ?
  *Hypothèse : quelques dizaines de pages + actualités depuis ~2015, tout est migré.*
- **Q2 — Extensions WordPress utilisées** : formulaires de contact, newsletter, galeries photo, agenda, commentaires, pages protégées par mot de passe, intégrations réseaux sociaux ?
  *Hypothèse : pas de fonctionnalité métier cachée dans WordPress ; commentaires non repris.*
- **Q3 — Qui rédige ?** Combien de rédacteurs, ont-ils déjà un compte KPI (app4) ? Un circuit de validation (brouillon → relecture → publication) est-il nécessaire ?
  *Hypothèse : 2–5 rédacteurs ayant déjà un compte, pas de circuit de validation.*
- **Q4 — Êtes-vous prêt à abandonner WordPress** au profit d'un module éditorial dans app4, ou tenez-vous à conserver l'interface WordPress pour les rédacteurs (→ option B *headless*) ?
  *Hypothèse : abandon de WordPress (option C).*

### Architecture

- **Q5 — Un conteneur Node (SSR) en production est-il acceptable** sur le VPS (≈ 150–300 Mo de RAM) ? Sinon, on se replie sur pré-rendu + SPA avec un SEO dégradé.
  *Hypothèse : oui.*
- **Q6 — Nom et URL** : le nouveau site remplace-t-il bien `www.kayak-polo.info` à la racine ? Quel sous-domaine de prévisualisation pendant le développement (`beta.kayak-polo.info`, `preprod` uniquement…) ?
  *Hypothèse : racine du domaine ; prévisualisation en préprod uniquement.*
- **Q7 — Layer commun** : acceptez-vous de modifier app2 et app4 pour qu'elles consomment `kpi-layer` (léger travail de refactoring, montage de `sources/` dans les builds) ?
  *Hypothèse : oui, progressivement, sans bloquer les autres chantiers.*
- **Q8 — Avenir d'app2** : doit-elle rester indéfiniment une application séparée (`app.kayak-polo.info`), ou envisagez-vous à terme qu'app5 absorbe le suivi d'événement ?
  *Hypothèse : app2 reste séparée ; on réévalue après la bascule.*

### Périmètre

- **Q9 — Affichages écrans et iframes tiers** (`frame_*.php`, thèmes `Css=`) : combien de sites externes les intègrent encore ? Les inclut-on dans ce chantier (phase 7) ou les laisse-t-on sur le legacy ?
  *Hypothèse : laissés sur le legacy au début, phase 7 ensuite.*
- **Q10 — Design** : refonte graphique complète (nouvelle identité, éventuellement avec un graphiste) ou reprise de l'identité actuelle (bandeau FFCK, couleurs) dans le design system d'app2/app4 ? Contraintes de charte FFCK ?
  *Hypothèse : identité actuelle modernisée, alignée sur app2/app4.*
- **Q11 — Nouvelles fonctionnalités souhaitées** au passage : recherche globale (joueur, équipe, club), favoris/suivi d'équipe, pages joueur publiques, statistiques multi-saisons, abonnement ICS par compétition… ?
  *Hypothèse : recherche globale et ICS par compétition inclus ; pages joueur exclues (données personnelles).*
- **Q12 — Pages joueurs / données personnelles** : les compositions d'équipe publiques affichent nom et prénom. Existe-t-il des demandes de non-diffusion à respecter, ou une règle RGPD fédérale à appliquer ?
  *Hypothèse : mêmes données qu'aujourd'hui, pas plus.*

---

## Références

- [ROADMAP_KPI.md](../../ROADMAP_KPI.md) — « Refonte de la Navigation Publique en Nuxt 4 + API »
- [KPI_FUNCTIONALITY_INVENTORY.md](../../reference/KPI_FUNCTIONALITY_INVENTORY.md) — inventaire des pages publiques
- [API2_ENDPOINTS.md](../../reference/API2_ENDPOINTS.md) — endpoints existants
- [APP2_TECHNICAL_ARCHITECTURE.md](../../reference/APP2_TECHNICAL_ARCHITECTURE.md), [APP4_STRUCTURE.md](../../reference/APP4_STRUCTURE.md)
- [FRANKENPHP_MIGRATION_ANALYSIS.md](../../audits/FRANKENPHP_MIGRATION_ANALYSIS.md) — routage Traefik / api2
- [DOCUMENTS_MIGRATION.md](../DOCUMENTS_MIGRATION.md), [LEGACY_PDF_STANDALONE_ACCESS.md](../LEGACY_PDF_STANDALONE_ACCESS.md) — PDF
- [WORDPRESS_MIGRATION_OLD_PROD_TO_VPS.md](../../infrastructure/wordpress/WORDPRESS_MIGRATION_OLD_PROD_TO_VPS.md) — installation WordPress actuelle
