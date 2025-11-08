# KPI WSM (WebSocket Manager) - app_wsm_dev Comprehensive Analysis

## Project Overview

**app_wsm_dev** is a Vue 3 + Vuex + Vue Router application for managing WebSocket connections to real-time game/match data in the KPI sports management system. It acts as a relay/manager between WebSocket brokers (STOMP or raw WebSockets) and the KPI backend database, enabling live match monitoring and game statistics.

**Current Stack:**
- Vue 3.0.0
- Vue Router 4.0
- Vuex 4.0 with Vuex ORM
- Bootstrap 5.0.1
- Axios 1.8.2
- STOMP (@stomp/stompjs)
- WebSocket support
- i18n (Vue i18n 9.14.3)
- PWA with Workbox
- IndexedDB for local storage

---

## Directory Structure

```
sources/app_wsm_dev/
├── src/
│   ├── App.vue                          # Root component (Navbar, Update, Message)
│   ├── main.js                          # Entry point
│   ├── registerServiceWorker.js         # PWA service worker registration
│   ├── service-worker.js                # PWA service worker
│   ├── ws_params.js                     # WebSocket parameters
│   ├── assets/                          # Static assets
│   │   ├── fonts/                       # Custom fonts (7segments, LCDWinTT)
│   │   └── styles/                      # SCSS styles
│   ├── components/
│   │   ├── design/                      # UI components (Navbar, Online, Message, etc.)
│   │   └── display/                     # Display components (Main, Match, Score)
│   ├── locales/                         # i18n translations (en.json, fr.json, es.json)
│   ├── mixins/                          # Vue mixins (wsMixin, gameMixin, prefsMixin, etc.)
│   ├── network/                         # API and WebSocket clients
│   │   ├── api.js                       # Base Axios instance
│   │   ├── liveApi.js                   # Live/game API calls
│   │   ├── privateApi.js                # Authentication API
│   │   ├── ws.js                        # WebSocket context
│   │   ├── wsGames.js                   # Game-specific WebSocket functions
│   │   └── publicApi.js                 # Public API calls
│   ├── router/
│   │   └── index.js                     # Vue Router configuration
│   ├── services/
│   │   ├── i18n.js                      # i18n setup
│   │   ├── idbStorage.js                # IndexedDB wrapper
│   │   └── snippets.js                  # Utility functions
│   ├── store/
│   │   ├── index.js                     # Vuex store with Vuex ORM
│   │   ├── database/
│   │   │   └── index.js                 # Vuex ORM database config
│   │   └── models/
│   │       ├── User.js                  # User model
│   │       ├── Preferences.js           # User preferences model
│   │       └── Status.js                # App status model
│   └── views/                           # Page components
│       ├── Home.vue                     # Home/landing page
│       ├── Login.vue                    # Authentication form
│       ├── Manager.vue                  # WebSocket manager (main feature)
│       ├── Stats.vue                    # Game statistics collector
│       └── Faker.vue                    # Test data generator
└── public/                              # Static files (PWA manifest, icons)
```

---

## Key Features & Functionalities

### 1. Authentication (Login.vue)
- **Purpose:** User login and token management
- **Features:**
  - Basic authentication with login/password
  - Base64 token encoding
  - Token stored in cookies and IndexedDB
  - User data persistence
  - Logout functionality with cookie/storage cleanup
- **Mixins Used:** userMixin, logoutMixin, statusMixin
- **Storage:** IndexedDB ('user' object store)

### 2. WebSocket Manager (Manager.vue) - CORE FEATURE
- **Purpose:** Main application for managing WebSocket connections to match data brokers
- **Features:**
  - Multiple pitch connections (1-19) + broker (0) + faker (20)
  - Dual WebSocket protocol support:
    - Raw WebSocket for libwebsockets-style connections
    - STOMP protocol for Apache ActiveMQ
  - Real-time game data reception:
    - Score updates
    - Timer/chrono data
    - Period/quarter changes
    - Player statistics (cards, goals, possession)
    - Team composition
  - Dual connection modes:
    - Publisher (broadcast data out)
    - Subscriber (receive updates)
  - Message logging and flow tracking
  - Connection persistence (saved to IndexedDB)
  - Database synchronization (optional)
  - Faker component for test data generation

