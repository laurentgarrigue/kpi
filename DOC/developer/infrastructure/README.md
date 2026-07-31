# Documentation Infrastructure - KPI

Documentation technique sur l'infrastructure Docker, Nginx, CORS et déploiement.

## 📚 Documents Disponibles

### Déploiement et Architecture

#### [DEPLOYMENT_RUNBOOK.md](DEPLOYMENT_RUNBOOK.md) ⭐
**Status**: ✅ Opérationnel (2026-07-30) — préprod auto + prod manuelle

**Runbook** des déploiements : à lire **en situation**, quand il faut agir.

**Sujets couverts**:
- Déclencher un déploiement préprod / production / **expérimental** (branche feature + TTL)
- Où regarder pour constater un problème (Actions, log du wrapper, `make api2_logs`)
- Table des échecs courants et de leur correctif (timeout SSH, ACL, remote git, politique de branche)
- Rollback du **code** : automatique, réparation de `develop`, procédure manuelle
- Rollback de la **base de données** prod depuis `pre-migration/`
- Ce qui n'est PAS en place (notifications, uptime externe)

**Pour qui**: Mainteneur, astreinte
**Pré-requis**: Accès SSH VPS, droits GitHub Actions

---

#### [NGINX_STATIC_APP_DEPLOYMENT.md](NGINX_STATIC_APP_DEPLOYMENT.md)
**Status**: ✅ Implémenté (2025-12-21)

Documentation complète sur le déploiement des applications Nuxt (app2 & app3) via Nginx en mode SSG (Static Site Generation).

**Sujets couverts**:
- Architecture Nginx + Nuxt SSG
- Configuration Nginx pour SPA routing
- Workflow de build dev vs prod
- Containers Node.js temporaires pour builds prod
- Variables d'environnement (.env.development, .env.production)
- Intégration Docker Compose et Traefik
- Commandes Makefile (`app2_generate_dev`, `app2_generate_prod`)
- Troubleshooting (403, Service Worker, URLs incorrectes)

**Pour qui**: Développeurs, DevOps
**Pré-requis**: Connaissances Docker, Nuxt.js

---

#### [../audits/FRANKENPHP_MIGRATION_ANALYSIS.md](../audits/FRANKENPHP_MIGRATION_ANALYSIS.md)
**Status**: ✅ Implémenté en dev (2026-07-16) — validation préprod/prod à faire

**Le document de référence sur l'architecture web actuelle.** `/api2` a été extrait dans un
conteneur **FrankenPHP (Caddy) en mode worker** ; Apache continue de servir le legacy et WordPress.

**Sujets couverts**:
- Pourquoi api2 seulement, et pas un remplacement global d'Apache
- Conteneur `api2` : Dockerfile, Caddyfile, service Compose, routage Traefik (`stripprefix`)
- Hub **Mercure** natif (SSE) et configuration `symfony/mercure-bundle`
- Pièges : chemin du Caddyfile, `DEFAULT_URI`, `setcap`, `watch`, montage du cache live
- Pin **Symfony 7.4 LTS** (dérive 8.x corrigée dans le `composer.lock`)
- Où lire les logs (`make api2_logs`) — plus rien dans `docker/apachelogs_8/` pour api2
- Plan de validation et procédure de rollback

**Pour qui**: Développeurs backend, DevOps
**Pré-requis**: Docker, Symfony, notions de reverse proxy

---

#### [CORS_CONFIGURATION.md](CORS_CONFIGURATION.md)
**Status**: ✅ Implémenté (2025-12-21) — ⚠️ **périmètre réduit au legacy depuis 2026-07-16**

Configuration CORS globale via PHP auto-prepend pour les endpoints PHP **servis par Apache**.
**Ne concerne plus `/api2`**, qui tourne sous FrankenPHP et utilise NelmioCorsBundle.

**Sujets couverts**:
- Mécanisme PHP `auto_prepend_file`
- Configuration CORS globale pour les endpoints Apache (API legacy, custom files, WordPress)
- Gestion des origines autorisées (production & développement)
- Headers CORS et leur signification
- Gestion des requêtes preflight (OPTIONS)
- Migration depuis configuration Apache statique
- Troubleshooting headers dupliqués
- Tests CORS (curl, browser DevTools)

**Pour qui**: Développeurs backend, DevOps
**Pré-requis**: Connaissances PHP, HTTP/CORS

---

### Multi-Environnements

#### [MAKEFILE_MULTI_ENVIRONMENT.md](../guides/infrastructure/MAKEFILE_MULTI_ENVIRONMENT.md)
**Status**: ✅ Implémenté

Support multi-environnements (dev, preprod, prod) sur le même serveur.

**Sujets couverts**:
- Configuration `APPLICATION_NAME` dans `.env`
- Détection automatique des containers par le Makefile
- Commandes `make dev_*`, `make preprod_*`, `make prod_*`
- Réseaux Docker par environnement

**Pour qui**: DevOps, administrateurs système

---

### Gestion des Dépendances

#### [NPM_BACKEND_PRODUCTION_GUIDE.md](../guides/infrastructure/NPM_BACKEND_PRODUCTION_GUIDE.md)
**Status**: ✅ Implémenté

Gestion des dépendances JavaScript (Flatpickr, Day.js, etc.) dans le backend PHP.

**Sujets couverts**:
- Commandes Makefile NPM pour backend
- Installation via container Node.js temporaire
- Copie des fichiers dans `sources/lib/`
- Intégration dans templates Smarty

**Pour qui**: Développeurs backend

---

## 🔗 Liens Rapides

### Commandes Courantes

