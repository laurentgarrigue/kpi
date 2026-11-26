# Spec — Mise à jour automatique des apps (app2 & app4)

**Statut** : **app4 fait** (2026-10-17, §5) — **app2 et Nginx faits** (2026-11-26, §3, §4, chantier C8 du
[plan de communication 2027](../developer/in-progress/PLAN_COMMUNICATION_SAISON_2027.md)) ; reste la validation
sur smartphone en preprod (PWA-09, PWA-10) et `make nginx_static_reload` après chaque déploiement de la conf.
**Périmètre** : `sources/app2`, `sources/app4`, `docker/config/nginx-static*.conf`
**Objectif** : garantir que tout utilisateur (PC, smartphone, PWA installée) tourne
toujours sur la dernière version déployée, sans écran blanc ni chunk manquant.

---

## 1. Contexte & problème

app2 et app4 sont des **SPA Nuxt statiques** (`ssr: false`) générées puis servies par
Nginx. Deux pièges classiques de ce type de déploiement :

1. **`index.html` périmé** — c'est le maillon faible, pas le JS. `index.html` référence
   les chunks du dernier build ; s'il est servi depuis un cache navigateur / un ancien
   service worker, il pointe vers des chunks qui n'existent plus sur le serveur →
   écran blanc / `Failed to fetch dynamically imported module`.
2. **Onglet resté ouvert** — un utilisateur qui garde l'app ouverte des heures ne
   récupère jamais la nouvelle version sans un signal explicite.

### État des lieux (avant cette spec)

| | app2 | app4 |
|---|---|---|
| Module PWA (`@vite-pwa/nuxt`) | ✅ `autoUpdate` | ❌ aucun |
| `buildId` cache-busting (`/_nuxt/v<ts>/`) | ✅ | ❌ (hash de contenu seul) |
| Re-check SW (60 min / visibilité / online) | ⚠️ `usePwa.ts` écrit mais **jamais appelé** | ❌ |
| Bandeau « nouvelle version » | ⚠️ `UpdatePrompt.vue` monté mais **jamais affiché** (`showUpdate` jamais vrai) | ❌ |
| Nginx HTML | `max-age=300` | `max-age=300` |
| Nginx en-têtes de sécurité | ❌ jamais envoyés (cf. §3) | ❌ idem |
| Squelette online/offline | ✅ `useOnlineStatus()` | ✅ `useOnlineStatus()` |

Constat 2026-11-26 sur app2 : aucun composant n'appelle `usePwa()`. Le worker est enregistré par le plugin du
module (`client.registerPlugin` actif par défaut) en `'autoUpdate'`, donc il **bascule en silence**
(`skipWaiting` + `clientsClaim`) : risque de casse en pleine navigation, et aucun bandeau.

---

## 2. Décisions retenues

- **app4** : PWA complète, en miroir d'app2.
- **Comportement de MAJ** : **bandeau explicite « Recharger », sans `skipWaiting`**
  (pattern *prompt-to-update*). Le nouveau service worker attend l'action de
  l'utilisateur au lieu de prendre le contrôle en plein milieu d'une action.
  → `skipWaiting`/`clientsClaim` **retirés d'app2** et **non activés sur app4**.
- ⚠️ **Comment l'obtenir réellement (constat 2026-10-17)** : c'est
  **`registerType: 'prompt'`**, et non `'autoUpdate'`. Avec `'autoUpdate'`,
  `@vite-pwa/nuxt` **force** `workbox.skipWaiting` et `workbox.clientsClaim` dès que
  `client.registerPlugin` est actif (son `doSetup`), quelles que soient les options
  `workbox` écrites dans `nuxt.config.ts` : le `sw.js` généré appelle alors
  `self.skipWaiting()` + `clientsClaim()` à l'activation. Avec `'prompt'`,
  `skipWaiting()` n'est appelé qu'en réponse au message `SKIP_WAITING` émis par le
  bouton « Recharger » — ce qui est exactement le contrat voulu.
  **Vérification automatique** : `node scripts/ci/check-sw-prompt.mjs <app>/.output/public/sw.js`
  (job CI `build-nuxt`, après `nuxt build`).
- **Icônes PWA app4** : **copie des icônes app2** (`pwa-192x192.png`, `pwa-512x512.png`).
- **En-têtes de sécurité Nginx** : corrigés dans C8 (décision du 2026-11-26), puisqu'ils vivent sur les mêmes
  lignes que le cache.
