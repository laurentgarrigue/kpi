# API2 - Symfony 7.4 LTS + API Platform 4.3 (FrankenPHP)

This is a modern REST API built with Symfony 7.4 LTS and API Platform 4.3, providing the same functionalities as the legacy PHP API in `/sources/api`.

Since 2026-07-16 it runs on **FrankenPHP in worker mode**, in its own container (`${APPLICATION_NAME}_api2`) — **separate from the Apache container that serves the legacy code and WordPress**.

## Features

- **Symfony 7.4 LTS** - pinned; Symfony 8 is deliberately not used
- **API Platform 4.3** - REST API with OpenAPI documentation
- **FrankenPHP worker mode** - Symfony kernel stays in memory between requests (~1.8 ms vs 20–60 ms bootstrap)
- **Built-in Mercure hub** - SSE without a separate `dunglas/mercure` container
- **Doctrine ORM** - Database abstraction layer
- **CORS Support** - via NelmioCorsBundle
- **Same Database** - Uses the existing KPI database (MariaDB 11.5)

## Runtime architecture

Traefik terminates TLS and routes `PathPrefix('/api2')` here (priority over the legacy router),
**stripping the `/api2` prefix** before forwarding. Symfony therefore receives requests at the root.

What this changes compared to the old Apache setup:

| Topic | Under FrankenPHP |
|---|---|
| `.htaccess` | **Ignored** — Caddy does not read it (`public/.htaccess` is now dead weight) |
| CORS | NelmioCorsBundle only — the legacy PHP `auto_prepend_file` does **not** run here |
| `DEFAULT_URI` | **Must** include `/api2` (stripprefix), else API Platform emits wrong IRIs |
| Logs | `make api2_logs` / `make api2_logs_errors` — **nothing** lands in `docker/apachelogs_8/` |
| Code changes | Dev: hot-reloaded via Caddy's `watch`. **Prod: `make api2_restart` is required** |
| Mount | Needs `../sources:/var/www/html` too, for the shared live cache (`live_document_root`) |

Rationale, pitfalls and validation plan:
[FRANKENPHP_MIGRATION_ANALYSIS.md](../../DOC/developer/audits/FRANKENPHP_MIGRATION_ANALYSIS.md)

### Mercure

The hub is served by FrankenPHP itself at `https://kpi.localhost/api2/.well-known/mercure`.
`symfony/mercure-bundle` is installed, so publishing is done with `HubInterface`:

```php
$hub->publish(new Update('test/ping', json_encode(['message' => 'hello'])));
```

| Variable | Dev value | Purpose |
|---|---|---|
| `MERCURE_URL` | `http://localhost/.well-known/mercure` | Publish from Symfony, **inside** the container (bypasses Traefik + its self-signed cert) |
| `MERCURE_PUBLIC_URL` | `https://kpi.localhost/api2/.well-known/mercure` | Subscribe from the browser, **through** Traefik |
| `MERCURE_JWT_SECRET` | = `MERCURE_JWT_SECRET` in `docker/.env` | **Must match the hub's secret**, otherwise publishes get 403 |

A test bench (profile 1 only) is available in app4 under **Operations → Mercure**.

> ⚠️ `EventSource` cannot send an `Authorization` header. In dev, browser subscriptions rely on
> `MERCURE_ANONYMOUS=1`. In preprod/prod (`MERCURE_ANONYMOUS=0`) a subscriber JWT will be needed —
> to be handled by the live scoring refactor.

> ⚠️ Do **not** re-run the `symfony/mercure-bundle` Flex recipe: it injects a standalone
> `dunglas/mercure` service into `compose.yaml`/`compose.override.yaml` (files unused by this
> project) — precisely what the built-in hub makes unnecessary. Those edits were reverted on purpose.

## Installation

### Initial Setup

1. Copy the `.env.dist` file to `.env`:
   ```bash
   cp sources/api2/.env.dist sources/api2/.env
   ```
   Or use the Makefile command:
   ```bash
   make init_env_api2
   ```

2. Install Composer dependencies:
   ```bash
   make api2_backend_composer_install
   ```

The API dependencies are managed by Composer and will be installed in the `vendor/` directory.

### Configuration

The API is configured through the `.env` file (copied from `.env.dist`):

```env
DATABASE_URL="mysql://root:root@kpi_db:3306/kayak_polo?serverVersion=11.5.2-MariaDB&charset=utf8mb4"
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1|.*\.localhost)(:[0-9]+)?$'
```

**Note**: The `.env` file is not versioned in Git (ignored via `.gitignore`). Always use `.env.dist` as the template and create your local `.env` file from it.

## API Endpoints

### Authentication

