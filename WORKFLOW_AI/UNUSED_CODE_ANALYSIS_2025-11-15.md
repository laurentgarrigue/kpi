# KPI Codebase - Unused Features & Code Analysis Report

**Date**: November 15, 2025
**Scope**: Complete analysis of legacy applications, modern app, API endpoints, and dependencies

---

## Executive Summary

The KPI codebase shows a classic monolithic legacy system with multiple frontend applications and a centralized PHP API backend. Key findings:

- **3 Legacy Vue.js Applications** (85-63 files each) that are **NOT actively deployed** but maintained
- **1 Modern Nuxt 4 Application** (App2) - actively developed, but still incomplete
- **Multiple empty directory placeholders** for compiled legacy apps
- **Several unused stores and components** in App2
- **API endpoints** with potential low usage (report, wsm endpoints need verification)
- **Large admin section** (256 files) for backend management
- **Substantial report library** (3601 files) mostly composed of library dependencies

---

## 1. LEGACY APPLICATIONS ANALYSIS

### 1.1 Status Overview

All three legacy Vue applications are **STILL IN THE CODEBASE but NOT IN DOCKER COMPOSE**:

| App | Files | Status | Docker | Notes |
|-----|-------|--------|--------|-------|
| **app_dev** | 85 | Replaced by app2 | Commented out | Main frontend legacy app |
| **app_live_dev** | 59 | Maintained | Commented out | Live scoring overlay |
| **app_wsm_dev** | 63 | Maintained | Commented out | Water Sport Manager (match management) |

**Directory**: `/home/user/kpi/sources/`

### 1.2 Legacy App Directories (Empty Placeholders)

These directories are **INTENTIONALLY EMPTY** - they hold compiled output from the dev versions:

- `/home/user/kpi/sources/app/` - compiled from app_dev (empty .gitkeep)
- `/home/user/kpi/sources/app_live/` - compiled from app_live_dev (empty .gitkeep)
- `/home/user/kpi/sources/app_prev/` - preview version (empty .gitkeep)
- `/home/user/kpi/sources/app_wsm/` - compiled from app_wsm_dev (empty .gitkeep)
- `/home/user/kpi/sources/app_wsm_prev/` - preview version (empty .gitkeep)

**Recommendation**: **CONFIDENCE: HIGH** - These can be removed if legacy apps are not being built

### 1.3 app_dev Components Analysis

**Unused Component Found**:

- **ChartChampionship.vue** - Defined but never imported/used anywhere
  - Path: `/home/user/kpi/sources/app_dev/src/components/ChartChampionship.vue`
  - Confidence: **HIGH**
  - Action: Safe to remove

### 1.4 Legacy Apps API Usage

**app_dev** uses these endpoints:
- `/staff/{eventId}/teams` - GetTeamsController
- `/staff/{eventId}/players/{teamId}` - GetPlayersController  
- `/staff/{eventId}/player/{playerId}/team/{teamId}/*` - PutPlayerController
- `/report/{eventId}/game/{gameId}` - GetGameController

**app_live_dev** uses:
- `/events/all` - Public endpoint (custom implementation)

**app_wsm_dev** uses:
- All WSM endpoints (eventNetwork, gameParam, gameEvent, etc.)

---

## 2. APP2 (NUXT 4) ANALYSIS

### 2.1 Component Analysis

**App2 Component Overview**:
- 18 components in `/sources/app2/components/`
- All appear to be actively used in pages
- No unused components found

### 2.2 Pages

**Current Pages** (8 total):
1. `index.vue` - Home page
2. `about.vue` - About page
3. `login.vue` - Authentication
4. `charts.vue` - Statistics charts
5. `games.vue` - Game listings
6. `event/[id].vue` - Event details
7. `team/[[team]].vue` - Team statistics
8. `scrutineering.vue` - Equipment checking (NEW - staff function)

All pages appear to be used and accessible.

### 2.3 Stores Analysis

**Stores Defined**: 6 total

| Store | Used | Purpose |
|-------|------|---------|
| `chartStore.js` | ✅ YES | Chart data state |
| `gameStore.js` | ✅ YES | Game list state |
| `preferenceStore.js` | ✅ YES | User preferences (IndexedDB) |
| `scrutineeringStore.js` | ✅ YES | Scrutineering (equipment) |
| `eventStore.js` | ✅ YES | Event selection |
| **majRefStore.js** | ❌ **NO** | Unused - Never imported |