- **Manifest app2** : `<link rel="manifest">` injecté par `<NuxtPwaManifest />` comme dans app4, au lieu du lien
  écrit à la main dans `nuxt.config.ts` (décision du 2026-11-26).

---

## 3. Étape 1 — Nginx : revalidation de `index.html` et du SW, en-têtes de sécurité

Fichiers : `docker/config/nginx-static-prod.conf` (preprod + prod) et `nginx-static.conf` (dev), un bloc
`server` par app (app2 sur `Host: app.*`, app4 en `default_server`, `/admin2` déjà retiré par Traefik).

Principe : `index.html` et les fichiers du service worker sont **toujours revalidés**
(`no-cache` = le navigateur peut stocker mais **doit** revalider ; réponse `304`
quasi gratuite si inchangé). Les assets restent **immuables 1 an**.

**Piège de l'héritage `add_header`** : un `add_header` écrit dans un `location` **remplace** tous ceux du bloc
`server`. Les deux `location` principaux en avaient un (le cache), donc `X-Frame-Options`,
`X-Content-Type-Options` et `X-XSS-Protection` n'étaient **jamais** envoyés. D'où la mise en œuvre retenue :

- un `map $uri $kpi_static_cache_control` (contexte `http`, le fichier est inclus dans `conf.d/`) donne la
  politique de cache selon le chemin ;
- **aucun `add_header` dans les `location`** : tous les en-têtes sont au niveau `server`, donc hérités partout ;
  les en-têtes de sécurité portent `always` (aussi sur 403/404), le `Cache-Control` non (pas de cache dur
  sur une erreur) ;
- plus de directive `expires` (elle émettrait un second `Cache-Control`).
- Pas de fichier `include` séparé : il faudrait un nouveau montage dans les trois compose, et un conteneur
  redémarré sans être recréé ne trouverait pas le fichier (Nginx refuserait de démarrer : app2 et app4 hors
  ligne).

| Chemin (`$uri`, après la redirection interne vers `/index.html`) | prod / preprod | dev |
|---|---|---|
| `sw.js`, `registerSW.js`, `workbox-*.js`, `manifest.webmanifest` | `no-cache, must-revalidate` | idem |
| `*.html` (donc toute route SPA) et tout le reste | `no-cache, must-revalidate` | `no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0` |
| `*.{js,css,jpg,jpeg,png,gif,webp,ico,svg,woff,woff2,ttf,eot}` | `public, max-age=31536000, immutable` | `public, max-age=3600` |

Le `location` des assets est conservé **sans** `add_header` : il n'a pas de `try_files … /index.html`, donc un
chunk absent répond **404** et non `index.html` (qui ferait échouer l'import avec une erreur MIME au lieu de
déclencher le rechargement automatique de Nuxt, §6).

