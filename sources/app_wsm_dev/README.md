# KPI WSM APP (Vue 3 + Vue CLI)

Application de gestion du websocket (WebSocket Manager) pour KPI (Kayak Polo Info).

## 🎯 Rôle dans la chaîne temps réel

WSM est le **pont** entre le matériel de la table de marque et KPI :

```
console de marque propriétaire → passerelle (STOMP, LAN) → app_wsm → KPI (écritures DB)
                                              └──→ broker KPI → app_live (incrustation)
```

Il se connecte en **STOMP** aux flux de la passerelle (un par terrain), **persiste** les données du
match dans KPI via `fetch`/AJAX (`/api/wsm/*`), et **rediffuse** optionnellement les événements vers
le broker WebSocket KPI que consomme l'incrustation `app_live`. Clé d'échange broker :
`<numeroEvenement>_<numero_terrain>`.

> ⚠️ **Dépendance au cache** : le chargement des équipes (`set-teams`) et l'enchaînement des matchs
> reposent sur les fichiers JSON `event{event}_pitch{pitch}.json` (`id_match` = match courant,
> `id_next` = match suivant) **générés par l'Event Cache Manager**, désormais un **worker en
> arrière-plan**. Le worker doit tourner pendant l'événement, sinon WSM chargerait un mauvais match.
> Voir [Event Cache Manager](../../DOC/user/EVENT_CACHE_MANAGER.md).

> ℹ️ **Persistance** : score, période, buts/cartons, joueurs actifs et chrono de jeu sont écrits en
> base. Le **shotclock** (`POSSES`) et le **compteur de pénalités** sont **live seulement** (broker +
> affichage), non persistés.

> 🔌 **Périmètre — mode complet propriétaire uniquement** : WSM ne couvre que les terrains équipés
> **console de marque + passerelle**. Les terrains **sans ce matériel** sont gérés en parallèle par la **feuille
> de marque KPI** (`FeuilleMarque2.php` = écritures PHP sans WebSocket ; `FeuilleMarque3.php` =
> écritures PHP + broker WebSocket) ou en **saisie a posteriori** (incrustation des noms d'équipe
> seuls). Voir §14 « Modes de fonctionnement » de la doc technique.

📖 **Documentation technique complète** (protocoles STOMP, payloads, diagrammes, démarrage/
enchaînement, contrôles visuels, **modes dégradés**) :
[DOC/developer/reference/LIVE_MATCH_WEBSOCKET_ARCHITECTURE.md](../../DOC/developer/reference/LIVE_MATCH_WEBSOCKET_ARCHITECTURE.md)

## 🚀 Démarrage rapide

### Développement

```bash
# Dans le conteneur Docker
docker exec kpi_node_wsm npm run serve:dev

# Accès
https://wsm.kpi.localhost (via Traefik)
http://localhost:8080 (direct)
```

### Installation des dépendances

```bash
docker exec kpi_node_wsm npm install --legacy-peer-deps
```

## 📦 Scripts disponibles

### Serveur de développement

```bash
# Mode development (défaut) - utilise .env.development
npm run serve
npm run serve:dev

# Mode production - utilise .env.production
npm run serve:prod
```

### Build pour déploiement

```bash
# Development build
npm run build:dev

# Pre-production build
npm run build:preprod

# Production build (optimisé)
npm run build:prod
```

### Autres commandes

```bash
# Linter
npm run lint

# i18n report
npm run i18n:report

# Serveur HTTP statique (après build)
npm run http-server
```

## 🔧 Configuration

Voir [ENV_USAGE.md](ENV_USAGE.md) pour la documentation complète sur les environnements.

### Fichiers d'environnement

- `.env.development` - Développement local
- `.env.production` - Production
- `.env.preprod` - Pré-production
- `.env.local` - Surcharges locales (non committé)

### Variables d'environnement

```env
VUE_APP_TITLE=KPI WSM
VUE_APP_API_BASE_URL=http://kpi.localhost
VUE_APP_BASE_URL=http://wsm.kpi.localhost
VUE_APP_I18N_LOCALE=en
VUE_APP_I18N_FALLBACK_LOCALE=en
```

## 🛠️ Stack technique

- **Vue 3** - Framework JavaScript
- **Vue CLI 5** - Tooling et build
- **Vuex 4** - State management
- **Vue Router 4** - Routing
- **Vue i18n 9** - Internationalisation
- **Bootstrap 5** - UI Framework
- **Element Plus** - Composants UI
- **Axios** - HTTP client
- **Day.js** - Date manipulation
- **IndexedDB (idb)** - Storage local

## 📱 PWA

L'application est une Progressive Web App avec :
- Service Worker personnalisé
- Manifest configuré
- Support offline
- Installation possible

## 🌐 Accès

### Développement
- **Traefik** : `https://wsm.kpi.localhost`
- **Direct** : `http://localhost:8080`

### Production
- `https://wsm.kayak-polo.info`

## 🔄 Migration vers Nuxt 4

Cette application Vue 3 avec Vue CLI est prévue pour être migrée progressivement vers Nuxt 4.

## 📝 Notes

- Utiliser `--legacy-peer-deps` pour l'installation npm (conflits de peer dependencies)
- Le HMR fonctionne via WebSocket (wss:// en HTTPS)
- Buffer polyfill ajouté pour compatibilité webpack 5

---

## Ancienne documentation

### Apache ActiveMQ
```bash
cd ~/Documents/dev/activemq/apache-activemq-5.17.1/bin
./activemq console

# Interface admin
http://localhost:8161/admin/
# Credentials: admin / admin

# WebSocket
ws://localhost:61614
# Credentials: admin / password
```
