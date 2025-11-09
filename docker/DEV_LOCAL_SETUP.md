# Configuration Développement Local avec sous-domaines .localhost

Ce guide explique comment configurer l'environnement de développement local avec des sous-domaines `.localhost` via Traefik.

## 🌐 Domaines Disponibles

### HTTP (fonctionne nativement sans configuration)

Les domaines `.localhost` sont automatiquement résolus par le navigateur sans modifier `/etc/hosts` :

- **Backend PHP 7.4** : http://kpi.localhost
- **Backend PHP 8** : http://kpi8.localhost
- **App2 (Nuxt 4)** : http://app2.localhost
- **App2 WSM (Nuxt 4)** : http://app2-wsm.localhost

### HTTPS (nécessite configuration)

Pour utiliser HTTPS en local, vous devez :
1. Configurer `/etc/hosts`
2. Générer des certificats SSL locaux
3. Modifier les domaines dans `docker/.env`

## 📋 Prérequis

### 1. Traefik doit être lancé

Assurez-vous que Traefik tourne avec les entrypoints `web` (HTTP:80) et `websecure` (HTTPS:443).

**Exemple de configuration Traefik** (`traefik.yml`) :
```yaml
entryPoints:
  web:
    address: ":80"
  websecure:
    address: ":443"

providers:
  docker:
    endpoint: "unix:///var/run/docker.sock"
    exposedByDefault: false
    network: traefiknetwork

api:
  dashboard: true
  insecure: true
```

### 2. Réseaux Docker créés

```bash
make init_networks
# ou manuellement :
docker network create network_kpi
docker network create pma_network
docker network create traefiknetwork
```

### 3. Variables d'environnement configurées

```bash
# Initialiser le fichier .env
make init_env

# Éditer docker/.env et remplir :
vi docker/.env
```

**Variables minimales requises** :
```bash
USER_ID=1000
GROUP_ID=1000

DB_ROOT_PASSWORD=root
DB_USER=kpi
DB_PASSWORD=kpi123
DB_NAME=kpi

DBWP_ROOT_PASSWORD=root
DBWP_USER=wp
DBWP_PASSWORD=wp123
DBWP_NAME=wordpress
```

## 🚀 Démarrage

### 1. Initialisation complète

```bash
# Initialiser l'environnement
make init

# Lancer les containers
make dev_up

# Vérifier que tous les services sont démarrés
make dev_status
```

### 2. Installation des dépendances

```bash
# PHP (Composer)
make composer_install

# App2 (Nuxt)
make npm_install_app2

# App2 WSM (Nuxt)
make npm_install_app2_wsm
```

### 3. Lancer les serveurs de développement Nuxt

```bash
# Dans un terminal : App2
make run_dev

# Dans un autre terminal : App2 WSM
make run_dev_wsm
```

## 🌍 Accès aux applications

Une fois tout démarré :

| Application | URL HTTP | Port direct |
|-------------|----------|-------------|
| Backend PHP 7.4 | http://kpi.localhost | http://localhost:8001 |
| Backend PHP 8 | http://kpi8.localhost | http://localhost:8801 |
| App2 | http://app2.localhost | http://localhost:3002 |
| App2 WSM | http://app2-wsm.localhost | http://localhost:3003 |

## 🔧 Configuration des fichiers .env

### Docker (.env principal)

Fichier : `docker/.env`

```bash
# Copier depuis le template
cp docker/.env.dist docker/.env

# Éditer et configurer
vi docker/.env
```

**Domaines par défaut** (HTTP via .localhost) :
```bash
KPI_DOMAIN_NAME=kpi.localhost
KPI_DOMAIN_NAME_8=kpi8.localhost
APP2_DOMAIN_NAME=app2.localhost
APP2_WSM_DOMAIN_NAME=app2-wsm.localhost
```

**Pour utiliser HTTPS** (nécessite certificats) :
```bash
KPI_DOMAIN_NAME=kpi.local
KPI_DOMAIN_NAME_8=kpi8.local
APP2_DOMAIN_NAME=app2.local
APP2_WSM_DOMAIN_NAME=app2-wsm.local
```

### Nuxt App2

Fichier : `sources/app2/.env.development`

```bash
BASE_URL=
API_BASE_URL=http://kpi.localhost/api
BACKEND_BASE_URL=http://kpi.localhost
```

