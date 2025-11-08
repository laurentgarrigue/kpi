# KPI App2 WSM - WebSocket Manager

Application Nuxt 4 moderne pour la gestion des connexions WebSocket et la collecte de données de matchs en temps réel.

## 🚀 Fonctionnalités

### WebSocket Manager
- Gestion de multiples connexions WebSocket simultanées (jusqu'à 19 terrains)
- Support des protocoles WebSocket brut et STOMP
- Persistance des connexions dans IndexedDB
- Journal des messages en temps réel
- Synchronisation optionnelle avec la base de données

### Collecte de Statistiques
- Interface de collecte de statistiques de match en temps réel
- Actions de jeu pré-définies (passes, tirs, arrêts, etc.)
- Envoi des statistiques à l'API backend

### Générateur de Données de Test (Faker)
- Simulation de scénarios de match sans matériel réel
- Messages pré-définis pour les événements courants
- Support des messages personnalisés

## 🛠 Technologies

- **Framework**: Nuxt 4
- **Vue**: Vue 3 avec Composition API
- **State Management**: Pinia
- **Styling**: Tailwind CSS v4
- **i18n**: @nuxtjs/i18n (Français, Anglais, Espagnol)
- **WebSocket**: @stomp/stompjs pour le support STOMP
- **Storage**: Dexie (IndexedDB)
- **PWA**: @vite-pwa/nuxt
- **Utilities**: Day.js pour les dates

## 📋 Prérequis

- Docker et Docker Compose
- Make
- Node.js 20+ (via Docker)

## 🔧 Installation

1. **Initialiser l'environnement**:
   ```bash
   make init_env_app2_wsm
   ```

2. **Installer les dépendances**:
   ```bash
   make npm_install_app2_wsm
   ```

3. **Lancer le serveur de développement**:
   ```bash
   make run_dev_wsm
   ```

   L'application sera accessible sur `http://localhost:3003`

## 📚 Commandes Make

### Développement
- `make run_dev_wsm` - Lance le serveur de développement (port 3003)
- `make run_build_wsm` - Build pour la production
- `make run_generate_wsm` - Génère le site statique
- `make run_lint_wsm` - Lance ESLint

### Gestion des dépendances
- `make npm_install_app2_wsm` - Installe toutes les dépendances
- `make npm_clean_app2_wsm` - Supprime node_modules
- `make npm_update_app2_wsm` - Met à jour les dépendances
- `make npm_add_app2_wsm package=<nom>` - Ajoute un package
- `make npm_add_dev_app2_wsm package=<nom>` - Ajoute un package de dev

### Shell
- `make node_bash_wsm` - Ouvre un shell dans le container Node

## 📁 Structure du Projet

```
app2_wsm/
├── app.vue                     # Composant racine
├── nuxt.config.ts              # Configuration Nuxt
├── package.json                # Dépendances
├── tailwind.config.js          # Configuration Tailwind
├── tsconfig.json               # Configuration TypeScript
│
├── assets/
│   └── css/
│       └── app.css             # Styles globaux
│
├── components/
│   ├── app/                    # Composants d'application
│   │   ├── AppNavbar.vue
│   │   ├── AppFooter.vue
│   │   ├── AppLocaleSwitcher.vue
│   │   ├── AppOnlineStatus.vue
│   │   ├── AppInstallPrompt.vue
│   │   └── AppUpdatePrompt.vue
│   ├── manager/                # Composants WebSocket Manager
│   │   ├── ManagerConnectionList.vue
│   │   └── ManagerConnection.vue
│   └── stats/                  # Composants de statistiques
│       └── StatsCollector.vue
│
├── composables/                # Logique réutilisable
│   ├── useApi.js               # Requêtes API
│   ├── useAuth.js              # Authentification
│   ├── usePrefs.js             # Préférences
│   ├── useWebSocket.js         # Gestion WebSocket
│   └── usePwa.ts               # PWA
│
├── i18n/
│   └── locales/
│       ├── en.json             # Traductions anglais
│       ├── fr.json             # Traductions français
│       └── es.json             # Traductions espagnol
│
├── layouts/
│   └── default.vue             # Layout par défaut
│
├── middleware/
│   └── auth.ts                 # Protection des routes
│
├── pages/                      # Routes (file-based routing)
│   ├── index.vue               # Page d'accueil
│   ├── login.vue               # Connexion
│   ├── manager.vue             # WebSocket Manager
│   ├── stats.vue               # Collecte de statistiques
│   └── faker.vue               # Générateur de test
│
├── stores/                     # Pinia stores
│   ├── eventStore.js           # Gestion des événements
│   ├── preferencesStore.js     # Préférences utilisateur
│   ├── statusStore.js          # Statut en ligne/hors ligne
│   └── userStore.js            # Données utilisateur
│
└── utils/
    └── db.js                   # Configuration Dexie
```

## 🌐 Internationalisation

L'application supporte 3 langues:
- **Français** (par défaut)
- **Anglais**
- **Espagnol**

Le changement de langue se fait via le sélecteur dans la barre de navigation.

## 💾 Stockage Local

### IndexedDB (Dexie)
- **preferences**: Préférences utilisateur (langue, événement sélectionné, etc.)
- **user**: Données de l'utilisateur connecté
- **connections**: Configurations des connexions WebSocket
- **events**: Liste des événements
- **messages**: Journal des messages WebSocket

## 🔌 WebSocket / STOMP

### Topics STOMP supportés
```
/game/ready-to-start-game
/game/set-teams
/game/game-state
/game/period
/game/chrono
/game/data-game
/game/player-info
/game/team-game
/game/game-phase
```

### Utilisation

```vue
<script setup>
const { createConnection, connect, disconnect } = useWebSocket()

// Créer une connexion STOMP
const conn = createConnection({
  id: 'pitch-1',
  url: 'ws://localhost:8080',
  type: 'stomp',
  topics: ['/game/data-game'],
  onMessage: (msg) => {
    console.log('Message reçu:', msg)
  },
  onConnect: () => {
    console.log('Connecté!')
  }
})

// Se connecter
connect('pitch-1')

// Se déconnecter
disconnect('pitch-1')
</script>
```

## 🔐 Authentification

L'authentification se fait via la page `/login` avec:
- Identifiant (numéro de licence)
- Mot de passe

Les routes protégées utilisent le middleware `auth` qui redirige vers `/login` si non authentifié.

## 🎨 Thème et Styles

- **Tailwind CSS v4** pour le styling
- **Couleurs principales**:
  - Primaire: Gray-900 (navigation, footer)
  - Accent: Green-600 (boutons d'action)
  - État connecté: Green-500
  - État déconnecté: Red-500

## 📱 PWA

L'application est une Progressive Web App avec:
- Installation sur l'écran d'accueil
- Fonctionnement hors ligne
- Notifications de mise à jour
- Service Worker automatique

## 🚦 Routes

- `/` - Page d'accueil (publique)
- `/login` - Authentification (publique)
- `/manager` - WebSocket Manager (protégée)
- `/stats` - Collecte de statistiques (protégée)
- `/faker` - Générateur de test (protégée)

## 🔄 Migration depuis app_wsm_dev

Cette application est une réécriture moderne de `app_wsm_dev` avec:
- ✅ Vue 3 Composition API (au lieu de mixins)
- ✅ Pinia (au lieu de Vuex)
- ✅ Tailwind CSS (au lieu de Bootstrap)
- ✅ fetch API (au lieu d'Axios)
- ✅ Nuxt 4 (au lieu de Vue CLI)
- ✅ Composables (au lieu de mixins)

## 📝 Notes

- Les fichiers `.env.development` et `.env.production` sont déjà présents
- La configuration Docker nécessite un container Node séparé `kpi_node_app2_wsm`
- Le port par défaut est 3003 (configurable dans `nuxt.config.ts`)

## 🤝 Contribution

Pour contribuer:
1. Créer une branche depuis `develop`
2. Faire les modifications
3. Tester avec `make run_dev_wsm`
4. Créer une pull request

## 📄 Licence

Voir le fichier LICENSE du projet principal.