**UNUSED STORE FOUND**:
- **majRefStore.js** 
  - Location: `/home/user/kpi/sources/app2/stores/majRefStore.js`
  - Defines: `useMajRefStore` - Local storage for "maj_ref" data
  - Usage: **ZERO** - Never imported anywhere
  - Confidence: **HIGH**
  - Action: Can be removed if not needed for future functionality

### 2.4 Composables Analysis

**Composables Defined**: 14

All composables are used:
- `useApi.js` - API communication (fetch wrapper)
- `useAuth.js` - Authentication (login)
- `useCharts.js` - Chart loading with caching
- `useEventGuard.ts` - Route middleware for event validation
- `useGameDisplay.js` - Game display formatting
- `useGames.js` - Game loading with IndexedDB caching
- `useIndexedDB.js` - IndexedDB wrapper
- `useLoadingState.js` - Global loading state
- `useNavigation.js` - Navigation helpers
- `usePrefs.js` - Preferences access
- `usePwa.ts` - PWA functionality
- `useScrutineering.js` - Scrutineering data management
- `useStatus.js` - Status management
- `useUser.js` - User information

**No unused composables found** ✅

### 2.5 Middleware

**middleware/event-guard.ts** - Used by 5 pages (games, charts, scrutineering, team, login)
- ✅ Actively used

### 2.6 Layouts

**layouts/default.vue** - Single layout, used by all pages
- ✅ Actively used

---

## 3. PHP BACKEND API ANALYSIS

### 3.1 API Controller Summary

**Location**: `/home/user/kpi/sources/api/`

**4 Controller Files**:

1. **publicControllers.php** (451 lines)
   - Routes: login, events, event, games, charts, team-stats, stars, rating
   - Used by: App2, app_dev, app_live_dev, app_wsm_dev
   - Status: ✅ **ACTIVE**

2. **staffControllers.php** (120 lines)
   - Routes: test, teams, players, player (equipment & comments)
   - Used by: App2 (scrutineering page), app_dev (game reports)
   - Status: ✅ **ACTIVE**

3. **reportControllers.php** (76 lines)
   - Routes: game (game statistics)
   - Used by: app_dev (GameReports.vue), potentially app2
   - Status: ⚠️ **MEDIUM USAGE** - Only app_dev actively uses this

4. **wsmControllers.php** (238 lines)
   - Routes: eventNetwork, gameParam, gameEvent, playerStatus, gameTimer, stats
   - Used by: app_wsm_dev (live match management)
   - Status: ⚠️ **LEGACY** - Only app_wsm_dev uses this

### 3.2 API Endpoint Usage Analysis

**Public Endpoints** (Used by all apps):
```
GET  /public/events        - List events
GET  /public/event         - Get single event
GET  /public/games/:id     - Get games for event
GET  /public/charts/:id    - Get charts for event
GET  /public/team-stats    - Team statistics
GET  /public/stars         - Star ratings
POST /public/login         - Authentication
POST /public/rating        - Submit ratings
```
✅ All actively used by App2

**Staff Endpoints** (Protected):
```
GET /staff/:eventId/teams                    - GetTeamsController ✅ Used
GET /staff/:eventId/players/:teamId          - GetPlayersController ✅ Used
PUT /staff/:eventId/player/:playerId/...     - PutPlayerController ✅ Used
GET /staff/:eventId/test                     - StaffTestController (TEST endpoint)
```

**Report Endpoints** (Protected):
```
GET /report/:eventId/game/:gameId            - GetGameController ⚠️ USED ONLY BY app_dev
```
Confidence: **MEDIUM** - Only legacy app_dev uses this, not in App2 roadmap

**WSM Endpoints** (Live Match Management):
```
PUT /wsm/eventNetwork      - Network configuration (app_wsm_dev only)
PUT /wsm/gameParam         - Game parameters (app_wsm_dev only)
PUT /wsm/gameEvent         - Game events (app_wsm_dev only)
PUT /wsm/playerStatus      - Player status (app_wsm_dev only)
PUT /wsm/gameTimer         - Match timer (app_wsm_dev only)
PUT /wsm/stats             - Statistics (app_wsm_dev only)
```
⚠️ **LEGACY** - Only app_wsm_dev uses these. Not part of App2 migration.

---

## 4. COMMON PHP UTILITIES

### 4.1 Core Files

**Location**: `/home/user/kpi/sources/commun/`

