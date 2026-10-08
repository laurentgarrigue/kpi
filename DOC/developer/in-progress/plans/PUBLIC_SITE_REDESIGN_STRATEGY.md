# Stratégie de refonte de la partie publique (kayak-polo.info)

**Date** : 7 octobre 2026 (v4 : phases 0a/0b closes, domaines beta, livraison continue, méthode specs + clean code)
**Statut** : ✅ Orientations validées — phases 0a et 0b **closes** (06/10/2026) ; phase 1 (socle) **livrée dans le dépôt**, activation serveur à faire ; phase 2 = prochaine (§ 12)
**Périmètre** : page d'accueil et contenus WordPress, pages publiques `kp*.php`, affichages `frame_*.php`, exports publics (PDF, ICS), médias, articulation avec app2 / app4 / api2

---

## 0. Résumé

| Sujet | Décision |
|---|---|
| **Brique** | **Application distincte `sources/app3/`**, repartie de zéro après **suppression complète de l'ancien app3** (prototype de feuille de marque abandonné, cf. § 3). Socle partagé **`sources/kpi-layer/`** (Nuxt Layer) consommé par app2, app3 et app4. |
| **Rendu** | **Nuxt 4 en SSR hybride**, serveur Nitro dans un **conteneur Node** dédié (validé). Cache SWR court sur les résultats, rafraîchissement client + Mercure pour le live. |
| **CMS** | **Abandon de WordPress** (option C) : module éditorial natif — articles, pages, menu, médias, galeries, SEO, partage social, **formulaires d'inscription** — édité dans **app4**, servi par **api2**. Reprise d'environ **50 articles sur 325** et des **pages utiles sur 43**. |
| **URL** | **Termes anglais**, alignés sur app4 (`/games`, `/pitches`, `/ranking`, `/teams/{id}`…). Anciennes URL redirigées en 301. |
| **Domaine** | Remplace `www.kayak-polo.info` à la bascule. D'ici là, le site est servi **en parallèle** sur **`beta.preprod.kayak-polo.info`** (préprod) et **`beta.kayak-polo.info`** (prod), non indexé (§ 4.3). |
| **Livraison** | **Continue et sans impact** : chaque incrément est mergé sur `main` et déployé en préprod puis en prod, sans toucher au legacy, à app2 ni à app4 jusqu'à la bascule (§ 4.3). |
| **Méthode** | **Spec avant code** pour le template, les menus et chaque page ou fonctionnalité ([DOC/specs/public/](../../../specs/public/README.md)) ; principes **DRY, KISS, SOLID, YAGNI, TDD** ([CLEAN_CODE.md](../../guides/CLEAN_CODE.md)), applicables à ce chantier et aux suivants (§ 12.1). |
| **app2** | Reste séparée (`app.kayak-polo.info`) ; son authentification disparaîtra (le contrôle/scrutineering migre dans app4, chantier distinct). Convergence réévaluée après la bascule. |
| **Médias** | Les images uploadées (logos clubs/compétitions, photos d'équipes…) **sortent de Git** vers un stockage non versionné, avec une **sauvegarde dédiée** (§ 8). **Pas de réécriture de l'historique Git** (§ 8.4). |
| **Identité visuelle** | Charte FFCK, **univers Compétition** : bleus `#69b9e6` / `#357b9c`, rouges `#c94a4c` / `#882831`, noir `#1e1e1c`, gris `#c6c7c7` ; titres en Agency FB, textes en Raleway (§ 10). |
| **Paiement** | Pas de paiement dans KPI : les formulaires payants renvoient vers **HelloAsso**. |
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
- **`sources/kpi-layer/`** — Nuxt Layer (`extends: ['../kpi-layer']`) utilisé **d'abord par app3**, puis par app2 et app4 à partir de la phase 6 (pour ne pas les impacter avant la bascule, § 4.3) :
  - thème Tailwind et `app.config.ts` Nuxt UI (couleurs, typographie, dark mode — cf. `DOC/specs/DARK_MODE.md`), construits à partir des **jetons de la charte FFCK, univers Compétition** (§ 10) ;
  - client api2 typé (`useApi2`) et types TypeScript des réponses publiques ;
  - composants d'affichage **en lecture seule** : liste de matchs, feuille de match, classement, tableau de phases, carte des clubs, nom/logo d'équipe, sélecteur de langue, bouton de partage ;
  - clés i18n communes (phases, statuts de match, libellés de classement).
- **Extraction progressive** : un composant d'app2/app4 est porté dans le layer au moment où app3 en a besoin. app2/app4 gardent leur copie jusqu'à leur adoption du layer : cette duplication temporaire est **tracée** dans [kpi-layer/README.md](../../../../sources/kpi-layer/README.md) et résorbée en phase 6.
- **Passerelles** : chaque page compétition/événement propose « 📱 Suivre dans l'app » vers app2 ; app2 renvoie vers le site pour l'historique, les clubs et les articles.

**Contrainte de build** : les cibles `make app2_generate_*` ne montent que `sources/app2` dans le conteneur Node temporaire. Avec le layer, il faut monter `sources/` (ou `sources/kpi-layer` en plus) ; idem dans la CI (`.github/workflows/ci.yml`).

### 2.3 Conséquence de la fin de l'authentification dans app2

Une fois le scrutineering migré dans app4, app2 devient une application **100 % publique en lecture**. Deux effets :
- tous ses composants deviennent candidats au layer (plus de logique d'authentification à isoler) ;
- la convergence app2 ↔ app3 devient techniquement simple. On la réévalue après la bascule, comme décidé.

---

## 3. Réutiliser le nom app3 : nettoyage préalable ✅

> ✅ **Terminé** : fusionné sur `main` (#330) et déployé jusqu'en production. Section conservée pour mémoire.

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

> ✅ **Réalisé le 28/09/2026** (commit « Chore: remove legacy app3 match-sheet prototype »), et finalisé le 29/09/2026 :
> - le **tag `archive/app3-matchsheet`** est publié ; il pointe sur `cdb2081014`, dernier commit de `main` contenant l'ancien app3 ;
> - la branche `claude/scoring-refactoring-strategy-3d43ac` a mis à jour `PAGE_SCORING.md` et `LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md` pour citer ce tag (`git show archive/app3-matchsheet:sources/app3/composables/useBroadcast.ts`). Ces deux fichiers ne sont pas modifiés dans cette branche-ci, pour éviter les conflits.
> - Côté portage : `useTimer` est porté (réécrit en horodatage), `useBroadcast` est porté en `useScoringBroadcast` (contrat `kpi_channel` conservé). `useWebSocket` n'a pas à être porté : la diffusion distante passe par Mercure.

1. Coordination avec le chantier scoring : tag d'archive posé sur le dernier commit contenant l'ancien app3, référencé par les docs de portage.
2. Supprimer `sources/app3/`, le service `node_app3`, les cibles Makefile, `APP3_DOMAIN_NAME`, les exclusions CI/Dependabot/CodeQL, et nettoyer la documentation.
3. **Commit dédié**, séparé de la création du nouveau site, pour pouvoir le restaurer facilement.
4. Recréer ensuite `sources/app3/` (site public), avec ses propres cibles `make app3_*` et ses services (`node_app3` en dev, `app3` en préprod/prod) — cf. [SITE_PLATFORM.md](../../../specs/public/SITE_PLATFORM.md). Le domaine est dérivé de `KPI_DOMAIN_NAME` (`beta.${KPI_DOMAIN_NAME}`) : aucune nouvelle variable n'est nécessaire.

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
- **Conteneur** `${APPLICATION_NAME}_app3` : `node:22-alpine` exécutant `.output/server/index.mjs`, sortie de `nuxt build` construite par `make app3_generate_<env>` dans un conteneur temporaire (même principe qu'app2/app4). Il est sur `network_${APPLICATION_NAME}` pour appeler api2 **en interne** côté serveur (`http://${APPLICATION_NAME}_api2`, sans repasser par Traefik), et via l'URL publique côté navigateur. `restart: unless-stopped`, healthcheck sur `/healthz`, `make app3_logs`, `make app3_restart`. Détail : [SITE_PLATFORM.md](../../../specs/public/SITE_PLATFORM.md).
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
  beta.kayak-polo.info ──► Node app3 (prod, avant bascule)   — beta.preprod.kayak-polo.info en préprod
  app.kayak-polo.info  ──► Nginx app2 (PWA, inchangée)
```

- Aujourd'hui Apache est le **routeur par défaut** (`!PathPrefix('/api2') && !PathPrefix('/admin2')`). À la bascule, **app3 le devient**, et Apache ne reçoit plus qu'une **liste explicite** de préfixes legacy. Cette liste sert de checklist de décommissionnement.
- WordPress (conteneur `dbwp` compris) est retiré après la migration et une période de sécurité (archive SQL + fichiers conservée).

### 4.3 Livraison continue sans impact jusqu'à la bascule

Le site est développé **par incréments mergés sur `main`** et déployés comme le reste du dépôt (préprod automatique, prod sur tag), **sans attendre la fin du chantier**. Garde-fous :

| Garde-fou | Mise en œuvre |
|---|---|
| **Domaine séparé** | Routeur Traefik dédié `Host(beta.${KPI_DOMAIN_NAME})` → `beta.preprod.kayak-polo.info` en préprod, `beta.kayak-polo.info` en prod, `beta.kpi.localhost` en dev. Les routeurs existants (legacy, api2, app2, app4) ne sont **pas modifiés**. |
| **Pas d'indexation** | Tant que `NUXT_PUBLIC_BETA` vaut `true` (défaut) : `robots.txt` « Disallow: / », en-tête `X-Robots-Tag: noindex, nofollow` et balise `robots`. Aucun contenu dupliqué avec le site actuel. |
| **Lecture seule** | Le site ne consomme que des endpoints publics GET d'api2. Les nouveaux endpoints sont **ajoutés**, jamais modifiés à la place d'un existant. |
| **app2 / app4 intacts** | Ils ne consomment pas `kpi-layer` avant la bascule. Leur adoption du layer est un incrément distinct (phase 6), testé séparément. |
| **Navigation partielle assumée** | Une entrée de menu dont la page n'est pas encore livrée pointe vers la page legacy équivalente sur `www` (cf. [SITE_NAVIGATION.md](../../../specs/public/SITE_NAVIGATION.md)) : le beta est utilisable à chaque incrément. |
| **Panne isolée** | Si app3 ne démarre pas, seul `beta.*` est indisponible. Son URL n'entre pas dans les smoke tests bloquants avant la bascule. |
| **Prérequis serveur (une fois)** | Enregistrements DNS `beta.preprod` et `beta` ; ajout de `make app3_generate_${ENV}` dans `deploy-wrapper.sh` (dépôt privé `vps-manager`). Procédure : [SITE_PLATFORM.md § 6](../../../specs/public/SITE_PLATFORM.md). |

La **bascule** (phase 5) se réduit alors à faire pointer `www` vers app3, inverser le routeur par défaut et activer l'indexation.

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

**Inscriptions payantes** : aucun paiement n'est traité par KPI. Le formulaire est paramétré en mode « HelloAsso » :
- l'organisateur saisit l'URL de sa billetterie ou de son formulaire HelloAsso ;
- le site affiche soit le **widget HelloAsso intégré** (iframe officielle), soit un bouton vers la page HelloAsso ;
- la liste publique des inscrits reste possible à partir d'un **import CSV** de l'export HelloAsso dans app4.

La synchronisation automatique via l'API HelloAsso (webhooks de paiement) est une amélioration possible, mais hors MVP.

### 5.4 Reprise du contenu WordPress

- **Périmètre** : les **50 articles publiés les plus récents** (`post_status = 'publish'`, tri par `post_date` décroissant) et les pages utiles (sur 43), dont la liste est validée par les rédacteurs.
- **Outil** : commande Symfony `app:import-wordpress --articles=50 --pages=<ids>` lisant la base `dbwp`. Elle convertit le HTML, rapatrie les médias référencés (images, galeries) dans le stockage médias (§ 8) et produit un **rapport** : liens internes cassés, shortcodes non convertis (`[ninja_form]`, `[table]`, `[gallery]`…).
- **Galeries** : les photos sont rapatriées dans `media/content/` sur le serveur et regroupées en albums.
- **Articles non repris** : **redirection 301 vers `/news`**. Le dump WordPress complet (base + `wp-content/uploads`) est archivé.
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
| `/competitions` → `/competitions/{season}?group=` | `kpclassements.php` (sélecteur) |
| `/competitions/{season}/{code}` → `…/games` | `kpmatchs.php` |
| `…/pitches` | `kpterrains.php` |
| `…/info` | `kpdetails.php` |
| `…/progress` | `kpchart.php` (déroulement) |
| `…/phases` | `kpphases.php` |
| `…/ranking` | `kpclassement.php` |
| `…/stats` | `kpstats.php` |
| `…/calendar.ics` | *(nouveau)* abonnement ICS par compétition |
| `/groups/{season}/{code}/games\|pitches` | `kpmatchs.php` / `kpterrains.php` `?Group=…&Compet=*` (vue groupe) |
| `/events/{id}/games\|pitches` ; `/competitions/…?event={id}` | `kp*.php?event=…` |
| `/history/{groupCode}` | `kphistorique.php` |
| `/teams` (recherche), `/teams/{id}` | `kpequipes.php` |
| `/clubs`, `/clubs/{code}` | `kpclubs.php`, `kplogos.php` |
| `/games/{id}` | feuille de match publique (app2 `/game/[id]`) |

Les anciennes URL (`kp*.php?Compet=N1&Saison=2026&Group=N&J=…&lang=en`) sont redirigées en **301** par un middleware serveur Nitro (table de correspondance des paramètres, testée automatiquement). Les liens partagés, les favoris et le référencement sont ainsi préservés.

---

## 7. Travaux api2

### 7.1 Principes

- Endpoints publics **en lecture seule** dans des contrôleurs `Public*Controller`, sans authentification (hors envoi de formulaire).
- **Factoriser** en services (`src/Service/...`) la logique aujourd'hui dans les contrôleurs admin (classements, stats, clubs, compétitions), appelés des deux côtés, sans copier-coller.
- Ne renvoyer que le **publié** (`Publication = 'O'` sur compétition, journée, match, champs *_publi pour les classements), comme `/game-sheet`.
- En-têtes `Cache-Control` et `ETag` ; limitation de débit sur `/search` et sur l'envoi de formulaires.
- **Données personnelles** : exactement les mêmes champs qu'aujourd'hui (nom, prénom, numéro, catégorie, équipe, club ou nation), rien de plus, via des DTO publics dédiés (§ 11). L'évaluation RGPD complète est un chantier ultérieur.
- Symfony reste en **7.4 LTS** : aucun nouveau bundle ne doit tirer Symfony 8.

### 7.2 Endpoints à créer

| Endpoint | Usage |
|---|---|
| `GET /seasons`, `GET /group/{s}/{code}/competitions`, `GET /competition/{s}/{code}[/games\|charts\|ranking\|scorers\|info]`, `GET /event/{id}/competitions` | Résultats (phase 2) — détail, formats et refactorisation des endpoints existants : [API_PUBLIC_RESULTS.md](../../../specs/public/API_PUBLIC_RESULTS.md) |
| `GET /competition/{s}/{code}/calendar.ics`, `GET /gameday/{id}.ics` | Exports et abonnements ICS (phase 3) |
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

Sur le serveur de production, `sources/img/` pèse **≈ 437 Mo**. Seule une partie est versionnée : **1 450 fichiers, ≈ 113 Mo**. Le reste est déjà ignoré par Git (`KIP/players`, `KIP/teams/`, `boats`, `vests`, `helmets`…), mais vit **dans l'arborescence du dépôt**, sans sauvegarde dédiée.

Tous les dossiers ci-dessous sont **alimentés par des uploads** (confirmé) :

| Dossier | Fichiers versionnés | Taille versionnée | Contenu | Cible |
|---|---|---|---|---|
| `img/KIP/` (logo, teams, colors, players…) | 345 | 71 Mo | Logos de clubs, photos d'équipes et de joueurs, couleurs | **Stockage médias** |
| `img/logo/` | 674 | 26 Mo | Logos et bandeaux de compétitions | **Stockage médias** |
| `img/presentations/` | 56 | 4,9 Mo | Visuels de présentation (écrans/TV) | **Stockage médias** |
| `img/schemas/` | 13 | 3,3 Mo | Schémas | **Stockage médias** |
| `img/Nations/` | 71 | 2,2 Mo | Logos d'équipes nationales | **Stockage médias** |
| `img/referees/` | 15 | 0,4 Mo | Arbitres | **Stockage médias** |
| `img/Pays/`, icônes `*.gif`/`*.png` à la racine, `calendar/`, `admin-choice/` | ~250 | < 1 Mo | Ressources de l'application | **Restent versionnés** |

S'y ajoutent les **médias WordPress** repris (§ 5.4) et ceux du nouveau module éditorial (articles, galeries, formulaires).

### 8.2 Cible

- **Un seul stockage médias, hors de l'arborescence du dépôt**, désigné par une variable `HOST_MEDIA_PATH` dans `docker/.env` (même principe que `HOST_WORDPRESS_PATH`) :
  ```
  ${HOST_MEDIA_PATH}/
  ├── img/            (mêmes noms qu'avant : logo/, KIP/, Nations/, presentations/, schemas/, referees/)
  └── content/        (réservé : articles, pages, galeries — dont ex wp-content/uploads — et formulaires)
  ```
  Les dossiers gardent leur nom historique : aucun chemin ni aucune URL ne change. Seul api2 a dû être corrigé :
  sous FrankenPHP, il résolvait mal le chemin de `sources/img/` (paramètre `legacy_document_root`). Le détail d'exploitation est dans [MEDIA_STORAGE.md](../../infrastructure/MEDIA_STORAGE.md).
- **URL et chemins existants préservés** : chaque sous-dossier est **monté à son ancien emplacement** (`/var/www/html/img/logo`, `/var/www/html/img/KIP/teams`…) dans les conteneurs **Apache (`kpi`), `api2` et `event-cache-worker`**, pour les 3 environnements. Le legacy, api2 (qui lit et écrit dans ces dossiers : `img/logo/`, `img/KIP/logo/`, `img/KIP/teams/`) et les PDF fonctionnent sans modification de code. Les nouveaux médias sont servis sous `/media/...`.
- **Pourquoi hors du dépôt** : tant que les fichiers vivent dans le dépôt, une opération Git (`pull`, `checkout` d'une autre branche, `clean`) peut les créer, les écraser ou les supprimer. Hors du dépôt, Git ne peut plus les toucher (§ 8.4).
- **Dev** : `make media_init` (récupère les images depuis l'historique Git et installe les images par défaut), et `make media_sync_from src=…` pour une copie de la prod.

### 8.3 Sauvegarde dédiée des médias

| Élément | Décision |
|---|---|
| Outil | **restic** (chiffré, dédupliqué, incrémental), lancé par cron sur l'hôte |
| Périmètre | `HOST_MEDIA_PATH` complet (et, pendant la transition, `docker/wordpress/wp-content/uploads`) |
| Destination | **Sur le VPS lui-même** pour l'instant : un dépôt restic par instance (`/data/backups/<instance>/media-restic`), distinct de `HOST_MEDIA_PATH` et des dossiers Docker |
| Fréquence / rétention | Quotidienne ; 7 quotidiennes, 4 hebdomadaires, 12 mensuelles |
| Cohérence | Exécutée juste après le dump SQL quotidien, pour que base et médias restent cohérents à la restauration |
| Cibles Makefile | `media_init`, `media_status`, `media_backup`, `media_backup_list`, `media_backup_check`, `media_restore snapshot=…`, `media_sync_from src=…` |
| Vérification | Contrôle hebdomadaire (dimanche 5h) : `restic check` + restauration d'un fichier au hasard + alerte si la dernière sauvegarde a plus de 26 h ; e-mail en cas d'échec (script `media-backup.sh` du dépôt `vps-manager`) |

> ⚠️ Une sauvegarde sur le même VPS protège contre les **erreurs** (suppression, écrasement, mauvaise manipulation), **pas contre la perte du serveur**. L'externalisation est un chantier ultérieur, déjà décidé. Avec restic, elle se limitera à déclarer un second dépôt distant et à y lancer `restic copy`. Aucune refonte ne sera nécessaire.

✅ **En production** depuis la phase 0b (06/10/2026).

### 8.4 Faut-il réécrire l'historique Git ? — Non

**La sortie des médias ne nécessite pas de réécrire l'historique.** C'est un commit ordinaire (`git rm -r --cached` + règles `.gitignore`) : les fichiers ne sont plus suivis à partir de ce commit, mais restent dans les commits passés. Les SHA existants ne changent pas, et les branches en cours restent valides.

**Ce que coûte le fait de ne pas réécrire** : le dossier `.git` garde son poids (≈ 274 Mo aujourd'hui, dont ≈ 113 Mo d'images). C'est acceptable : on ne clone le dépôt que rarement, et un clone partiel (`--filter=blob:none`) contourne le problème si besoin.

**Impacts réels du commit de suppression, et parades** :

| Situation | Effet | Parade |
|---|---|---|
| `git pull` du commit sur un serveur (dev, préprod, prod) | Git **supprime du disque** les fichiers qui étaient suivis | **Copier d'abord** les dossiers vers `HOST_MEDIA_PATH` et activer les montages, **puis** tirer le commit. Procédure pas à pas dans le runbook, environnement par environnement. |
| `checkout` sur un serveur d'une branche antérieure au commit (ex. préprod expérimentale) | Git **recrée** les anciens fichiers dans `sources/img/…` | Sans effet : le conteneur voit le montage `HOST_MEDIA_PATH`, qui masque le dossier du dépôt. Les fichiers disparaissent à nouveau au retour sur une branche récente. |
| Branche en cours qui **ajoute ou modifie** une image de ces dossiers | Conflit *modify/delete* au merge | Garder la suppression, déposer l'image dans le stockage médias. **Au 28/09/2026, aucune des 11 branches distantes ne touche `sources/img/`.** |
| Branche en cours qui ne touche pas ces dossiers | Aucun : la suppression arrive au prochain merge de `main` | — |
| Nouveau clone de dev | Pas d'images | `make media_init` (historique Git + images par défaut) ou `make media_sync_from src=…` |

**Réécriture de l'historique (`git filter-repo`) : déconseillée.** Le seul gain serait de récupérer ≈ 113 Mo dans `.git`. En contrepartie :
- tous les SHA changent ;
- chaque branche, worktree et PR ouverte doit être recréée ou rebasée, et chaque serveur recloné ;
- les liens vers des commits (issues, PR, documentation) sont cassés ;
- les forks et clones existants divergent.

Si elle devenait un jour souhaitable, il faudrait la faire à un moment sans aucune branche ouverte, en chantier séparé.

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

## 10. Identité visuelle — charte FFCK, univers Compétition

Source : *FFCK – Charte graphique Rebranding v2* (avril 2021, Studio Ellair), pages « Univers FFCK », « Typographie », « Interdits » et « Univers Compétition ». Le site suit l'**univers Compétition**. Le bleu marine institutionnel ne sert qu'aux zones qui portent le logo FFCK.

### 10.1 Palette et usage (valeurs RVB = supports numériques)

| Rôle | Couleur | Contraste sur blanc | Contraste sur `#1e1e1c` | Usage proposé |
|---|---|---|---|---|
| **Primaire** | `#357b9c` bleu compétition foncé | 4,70 ✅ AA | 3,55 | Liens, boutons, onglets actifs, titres de section (mode clair) |
| **Primaire clair** | `#69b9e6` bleu compétition | 2,17 ❌ texte | 7,70 ✅ | Bandeaux, aplats, survols, fonds d'en-tête ; **jamais du texte sur blanc** ; primaire en mode sombre |
| **Accent** | `#c94a4c` rouge | 4,60 ✅ AA | 3,63 | « En cours / live », alertes, appels à l'action |
| **Accent foncé** | `#882831` rouge foncé | 8,77 ✅ | 1,90 | Survol des accents, texte d'alerte |
| **Texte** | `#1e1e1c` noir | 16,7 ✅ | — | Texte courant ; fond du mode sombre |
| **Neutre** | `#c6c7c7` gris | 1,69 | 9,86 ✅ | Bordures, séparateurs, états désactivés |
| **Or** (secondaire) | `#e9b410` / `#9a7208` | 1,91 / 4,39 | 8,75 / 3,80 | Médaille d'or, 1<sup>re</sup> place (aplat ; texte en `#9a7208` ou noir) |
| **Vert** (secondaire) | `#209452` / `#186a32` | 3,88 / 6,67 ✅ | 4,31 / 2,50 | Victoire, qualifié, formulaire ouvert |
| **Marine FFCK** (univers institutionnel) | `#20265b` | 14,1 ✅ | 1,18 | Pied de page et bandeau institutionnel portant le logo FFCK |

- Les **gammes Tailwind 50→950** (`--color-kpi-primary-*`, `--color-kpi-accent-*`…) sont générées à partir de ces ancres dans `kpi-layer` et exposées à Nuxt UI (`app.config.ts` → `ui.colors.primary = 'kpi-primary'`, etc.).
- La palette n'a **pas de bronze** : on conserve le bronze actuel des classements (`img/BRONZE.png`).
- **Mode sombre** : fond `#1e1e1c`, primaire `#69b9e6`, accent `#c94a4c` réservé aux aplats (contraste 3,6 : insuffisant pour du petit texte).
- **app2 et app4** reçoivent la même palette par le layer ; app4 peut garder une densité « outil » tout en partageant les couleurs.

### 10.2 Typographie

| Usage | Police de la charte | Web |
|---|---|---|
| Titres, sous-titres, chiffres forts (scores, rangs) | **Agency FB** (Thin, Light, Regular, Bold) | ✅ Déjà utilisée dans app4 **sous licence FFCK** (`sources/app4/public/fonts/agencyfb.ttf`, déclarée dans `assets/css/admin.css` et `tailwind.config.ts`). Elle passe dans `kpi-layer` (convertie en WOFF2) pour être partagée par app2, app3 et app4. |
| Texte courant | **Raleway** (Thin → Black, italiques) | Libre (OFL), **auto-hébergée** dans `kpi-layer` (pas d'appel à Google Fonts, cohérent avec l'approche RGPD) |

Les chiffres de score et de classement utilisent des **chiffres tabulaires** (`font-variant-numeric: tabular-nums`) pour l'alignement.

### 10.3 Logo et pictogrammes

- **Logo FFCK** : jamais modifié (pas de contour, d'ombre, de déformation, de recoloration). Logo couleur sur fond clair, blanc sur fond foncé. Choisir la **version responsive** adaptée à la taille (≥ 100 px, 100–50 px, 50–25 px, < 25 px) et respecter la **zone de respiration**. Aucun fichier vectoriel officiel n'est disponible : on utilise les logos existants du dépôt (`sources/img/LOGO_FFCK.png`, logos KPI) à leur meilleure résolution, sans jamais les redessiner. Les versions vectorielles seront intégrées si la FFCK les fournit un jour.
- **Marque « kayak-polo.info »** : on reprend le co-marquage actuel (logo FFCK + nom du site). Aucune règle officielle n'existe.
- **Pictogrammes** : la charte fournit un pictogramme **Kayak-Polo** et une série de pictogrammes web de style amérindien (Partager, Rechercher, Lieu, Temps, Statistiques, Photos, Retour, Paramètres…). Ils ne sont **pas disponibles en fichiers** : l'interface utilise **Heroicons** (déjà dans app2/app4). Les pictogrammes FFCK pourront être ajoutés plus tard comme collection d'icônes personnalisée Nuxt Icon, s'ils sont obtenus.
- La possibilité de **déformer Agency FB** (texte incurvé) est réservée aux visuels éditoriaux (images d'articles), pas à l'interface.

---

## 11. Données personnelles

L'évaluation RGPD complète est un **chantier distinct, ultérieur**. Le principe applicable dès maintenant est la **limitation des données exposées**. Les seules données personnelles publiées (pages, API publiques, flux, exports ICS, recherche) sont :

| Donnée | Publiée |
|---|---|
| Nom, prénom de l'athlète | ✅ |
| Numéro de joueur | ✅ |
| Catégorie (âge) | ✅ si pertinente pour la compétition |
| Équipe, club ou nation | ✅ |
| Statistiques de match (buts, cartons) rattachées à ces données | ✅ comme aujourd'hui |
| Date de naissance, n° de licence, sexe hors libellé de catégorie, photo individuelle, coordonnées | ❌ |

Règles de mise en œuvre :
- **DTO publics dédiés** dans api2 : les endpoints publics ne sérialisent jamais une entité complète. Un **test automatisé** vérifie la liste des champs exposés.
- **Pas de page joueur** ; la **recherche globale n'indexe pas les personnes**.
- Formulaires : minimisation des champs, mention d'information, purge après l'événement. Pour les inscriptions payantes, les données de paiement restent chez **HelloAsso**.
- Matomo en mode sans cookie ; polices et icônes auto-hébergées (aucun appel à un tiers).
- Le chantier RGPD ultérieur couvrira : registre des traitements, base légale, procédure d'opposition (masquage d'un athlète), durées de conservation.

---

## 12. Phasage

| Phase | Contenu | Livrable | Charge indicative |
|---|---|---|---|
| **0a. Nettoyage app3** ✅ | Tag d'archive, suppression de l'ancien app3 et de ses références (§ 3) | Commit dédié | fait (tag publié, mergé et déployé) |
| **0b. Médias** ✅ | Stockage non versionné, montages, `git rm --cached`, sauvegarde restic (§ 8) — [MEDIA_STORAGE.md](../../infrastructure/MEDIA_STORAGE.md) | Médias hors Git et sauvegardés | fait : dev, préprod et prod migrés, cron de sauvegarde actif (06/10/2026) — [checklist archivée](../../archive/completed-migrations/MERGE_CHECKLIST_APP3_MEDIA.md) |
| **0c. Cadrage** 🟡 | Jetons de la charte FFCK univers Compétition (§ 10), polices, **specs du template, des menus et du socle** ; table de redirections et validation des pages reprises, affinées à chaque phase. Pas de maquettes séparées : le beta tient lieu de maquette, validée incrément par incrément | [Specs du socle](../../../specs/public/README.md) | specs du socle rédigées (07/10/2026) |
| **1. Socle** 🟡 | `kpi-layer` (jetons, polices, client api2), squelette app3 SSR (layout, menus, i18n, SEO, `/healthz`), service Docker dans les 3 compose, cibles Makefile, CI (lint, typecheck, 49 tests, build), déploiement sur **`beta.*`** | Site navigable sur `beta.*`, menus pointant vers le legacy | **code livré (07/10/2026)** ; reste l'activation serveur : DNS + ligne `deploy-wrapper.sh` ([SITE_PLATFORM.md § 6](../../../specs/public/SITE_PLATFORM.md)) |
| **2. Résultats** | Pages compétition, groupe, événement (games, pitches, info, progress, phases, ranking, stats) avec les composants d'app2 passés au layer ; endpoints `competition/*` ([specs](../../../specs/public/README.md)) | Parité avec `kpmatchs` / `kpclassement` / … | specs proposées (08/10/2026), en attente de validation ; 3–4 sem. |
| **3. Transverse** | Calendrier, ICS, historique, équipes, clubs (+ carte), logos, recherche globale | Parité avec le reste des `kp*.php` | 3–4 sem. |
| **4a. Éditorial** | Articles, pages, menu, médias, galeries, SEO, partage, blocs d'accueil, droit Rédacteur, RSS ; import des ~50 articles et des pages | CMS opérationnel, contenu repris | 3–4 sem. |
| **4b. Formulaires** | Constructeur, inscriptions, notifications, journal des e-mails, export, liste publique, anti-spam, mode HelloAsso (widget/lien + import CSV) | Remplacement de Ninja Forms / TablePress | 2 sem. |
| **5. Bascule** | Vérification des champs exposés (§ 11), `www` → app3, inversion du routage Traefik, indexation, redirections 301 (legacy + WordPress), sitemap, Search Console, suivi Matomo des 404 | `www.kayak-polo.info` servi par app3 | 1 sem. + suivi |
| **6. Décommissionnement** | WordPress en lecture seule puis arrêt (`dbwp`), suppression des `kp*.php`, `json-*.php`, templates et CSS « material » ; adoption de `kpi-layer` par app2 et app4 | Legacy public retiré | 1 sem. |
| **7. Écrans / embeds** | `/embed/...` + thèmes, redirection des `frame_*.php` | Fin de la dépendance Apache pour l'affichage | 2–3 sem. |

**Dépendances** :
- 0a précède 1 ✅. 0b précède 4a ✅.
- Chaque page des phases 2 à 4 commence par **sa spec** dans `DOC/specs/public/`, validée avant l'implémentation.
- La mise à jour Mercure du live dépend du chantier scoring ; les phases 2–3 livrent d'abord avec du polling.
- Tant que la phase 5 n'a pas eu lieu, le site actuel reste intact (§ 4.3).

### 12.1 Méthode de travail (ce chantier et les suivants)

1. **Spec d'abord** : une spec par élément (template, navigation, page, fonctionnalité) dans [DOC/specs/public/](../../../specs/public/README.md), avec des critères d'acceptation testables. Pas de code sans spec validée.
2. **TDD** : les critères d'acceptation deviennent des tests (Vitest côté Nuxt, PHPUnit côté api2), écrits avant le code.
3. **Clean code** : DRY, KISS, SOLID, YAGNI, nommage explicite, fonctions courtes. Règles et *Definition of Done* dans [CLEAN_CODE.md](../../guides/CLEAN_CODE.md), référencé par `CLAUDE.md` pour tous les chantiers.
4. **Petits incréments** : une PR = une spec (ou une partie), mergeable et déployable seule, sans impact hors `beta.*`.

---

## 13. Questions restantes

### Décisions prises

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
| Q10 | Identité **modernisée**, partiellement alignée sur app2/app4, **charte FFCK univers Compétition** (§ 10) |
| Q11 | Recherche globale et ICS par compétition **inclus** ; pages joueur **exclues** |
| Q12 | Données exposées limitées (§ 11) ; évaluation RGPD = chantier ultérieur |
| Q-A | Inscriptions parfois payantes → **HelloAsso** pour le paiement (§ 5.3) |
| Q-B | Galeries **stockées sur le serveur** ; `sources/img/` pèse ≈ 437 Mo en prod |
| Q-C | Reprise des **50 articles publiés les plus récents** ; les autres → **301 vers `/news`** |
| Q-D | Charte FFCK Rebranding v2, **univers Compétition** |
| Q-E | Sauvegardes **sur le VPS** pour l'instant ; externalisation = chantier ultérieur |
| Q-F | `presentations/`, `schemas/`, `Nations/`, `referees/` = **uploads** → stockage médias |
| Q-G | RGPD : chantier distinct ultérieur ; principe de limitation appliqué dès maintenant |
| — | Brique distincte **app3** (ancien app3 supprimé) ; **URL en anglais** ; **pas de réécriture de l'historique Git** (§ 8.4) ; live dans le chantier scoring |
| Q-H | Pas de fichiers de marque disponibles : logos raster existants, Heroicons, bronze actuel conservé |
| Q-I | **Agency FB** déjà disponible dans app4 sous licence FFCK → partagée via `kpi-layer` |

Aucune question ouverte à ce stade.

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
