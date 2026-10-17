# Spec — Mise à jour automatique des apps (app2 & app4)

**Statut** : **app4 fait** (2026-10-17, §5) — **app2 et Nginx restent à faire** (§3, §4)
**Périmètre** : `sources/app2`, `sources/app4`, `docker/config/nginx-*.conf`
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
| Re-check SW (60 min / visibilité / online) | ✅ `usePwa.ts` | ❌ |
| Bandeau « nouvelle version » | ❌ `needRefresh` exposé mais **jamais affiché** | ❌ |
| Nginx HTML | `max-age=300` | `max-age=300` |
| Squelette online/offline | ✅ | ✅ `useOnlineStatus()` |

Deux trous : **app2 ne prévient jamais l'utilisateur** (le SW `skipWaiting` basculait
en silence, risque de casse en pleine navigation) ; **app4 n'a aucun mécanisme**.

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
  **Vérification** : après un build, `grep -o '.\{40\}skipWaiting.\{40\}'
  .output/public/sw.js` doit montrer le `addEventListener("message", …)`, pas un appel
  direct.
- **Icônes PWA app4** : **copie des icônes app2** (`pwa-192x192.png`, `pwa-512x512.png`).

---

## 3. Étape 1 — Nginx : revalidation de `index.html` et du SW

Fichiers : `docker/config/nginx-app2-prod.conf`, `nginx-app2.conf`,
`nginx-app4-prod.conf`, `nginx-app4.conf`.

Principe : `index.html` et les fichiers du service worker sont **toujours revalidés**
(`no-cache` = le navigateur peut stocker mais **doit** revalider ; réponse `304`
quasi gratuite si inchangé). Les assets hashés restent **immuables 1 an**.

```nginx
# index.html : toujours revalider (supprime la fenêtre de 5 min)
location = /index.html {
    add_header Cache-Control "no-cache, must-revalidate";
}

# Service worker + registre + manifest : jamais en cache dur,
# sinon la mise à jour ne se propage pas.
location ~* (sw\.js|workbox-.*\.js|registerSW\.js|manifest\.webmanifest)$ {
    add_header Cache-Control "no-cache, must-revalidate";
}

# Assets hashés : inchangés, cache agressif
location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

⚠️ **Ordre des `location`** : le bloc SW (regex spécifique) doit être placé **avant**
le bloc `.js` générique, sinon `sw.js`/`workbox-*.js` seraient rattrapés par
`immutable 1y`. `try_files … /index.html` reste inchangé.

Appliquer ensuite : `make docker_dev_restart` (ou `preprod`/`prod`).

---

## 4. Étape 2 — app2 : afficher le bandeau « nouvelle version »

Le composable `sources/app2/composables/usePwa.ts` expose déjà
`needRefresh` / `updateApp` — il manque uniquement l'UI.

- **Nouveau composant** `sources/app2/components/PwaUpdatePrompt.vue` : bandeau /
  toast discret « Une nouvelle version est disponible » + bouton **Recharger**,
  monté sur `needRefresh`, appelant `updateApp()` (→ `updateServiceWorker()` + reload).
- **Montage** : dans `sources/app2/app.vue`, à l'intérieur de `<UApp>`.
- **i18n** : clés `pwa.update_available` + `pwa.reload` dans `en.json` / `fr.json`
  d'app2. En anglais : « Update available » (règle i18n projet : anglais explicite).
- **`nuxt.config.ts`** : retirer `skipWaiting: true` et `clientsClaim: true` du bloc
  `pwa.workbox`.

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

## 6. Étape 4 — Déploiement non destructif (exploitation)

Chaque build vit dans son propre sous-dossier `/_nuxt/v<timestamp>/`. Si le
déploiement **efface** l'ancien buildId alors qu'un client tourne encore dessus, ce
client casse malgré tout le reste.

**Reco** : déployer **sans `--delete`** (rsync) pour conserver l'ancien build le temps
que les clients migrent, puis **purger les vieux `v<timestamp>` après quelques jours**.
Aucune modif de code Makefile requise — note d'exploitation uniquement.

---

## 7. Ordre d'exécution & vérification

1. ⬜ Nginx (§3) → `make docker_*_restart`.
2. ✅ app4 : config + composable + icônes + bandeau + i18n (§5).
3. ⬜ app2 : bandeau + retrait `skipWaiting` (§4).
4. Lint : `make app2_lint`, `make app4_lint` ; build de contrôle app4 (fait pour app4).
5. ⬜ Note de déploiement (§6).

⚠️ **Tant que l'étape 1 n'est pas faite, l'étape 2 n'est qu'à moitié efficace** :
`index.html` et `sw.js` d'app4 restent servis avec `max-age=300`, donc la détection
d'une nouvelle version peut traîner jusqu'à 5 minutes.

**Hors périmètre** : logique métier, Makefile (hors ajout de dépendance).
**Dev servers non lancés par l'assistant** — démarrés par l'utilisateur via
`make app2_dev` / `make app4_dev`.

---

## 8. Comment tester

- **Chunk manquant** : déployer build B, puis avec un onglet encore sur build A,
  naviguer → le bandeau doit apparaître ; « Recharger » recharge sans erreur.
- **PWA smartphone** : installer l'app, déployer, rouvrir → bandeau au
  `visibilitychange` (retour au premier plan).
- **Nginx** : `curl -I https://.../index.html` doit renvoyer
  `Cache-Control: no-cache`, et `curl -I .../sw.js` de même ; un asset hashé doit
  renvoyer `immutable`.
