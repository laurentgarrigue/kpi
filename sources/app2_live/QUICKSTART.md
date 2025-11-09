# Guide de démarrage rapide - App2 Live

Guide pour démarrer rapidement l'application app2_live en environnement Docker.

## 🚀 Démarrage rapide

### 1. Initialiser l'environnement

```bash
# À la racine du projet
make init_env_app2_live
```

Cela créera les fichiers `.env.development` et `.env.production` dans `sources/app2_live/`.

### 2. Démarrer les containers Docker

```bash
# Démarrer l'environnement de développement
make dev_up

# Attendre que les containers soient prêts
make dev_status
```

### 3. Installer les dépendances NPM

```bash
# Installer toutes les dépendances
make npm_install_app2_live
```

Cette commande va :
- Se connecter au container `kpi_node_app2_live`
- Installer toutes les dépendances NPM listées dans `package.json`
- Créer le dossier `node_modules/`

### 4. Lancer le serveur de développement

```bash
# Démarrer Nuxt en mode développement
make run_dev_live
```

L'application sera accessible sur : **http://localhost:3001/app2_live**

## 📋 Configuration

### Variables d'environnement

Fichier `.env.development` (créé automatiquement) :
```bash
BASE_URL=/app2_live
API_BASE_URL=https://kpi.local/api
BACKEND_BASE_URL=https://kpi.local
```

### Ports

- **3001** : Port de développement Nuxt (host et container)
- **3002** : Port app2 (pour référence)

### Container Docker

Le service `node_app2_live` est configuré dans `docker/compose.dev.yaml` :

```yaml
node_app2_live:
    container_name: kpi_node_app2_live
    ports:
        - "3001:3001"
    volumes:
        - ../sources/app2_live:/app
```

## 🔧 Commandes utiles

### NPM

```bash
# Installer les dépendances
make npm_install_app2_live

# Nettoyer node_modules
make npm_clean_app2_live

# Mettre à jour les dépendances
make npm_update_app2_live

# Ajouter un package
make npm_add_app2_live package=nom-du-package

# Ajouter un package de développement
make npm_add_dev_app2_live package=nom-du-package
```

### Nuxt

```bash
# Serveur de développement
make run_dev_live

# Build pour production
make run_build_live

# Générer site statique
make run_generate_live

# Linter ESLint
make run_lint_live
```

### Docker

```bash
# Accéder au shell du container
make node_bash_live

# Redémarrer le container
make dev_restart

# Voir les logs
make dev_logs
```

## 🌐 Utilisation

### Page d'accueil

Accédez à : **http://localhost:3001/app2_live**

Vous verrez la page d'accueil avec les instructions d'utilisation.

### Affichage live

Format d'URL : `/app2_live/live/:event/:pitch/:options`

**Exemple** :
```
http://localhost:3001/app2_live/live/123/1/score/inter/full
```

Où :
- `123` = ID de l'événement
- `1` = Numéro du terrain
- `score` = Mode d'affichage
- `inter` = Zone internationale (codes 3 lettres + drapeaux)
- `full` = Mode complet avec animations

### Options disponibles

**Modes d'affichage** :
- `score` - Affichage des scores
- `main` - Affichage principal (à venir)
- `match` - Affichage match (à venir)

**Zones** :
- `club` - Noms complets
- `inter` - Codes 3 lettres + drapeaux

**Modes d'animation** :
- `full` - Complet avec animations
- `only` - Score uniquement
- `events` - Événements uniquement
- `static` - Sans animations

**Langues** :
- `fr` - Français
- `en` - English

**Styles** :
- `default` - Style par défaut
- `saintomer2022` - Style Saint-Omer 2022
- `welland2018` - Style Welland 2018

## 🐛 Dépannage

### Le container ne démarre pas

Vérifiez que les réseaux Docker existent :
```bash
make networks_create
```

### Erreur "Cannot find module"

Réinstallez les dépendances :
```bash
make npm_clean_app2_live
make npm_install_app2_live
```

### Port 3001 déjà utilisé

Modifiez le port dans `docker/compose.dev.yaml` :
```yaml
ports:
    - "3003:3001"  # Utilisez 3003 au lieu de 3001
```

Puis redémarrez :
```bash
make dev_down
make dev_up
```

### Problèmes de permissions

Le container utilise votre USER_ID et GROUP_ID. Vérifiez `docker/.env` :
```bash
cat docker/.env | grep USER_ID
```

## 📚 Documentation complète

Voir [README.md](README.md) pour la documentation complète de l'application.

## 🔗 Liens utiles

- **Page d'accueil** : http://localhost:3001/app2_live
- **Exemple live** : http://localhost:3001/app2_live/live/123/1/score/inter/full
- **Documentation Nuxt** : https://nuxt.com/docs
- **Documentation Tailwind** : https://tailwindcss.com/docs