| File | Purpose | Status |
|------|---------|--------|
| `MyBdd.php` | Database abstraction (56KB) | ✅ Core |
| `Bdd_PDO.php` | PDO wrapper | ✅ Core |
| `MyTools.php` | Utility functions (30KB) | ✅ Core |
| `MyConfig.php` | Configuration | ✅ Core |
| `MySmarty.php` | Smarty template engine | ⚠️ Legacy |
| `MyPage.php` | Page base class | ⚠️ Legacy |
| `MyPDF.php` | PDF generation (mPDF) | ✅ Used |
| `cron_maj_licencies.php` | Cron task (licenses) | ✅ Active |
| `cron_verrou_presences.php` | Cron task (presence) | ✅ Active |

**No obvious unused functions identified** - would require deeper code analysis

---

## 5. DATABASE & SQL ANALYSIS

### 5.1 Migration Scripts

**Location**: `/home/user/kpi/SQL/` (34 files)

**Recent additions** (likely still in use):
- `20251003_add_comment_to_scrutineering.sql` - Scrutineering comments (Oct 2025)
- `20250705_feat_imprime.sql` - Print feature (Jul 2025)
- `20240208_feat_match_chrono_shotclock_penalties.sql` - Match timer features (Feb 2024)

**Older scripts** (possibly obsolete):
- `bdd_minuscules.sql` - Case conversion (legacy)
- `myisamToInnodb.sql` - Storage engine migration (legacy)
- `couleurs.sql` - Colors (possibly unused)

**Unknown usage** (no context):
- Various feature SQL files without clear associations

**Recommendation**: Review old migration scripts for archival

---

## 6. DEPENDENCIES ANALYSIS

### 6.1 App2 Package.json

**NPM Dependencies**: Minimal (uses Nuxt framework)
```json
{
  "dayjs": "^1.11.18",
  "dexie": "^4.2.0",
  "uuid": "^13.0.0",
  "buffer": "^6.0.3",
  "idb": "^8.0.3"
}
```

**Dev Dependencies**: 
- Nuxt 4.1.2
- Vue 3.5.17
- Pinia 3.0.3
- Tailwind CSS 4.1.13
- Nuxt UI 4.0.0
- i18n 10.1.0
- PWA support (@vite-pwa/nuxt)
- ESLint 9.36.0

**No unused dependencies detected** ✅

### 6.2 app_dev Package.json

**Large dependency set** for Vue 3:
- **UNUSED in modern app**: 
  - `@animxyz/vue3` - Animation library (not in App2)
  - `element-plus` - UI framework (replaced by Nuxt UI)
  - `bootstrap` - CSS framework (old, replaced by Tailwind)
  - `popper.js` - Tooltip library (legacy)
  - `register-service-worker` - Old PWA method (replaced by @vite-pwa/nuxt)
  - `workbox-cli` - Workbox CLI (legacy PWA)
  - `jQuery` - Heavy legacy dependency
  - `js-cookie` - Cookie management (basic functionality)

### 6.3 Composer.json

```php
{
  "mpdf/mpdf": "^8.2",        // PDF generation ✅
  "psr/log": "^1.1",          // PSR logging ✅
  "openspout/openspout": "^4.32", // Excel export ✅
  "twbs/bootstrap": "^5.3",   // Bootstrap (admin templates)
  "smarty/smarty": "^4"       // Template engine
}
```

All core dependencies appear necessary.

---

## 7. ADMIN SECTION ANALYSIS

### 7.1 Overview

**Location**: `/home/user/kpi/sources/admin/` (256 files, ~1.4MB)

**Purpose**: Backend administration interface for tournament management

**Key Modules**:
- `GestionJournee.php` (65KB) - Day/round management
- `FeuilleStats.php` (66KB) - Statistics sheets
- `GestionCompetition.php` (43KB) - Competition management
- `GestionClassement.php` (55KB) - Rankings management
- `FeuilleMarque3.php` (48KB) - Score sheets
- Various autocomplete and form handlers

**Status**: Still actively maintained based on recent code patterns

**No obvious unused files detected** - admin serves backend staff users

---

## 8. REPORT & LIBRARY SECTION ANALYSIS

### 8.1 Report Directory

**Location**: `/home/user/kpi/sources/report/` (3601 files, large)

**Composition**:
- **libraries/ subdirectory**: Contains bundled third-party libraries
  - `bootstrap-toggle-master/` - Toggle switch widget
  - `flag-icon-css-master/` - Flag icons
  - `fontawesome/` - Icon library
- **Core report generation PHP files**
- **Static assets**

**Status**: Report module appears self-contained and complete

### 8.2 Local Libraries

**Location**: `/home/user/kpi/sources/lib/` (4 libraries)

