# KPI Live - App2 Live

Application Nuxt 4 pour l'affichage en temps réel des matchs de kayak-polo. Cette application est une réécriture moderne de `app_live_dev` utilisant les technologies les plus récentes.

## 🚀 Technologies

- **Nuxt 4** - Framework Vue.js avec SSR/SSG
- **Vue 3** - Composition API & Script Setup
- **TypeScript** - Typage statique
- **Pinia** - State management moderne (remplace Vuex)
- **Tailwind CSS v4** - Utility-first CSS (remplace Bootstrap)
- **Dexie** - IndexedDB pour le cache local
- **@stomp/stompjs** - Communication temps réel WebSocket/STOMP
- **Day.js** - Manipulation de dates
- **Nuxt UI** - Composants UI préfabriqués
- **@nuxtjs/i18n** - Internationalisation (fr/en)
- **@vite-pwa/nuxt** - Progressive Web App
- **animate.css** - Animations CSS

## 📦 Installation

### Via Makefile (recommandé)

```bash
# Initialiser les fichiers d'environnement
make init_env_app2_live

# Installer les dépendances NPM
make npm_install_app2_live

# Lancer le serveur de développement
make run_dev_live
```

### Manuellement (si pas de Docker)

```bash
cd sources/app2_live

# Copier les fichiers d'environnement
cp .env.development.dist .env.development
cp .env.production.dist .env.production

# Installer les dépendances
npm install

# Lancer le serveur de développement
npm run dev
```

## 🎯 Utilisation

### Page d'accueil

Accédez à `/app2_live` pour voir la page d'accueil avec les instructions d'utilisation.

### Affichage live

URL format: `/app2_live/live/:event/:pitch/:options`

**Paramètres:**
- `:event` - ID de l'événement (numérique)
- `:pitch` - Numéro du terrain (numérique)
- `:options` - Options d'affichage (optionnel, multiples)

**Exemple:**
```
/app2_live/live/123/1/score/inter/full
```

### Options d'affichage

**Modes d'affichage (display):**
- `main` - Affichage principal (à venir)
- `match` - Affichage match (à venir)
- `score` - Affichage des scores en direct

**Zones:**
- `club` - Noms complets d'équipes
- `inter` - Codes 3 lettres + drapeaux (international)

**Modes:**
- `full` - Complet avec animations
- `only` - Score uniquement
- `events` - Événements uniquement
- `static` - Sans animations

**Styles CSS (personnalisation):**
- `default` - Style par défaut
- `saintomer2022` - Style Saint-Omer 2022
- `saintomer2022b` - Variante Saint-Omer 2022
- `welland2018` - Style Welland 2018
- `thury2014` - Style Thury 2014

**Langues:**
- `fr` - Français (par défaut)
- `en` - English

### Exemples d'utilisation

```bash
# Score en français, zone internationale, mode complet
/app2_live/live/123/1/score/inter/full/fr

# Score en anglais, zone club, mode statique
/app2_live/live/123/1/score/club/static/en

# Score avec style personnalisé
/app2_live/live/123/1/score/inter/full/saintomer2022
```

## 🏗️ Architecture

### Structure des dossiers

```
app2_live/
├── assets/              # Assets statiques (CSS, fonts)
│   ├── css/            # Styles CSS personnalisés
│   └── fonts/          # Polices LCD pour l'affichage des scores
├── components/         # Composants Vue
│   ├── display/       # Composants d'affichage (Score, Main, Match)
│   └── design/        # Composants UI (UpdatePrompt, OnlineIndicator, etc.)
├── composables/       # Composables (logique réutilisable)
│   ├── useApi.js      # Appels API
│   ├── useGame.js     # Gestion des matchs
│   ├── useWebSocket.js # WebSocket/STOMP
│   ├── useFormat.js   # Formatage (dates, scores, etc.)
│   └── useRouteOptions.js # Options d'URL
├── i18n/              # Traductions
│   └── locales/       # fr.json, en.json
├── layouts/           # Layouts Nuxt
├── middleware/        # Middlewares de route
├── pages/             # Pages (routing automatique)
│   ├── index.vue      # Page d'accueil
│   └── live/[event]/[pitch]/[...options].vue # Affichage live
├── plugins/           # Plugins Nuxt
├── public/            # Fichiers publics (favicon, PWA icons, etc.)
├── stores/            # Stores Pinia
│   ├── preferenceStore.js  # Préférences utilisateur
│   ├── statusStore.js      # Statut online/offline
│   ├── eventStore.js       # Événements
│   └── gameStore.js        # Données de match
├── utils/             # Utilitaires
│   └── db.js          # Configuration Dexie (IndexedDB)
├── app.vue            # Composant racine
├── nuxt.config.ts     # Configuration Nuxt
├── package.json       # Dépendances NPM
└── tailwind.config.js # Configuration Tailwind
```