```bash
# Build app2 pour développement
make app2_generate_dev

# Build app2 pour production (sans container Node.js permanent)
make app2_generate_prod

# Redémarrer nginx
docker restart kpi_nginx_app2

# Vérifier headers CORS
curl -k -I -H "Origin: https://app.kpi.localhost" https://kpi.localhost/api/test

# Rebuild Docker après changement Dockerfile
make docker_dev_rebuild
make docker_prod_rebuild
```

### Fichiers de Configuration Clés

**Apache (legacy, WordPress)** :

- `docker/config/nginx-app2.conf` - Configuration Nginx pour app2
- `docker/config/auto-prepend-cors.php` - Logique CORS globale (legacy uniquement)
- `docker/config/php-auto-prepend.ini` - Configuration PHP auto-prepend
- `docker/config/000-default.conf` - Configuration Apache (sans headers CORS statiques)
- `sources/app2/.env.development` - Variables env pour build dev
- `sources/app2/.env.production` - Variables env pour build prod

**FrankenPHP (api2)** — cf. [FRANKENPHP_MIGRATION_ANALYSIS.md](../audits/FRANKENPHP_MIGRATION_ANALYSIS.md) :

- `docker/config/Caddyfile.api2` - Caddy : mode worker, hub Mercure, trusted_proxies, logs
- `docker/config/opcache-api2-dev.ini` - Opcache dev pour api2
- `docker/config/php-error-logging-api2.ini` - Erreurs PHP → stderr (sinon **rien n'est loggé**)
- `sources/api2/config/packages/nelmio_cors.yaml` - CORS d'api2 (remplace l'auto-prepend)
- `sources/api2/config/packages/mercure.yaml` - Hub Mercure côté Symfony

### Dockerfiles

- `docker/config/Dockerfile.dev.web` - Image Apache/PHP dev (avec CORS auto-prepend)
- `docker/config/Dockerfile.prod.web` - Image Apache/PHP prod (avec CORS auto-prepend)
- `docker/config/Dockerfile.api2` - Image **FrankenPHP** pour api2 (worker + Mercure)

### Docker Compose

- `docker/compose.dev.yaml` - Services `nginx_app2`, `api2` (FrankenPHP) dev
- `docker/compose.prod.yaml` - Services `nginx_app2`, `api2` (FrankenPHP) prod

## 📖 Guides Associés

### Migration et Bonnes Pratiques
- [BEST_PRACTICES_JAVASCRIPT_SMARTY.md](../guides/BEST_PRACTICES_JAVASCRIPT_SMARTY.md) - Bonnes pratiques JS & Smarty

### Migrations JavaScript
- [FLATPICKR_MIGRATION_GUIDE.md](../guides/migrations/FLATPICKR_MIGRATION_GUIDE.md) - Migration datepicker
- [MIGRATION_AXIOS_FETCH_GUIDE.md](../guides/migrations/MIGRATION_AXIOS_FETCH_GUIDE.md) - Migration Axios → fetch()

## 🐛 Troubleshooting

### Problèmes Courants

#### 1. App2 retourne 403 Forbidden
**Solution**: Vérifier que `ssr: false` dans `nuxt.config.ts` et régénérer avec `make app2_generate_dev`

#### 2. Headers CORS dupliqués
**Solution** (legacy / Apache) :
- Vérifier que `000-default.conf` n'a pas de `Header always set Access-Control-*`
- Rebuild image: `make docker_dev_rebuild`

Sur **`/api2`**, la cause est différente : CORS y est géré **uniquement** par NelmioCorsBundle
(`sources/api2/config/packages/nelmio_cors.yaml` + `CORS_ALLOW_ORIGIN`). Si un `Alias /api2`
Apache était réactivé en parallèle du routage Traefik, les deux serveurs répondraient et les
headers pourraient réapparaître en double. Vérifier qui répond :

```bash
curl -skI https://kpi.localhost/api2/doc | grep -i '^server:'   # attendu : FrankenPHP Caddy
```

#### 3. Une erreur dans api2 n'apparaît nulle part
**Solution**: les logs d'api2 **ne vont pas** dans `docker/apachelogs_8/` (Apache uniquement).

```bash
make api2_logs           # erreurs PHP + Symfony + Caddy
make api2_logs_errors    # erreurs seules
```

#### 4. Service Worker cache old URLs
**Solution**: Désactiver temporairement PWA dans `nuxt.config.ts`:
```typescript
pwa: { disable: true }
```

#### 5. Build prod échoue (pas de Node.js)
**Solution**: Utiliser `make app2_generate_prod` qui crée un container temporaire

## 📞 Support

Pour toute question technique:
1. Consulter la documentation associée
2. Vérifier les logs: `make docker_dev_logs` ou `make docker_prod_logs`
3. Tester avec curl pour CORS
4. Inspecter Network tab dans DevTools

## 🔄 Historique

| Date | Document | Changement |
|------|----------|------------|
| 2025-12-21 | NGINX_STATIC_APP_DEPLOYMENT.md | ✅ Création - Infrastructure Nginx pour app2/app3 |
| 2025-12-21 | CORS_CONFIGURATION.md | ✅ Création - CORS global via PHP auto-prepend |
| 2024-xx-xx | MAKEFILE_MULTI_ENVIRONMENT.md | ✅ Support multi-environnements |
| 2024-xx-xx | NPM_BACKEND_PRODUCTION_GUIDE.md | ✅ NPM pour backend PHP |

## 📝 Notes

- Les fichiers générés (`.output/public/`) ne sont **jamais** commités dans Git
- Les builds se font à la demande via Makefile
- En production, pas besoin de container Node.js permanent
- CORS géré de manière centralisée pour tous les endpoints PHP