**Subscribed Topics (STOMP):**
- `/game/ready-to-start-game`
- `/game/set-teams`
- `/game/game-state`
- `/game/period`
- `/game/chrono`
- `/game/data-game`
- `/game/player-info`
- `/game/team-game`
- `/game/game-phase`

**Key Data Updates:**
- Match status (ATT/ON/END)
- Score details (team A, team B)
- Period information (M1, M2, P1, P2, TB)
- Game timer (countdown format)
- Possession timer (60s countdown)
- Player selection status
- Card assignments (YELLOW, RED, NONE)
- Goal events

### 3. Game Statistics (Stats.vue)
- **Purpose:** Real-time game statistics collection during matches
- **Features:**
  - Event selection (event + pitch picker)
  - Network configuration fetching
  - WebSocket listener for game updates
  - Live score display
  - Period tracking
  - Player action tracking:
    - Goals/shots on target
    - Possession changes
    - Kickoff wins/losses
    - Player selection
  - Button-based action interface
  - Data submission to API
- **Mixins Used:** routeMixin, gameMixin, wsMixin
- **API:** liveApi.setStat()

### 4. Faker (Test Data Generator)
- **Purpose:** Simulate game data for testing without real hardware
- **Features:**
  - Preset test messages for common game states
  - Configurable STOMP broker
  - Topic customization
  - Manual message injection
  - Test scenarios (team ready, goals, cards, possession, etc.)

---

## Mixins (Reusable Logic)

### 1. **wsMixin.js** - WebSocket Management
Handles all WebSocket/STOMP operations:
- WebSocket creation and lifecycle
- STOMP client initialization
- Message sending/receiving
- Data parsing and broadcasting
- Log management
- Connection validation and saving
- Database sync methods

**Key Methods:**
- `startUrl(id)` - Connect to WebSocket/STOMP
- `stopUrl(id)` - Disconnect
- `sendMessage(id, message, topic)` - Send data
- `broadcast(pitch, topic, value)` - Broadcast to display
- `socketCreate(id)` - Raw WebSocket
- `stompCreate(id)` - STOMP protocol
- `stompSubscribe(id)` - Set up listeners
- `syncPlayerSelected()` - Sync player status to DB
- `syncTimer()` - Sync timer to DB
- `sync()` - Generic sync method
- `syncGameEvt()` - Sync game events
- `setTeams()` - Set match teams

### 2. **gameMixin.js** - Game Data Fetching
Handles game data retrieval and processing:
- Event fetching
- Game data loading
- Score fetching
- Timer data
- Logo retrieval
- Period/timer formatting
- Game event handling

**Key Methods:**
- `fetchEvents()` - Get available events
- `fetchGame(gameTarget, event, pitch)` - Get game data
- `fetchScore(id)` - Get score data
- `fetchLogo(numero)` - Get team logo
- `msToMMSS()` - Format milliseconds to MM:SS
- `msToSS()` - Format milliseconds to seconds

### 3. **prefsMixin.js** - User Preferences
Manages user settings and preferences:
- Load/save preferences
- Event selection
- Pitch selection
- Database sync toggle
- Preferences persistence

**Key Methods:**
- `getPrefs()` - Load from IndexedDB
- `savePrefs()` - Save to IndexedDB

### 4. **userMixin.js** - User Authentication
Manages user data:
- Load user from IndexedDB
- Check authorization
- Filter events by user permissions

**Key Methods:**
- `getUser()` - Load user
- `checkAuthorized()` - Validate user session

### 5. **logoutMixin.js** - Logout Logic
- Clear user data
- Remove cookies
- Clear IndexedDB

### 6. **statusMixin.js** - Online Status
- Check online status
- Update status messages

### 7. **routeMixin.js** - Route Parameters & CSS Loading
- Parse route parameters
- Load dynamic CSS
- Support for display modes (main, match, score)
- Style themes (default, thury2014, etc.)
- Language switching
- Zone support (club, inter)

---

## Store (Vuex + Vuex ORM)

### Database Models:

**1. User Model**
```javascript
{
  id: number,
  name: string,
  firstname: string,
  profile: number,          // Permission level
  events: string,           // Pipe-separated event IDs
  token: string             // Authentication token
}
```