### Composables vs Mixins

Cette application utilise des **composables** (Composition API) au lieu de **mixins** (Options API).

**Migration des mixins:**
- `gameMixin.js` → `useGame.js`
- `routeMixin.js` → `useRouteOptions.js`
- `wsMixin.js` → `useWebSocket.js`
- `updateMixin.js` → `usePwa.ts`
- `statusMixin.js` → intégré dans `statusStore.js`

### Pinia vs Vuex ORM

Cette application utilise **Pinia** au lieu de **Vuex ORM**.

**Migration des models:**
- `User` → Non utilisé (pas d'auth dans app2_live)
- `Preferences` → `preferenceStore.js`
- `Status` → `statusStore.js`
- `Events` → `eventStore.js`
- `Games` → `gameStore.js`
- Les autres models (Players, Teams, etc.) ne sont pas migrés car non utilisés dans l'affichage live

### Tailwind vs Bootstrap

Cette application utilise **Tailwind CSS v4** au lieu de **Bootstrap 5**.

**Équivalences:**
- Classes Bootstrap → Classes Tailwind utilitaires
- Composants Bootstrap → Composants Nuxt UI
- Grid Bootstrap → Flexbox/Grid Tailwind
- Icons Bootstrap → Heroicons (via Nuxt UI)

### Fetch vs Axios

Cette application utilise l'**API Fetch native** au lieu d'**Axios**.

- Moins de dépendances
- API moderne standard
- Meilleure intégration avec Nuxt

## 🔧 Configuration

### Variables d'environnement

Fichier `.env.development`:
```bash
BASE_URL=/app2_live
API_BASE_URL=https://kpi.local/api
BACKEND_BASE_URL=https://kpi.local
```

Fichier `.env.production`:
```bash
BASE_URL=/app2_live
API_BASE_URL=https://kayak-polo.info/api
BACKEND_BASE_URL=https://kayak-polo.info
```

### Ports

- **Développement**: Port 3001 (container Docker)
- **Production**: Généré en mode statique

### Cache

L'application utilise **Dexie** (IndexedDB) pour mettre en cache:
- Préférences utilisateur
- Configuration réseau
- Données d'événements
- Données de matchs
- Scores en direct

## 📡 Communication temps réel

### WebSocket/STOMP

L'application supporte deux modes de communication:

1. **Polling HTTP** (fallback)
   - Rafraîchissement périodique (5 secondes)
   - Fichiers JSON en cache

2. **WebSocket/STOMP** (temps réel)
   - Topics:
     - `/game/chrono` - Timer du match
     - `/game/period` - Changement de période
     - `/game/data-game` - Scores
     - `/game/player-info` - Événements joueur (buts, cartes)

## 🎨 Personnalisation

### Thèmes CSS

Les thèmes CSS sont chargés dynamiquement depuis `/live/css/{style}.css`.

Pour créer un nouveau thème:
1. Créer un fichier CSS dans `/live/css/`
2. Ajouter le nom du thème dans `useRouteOptions.js` (`allowedStyles`)

### Polices LCD

L'application utilise des polices spéciales pour l'affichage des scores:
- `7segments.ttf` - Affichage LCD 7 segments
- `LiquidCrystal-ExBold.otf` - Affichage LCD cristal liquide

## 📱 PWA

L'application est une **Progressive Web App** avec:
- Service Worker automatique
- Mise en cache des assets
- Notifications de mise à jour
- Mode hors ligne (données en cache)

## 🌐 Internationalisation

Support de deux langues:
- Français (par défaut)
- English

Les traductions sont dans `/i18n/locales/`.

## 🚢 Déploiement

### Build pour production

```bash
# Via Makefile
make run_generate_live

# Ou manuellement
npm run generate
```

Les fichiers sont générés dans `.output/public/`.

### Docker

L'application tourne dans un container Node.js:
- Image: `node:20-alpine`
- Port: 3001
- Volume: `./sources/app2_live`

## 🔍 Debugging

### Logs

Les logs sont disponibles dans la console du navigateur:
- `[PWA]` - Service Worker
- `[API]` - Appels API
- `STOMP:` - WebSocket/STOMP

### Outils de développement

- Vue DevTools
- Nuxt DevTools
- Network tab (pour voir les appels API)
- Application tab (pour voir IndexedDB)

## 📝 TODO / À venir

- [ ] Implémenter les modes `main` et `match`
- [ ] Améliorer la gestion des erreurs réseau
- [ ] Ajouter plus de tests
- [ ] Documenter l'API backend attendue
- [ ] Ajouter support pour d'autres événements (pas seulement buts/cartes)

## 🤝 Contribution

Cette application fait partie du projet KPI (Kayak Polo Information).

## 📄 Licence

Voir le fichier LICENSE à la racine du projet.