Appliquer : `make nginx_static_reload` sur chaque environnement (valide la conf puis recharge, sans coupure ;
Nginx ne relit pas seul un fichier monté — un build seul n'en a pas besoin). `make docker_<env>_restart` convient aussi.

Test d'intégration : `node --test scripts/ci/nginx-static.test.mjs` démarre le binaire `nginx` local sur chaque
fichier de conf avec des fixtures (`make ci_scripts_test`, job CI `lint-docker`).

---

## 4. Étape 2 — app2 : prompt-to-update réellement câblé

- **`sources/app2/nuxt.config.ts`** :
  - `registerType: 'prompt'` ; retirer `skipWaiting: true` et `clientsClaim: true` du bloc `pwa.workbox` ;
  - `client: { installPrompt: false, registerPlugin: false }` (c'est `usePwa` qui enregistre ;
    `AppInstallPrompt.vue` gère l'installation lui-même) et `devOptions.enabled: false` ;
  - `navigateFallbackDenylist` : ajouter `/^\/api2\//` (app2 lit l'api2) à `/^\/api\//` ;
  - retirer le `<link rel="manifest">` écrit à la main dans `app.head` (remplacé par `<NuxtPwaManifest />`).
- **`sources/app2/composables/usePwa.ts`** : port de celui d'app4. L'état online/offline n'est **pas** redéfini,
  il est lu depuis `useOnlineStatus()` ; re-vérification au chargement, au retour au premier plan, au retour en
  ligne et toutes les 60 min ; `updateApp()` → `updateServiceWorker(true)` (envoie `SKIP_WAITING` puis recharge)
  quand un worker attend et que la page est contrôlée, sinon simple rechargement (PWA-06b, `utils/pwaUpdate.ts`).
- **`sources/app2/components/UpdatePrompt.vue`** (existant, réutilisé — pas de second composant) : affiché sur
  `needRefresh`, bouton « Appliquer maintenant ! » → `updateApp()`. « Plus tard » et la croix masquent le
  bandeau pour la page en cours : le worker reste en attente et s'active de lui-même quand tous les onglets
  sont fermés. Reste monté dans `layouts/default.vue` (seul layout d'app2).
- **i18n** : clés `Update.*` existantes (`Message`, `Description`, `Button`, `Later`), convention PascalCase
  d'app2 — aucune clé nouvelle.
- **`sources/app2/app.vue`** : `<NuxtPwaManifest />` dans `<UApp>`.
- **Tests** : projet Vitest `nuxt` ajouté à app2 (comme app4) pour le composant.

---

## 5. Étape 3 — app4 : PWA complète (miroir app2) — ✅ fait le 2026-10-17

1. ✅ **Dépendance** `@vite-pwa/nuxt` (déjà au `package.json` d'app4).
2. ✅ **`sources/app4/nuxt.config.ts`** :
   - `const buildId = v${Date.now()}` + `app.buildAssetsDir = /_nuxt/${buildId}/`.
   - `@vite-pwa/nuxt` dans les `modules`.
   - Bloc `pwa` **`registerType: 'prompt'`** (cf. §2 : `'autoUpdate'` forcerait
     `skipWaiting`), `base`/`scope` = `/admin2/`,
     `navigateFallback: '/admin2/index.html'`, `cacheId: kpi-app4-${buildId}`,
     `runtimeCaching` NetworkFirst sur la navigation et sur `/_nuxt/*.{js,css}`,
     CacheFirst sur images/polices, `navigateFallbackDenylist` sur `/api2/` et `/api/`,
     `injectRegister: false` + `client.registerPlugin: false` (c'est `usePwa` qui
     enregistre).
   - Manifest : nom « KPI Admin », `theme_color: '#1e40af'`, icônes 192/512 (+ maskable).
3. ✅ **Icônes** `pwa-192x192.png` + `pwa-512x512.png` reprises d'app2 dans
   `sources/app4/public/`.
4. ✅ **Composable** `sources/app4/composables/usePwa.ts` : port de celui d'app2 ; l'état
   online/offline **n'est pas redéfini**, il est lu depuis `useOnlineStatus()` (seule
   source de vérité), et `usePwa` n'ajoute que la re-vérification du worker.
5. ✅ **Bandeau** `sources/app4/components/PwaUpdatePrompt.vue`, monté dans `app.vue`,
   clés i18n `pwa.update_available` / `pwa.reload` (fr/en).
6. ✅ **`<head>`** app4 : `apple-touch-icon`, `mobile-web-app-capable`,
   `apple-mobile-web-app-capable`, `apple-mobile-web-app-status-bar-style`
   (le `<link rel="manifest">` est injecté par `<NuxtPwaManifest />`, déjà dans `app.vue`).

**Validation exécutée** : `npx eslint .` (0 erreur), `npm run build` (succès),
`manifest.webmanifest` et `sw.js` relus dans `.output/public/`, `npm test` (81 tests).
**Non vérifié** : le comportement en navigateur réel (installation, bandeau au
`visibilitychange`) — cf. §8, à faire côté dev.

---

## 6. Étape 4 — Déploiement : l'ancien build disparaît

`make app2_generate_*` / `app4_generate_*` lancent `nuxt generate` **sur place** : `.output/` est recréé en
entier, donc l'ancien `/_nuxt/v<timestamp>/` **disparaît à chaque build** (la recommandation précédente,
« rsync sans `--delete` », ne correspond pas au déploiement réel). Ce qui protège un client resté sur l'ancien
build :

1. le service worker en attente garde en cache (`assets-js-css`, NetworkFirst) les chunks déjà chargés ;
2. un chunk jamais chargé répond **404** (§3), et Nuxt **recharge la page** sur l'erreur de chunk à la
   navigation (`experimental.emitRouteChunkError: 'automatic'`, valeur par défaut — ne pas la changer).

Aucune modification du Makefile.

---

## 7. Ordre d'exécution & vérification

1. ✅ Nginx (§3) — à appliquer par `make nginx_static_reload` après le déploiement de chaque environnement.
2. ✅ app4 : config + composable + icônes + bandeau + i18n (§5).
3. ✅ app2 : prompt-to-update + manifest (§4), et PWA-06b corrigé dans app4.
4. Lint, tests et build : `make app2_lint`, `make app2_test`, `make ci_scripts_test` ; build de contrôle app2.
5. ⬜ Validation navigateur réel en preprod (§8, critères PWA-08 à PWA-10).

**Hors périmètre** : logique métier, Makefile (hors cible de test).
**Dev servers non lancés par l'assistant** — démarrés par l'utilisateur via
`make app2_dev` / `make app4_dev`.

---

## 8. Critères d'acceptation

Automatisés (test d'intégration Nginx `scripts/ci/nginx-static.test.mjs`, tests Vitest d'app2, script
`scripts/ci/check-sw-prompt.mjs`) :

- **PWA-01** — `/index.html`, `/` et une route profonde (`/games/123`) renvoient `200`, le HTML de l'app et **un
  seul** `Cache-Control: no-cache, must-revalidate` (prod/preprod), pour app2 (`Host: app.*`) et app4.
  En dev : `no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0`.
- **PWA-02** — `/sw.js`, `/registerSW.js`, `/workbox-<hash>.js` et `/manifest.webmanifest` renvoient
  `Cache-Control: no-cache, must-revalidate`, en prod comme en dev.
- **PWA-03** — un asset `/_nuxt/v<ts>/*.js` et une image renvoient `public, max-age=31536000, immutable` en prod
  (`public, max-age=3600` en dev) ; un chunk absent renvoie **404**, pas `index.html`.
- **PWA-04** — toute réponse, `200`, `403` (fichier caché) ou `404`, porte `X-Frame-Options: SAMEORIGIN`,
  `X-Content-Type-Options: nosniff` et `X-XSS-Protection: 1; mode=block`.
- **PWA-05** — le `sw.js` d'app2 et celui d'app4 générés n'appellent `skipWaiting()` que dans le gestionnaire
  du message `SKIP_WAITING`, et jamais `clientsClaim()`.
- **PWA-06** — le bandeau d'app2 n'est affiché que si `needRefresh` est vrai ; le bouton appelle `updateApp()` ;
  « Plus tard » le masque.
- **PWA-06b** — si aucun worker n'attend, ou si la page n'est contrôlée par aucun worker (première visite :
  sans `clientsClaim`, le worker du build suivant s'active directement, mais `vite-plugin-pwa` lève quand même
  `needRefresh`), le bouton **recharge la page** au lieu d'envoyer `SKIP_WAITING` dans le vide (constat
  2026-11-26 en Chromium : clic sans effet). Même correctif dans app4 (`utils/pwaUpdate.ts`, testé dans les
  deux apps).
- **PWA-07** — l'`index.html` généré d'app2 contient **un seul** `<link rel="manifest">`.

**Validation exécutée le 2026-11-26** (branche `feat/app2-pwa-auto-update`) : `nginx-static.test.mjs` 16/16
(nginx 1.24 local), `check-sw-prompt` rouge sur le `sw.js` d'app2 avant (`self.skipWaiting(),e.clientsClaim()`),
vert après et sur app4 ; app2 27 tests, app4 682 tests, ESLint 0 erreur ; `nuxt generate` d'app2. En Chromium
(Playwright), build A servi par `nginx-static-prod.conf`, puis remplacement par le build B : un seul
`<link rel="manifest">`, bandeau affiché au retour au premier plan, **aucun rechargement sans clic**, nouveau
worker en attente, clic → build B chargé ; même résultat en première visite après le correctif PWA-06b (avant :
clic sans effet). PWA-08 est donc couvert en Chromium de bureau ; PWA-09 à PWA-11 restent manuels.

Manuels (navigateur réel, preprod) :

- **PWA-08** — onglet ouvert sur le build A, déploiement du build B : le bandeau apparaît au retour au premier
  plan, au retour en ligne, ou au plus tard après 60 min ; **aucun rechargement sans clic** ; le bouton charge B
  sans écran blanc.
- **PWA-09** — PWA installée sur smartphone : à la réouverture après un déploiement, le bandeau s'affiche.
- **PWA-10** — après suppression de l'ancien build, la navigation vers une route jamais chargée recharge la page
  sur B au lieu d'afficher `Failed to fetch dynamically imported module`.
- **PWA-11** — en dev (`make app2_dev`), aucun service worker n'est enregistré.

Contrôle à la main côté serveur : `curl -sI https://app.<domaine>/` et `curl -sI https://app.<domaine>/sw.js`
(`no-cache`), `curl -sI https://<domaine>/admin2/` (idem), un asset `/_nuxt/v…/…js` (`immutable`).