**2. Preferences Model**
```javascript
{
  id: number,
  selectedEvent: number,    // Currently selected event
  locale: string,           // 'en', 'fr', 'es'
  pitches: number,          // Number of visible pitches (default: 4)
  databaseSync: boolean     // Enable/disable DB sync
}
```

**3. Status Model**
```javascript
{
  online: boolean,
  messageText: string,
  messageClass: string
}
```

**Storage Backends:**
- Vuex for state management
- IndexedDB (v4) for persistence:
  - 'connections' - WebSocket connection configs
  - 'event' - Event data
  - 'preferences' - User preferences
  - 'user' - User credentials and info

---

## API Endpoints (liveApi)

### Get Endpoints:
- `getEvents()` → `/api/events/all`
- `getEventNetwork(event)` → `/live/cache/event{event}_network.json`
- `getGameId(event, pitch)` → `/live/cache/event{event}_pitch{pitch}.json`
- `getGame(gameId)` → `/live/cache/{gameId}_match_global.json`
- `getLogo(numero)` → `/live/cache/logos/logo_{numero}.json`
- `getScore(gameId)` → `/live/cache/{gameId}_match_score.json`
- `getTimer(gameId)` → `/live/cache/{gameId}_match_chrono.json`

### Put Endpoints (Database Sync):
- `setEventNetwork(event, network)` → `/api/wsm/eventNetwork/{event}`
- `setGameParams(gameId, param, value)` → `/api/wsm/gameParam/{gameId}`
- `setGameEvent(gameId, params)` → `/api/wsm/gameEvent/{gameId}`
- `setPlayerStatus(gameId, params)` → `/api/wsm/playerStatus/{gameId}`
- `setGameTimer(gameId, params)` → `/api/wsm/gameTimer/{gameId}`
- `setStat(statsObj)` → `/api/wsm/stats/`

---

## Components

### Design Components:
- **Navbar.vue** - Top navigation with router links
- **LocaleSwitcher.vue** - Language switcher (en/fr/es)
- **Online.vue** - Online status indicator
- **Message.vue** - Global message notifications
- **Update.vue** - PWA update notification
- **AddToHomeScreen.vue** - PWA installation prompt

### Display Components:
- **Main.vue** - Main display component
- **Match.vue** - Match information display
- **Score.vue** - Score display

---

## Internationalization (i18n)

**Locales Supported:**
- English (en) - /src/locales/en.json
- French (fr) - /src/locales/fr.json
- Spanish (es) - /src/locales/es.json

**Key Translation Keys:**
```
status.Offline
nav.Home, nav.Manager, nav.Faker, nav.Stats, nav.Account
Login: Authentication, Login, Password, Submit, Logout, etc.
Event, Pitch, Pitches, Team
Actions: Shot, Stop, Kickoff, Pass/Possession, Neutral
```

---

## Routes

```javascript
{
  path: '/',           // Home (redirects to Login)
  path: '/login',      // Authentication
  path: '/manager',    // WebSocket Manager (main feature)
  path: '/faker',      // Test data generator
  path: '/stats',      // Game statistics collection
}
```

---

## Data Flow Architecture

### WebSocket Manager Flow:
```
1. Manager.vue loads
2. Loads saved connections from IndexedDB
3. Loads events from API
4. User selects event and number of pitches
5. For each pitch connection:
   - User enters WebSocket URL and topic
   - User starts connection → stompCreate() or socketCreate()
   - Listener subscribes to game topics
   - Receives game events (goals, cards, period changes, timer)
   - Updates local state and broadcasts to display
   - Optionally syncs changes to database
6. Connections saved to IndexedDB for persistence
```

### Game Stats Flow:
```
1. Stats.vue loads events
2. User selects event and pitch
3. Fetches game data from live API
4. Connects to WebSocket for live updates
5. User selects button mode (goal, card, possession, etc.)
6. User clicks player or team
7. Action submitted to API: /api/wsm/stats/
8. Data persisted in database
```

---

## Key Technical Details

### WebSocket Data Format (Raw):
```javascript
{
  p: "eventId_pitchId",      // Pitch identifier
  t: "topic",                 // Topic name
  v: value                    // Data value
}
```

