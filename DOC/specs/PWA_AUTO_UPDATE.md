# Spec — Mise à jour automatique des apps (app2 & app4)

**Statut** : à implémenter
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

## 5. Étape 3 — app4 : PWA complète (miroir app2)

1. **Dépendance** : `make app4_npm_add_dev package=@vite-pwa/nuxt`
   (⚠️ jamais un `npm install` sauvage — cf. règles de build app2/app4).
2. **`sources/app4/nuxt.config.ts`** :
   - `const buildId = v${Date.now()}` + `app.buildAssetsDir = /_nuxt/${buildId}/`.
   - Ajouter `@vite-pwa/nuxt` aux `modules`.
   - Bloc `pwa` `registerType: 'autoUpdate'`, **sans `skipWaiting`/`clientsClaim`**,
     avec `base`/`scope` = `/admin2/` (baseUrl app4),
     `navigateFallback: '/admin2/index.html'`,
     `cacheId: kpi-app4-${buildId}`,
     `runtimeCaching` NetworkFirst sur la navigation,
     `navigateFallbackDenylist` sur `/api2/` et `/api/`.
   - Manifest : nom « KPI Admin », `theme_color: '#1e40af'`, icônes 192/512.
3. **Icônes** : copier `pwa-192x192.png` + `pwa-512x512.png` d'app2 vers
   `sources/app4/public/`.
4. **Composable** `sources/app4/composables/usePwa.ts` : port de celui d'app2,
   **fusionné proprement avec `useOnlineStatus()`** existant (pas de duplication de la
   logique online/offline).
5. **Bandeau** `sources/app4/components/PwaUpdatePrompt.vue` + montage dans `app.vue`,
   i18n `fr`/`en`.
6. **`<head>`** app4 : ajouter `manifest`, `apple-touch-icon`, meta
   `mobile-web-app-capable` / `apple-mobile-web-app-capable` (theme-color `#1e40af`
   déjà présent).

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

1. Nginx (§3) → `make docker_*_restart`.
2. app4 : config + composable + icônes + bandeau + i18n (§5).
3. app2 : bandeau + retrait `skipWaiting` (§4).
4. Lint : `make app2_lint`, `make app4_lint` ; build de contrôle app4.
5. Note de déploiement (§6).

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