#### Login
- `POST /login` - User authentication
  - **Authentication:** HTTP Basic Auth (Authorization header)
  - **Request:** `Authorization: Basic base64(username:password)`
  - **Response:**
    ```json
    {
      "user": {
        "id": "123456",
        "name": "Dupont",
        "firstname": "Jean",
        "profile": "O",
        "events": "123|456|789",
        "token": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
      }
    }
    ```
  - **Token Usage:** The returned `token` should be used in Staff and Report endpoints
  - **Token Validity:** 10 days from generation
  - **Token Transmission:**
    - **Recommended:** Via header `X-Auth-Token: {token}` (used by app2)
    - Alternative: Via cookie `kpi_app={token}`

### Public Endpoints (No Authentication)

#### Events
- `GET /events/{mode}` - Get events list
  - `mode`: `std`, `champ`, or `all`
- `GET /event/{id}` - Get single event

#### Games
- `GET /games/{eventId}` - Get games for an event

#### Charts & Rankings
- `GET /charts/{eventId}` - Get charts and rankings for an event

#### Team Statistics
- `GET /team-stats/{teamId}/{eventId}` - Get team statistics

#### App Ratings
- `GET /stars` - Get app ratings statistics
- `POST /rating` - Submit app rating
  ```json
  {
    "uid": "uuid-v4",
    "stars": 4
  }
  ```

### Staff Endpoints (Token Authentication Required)

**Authentication:** All staff endpoints require a valid token obtained from `/login`

**Token transmission:** Use `X-Auth-Token` header or `kpi_app` cookie

- `GET /staff/{eventId}/teams` - Get teams for scrutineering
- `GET /staff/{eventId}/team/{teamId}/players` - Get players for a team (returns fresh data with no-cache headers)
- `PUT /staff/{eventId}/team/{teamId}/player/{playerId}/{parameter}/{value}` - Update player scrutineering data
  - `parameter`: `kayak_status`, `vest_status`, `helmet_status`, `paddle_count`
- `PUT /staff/{eventId}/team/{teamId}/player/{playerId}/comment` - Update player comment
  ```json
  {
    "comment": "Comment text"
  }
  ```

### Report Endpoints (Token Authentication Required)

**Authentication:** All report endpoints require a valid token obtained from `/login`

**Token transmission:** Use `X-Auth-Token` header or `kpi_app` cookie

- `GET /report/game/{gameId}` - Get game details with events and players

### WSM (Web Score Management) Endpoints

- `PUT /api/wsm/eventNetwork/{eventId}` - Update event network data
- `PUT /api/wsm/gameParam/{matchId}` - Update game parameters
  ```json
  {
    "param": "Statut|Periode|ScoreA|ScoreB|ScoreDetailA|ScoreDetailB|Heure_fin",
    "value": "..."
  }
  ```
- `PUT /api/wsm/gameEvent/{matchId}` - Add/remove game events
  ```json
  {
    "params": {
      "action": "add|remove",
      "uid": "event-id",
      "period": 1,
      "tpsJeu": "10:00",
      "code": "B|V|J|R|D",
      "player": "123456",
      "number": 5,
      "team": "A|B",
      "reason": "..."
    }
  }
  ```
- `PUT /api/wsm/playerStatus/{matchId}` - Update player status
  ```json
  {
    "params": {
      "team": "A|B",
      "player": "123456",
      "status": "C|E|..."
    }
  }
  ```
- `PUT /api/wsm/gameTimer/{matchId}` - Control game timer
  ```json
  {
    "params": {
      "action": "run|stop|RAZ",
      "startTime": 0,
      "runTime": 600,
      "maxTime": 1200
    }
  }
  ```
- `PUT /api/wsm/stats` - Add game statistics
  ```json
  {
    "user": "user-id",
    "game": 123,
    "team": "A|B",
    "player": "123456",
    "action": "pass|possession|kickoff|shot-in|shot-out|shot-stop",
    "period": 1,
    "timer": "10:00"
  }
  ```

### Admin Endpoints (JWT Protected, app4)

All admin endpoints are documented in detail in **[API2_ENDPOINTS.md](../../DOC/developer/reference/API2_ENDPOINTS.md)**.

**Controllers:**
- `AdminAuthController` - JWT login, me, refresh, reset password
- `AdminAuthMandateController` - Multi-mandate support (list mandates, switch)
- `AdminEventController` - Events CRUD
- `AdminCompetitionsController` - Competitions CRUD, status, lock, publish
- `AdminCompetitionCopyController` - Competition schema copy
- `AdminGamedaysController` - Gamedays CRUD, bulk ops, officials, events
- `AdminGamesController` - Games CRUD, bulk ops (26 routes), referee autocomplete
- `AdminTeamsController` - Competition teams, pool/draw, colors, logos
- `AdminGroupsController` - Groups CRUD, reorder
- `AdminRankingsController` - Rankings compute, publish, transfer, initial
- `AdminRcController` - Competition officials (RC)
- `AdminPresenceController` - Team & match player compositions
- `AdminStatsController` - Statistics data, filters, PDF/XLSX exports
- `AdminAthletesController` - Athlete search, profile, participations
- `AdminClubsController` - Clubs search, map, detail, teams, departmental committees
- `AdminUsersController` - Users CRUD, mandates, password reset
- `AdminTvController` - TV control (channels, presentations, scenarios, labels)
- `AdminSchemaController` - Competition schema visualization
- `AdminJournalController` - Action log consultation
- `AdminOperationsController` - System operations (seasons, images, merges, imports)
- `AdminFiltersController` - Shared filter data (seasons, competitions, events)