| Library | Purpose | Status |
|---------|---------|--------|
| `dayjs-1.11.1/` | Date/time library | ⚠️ Superceded by npm dayjs |
| `easytimer-4.6.0/` | Timer utility | ⚠️ Potentially unused (check codebase) |
| `htmlpurifier/` | HTML sanitization | ⚠️ May be unused |
| `qrcode/` | QR code generation | ⚠️ May be used in reports |

**Recommendation**: Verify actual usage before cleanup

---

## 9. CONNECTOR SECTION

**Location**: `/home/user/kpi/sources/connector/` (11 files)

**Purpose**: Event synchronization and configuration

**Files**:
- `get_evenement.php` - Fetch event data
- `set_evenement.php` - Store event data
- `replace_evenement.php` - Replace event data
- Ajax and test files

**Status**: Appears to be utility code for admin tools

**No obvious dead code found**

---

## SUMMARY OF FINDINGS

### High Confidence Removals

| Item | Type | Location | Confidence |
|------|------|----------|------------|
| ChartChampionship.vue | Component | app_dev/src/components/ | **HIGH** |
| majRefStore.js | Store | app2/stores/ | **HIGH** |
| app/, app_live/, app_prev/, app_wsm/, app_wsm_prev/ | Directories | sources/ | **HIGH** |

### Medium Confidence (Legacy)

| Item | Type | Location | Confidence |
|------|------|----------|------------|
| app_dev/ (entire) | Vue App | sources/ | **MEDIUM** - Kept for reference |
| app_live_dev/ | Vue App | sources/ | **MEDIUM** - Maintained, not deployed |
| app_wsm_dev/ | Vue App | sources/ | **MEDIUM** - Maintained, not deployed |
| reportControllers.php | API | api/controllers/ | **MEDIUM** - Only used by legacy app_dev |
| wsmControllers.php | API | api/controllers/ | **MEDIUM** - Only used by legacy app_wsm_dev |
| MySmarty.php | Utility | commun/ | **MEDIUM** - Legacy template engine |

### Candidates for Future Refactoring

| Item | Type | Location | Notes |
|------|------|----------|-------|
| Old SQL migration scripts | Database | SQL/ | Review for archival |
| Local lib/ files | JavaScript | lib/ | Verify npm versions are used instead |
| Admin PHPfiles | Backend | admin/ | Large codebase but still active |

---

## RECOMMENDATIONS

### Immediate Actions (Low Risk)

1. **Remove majRefStore.js** - Never used, safe to delete
   - File: `/home/user/kpi/sources/app2/stores/majRefStore.js`

2. **Remove ChartChampionship.vue** from app_dev - Unused component
   - File: `/home/user/kpi/sources/app_dev/src/components/ChartChampionship.vue`

3. **Remove compiled directory placeholders** - Empty directories
   - Directories: `app/`, `app_live/`, `app_prev/`, `app_wsm/`, `app_wsm_prev/`

### Medium-Term Actions (Requires Planning)

1. **Document legacy API endpoints** - Create API deprecation timeline
   - Report endpoints (used only by app_dev)
   - WSM endpoints (used only by app_wsm_dev)

2. **Migration to App2**
   - Scrutineering functionality (now in App2 pages/scrutineering.vue) ✅
   - Game reports (still only in app_dev)
   - Team statistics (now in app2 pages/team/[[team]].vue) ✅

3. **Verify local lib/ files** - Check if npm equivalents are being used
   - dayjs (already in npm)
   - easytimer (verify usage)
   - htmlpurifier (verify usage)
   - qrcode (verify usage)

### Long-Term Strategy (6-12 Months)

1. **Decommission legacy Vue apps** - After App2 feature parity
   - Remove app_dev/ source files
   - Remove app_live_dev/ source files
   - Remove app_wsm_dev/ source files

2. **Clean up old API endpoints** - When legacy apps are retired
   - reportControllers.php
   - wsmControllers.php

3. **Modernize admin section** - Consider Vue/Nuxt migration for admin panel

---

## RISK ASSESSMENT

### Low Risk (Safe to remove immediately)
- majRefStore.js ✅
- ChartChampionship.vue ✅
- Empty directory placeholders ✅

### Medium Risk (Requires coordination)
- Legacy app_dev, app_live_dev, app_wsm_dev (keeping as reference)
- Old SQL migration scripts (archive vs delete decision)

### High Risk (Core functionality)
- API controllers (public, staff) - **DO NOT MODIFY**
- Database schema files - **DO NOT MODIFY**
- Admin section - **DO NOT MODIFY**

