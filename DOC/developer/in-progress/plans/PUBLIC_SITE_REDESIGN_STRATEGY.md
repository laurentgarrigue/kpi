# Stratégie de refonte de la partie publique (kayak-polo.info)

**Date** : 28 septembre 2026 (v2, intègre les décisions du 28/09)
**Statut** : ✅ Orientations validées — questions résiduelles en [§ 12](#12-questions-restantes)
**Périmètre** : page d'accueil et contenus WordPress, pages publiques `kp*.php`, affichages `frame_*.php`, exports publics (PDF, ICS), médias, articulation avec app2 / app4 / api2

---

## 0. Résumé

| Sujet | Décision |
|---|---|
| **Brique** | **Application distincte `sources/app3/`**, repartie de zéro après **suppression complète de l'ancien app3** (prototype de feuille de marque abandonné, cf. § 3). Socle partagé **`sources/kpi-layer/`** (Nuxt Layer) consommé par app2, app3 et app4. |
| **Rendu** | **Nuxt 4 en SSR hybride**, serveur Nitro dans un **conteneur Node** dédié (validé). Cache SWR court sur les résultats, rafraîchissement client + Mercure pour le live. |
| **CMS** | **Abandon de WordPress** (option C) : module éditorial natif — articles, pages, menu, médias, galeries, SEO, partage social, **formulaires d'inscription** — édité dans **app4**, servi par **api2**. Reprise d'environ **50 articles sur 325** et des **pages utiles sur 43**. |
| **URL** | **Termes anglais**, alignés sur app4 (`/games`, `/pitches`, `/ranking`, `/teams/{id}`…). Anciennes URL redirigées en 301. |
| **Domaine** | Remplace `www.kayak-polo.info` ; prévisualisation sur **`beta.kayak-polo.info`**. |
| **app2** | Reste séparée (`app.kayak-polo.info`) ; son authentification disparaîtra (le contrôle/scrutineering migre dans app4, chantier distinct). Convergence réévaluée après la bascule. |
| **Médias** | Les images uploadées (logos clubs/compétitions, photos d'équipes…) **sortent de Git** vers un stockage non versionné, avec une **sauvegarde dédiée** (§ 8). |
| **Hors périmètre initial** | Écrans et iframes tiers (`frame_*.php`) → legacy, puis phase 7. PDF publics → legacy. **Live** → branche `claude/scoring-refactoring-strategy-3d43ac`. |

---

## 1. État des lieux

### 1.1 Composants actuels de la partie publique

| Brique | Techno | Rôle | Emplacement |
|---|---|---|---|
| **Accueil / articles / pages** | WordPress (thème « Material »), base `dbwp` — 325 articles, 43 pages depuis 2011 | Accueil, actualités, pages, formulaires d'inscription, galeries | `sources/index.php` → `wordpress/wp-blog-header.php` ; `docker/wordpress/` (hors Git) |
| **Pages de consultation** | PHP + Smarty + Bootstrap 5 + jQuery/DataTables | Calendrier, matchs, classements, historique, équipes, clubs, stats… | `sources/kp*.php`, `smarty/templates/kp*.tpl` |
| **Écrans / iframes** | PHP + Smarty | Écrans de gymnase (rotation `voie`/`intervalle`), **intégration chez des tiers** avec thème (`?Css=simply`, `ckca`, `saintomer2022`…) | `sources/frame_*.php` |
| **Exports publics** | mPDF, ICS | Classements, listes de matchs, feuilles de marque ; ICS d'une journée | `sources/Pdf*.php`, `upload_ics.php` |
| **Flux JSON legacy** | PHP | FullCalendar, carte Leaflet | `json-events.php`, `json-clubs.php`, `searchClubs.php` |
| **app2** | Nuxt 4 SPA/PWA (`ssr: false`) | Suivi d'un événement ou d'un groupe | `sources/app2/`, `app.kayak-polo.info` |
| **app4** | Nuxt 4 SPA (`ssr: false`) | Administration | `sources/app4/`, `/admin2` |
| **api2** | Symfony 7.4 + API Platform 4.3 / FrankenPHP | API publique, staff, admin, WSM, Mercure | `sources/api2/`, `/api2` |

### 1.2 La cohérence visuelle actuelle repose sur des copies

- `kppage.tpl` charge `css/wordpress_material_*.css`, des copies des feuilles du thème WordPress.
- Le menu public est codé en dur dans `commun/MyPage.php` **et** redéfini dans WordPress.
- `kpfooter.tpl`, les balises Open Graph et le flux RSS pointent vers `wordpress/wp-content/...` et `?feed=rss2`.

Chaque évolution de design ou de menu se fait donc deux fois, dans deux technologies.

### 1.3 Existant réutilisable

- **api2 public** : `/events/{mode}`, `/event/{id}`, `/event/{id}/games|charts`, `/groups/{season}`, `/group/{season}/{code}/games|charts|teams|team/{id}/stats`, `/event/{id}/team/{id}/stats`, `/game-sheet/{id}`.
- **Logique déjà écrite pour l'admin** : classements (`AdminRankingsController`), statistiques (`AdminStatsController`), clubs (`AdminClubsController`), compétitions, journées → à **factoriser en services** pour les exposer en lecture publique.
- **Composants app2** : `GameList`, `GameSheet`, `Charts`, `ChartGroup`, `ChartChptRanking`, `ChartCpRanking`, `TeamName`, `EventQrCode`, `LanguageSwitcher`.
- **Composants app4** : `ClubMap.client.vue`, `Pagination`, `Card`, `PageHeader`, sélecteurs de compétition, upload d'images.
- **Mercure** (FrankenPHP) et **Matomo** (déjà câblé dans app2/app4).

---

## 2. Brique distincte + socle partagé

### 2.1 Pourquoi ne pas étendre app2

| Critère | app2 | Site public (app3) |
|---|---|---|
| Point d'entrée | QR code, lien vers **un** événement | Google, réseaux sociaux, navigation libre |
| Axe | Un événement / un groupe, saison en cours | Toutes saisons, compétitions, clubs, historique, articles |
| Référencement | Sans importance | **Critique** |
| Rendu | SPA statique | HTML indexable (SSR) |
| Hors-ligne / PWA | **Essentiel** (bord de bassin) | Accessoire |
| Contenu éditorial | Aucun | Articles, pages, galeries, formulaires |

Passer app2 en SSR casserait sa stratégie hors-ligne (Dexie, service worker, `navigateFallback`) et son déploiement Nginx statique. Or `app.kayak-polo.info` est imprimé sur des QR codes et installé sur des téléphones.

### 2.2 Architecture retenue

- **`sources/app3/`** — nouveau site public, Nuxt 4 SSR, servi sur `www.kayak-polo.info`.
- **`sources/kpi-layer/`** — Nuxt Layer (`extends: ['../kpi-layer']`) utilisé par app2, app3 et app4 :
  - thème Tailwind et `app.config.ts` Nuxt UI (couleurs, typographie, dark mode — cf. `DOC/specs/DARK_MODE.md`), avec les **couleurs et typographies de la charte FFCK** ;
  - client api2 typé (`useApi2`) et types TypeScript des réponses publiques ;
  - composants d'affichage **en lecture seule** : liste de matchs, feuille de match, classement, tableau de phases, carte des clubs, nom/logo d'équipe, sélecteur de langue, bouton de partage ;
  - clés i18n communes (phases, statuts de match, libellés de classement).
- **Extraction progressive** : un composant passe d'app2/app4 au layer au moment où app3 en a besoin, sans bloquer les autres chantiers.
- **Passerelles** : chaque page compétition/événement propose « 📱 Suivre dans l'app » vers app2 ; app2 renvoie vers le site pour l'historique, les clubs et les articles.

**Contrainte de build** : les cibles `make app2_generate_*` ne montent que `sources/app2` dans le conteneur Node temporaire. Avec le layer, il faut monter `sources/` (ou `sources/kpi-layer` en plus) ; idem dans la CI (`.github/workflows/ci.yml`).

### 2.3 Conséquence de la fin de l'authentification dans app2

Une fois le scrutineering migré dans app4, app2 devient une application **100 % publique en lecture**. Deux effets :
- tous ses composants deviennent candidats au layer (plus de logique d'authentification à isoler) ;
- la convergence app2 ↔ app3 devient techniquement simple. On la réévalue après la bascule, comme décidé.

---

## 3. Réutiliser le nom app3 : nettoyage préalable

L'ancien `sources/app3` (prototype de feuille de marque, gelé, hors CI et hors déploiement) doit être **entièrement retiré** avant de créer le nouveau site sous le même nom.

### 3.1 Inventaire des références (au 28/09/2026)

`git grep -i app3` hors du dossier lui-même : **161 occurrences dans 22 fichiers** (plus les 34 fichiers suivis de `sources/app3/`).

| Zone | Fichiers | Nature |
|---|---|---|
| Infrastructure | `Makefile` (variable `NODE3_CONTAINER_NAME`, cibles `init_env_app3`, `app3_*`, liste `.PHONY`, génération des certificats), `docker/compose.dev.yaml` (service `node_app3`), `docker/.env.dist` (`APP3_DOMAIN_NAME`), `scripts/git-wt.sh` | Cibles et service à **supprimer puis recréer** pour le nouveau site |
| CI | `.github/workflows/ci.yml` (exclusion `!sources/app3/**`, commentaires), `.github/dependabot.yml`, `.github/workflows/codeql.yml` | Exclusions à retirer ; le nouveau app3 **entre** dans la CI |
| Code | `sources/app4/types/scoring.ts` (commentaire de provenance) | Commentaire à reformuler |
| Documentation | `CLAUDE.md`, `DOC/README.md`, `ENVIRONNEMENT_DEV.md`, `CI_CD_STRATEGY.md`, `CI_CD_EXECUTION_NOTES.md`, `CACHE_BUSTING_STRATEGY.md`, `CORS_CONFIGURATION.md`, `NGINX_STATIC_APP_DEPLOYMENT.md`, `infrastructure/README.md`, `GIT_WORKFLOW_SIMPLIFICATION.md`, `CONSOLIDATION_PHASES_CLASSEMENT.md`, `DARK_MODE.md` | Mentions à retirer ou réécrire |
| Documentation du chantier scoring | `DOC/specs/PAGE_SCORING.md`, `LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md` | ⚠️ Citent `app3/stores/matchStore.ts` etc. comme **source du portage** vers app4 |
| Faux positif | `sources/img/KIP/teams/72-2016-team.jpg` (octets binaires) | Rien à faire |

### 3.2 Procédure

1. **Coordination avec le chantier scoring** (`claude/scoring-refactoring-strategy-3d43ac`) : il utilise encore l'ancien app3 comme référence de portage. Avant suppression, poser un **tag Git `archive/app3-matchsheet`** sur le dernier commit qui contient l'ancien app3, et remplacer dans `PAGE_SCORING.md` / `LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md` les chemins `sources/app3/...` par une référence à ce tag.
2. Supprimer `sources/app3/`, le service `node_app3`, les cibles Makefile, `APP3_DOMAIN_NAME`, les exclusions CI/Dependabot/CodeQL, et nettoyer la documentation.
3. **Commit dédié** (« Chore: remove legacy app3 match-sheet prototype »), séparé de la création du nouveau site, pour pouvoir le restaurer facilement.
4. Recréer ensuite `sources/app3/` (site public), avec ses propres cibles : `app3_dev`, `app3_build`, `app3_restart`, `app3_logs`, `init_env_app3`, les services `node_app3` (dev) et `site_app3` (SSR, préprod/prod), et `APP3_DOMAIN_NAME` réutilisé pour `beta.` en préprod.

> Attention au **cache navigateur** : l'ancien app3 a pu être servi sur `app3.localhost` avec un service worker PWA. En dev, vider les données du site ou changer de domaine local évite des surprises.

---

## 4. Rendu et hébergement

### 4.1 SSR hybride avec Nitro

```ts
// app3/nuxt.config.ts (esquisse)
routeRules: {
  '/':                     { swr: 300 },   // accueil : à la une + prochains événements
  '/news/**':              { swr: 600 },
  '/clubs/**':             { swr: 3600 },
  '/history/**':           { swr: 86400 }, // saisons closes : quasi immuable
  '/competitions/**':      { swr: 60 },    // résultats : 1 min + rafraîchissement client
  '/events/**':            { swr: 60 },
  '/calendar/**':          { swr: 600 },
  '/forms/**':             { ssr: true, cache: false },
}
```

- **Live** : rafraîchissement côté client (polling comme app2), puis abonnement **Mercure** aux topics publics qu'introduira le chantier scoring.
- **Conteneur** `${APPLICATION_NAME}_site` : `node:22-alpine`, `.output/server/index.mjs`, sur `network_${APPLICATION_NAME}` pour appeler api2 **en interne** côté serveur (sans repasser par Traefik), et via l'URL publique côté navigateur. `restart: unless-stopped`, healthcheck, `make app3_logs`, `make app3_restart`.
- **SEO** : `useSeoMeta`, `sitemap.xml` dynamique, données structurées schema.org (`SportsEvent`, `SportsTeam`, `NewsArticle`), `hreflang` FR/EN, image Open Graph par article et par compétition.
- **Invalidation** : lors de la publication d'un article dans app4, api2 appelle un endpoint interne de purge du cache Nitro, pour que l'article apparaisse immédiatement.

### 4.2 Routage cible

```
                        Traefik (TLS)
  www.kayak-polo.info ─────┬─────────────────────────────────────────────────┐
    /api2/*   ──► FrankenPHP api2 (Symfony, Mercure)                          │
    /admin2/* ──► Nginx app4 (admin + module éditorial)                       │
    /admin/*, /Pdf*.php, /frame_*.php, /live/*, /api/*, /img/*, /media/*… ──► Apache legacy (liste explicite)
    /*  (tout le reste) ──► Node app3 (SSR) ── appels internes ──► api2
  beta.kayak-polo.info ──► Node app3 (préprod, avant bascule)
  app.kayak-polo.info  ──► Nginx app2 (PWA, inchangée)
```

- Aujourd'hui Apache est le **routeur par défaut** (`!PathPrefix('/api2') && !PathPrefix('/admin2')`). À la bascule, **app3 le devient**, et Apache ne reçoit plus qu'une **liste explicite** de préfixes legacy. Cette liste sert de checklist de décommissionnement.
- WordPress (conteneur `dbwp` compris) est retiré après la migration et une période de sécurité (archive SQL + fichiers conservée).

---

## 5. CMS natif (remplacement de WordPress)

### 5.1 Correspondance des fonctions WordPress

| Extension / fonction WP | Usage réel | Remplacement |
|---|---|---|
| Articles + Classic Editor | ✅ essentiel | Module **Articles** (éditeur riche TipTap/ProseMirror, HTML assaini côté api2) |
| Pages | ✅ essentiel | Module **Pages** + **Menu** éditable |
| **Ninja Forms + TablePress** | ✅ **inscriptions à des tournois** | Module **Formulaires** (§ 5.3) |
| Sassy Social Share | ✅ partage facilité | Bouton de partage (Web Share API sur mobile, liens Facebook / X / WhatsApp / e-mail / copie sur desktop) + balises Open Graph soignées |
| Galerie photo | 🟡 éventuellement | Bloc **Galerie** dans les articles et pages (album de médias, visionneuse) |
| Rank Math SEO | ✅ implicite | Champs SEO par contenu (titre, description, image OG, slug) + sitemap + schema.org |
| WP Multilang | ✅ FR/EN | Champs FR/EN par contenu, repli sur FR si EN vide |
| Connect Matomo | ✅ | Matomo déjà câblé dans le socle Nuxt |
| Feature a Page Widget, Classic Widgets | Accueil | **Blocs d'accueil** : à la une, bandeau d'alerte, partenaires, prochains événements |
| Enable Media Replace | Confort | « Remplacer le fichier » dans la médiathèque (même URL) |
| Email Log | Suivi des mails | Journal des e-mails envoyés par les formulaires (table dédiée, consultable dans app4) |
| Akismet | Anti-spam (formulaires) | Pot de miel + limitation de débit + captcha respectueux de la vie privée si nécessaire |
| Kadence Security, TAC, WP Fastest Cache, PWA | Propres à WordPress | Sans objet (Symfony + cache Nitro ; la PWA reste app2) |
| Commentaires | — | Non repris |

### 5.2 Modèle de contenu (MVP)

| Élément | Détail |
|---|---|
| **Articles** | titre FR/EN, chapô, corps, image de une, date de publication **programmable**, statut brouillon/publié, auteur, **liens optionnels vers compétition / événement / club** (l'article s'affiche aussi sur ces pages), champs SEO |
| **Pages** | slug, titre, corps, parent, position dans le menu, champs SEO |
| **Menu** | entrées « données » fixes (Calendrier, Compétitions, Clubs…) + entrées éditoriales libres |
| **Médias** | images et PDF, redimensionnement (vignette / moyen / grand), texte alternatif, remplacement de fichier, albums pour les galeries |
| **Accueil** | blocs configurables (§ 5.1) |
| **Droits** | nouveau droit **« Rédacteur »** dans le modèle de profils (`DOC/specs/DROITS_PAR_PROFIL.md`), attribué aux **5 rédacteurs, qui ont déjà un compte KPI** |
| **Sorties** | flux RSS (remplace `?feed=rss2`), sitemap |

### 5.3 Formulaires d'inscription (remplace Ninja Forms + TablePress)

Constructeur **volontairement simple** :
- champs typés (texte, e-mail, nombre, liste, case à cocher, date), obligatoires ou non, avec **pré-remplissage possible depuis les données KPI** (liste des clubs, des compétitions) ;
- période d'ouverture, nombre maximal d'inscriptions, liste d'attente ;
- e-mail de confirmation au déclarant et notification à l'organisateur, journalisés ;
- consultation et **export CSV/Excel** des inscriptions dans app4 (OpenSpout est déjà utilisé) ;
- **liste publique optionnelle des inscrits** (ce que fait aujourd'hui TablePress), avec choix des colonnes publiées ;
- rattachement optionnel à un événement ou une compétition ;
- RGPD : mention d'information, durée de conservation, purge automatique après l'événement.

Si un **paiement en ligne** est nécessaire, on ne le développe pas : on renvoie vers une plateforme spécialisée (voir Q-A).

### 5.4 Reprise du contenu WordPress

- **Périmètre** : environ **50 articles** (sur 325) et les pages utiles (sur 43), choisis par les rédacteurs (Q-C).
- **Outil** : commande Symfony `app:import-wordpress` lisant la base `dbwp` à partir d'une **liste d'identifiants**, qui convertit le HTML, rapatrie les médias référencés dans le stockage médias (§ 8), et produit un **rapport** (liens internes cassés, shortcodes non convertis — ex. `[ninja_form]`, `[table]`, galeries).
- **Articles non repris** : ils ne sont pas perdus sans le dire. Ils renvoient un **410 Gone** (ou une redirection vers `/news`), et le dump WordPress complet est archivé.
- **Redirections** : table `/?p=123` et permaliens WordPress → nouvelles URL `/news/{slug}`.

---

## 6. Plan d'URL (termes anglais, alignés sur app4)

Langue par défaut FR sans préfixe, anglais sous `/en/...` (slugs de contenu traduits, segments fixes identiques).

| Nouvelle URL | Remplace |
|---|---|
| `/` | Accueil WordPress |
| `/news`, `/news/{slug}` | Articles WordPress |
| `/{slug}` (pages) | Pages WordPress |
| `/forms/{slug}` | Formulaires Ninja Forms |
| `/search?q=` | *(nouveau)* recherche globale |
| `/calendar` | `kpcalendrier.php` |
| `/competitions/{season}` (filtres niveau/type) | `kpclassements.php` (sélecteur) |
| `/competitions/{season}/{code}` → `…/games` | `kpmatchs.php` |
| `…/pitches` | `kpterrains.php` |
| `…/info` | `kpdetails.php` |
| `…/progress` | `kpchart.php` (déroulement) |
| `…/phases` | `kpphases.php` |
| `…/ranking` | `kpclassement.php` |
| `…/stats` | `kpstats.php` |
| `…/calendar.ics` | *(nouveau)* abonnement ICS par compétition |
| `/groups/{season}/{code}/…` | `kp*.php?Group=…&Compet=*` (vue groupe) |
| `/events/{id}/…` (mêmes sous-pages) | `kp*.php?event=…` |
| `/history/{groupCode}` | `kphistorique.php` |
| `/teams/{id}` | `kpequipes.php` |
| `/clubs`, `/clubs/{code}` | `kpclubs.php`, `kplogos.php` |
| `/games/{id}` | feuille de match publique (app2 `/game/[id]`) |

Les anciennes URL (`kp*.php?Compet=N1&Saison=2026&Group=N&J=…&lang=en`) sont redirigées en **301** par un middleware serveur Nitro (table de correspondance des paramètres, testée automatiquement). Les liens partagés, les favoris et le référencement sont ainsi préservés.

---

## 7. Travaux api2

### 7.1 Principes

- Endpoints publics **en lecture seule** dans des contrôleurs `Public*Controller`, sans authentification (hors envoi de formulaire).
- **Factoriser** en services (`src/Service/...`) la logique aujourd'hui dans les contrôleurs admin (classements, stats, clubs, compétitions), appelés des deux côtés, sans copier-coller.
- Ne renvoyer que le **publié** (`Publication = 'O'` sur compétition, journée, match), comme `/game-sheet`.
- En-têtes `Cache-Control` et `ETag` ; limitation de débit sur `/search` et sur l'envoi de formulaires.
- **Données personnelles** : exactement les mêmes champs qu'aujourd'hui (nom, prénom, numéro, club), rien de plus. **Évaluation RGPD à mener** (§ 10) avant la mise en production.
- Symfony reste en **7.4 LTS** : aucun nouveau bundle ne doit tirer Symfony 8.

### 7.2 Endpoints à créer

| Endpoint | Usage |
|---|---|
| `GET /seasons` | Sélecteur de saison |
| `GET /season/{s}/competitions?level=&type=` | Liste des compétitions |
| `GET /season/{s}/competition/{code}` | En-tête, visuels, journées, officiels (`info`) |
| `GET /season/{s}/competition/{code}/games` | Matchs d'une compétition seule |
| `GET /season/{s}/competition/{code}/ranking?gameday=` | Classement, y compris par journée (CHPT) |
| `GET /season/{s}/competition/{code}/scorers` | Buteurs, cartons |
| `GET /season/{s}/competition/{code}/calendar.ics`, `GET /gameday/{id}.ics` | Exports et abonnements ICS |
| `GET /calendar?start=&end=` | Remplace `json-events.php` |
| `GET /history/{groupCode}` | Palmarès multi-saisons |
| `GET /team/{numero}` | Palmarès, compositions par saison |
| `GET /clubs`, `GET /club/{code}` | Liste, géolocalisation, équipes |
| `GET /search?q=` | Recherche globale : compétitions, équipes, clubs, articles (pas les joueurs) |
| `GET /news`, `GET /news/{slug}`, `GET /pages/{slug}`, `GET /menu`, `GET /home` | Contenu éditorial |
| `GET /forms/{slug}`, `POST /forms/{slug}/submissions`, `GET /forms/{slug}/entries` (si liste publique) | Formulaires |
| `GET /rss.xml` | Syndication |

Côté admin (app4) : CRUD `/admin/news`, `/admin/pages`, `/admin/menu`, `/admin/media`, `/admin/forms` (+ inscriptions, export), `/admin/mail-log`.

---

## 8. Médias : sortie de Git et sauvegarde

### 8.1 Constat

`sources/img/` contient **1 450 fichiers versionnés (≈ 113 Mo)**, dont une majorité de contenus **uploadés** qui n'ont rien à faire dans Git :

| Dossier | Fichiers | Taille | Nature | Cible |
|---|---|---|---|---|
| `img/KIP/` (logo, teams, colors…) | 345 | 71 Mo | Logos de clubs, photos d'équipes, couleurs — uploads | **Stockage médias** |
| `img/logo/` | 674 | 26 Mo | Logos et bandeaux de compétitions — uploads via app4 | **Stockage médias** |
| `img/presentations/` | 56 | 4,9 Mo | Visuels de présentation (écrans/TV) | À classer (Q-F) |
| `img/schemas/` | 13 | 3,3 Mo | Schémas | À classer (Q-F) |
| `img/Nations/` | 71 | 2,2 Mo | Logos d'équipes nationales | À classer (Q-F) |
| `img/referees/` | 15 | 0,4 Mo | ? | À classer (Q-F) |
| `img/Pays/`, icônes `*.gif`/`*.png` à la racine, `calendar/`, `admin-choice/` | ~250 | < 1 Mo | Ressources de l'application | **Restent versionnés** |

`.gitignore` exclut déjà une partie de `img/KIP/` (`players`, `boats`, `vests`, `helmets`, `teams/`), mais des fichiers ont été commités avant l'exclusion. À cela s'ajoutent les **médias WordPress** repris (§ 5.4) et ceux du nouveau module éditorial.

### 8.2 Cible

- **Un seul stockage médias non versionné** sur l'hôte, configuré par une variable `HOST_MEDIA_PATH` dans `docker/.env` (même principe que `HOST_WORDPRESS_PATH`), par exemple :
  ```
  media/
  ├── clubs/        (ex img/KIP/logo, colors)
  ├── teams/        (ex img/KIP/teams)
  ├── competitions/ (ex img/logo)
  ├── players/      (ex img/KIP/players — déjà hors Git)
  ├── content/      (articles, pages, galeries — ex wp-content/uploads)
  └── forms/        (pièces jointes éventuelles des inscriptions)
  ```
- **URL existantes préservées** : on monte les sous-dossiers aux anciens emplacements (`/var/www/html/img/logo`, `/var/www/html/img/KIP/...`) dans les conteneurs **Apache (`kpi`), `api2` et `event-cache-worker`**, pour les 3 environnements. Le legacy, api2 (qui écrit dans ces dossiers et référence `img/logo/`, `img/KIP/logo/`) et les PDF continuent de fonctionner sans modification. Les nouveaux médias sont servis sous `/media/...`.
- **Git** : `git rm --cached` des dossiers concernés + règles `.gitignore`, **après** copie sur chaque serveur (dev, préprod, prod), sinon le `git pull` suivant supprimerait les fichiers. Réécrire l'historique (`git filter-repo`) pour récupérer les 113 Mo n'est **pas recommandé** : cela casse les clones et les branches en cours.
- **Dev** : cible `make media_sync_from_prod` (ou archive de référence) pour disposer des images en local.

### 8.3 Sauvegarde dédiée des médias

| Élément | Proposition |
|---|---|
| Outil | **restic** (chiffré, dédupliqué, incrémental), conteneur ou cron hôte |
| Périmètre | `HOST_MEDIA_PATH` complet (et, pendant la transition, `docker/wordpress/wp-content/uploads`) |
| Destination | **Hors du VPS** : stockage objet S3-compatible ou serveur distant (Q-E) |
| Fréquence / rétention | Quotidienne ; 7 quotidiennes, 4 hebdomadaires, 12 mensuelles |
| Cohérence | Exécutée juste après le dump SQL quotidien, pour que base et médias restent cohérents à la restauration |
| Cibles Makefile | `media_backup`, `media_restore snapshot=…`, `media_backup_check`, `media_sync_prod_to_preprod` |
| Vérification | Test de restauration trimestriel documenté dans `DEPLOYMENT_RUNBOOK.md` ; alerte en cas d'échec (mail) |

Ce chantier est **indépendant** du site et profite déjà à l'admin : il peut démarrer immédiatement (phase 0).

---

## 9. Couverture fonctionnelle

Légende api2 : ✅ existe · 🟡 logique présente côté admin, à exposer · ❌ à créer

| Fonctionnalité | Legacy | api2 | Cible |
|---|---|---|---|
| Accueil, articles, pages, menu | WordPress | ❌ | ✅ app3 + app4 |
| Partage social | Sassy Social Share / `share_btn` | — | ✅ |
| Galerie photo | WordPress | ❌ | ✅ bloc galerie |
| Formulaires d'inscription + liste des inscrits | Ninja Forms + TablePress | ❌ | ✅ module Formulaires |
| SEO (méta, sitemap) | Rank Math | ❌ | ✅ |
| FR / EN | WP Multilang, `?lang=` | — | ✅ `/en` |
| Flux RSS | WordPress | ❌ | ✅ |
| Recherche globale | — | ❌ | ✅ *(nouveau)* |
| Calendrier | `kpcalendrier.php`, `json-events.php` | ❌ | ✅ |
| ICS journée / **abonnement par compétition** | `upload_ics.php` / — | ❌ | ✅ |
| Liste et sélecteur des compétitions | `kpclassements.php` | 🟡 | ✅ |
| Matchs, « prochains matchs » | `kpmatchs.php` | ✅ groupe · ❌ compétition | ✅ |
| Matchs par terrain | `kpterrains.php` | ✅ (dérivé) | ✅ |
| Infos compétition / journées | `kpdetails.php` | 🟡 | ✅ |
| Déroulement | `kpchart.php` | ✅ | ✅ |
| Phases / tableaux | `kpphases.php` | ✅ | ✅ |
| Classements (CHPT, CP, par journée) | `kpclassement.php` | ✅ / 🟡 | ✅ |
| Buteurs / stats | `kpstats.php` | 🟡 | ✅ |
| Historique / palmarès | `kphistorique.php` | ❌ | ✅ |
| Fiche équipe | `kpequipes.php` | 🟡 | ✅ |
| Clubs (liste, carte, équipes), logos | `kpclubs.php`, `kplogos.php` | 🟡 | ✅ |
| Feuille de match publique | app2 | ✅ | ✅ (composant du layer) |
| Bandeau / logo / lien de compétition | `kpnavgroup.tpl` | 🟡 | ✅ |
| QR codes vers app2 | `kpqr.php` | — | ✅ |
| Pages joueur | — | — | ❌ **exclues** |
| PDF publics | `Pdf*.php` | — | 🔗 liens vers le legacy (chantier `DOCUMENTS_MIGRATION.md`) |
| Contrôle TV / scénarios | `kptv.php`, `kptvscenario.php` | ✅ | ➖ déjà dans app4 (`/tv`), non public |
| Écrans et iframes tiers (`?Css=`) | `frame_*.php` | ✅ données | ⏳ legacy, puis **phase 7** (`/embed/...` + `?theme=`) |
| Live, incrustations | `live/*.php` | ✅ | ➖ branche `claude/scoring-refactoring-strategy-3d43ac` |

**Conclusion** : toutes les fonctionnalités publiques utilisées sont couvertes, à l'exception de trois points, volontairement reportés et restant fonctionnels sur le legacy :
- les écrans et iframes tiers (phase 7) ;
- les PDF (chantier documents) ;
- le live (chantier scoring).

---

## 10. RGPD — points à évaluer

Évaluation à mener avant l'ouverture de beta au public (pas seulement à la bascule) :

- **Résultats et compositions** (nom, prénom, club, numéro, buts, cartons) : base légale (intérêt légitime / mission de la fédération), information des licenciés, **procédure d'opposition** et moyen technique de masquer un athlète (flag en base respecté par tous les endpoints publics et par le legacy).
- **Formulaires d'inscription** : minimisation des champs, mention d'information, durée de conservation et purge, accès restreint aux organisateurs, journal des e-mails.
- **Mesure d'audience** : Matomo configuré sans cookie ou avec consentement.
- **Recherche globale** : n'indexe pas les personnes (cohérent avec l'exclusion des pages joueur).
- **Registre des traitements** à compléter avec le référent RGPD de la FFCK (Q-G).

---

## 11. Phasage

| Phase | Contenu | Livrable | Charge indicative |
|---|---|---|---|
| **0a. Nettoyage app3** | Tag d'archive, suppression de l'ancien app3 et de ses références (§ 3) | Commit dédié | 1–2 j |
| **0b. Médias** | Stockage non versionné, montages, `git rm --cached`, sauvegarde restic (§ 8) | Médias hors Git et sauvegardés | 1 sem. |
| **0c. Cadrage** | Charte FFCK → design tokens, maquettes (accueil, compétition, club, article, formulaire), table de redirections, liste des articles repris | Maquettes validées | 1–2 sem. |
| **1. Socle** | `kpi-layer` (thème, client api2, types), squelette app3 SSR (layout, menu, i18n, SEO), conteneur `site_app3` dans les 3 compose, cibles Makefile, CI, déploiement sur **beta.kayak-polo.info** | Site vide navigable en beta | 2 sem. |
| **2. Résultats** | Pages compétition, groupe, événement (games, pitches, info, progress, phases, ranking, stats) avec les composants d'app2 passés au layer ; endpoints `season/competition/*` | Parité avec `kpmatchs` / `kpclassement` / … | 3–4 sem. |
| **3. Transverse** | Calendrier, ICS, historique, équipes, clubs (+ carte), logos, recherche globale | Parité avec le reste des `kp*.php` | 3–4 sem. |
| **4a. Éditorial** | Articles, pages, menu, médias, galeries, SEO, partage, blocs d'accueil, droit Rédacteur, RSS ; import des ~50 articles et des pages | CMS opérationnel, contenu repris | 3–4 sem. |
| **4b. Formulaires** | Constructeur, inscriptions, notifications, journal des e-mails, export, liste publique, anti-spam | Remplacement de Ninja Forms / TablePress | 2 sem. |
| **5. Bascule** | Évaluation RGPD bouclée, inversion du routage Traefik, redirections 301/410 (legacy + WordPress), sitemap, Search Console, suivi Matomo des 404 | `www.kayak-polo.info` servi par app3 | 1 sem. + suivi |
| **6. Décommissionnement** | WordPress en lecture seule puis arrêt (`dbwp`), suppression des `kp*.php`, `json-*.php`, templates et CSS « material » | Legacy public retiré | 1 sem. |
| **7. Écrans / embeds** | `/embed/...` + thèmes, redirection des `frame_*.php` | Fin de la dépendance Apache pour l'affichage | 2–3 sem. |

**Dépendances** :
- 0a précède 1.
- 0b précède 4a (import des médias WordPress dans le stockage).
- La mise à jour Mercure du live dépend du chantier scoring ; les phases 2–3 livrent d'abord avec du polling.
- Tant que la phase 5 n'a pas eu lieu, le site actuel reste intact.

---

## 12. Questions restantes

### Décisions déjà prises (28/09/2026)

| # | Décision |
|---|---|
| Q1 | 325 articles / 43 pages depuis 2011 ; **~50 articles** à reprendre |
| Q2 | À reprendre : articles avec partage social, pages, galerie éventuelle, **formulaires d'inscription (Ninja Forms + TablePress)** |
| Q3 | **5 rédacteurs**, tous avec un accès KPI |
| Q4 | **Abandon de WordPress** (option C) |
| Q5 | **Conteneur Node SSR accepté** |
| Q6 | Remplace **www.kayak-polo.info** ; prévisualisation **beta.kayak-polo.info** |
| Q7 | **kpi-layer** accepté |
| Q8 | app2 reste séparée ; son authentification disparaît (scrutineering → app4) ; réévaluation après bascule |
| Q9 | Écrans et iframes tiers : legacy, puis phase 7 |
| Q10 | Identité **modernisée**, partiellement alignée sur app2/app4, **conforme à la charte FFCK** |
| Q11 | Recherche globale et ICS par compétition **inclus** ; pages joueur **exclues** |
| Q12 | Mêmes données qu'aujourd'hui ; **conformité RGPD à évaluer** |
| — | Brique distincte, nommée **app3** (ancien app3 supprimé) ; **URL en anglais** ; **médias hors Git + sauvegarde** ; live dans le chantier scoring |

### Nouvelles questions

- **Q-A — Formulaires d'inscription** : les inscriptions actuelles comportent-elles un **paiement** ? Combien de formulaires par saison, avec quels champs typiques ? La liste publique des inscrits (TablePress) est-elle remplie automatiquement ou à la main ?
  *Hypothèse : pas de paiement en ligne (sinon lien vers HelloAsso) ; quelques formulaires par saison ; la liste publique est générée à partir des inscriptions.*
- **Q-B — Galerie photo** : volume attendu et hébergement ? Photos stockées sur le serveur, ou liens vers un service externe (Flickr, Google Photos…) ?
  *Hypothèse : galeries modestes (quelques dizaines de photos par article), stockées localement et redimensionnées.*
- **Q-C — Sélection des ~50 articles** : critère (date, liste choisie par les rédacteurs) ? Pour les articles non repris : **410** (supprimé) ou redirection vers `/news` ?
  *Hypothèse : liste fournie par les rédacteurs ; 410 + archive WordPress conservée hors ligne.*
- **Q-D — Charte FFCK** : disposez-vous des éléments officiels (logos vectoriels, palette, typographies, règles d'usage avec la marque « kayak-polo.info ») ?
  *Hypothèse : à demander à la FFCK en phase 0c.*
- **Q-E — Destination des sauvegardes médias** : stockage objet (OVH, Scaleway, Backblaze…), autre serveur, NAS ? Un budget de quelques euros par mois est-il acceptable ?
  *Hypothèse : stockage objet S3-compatible, < 5 €/mois pour ~ 1 Go.*
- **Q-F — Classement des dossiers d'images** : `presentations/`, `schemas/`, `Nations/`, `referees/` sont-ils alimentés par des uploads (→ stockage médias) ou font-ils partie de l'application (→ restent versionnés) ?
  *Hypothèse : `Nations/` et `presentations/` sont des uploads ; `schemas/` et `referees/` font partie de l'application.*
- **Q-G — RGPD** : y a-t-il un référent RGPD / DPO à la FFCK à associer, et une procédure d'opposition existe-t-elle déjà ?
  *Hypothèse : à contacter en phase 0c ; flag « non diffusé » à créer.*

---

## Références

- [ROADMAP_KPI.md](../../ROADMAP_KPI.md) — « Refonte de la Navigation Publique en Nuxt 4 + API »
- [KPI_FUNCTIONALITY_INVENTORY.md](../../reference/KPI_FUNCTIONALITY_INVENTORY.md) — pages publiques
- [API2_ENDPOINTS.md](../../reference/API2_ENDPOINTS.md) — endpoints existants
- [APP2_TECHNICAL_ARCHITECTURE.md](../../reference/APP2_TECHNICAL_ARCHITECTURE.md), [APP4_STRUCTURE.md](../../reference/APP4_STRUCTURE.md)
- [FRANKENPHP_MIGRATION_ANALYSIS.md](../../audits/FRANKENPHP_MIGRATION_ANALYSIS.md) — routage Traefik / api2
- [DEPLOYMENT_RUNBOOK.md](../../infrastructure/DEPLOYMENT_RUNBOOK.md) — déploiement, sauvegarde et restauration
- [DOCUMENTS_MIGRATION.md](../DOCUMENTS_MIGRATION.md), [LEGACY_PDF_STANDALONE_ACCESS.md](../LEGACY_PDF_STANDALONE_ACCESS.md) — PDF
- [WORDPRESS_MIGRATION_OLD_PROD_TO_VPS.md](../../infrastructure/wordpress/WORDPRESS_MIGRATION_OLD_PROD_TO_VPS.md) — installation WordPress actuelle
- [IMAGE_UPLOAD_MANAGEMENT.md](../../../user/IMAGE_UPLOAD_MANAGEMENT.md) — upload d'images (app4)
- Branche `claude/scoring-refactoring-strategy-3d43ac` — refonte du live et de la feuille de marque