## Database Tables Used

- `kp_evenement` - Events
- `kp_journee` - Competition days
- `kp_match` - Matches/Games
- `kp_match_detail` - Match events (goals, cards, etc.)
- `kp_match_joueur` - Match players
- `kp_competition` - Competitions
- `kp_competition_equipe` - Teams
- `kp_competition_equipe_joueur` - Team players
- `kp_competition_equipe_journee` - Team standings per day
- `kp_licence` - Player licenses
- `kp_arbitre` - Referees
- `kp_app_rating` - App ratings
- `kp_scrutineering` - Equipment scrutineering data
- `kp_chrono` - Game timer data
- `kp_stats` - Game statistics
- `kp_utilisateur` - Users
- `kp_mandat` - User mandates
- `kp_club` - Clubs
- `kp_journal` - Action log
- `kp_groupe` - Competition groups
- `kp_schema` - Competition schemas
- `kp_tv_channel` - TV channels
- `kp_tv_scenario` - TV scenarios

## Development

### Running the API

The API is served through Apache in the Docker container. The document root is `/sources/api2/public/`.

### Accessing the API

- Development: `https://kpi.localhost/api2/`
- API Platform UI: `https://kpi.localhost/api2/doc` (API Platform interface)
- **Swagger UI**: `https://kpi.localhost/api2/doc` (Complete OpenAPI documentation - requires NelmioApiDocBundle)

**Note**: For complete Swagger/OpenAPI documentation with all endpoints, see [SWAGGER_SETUP.md](SWAGGER_SETUP.md) for installation instructions.

### Cache Management

```bash
# Clear cache
php bin/console cache:clear

# Warmup cache
php bin/console cache:warmup
```

## Complete API Documentation

For complete endpoint reference with examples and migration guide, see:
- **[API2_ENDPOINTS.md](../../DOC/developer/reference/API2_ENDPOINTS.md)** - Complete endpoint reference with examples

## Migration from Legacy API

This API provides the same endpoints and functionality as `/sources/api/` but with:

- **Better structure** - Controllers organized by domain
- **Type safety** - PHP 8 type hints throughout
- **Modern practices** - Dependency injection, service container
- **Auto-documentation** - OpenAPI/Swagger via API Platform
- **Extensibility** - Easy to add new endpoints and features
- **Security** - Built-in CSRF protection, input validation
- **RESTful URLs** - Staff routes follow RESTful patterns (`/staff/{eventId}/team/{teamId}/...`)
- **No-cache headers** - Staff endpoints return fresh data for real-time scrutineering

## TODO

- [x] ✅ Add `/login` endpoint for authentication
- [x] ✅ Add authentication documentation
- [x] ✅ Implement token validation service for staff and report endpoints
- [x] ✅ Update Staff/Report endpoints to use `X-Auth-Token` header (compatible with app2)
- [x] ✅ JWT authentication for admin endpoints
- [x] ✅ Admin CRUD for all management pages (events, competitions, gamedays, games, teams, users, clubs, etc.)
- [x] ✅ Multi-mandate authentication system
- [x] ✅ Match player composition management (team mode + match mode)
- [x] ✅ TV control endpoints
- [x] ✅ Competition schema endpoint
- [x] ✅ Journal/action log endpoint
- [x] ✅ Logging for user actions (AdminLoggableTrait)
- [ ] Add cache layer (similar to json_cache_read/write in legacy API)
- [ ] Implement cache creation for WSM endpoints (CacheMatch equivalent)
- [ ] Add rate limiting
- [ ] Add unit and functional tests
- [ ] Optimize database queries with Doctrine Query Builder
- [ ] Add API versioning support

## Architecture Notes

### Why Direct SQL Queries?

The API currently uses direct SQL queries (via Doctrine DBAL) instead of Doctrine ORM entities for several reasons:

1. **Complex queries** - The legacy API uses complex JOINs and aggregations that are easier to write in SQL
2. **Performance** - Direct SQL is faster for read-heavy operations
3. **Backward compatibility** - Ensures exact same results as legacy API
4. **Database schema** - The existing database wasn't designed with ORM in mind

### Future Improvements

For future versions, consider:

1. Creating proper Doctrine entities for write operations
2. Using Query Builder for simpler queries
3. Implementing a repository pattern
4. Adding DTOs (Data Transfer Objects) for API responses
5. Implementing CQRS pattern (Command Query Responsibility Segregation)

## Support

For questions or issues, refer to the main KPI documentation in `/WORKFLOW_AI/` or contact the development team.