### Nuxt App2 WSM

Fichier : `sources/app2_wsm/.env.development`

```bash
BASE_URL=
API_BASE_URL=http://kpi.localhost/api
BACKEND_BASE_URL=http://kpi.localhost
```

## 🔍 Vérification

### 1. Vérifier que Traefik voit les services

```bash
# Lister les containers
docker ps | grep kpi

# Vérifier les labels Traefik
docker inspect kpi_php | grep traefik
```

### 2. Tester les routes

```bash
# Tester le backend PHP
curl -I http://kpi.localhost

# Tester App2
curl -I http://app2.localhost

# Tester App2 WSM
curl -I http://app2-wsm.localhost
```

### 3. Logs Traefik

Si Traefik est configuré avec le dashboard :
- http://localhost:8080 (si `api.insecure=true`)

## ⚙️ Configuration Avancée

### Utiliser HTTPS en local

1. **Ajouter les domaines à /etc/hosts** :
```bash
sudo vi /etc/hosts

# Ajouter :
127.0.0.1 kpi.local kpi8.local app2.local app2-wsm.local
```

2. **Générer des certificats SSL** :
```bash
# Utiliser mkcert (recommandé)
brew install mkcert  # macOS
# ou
sudo apt install mkcert  # Ubuntu

# Installer le CA local
mkcert -install

# Générer les certificats
mkdir -p docker/certs
cd docker/certs
mkcert kpi.local kpi8.local app2.local app2-wsm.local "*.localhost"
```

3. **Configurer Traefik pour utiliser les certificats** :
```yaml
# traefik.yml
tls:
  certificates:
    - certFile: /certs/kpi.local+4.pem
      keyFile: /certs/kpi.local+4-key.pem
```

4. **Mettre à jour docker/.env** :
```bash
KPI_DOMAIN_NAME=kpi.local
APP2_DOMAIN_NAME=app2.local
APP2_WSM_DOMAIN_NAME=app2-wsm.local
```

### Ports personnalisés

Pour éviter les conflits de ports, vous pouvez modifier `DOCKER_SUFFIXE_PORT` dans `docker/.env` :

```bash
# docker/.env
DOCKER_SUFFIXE_PORT=02  # Ports seront 8002, 8802, etc.
```

## 🐛 Dépannage

### Les domaines .localhost ne fonctionnent pas

**Problème** : Le navigateur ne résout pas `kpi.localhost`

**Solutions** :
1. Vérifier que Traefik est démarré et écoute sur le port 80
2. Vérifier que les containers sont dans le réseau `traefiknetwork`
3. Essayer avec un autre navigateur (Chrome, Firefox supportent nativement .localhost)
4. Utiliser `.local` avec configuration /etc/hosts

### Traefik ne route pas les requêtes

**Vérifications** :
```bash
# Les containers sont dans le bon réseau ?
docker network inspect traefiknetwork

# Les labels Traefik sont corrects ?
docker inspect kpi_node_app2 | grep traefik

# Traefik voit les services ?
# Aller sur le dashboard Traefik : http://localhost:8080
```

### Port déjà utilisé

**Erreur** : `Bind for 0.0.0.0:3002 failed: port is already allocated`

**Solutions** :
```bash
# Vérifier quel processus utilise le port
sudo lsof -i :3002

# Changer le port dans compose.dev.yaml
# Par exemple : "3012:3000" au lieu de "3002:3000"
```

### Les modifications ne sont pas prises en compte

```bash
# Reconstruire les images
make dev_rebuild

# Ou manuellement
docker compose -f docker/compose.dev.yaml down
docker compose -f docker/compose.dev.yaml build --no-cache
docker compose -f docker/compose.dev.yaml up -d
```

## 📝 Notes

- Les domaines `.localhost` fonctionnent **sans configuration DNS** grâce au support natif des navigateurs
- Pour un environnement de production, utilisez des vrais domaines avec HTTPS
- Les logs Apache sont dans `docker/apachelogs/` et `docker/apachelogs8/`
- Les données MySQL sont dans `docker/db/mysql/` et `docker/db/mysqlwp/`

## 🔗 Ressources

- [Documentation Traefik](https://doc.traefik.io/traefik/)
- [Nuxt 4 Documentation](https://nuxt.com/docs)
- [mkcert pour certificats locaux](https://github.com/FiloSottile/mkcert)