### WebSocket Data Format (STOMP):
```javascript
// Subscribed to specific topics
/pitch{n}/topic  // Per-pitch messages
/game/chrono     // Game timer updates
/game/player-info // Player statistics
// etc.
```

### Time Formatting:
- Milliseconds to MM:SS format
- Milliseconds to MM:SS.D format (with tenths)
- Countdown mode for game timer

### Data Synchronization:
- Optional two-way sync with backend
- Only syncs when `preferences.databaseSync = true`
- Syncs: player status, timer, game params, game events

---

## PWA Features

- Service Worker registration
- Offline support
- Add to home screen capability
- Update notifications
- Manifest configuration:
  - name: "KPI WS Manager"
  - short_name: "KPI_WSM"
  - theme_color: "#f15a2a"
  - display: "standalone"

---

## Environment Variables

```
VUE_APP_API_BASE_URL      # Base URL for API calls
VUE_APP_BASE_URL          # Base URL for assets
VUE_APP_I18N_LOCALE       # Default locale (en/fr/es)
VUE_APP_INTERVAL_GAME     # Game refresh interval (ms)
VUE_APP_INTERVAL_GAMEEVENTSHOW  # Event display duration (ms)
```

---

## Dependencies Summary

### Core:
- vue@3.0.0
- vue-router@4.0.0-0
- vuex@4.0.0-0
- @vuex-orm/core@0.36.3

### UI & Styling:
- bootstrap@5.0.1
- bootstrap-icons@1.8.1
- animate.css@4.1.1
- @animxyz/vue3@0.5.0
- element-plus@2.1.0

### Networking:
- axios@1.8.2
- websocket@1.0.34
- @stomp/stompjs@6.1.2

### i18n & Localization:
- vue-i18n@9.14.3

### Storage:
- idb@6.0.0
- js-cookie@2.2.1

### Utilities:
- dayjs@1.10.5
- lodash.debounce@4.0.8
- uuid@8.3.2

### PWA:
- register-service-worker@1.7.2
- workbox@6.1.5

---

## Features to Migrate to Nuxt 4

### Must Migrate:
1. **Authentication System** (Login, logout, token management)
2. **WebSocket Manager** (Multiple connection management, STOMP/Raw WS)
3. **Game Data API Integration** (All liveApi endpoints)
4. **State Management** (User, preferences, status - can use Pinia instead of Vuex)
5. **Internationalization** (All locale files, i18n setup)
6. **IndexedDB Storage** (Connection persistence, user data)
7. **All Routes** (Login, Manager, Stats, Faker)
8. **Display Components** (Score, match info display)

### Should Migrate:
1. **Mixins → Composables** (Convert to Vue 3 Composition API)
2. **Bootstrap 5 → Tailwind CSS + Nuxt UI** (More aligned with app2)
3. **Styling** (SCSS to Tailwind classes)
4. **PWA Features** (Nuxt PWA module)

### Technology Replacements:
- Vuex → Pinia (state management)
- Vue Router → Nuxt Router (built-in)
- Manual API setup → Nuxt composables (useAsyncData, useFetch)
- Mixins → Vue 3 Composables
- PWA setup → Nuxt PWA module

---

## Migration Priority

### Phase 1 (Critical):
- Authentication flow
- WebSocket Manager (core functionality)
- API integration
- IndexedDB persistence

### Phase 2 (Important):
- Game Statistics view
- Internationalization
- Routing and navigation

### Phase 3 (Enhancement):
- UI/UX improvements (Bootstrap → Tailwind)
- Performance optimization
- Testing

---

## Notes for Migration

1. **Large Templates:** Manager.vue is a large component (1000+ lines) - consider breaking into sub-components
2. **Complex Logic:** wsMixin is feature-rich - needs careful conversion to composables
3. **WebSocket Handling:** Be careful with lifecycle and cleanup during component unmount
4. **Performance:** Multiple WebSocket connections need efficient state management
5. **Bootstrap Dependency:** Should be replaced with Tailwind/Nuxt UI for consistency with app2
6. **Element Plus:** May need to be replaced with Nuxt UI components
7. **Time Formatting:** Utility functions for timer formatting are essential

