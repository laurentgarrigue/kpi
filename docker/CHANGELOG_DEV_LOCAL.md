# Changelog - Configuration Développement Local

## 2025-01-09 - Support sous-domaines .localhost

### 🎯 Objectif
Faciliter le développement local avec des sous-domaines `.localhost` accessibles via Traefik, sans configuration DNS nécessaire.

### ✨ Nouveautés

#### 1. compose.dev.yaml

**Modifications** :
- ✅ Ajout du service `node_app2_wsm` pour la nouvelle application WebSocket Manager
- ✅ Support HTTP et HTTPS pour tous les services via Traefik
- ✅ Labels Traefik séparés pour HTTP (entrypoint `web`) et HTTPS (entrypoint `websecure`)
- ✅ Configuration des routes :
  - `kpi-http` / `kpi-https` pour le backend PHP 7.4
  - `kpi8-http` / `kpi8-https` pour le backend PHP 8
  - `app2-http` / `app2-https` pour Nuxt App2
  - `app2wsm-http` / `app2wsm-https` pour Nuxt App2 WSM

**Nouveau service node_app2_wsm** :
```yaml
node_app2_wsm:
  container_name: kpi_node_app2_wsm
  build:
    context: ../sources/app2_wsm
    dockerfile: ../../docker/node/Dockerfile
  ports:
    - "3003:3000"
  labels:
    - "traefik.http.routers.app2wsm-http.rule=Host(`${APP2_WSM_DOMAIN_NAME}`)"
```

#### 2. .env.dist

**Nouvelles variables** :
```bash
BASE_IMAGE_PHP_8=php:8.3-apache-bookworm
KPI_DOMAIN_NAME=kpi.localhost
KPI_DOMAIN_NAME_8=kpi8.localhost
APP2_DOMAIN_NAME=app2.localhost
APP2_WSM_DOMAIN_NAME=app2-wsm.localhost
HOST_APACHE2_LOG_PATH_8=./apachelogs8/
```

**Commentaires ajoutés** :
- Documentation des domaines .localhost vs .local
- Explication de la configuration HTTP vs HTTPS
- Notes sur la compatibilité

#### 3. Documentation

**Nouveau fichier** : `DEV_LOCAL_SETUP.md`

Contenu :
- Guide complet de configuration
- Prérequis et installation
- Configuration Traefik
- URLs d'accès pour chaque service
- Configuration HTTPS optionnelle avec mkcert
- Dépannage complet

### 🌐 Domaines disponibles

| Service | Domaine HTTP | Port direct |
|---------|--------------|-------------|
| Backend PHP 7.4 | http://kpi.localhost | :8001 |
| Backend PHP 8 | http://kpi8.localhost | :8801 |
| App2 (Nuxt) | http://app2.localhost | :3002 |
| App2 WSM (Nuxt) | http://app2-wsm.localhost | :3003 |

### 📋 Avantages

1. **Pas de configuration DNS** : Les domaines `.localhost` sont résolus nativement par les navigateurs
2. **Développement multi-app** : Chaque application a son propre sous-domaine
3. **Traefik routing** : Routage automatique basé sur les labels Docker
4. **Flexibilité HTTP/HTTPS** : Support des deux protocoles avec la même configuration
5. **Isolation** : Chaque service Node (app2, app2_wsm) a son propre container

### 🔄 Migration depuis l'ancienne config

**Avant** :
```bash
# Accès direct par ports
http://localhost:8001  # PHP
http://localhost:3002  # App2
```

**Après** :
```bash
# Accès par sous-domaines (plus les ports directs)
http://kpi.localhost      # PHP via Traefik
http://app2.localhost     # App2 via Traefik
http://app2-wsm.localhost # App2 WSM via Traefik

# Ports directs toujours disponibles
http://localhost:8001  # PHP direct
http://localhost:3002  # App2 direct
http://localhost:3003  # App2 WSM direct
```

### 🛠️ Commandes make ajoutées

Ajoutées dans le commit précédent :
- `make run_dev_wsm` - Lance App2 WSM en développement
- `make npm_install_app2_wsm` - Installe les dépendances
- `make node_bash_wsm` - Shell dans le container Node WSM

### ⚠️ Breaking Changes

**Aucun** - La configuration est rétrocompatible :
- Les ports directs fonctionnent toujours
- L'ancienne variable `NODE_DOMAIN_NAME` est conservée
- Pas besoin de reconstruire les images existantes

### 📝 Actions requises après update

1. **Mettre à jour docker/.env** :
```bash
cp docker/.env docker/.env.backup
# Ajouter les nouvelles variables de .env.dist
```

2. **Recréer les services** :
```bash
make dev_down
make dev_up
```

3. **Tester les nouveaux domaines** :
```bash
curl -I http://kpi.localhost
curl -I http://app2.localhost
curl -I http://app2-wsm.localhost
```

### 🔗 Fichiers modifiés

- `docker/compose.dev.yaml` - Configuration Docker Compose mise à jour
- `docker/.env.dist` - Template avec nouvelles variables
- `docker/DEV_LOCAL_SETUP.md` - Documentation complète (NOUVEAU)
- `docker/CHANGELOG_DEV_LOCAL.md` - Ce fichier (NOUVEAU)

### 📚 Prochaines étapes potentielles

- [ ] Configuration similaire pour compose.preprod.yaml
- [ ] Configuration similaire pour compose.prod.yaml
- [ ] Script d'initialisation automatique
- [ ] Tests automatisés des routes Traefik
- [ ] Configuration CI/CD adaptée

### 🐛 Issues connues

Aucune pour le moment.

### 💡 Notes

- La configuration Traefik doit avoir les entrypoints `web` (80) et `websecure` (443)
- Les certificats SSL pour HTTPS sont optionnels (recommandé : mkcert)
- Les domaines `.localhost` fonctionnent sans /etc/hosts sur la plupart des navigateurs modernes
