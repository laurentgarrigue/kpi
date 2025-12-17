# Gestion des Tokens API2 - Guide Complet

> **Date** : 2025-12-17
> **Audience** : Développeurs et Administrateurs

---

## 🎯 Comprendre les Tokens

### Deux Niveaux d'Authentification

```
┌─────────────────────────────────────────────────────────────┐
│  1. AUTHENTIFICATION UTILISATEUR (Frontend)                 │
│     - Login/password individuel                             │
│     - Gestion des droits par utilisateur                    │
│     - Session utilisateur                                   │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  2. AUTHENTIFICATION APPLICATION (API2)                     │
│     - Token par application (app2, app3)                    │
│     - Sécurité application-to-API                           │
│     - Pas d'authentification utilisateur                    │
└─────────────────────────────────────────────────────────────┘
```

### Principe

- **Un token par application**, pas par utilisateur
- Les utilisateurs continuent à se connecter avec leur login/password
- Le token permet à l'application d'accéder à l'API2

---

## 📋 Stratégie de Gestion des Tokens

### Pour Applications Internes (app2, app3)

**Recommandation** : Tokens longue durée (3-5 ans)

**Pourquoi ?**
- ✅ Moins de maintenance
- ✅ Moins de risque d'oubli
- ✅ Applications internes = risque acceptable
- ✅ Révocation possible via `active=0` si nécessaire

**Génération** :
```bash
# Token app2 (scrutineering) - 5 ans
docker exec kpi_php php /var/www/html/api2/bin/generate_token.php \
  app2-prod@kayak-polo.info staff 1825

# Token app3 (match sheet) - 5 ans
docker exec kpi_php php /var/www/html/api2/bin/generate_token.php \
  app3-prod@kayak-polo.info report 1825
```

### Pour Applications Temporaires/Externes

**Recommandation** : Tokens courte durée (7-30 jours)

**Exemples** :
```bash
# Token pour un arbitre lors d'un tournoi (7 jours, événement 226)
docker exec kpi_php php /var/www/html/api2/bin/generate_token.php \
  referee@tournament.com report 226 7

# Token pour un organisateur (30 jours, événement 226)
docker exec kpi_php php /var/www/html/api2/bin/generate_token.php \
  organizer@tournament.com staff 226 30
```

---

## 🔄 Renouvellement des Tokens

### Quand Renouveler ?

**Cas 1 : Token expirant bientôt**
```bash
# Vérifier les tokens expirant dans les 90 jours
docker exec kpi_php php /var/www/html/api2/bin/check_token_expiry.php 90

# Si app2 expire bientôt → rotation
docker exec kpi_php php /var/www/html/api2/bin/rotate_token.php 4aa7a96d 1825
```

**Cas 2 : Token compromis**
```bash
# Rotation immédiate
docker exec kpi_php php /var/www/html/api2/bin/rotate_token.php 4aa7a96d 1825

# Ou désactivation manuelle
docker exec kpi_db mariadb -uroot -proot my_database -e \
  "UPDATE kp_staff_tokens SET active=0 WHERE token LIKE '4aa7a96d%';"
```

**Cas 3 : Maintenance préventive**
- Tous les 3-5 ans pour les applications internes
- Rotation planifiée lors d'une maintenance

### Processus de Rotation

#### 1. Vérifier les tokens à renouveler

```bash
# Tokens expirant dans les 30 jours
docker exec kpi_php php /var/www/html/api2/bin/check_token_expiry.php 30
```

#### 2. Générer un nouveau token

**Option A : Rotation automatique** (recommandé)
```bash
# Trouve le token par son préfixe et le renouvelle
docker exec kpi_php php /var/www/html/api2/bin/rotate_token.php 4aa7a96d 1825
```

**Option B : Génération manuelle**
```bash
# Générer nouveau token
docker exec kpi_php php /var/www/html/api2/bin/generate_token.php \
  app2-prod@kayak-polo.info staff 1825

# Désactiver ancien token
docker exec kpi_db mariadb -uroot -proot my_database -e \
  "UPDATE kp_staff_tokens SET active=0 WHERE token='ancien_token_ici';"
```

#### 3. Mettre à jour la configuration

**Fichier : `sources/app2/.env.production`**
```bash
# Remplacer l'ancien token par le nouveau
API2_STAFF_TOKEN=nouveau_token_ici
```

#### 4. Redéployer l'application

```bash
# Production
cd /var/www/html/app2
npm run build
pm2 restart app2

# Ou selon votre méthode de déploiement
```

---

## 🛠️ Scripts de Gestion

### 1. Vérifier l'expiration des tokens

**Script** : `sources/api2/bin/check_token_expiry.php`

**Usage** :
```bash
# Vérifier tokens expirant dans les 30 jours (défaut)
docker exec kpi_php php /var/www/html/api2/bin/check_token_expiry.php

# Vérifier tokens expirant dans les 90 jours
docker exec kpi_php php /var/www/html/api2/bin/check_token_expiry.php 90

# Vérifier tous les tokens de l'année
docker exec kpi_php php /var/www/html/api2/bin/check_token_expiry.php 365
```

**Sortie** :
```
🔍 Vérification des tokens d'authentification
═══════════════════════════════════════════════════════════════

✅ Aucun token expiré

⚠️  TOKENS EXPIRANT BIENTÔT (2 dans les 90 jours)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Email:       app2-prod@kayak-polo.info
  Scope:       staff
  Event ID:    tous
  Expire le:   2026-03-15 10:00:00 (dans 60 jours)
  Token:       4aa7a96dd1c37c0b...

📊 STATISTIQUES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Tokens actifs:  5
  Tokens valides: 5
  Tokens expirés: 0
```

### 2. Rotation de token

**Script** : `sources/api2/bin/rotate_token.php`

**Usage** :
```bash
# Rotation avec les 8+ premiers caractères du token
docker exec kpi_php php /var/www/html/api2/bin/rotate_token.php 4aa7a96d 1825

# Avec confirmation interactive
# → Désactive l'ancien token
# → Génère un nouveau token identique (même user_email, scope, event_id)
# → Affiche le nouveau token à copier
```

**Sortie** :
```
🔄 Rotation de token
═══════════════════════════════════════════════════════════════

📋 ANCIEN TOKEN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Email:    app2-prod@kayak-polo.info
  Scope:    staff
  Event ID: tous
  Expire:   2026-01-16 06:01:19
  Actif:    Oui
  Token:    4aa7a96dd1c37c0b...

⚠️  Cette action va:
  1. Désactiver l'ancien token
  2. Générer un nouveau token avec les mêmes paramètres
  3. Validité du nouveau token: 1825 jours

Continuer ? [y/N] y

✅ Token renouvelé avec succès!

📋 NOUVEAU TOKEN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Token:    a1b2c3d4e5f6...nouveau_token_64_caracteres...
  Email:    app2-prod@kayak-polo.info
  Scope:    staff
  Event ID: tous
  Expire:   2030-01-15 07:15:30 (1825 jours)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

⚠️  ACTION REQUISE:
  1. Mettre à jour le token dans .env.production
  2. Redéployer l'application concernée
```

### 3. Générer un nouveau token

**Script** : `sources/api2/bin/generate_token.php`

**Usage** :
```bash
# Token pour application interne (5 ans)
docker exec kpi_php php /var/www/html/api2/bin/generate_token.php \
  app2-prod@kayak-polo.info staff 1825

# Token pour événement spécifique (30 jours, événement 226)
docker exec kpi_php php /var/www/html/api2/bin/generate_token.php \
  organizer@event226.com staff 226 30
```

---

## 🤖 Automatisation (Recommandé)

### Cron Job pour Monitoring

Ajoutez un cron job pour vérifier les tokens chaque semaine :

```bash
# Editer crontab
crontab -e

# Ajouter cette ligne (exécution tous les lundis à 9h)
0 9 * * 1 docker exec kpi_php php /var/www/html/api2/bin/check_token_expiry.php 90 | mail -s "KPI API2 - Vérification tokens" admin@kayak-polo.info
```

### Script de Notification

**Fichier : `sources/api2/bin/notify_token_expiry.sh`** (à créer)
```bash
#!/bin/bash

# Vérifier tokens expirant dans les 90 jours
OUTPUT=$(docker exec kpi_php php /var/www/html/api2/bin/check_token_expiry.php 90)

# Si des tokens expirent bientôt, envoyer une notification
if echo "$OUTPUT" | grep -q "TOKENS EXPIRANT BIENTÔT"; then
    echo "$OUTPUT" | mail -s "⚠️ KPI API2 - Tokens à renouveler" admin@kayak-polo.info
fi
```

---

## 📊 Cas d'Usage Pratiques

### Cas 1 : Configuration Initiale App2

```bash
# 1. Générer token production (5 ans)
docker exec kpi_php php /var/www/html/api2/bin/generate_token.php \
  app2-prod@kayak-polo.info staff 1825
# → Copier le token généré

# 2. Configurer .env.production
echo "API2_STAFF_TOKEN=token_copié_ici" >> sources/app2/.env.production

# 3. Configurer Nuxt
# Fichier: sources/app2/nuxt.config.ts
# runtimeConfig.public.api2StaffToken = process.env.API2_STAFF_TOKEN
```

### Cas 2 : Rotation Annuelle Préventive

```bash
# Mars 2026 : Rotation du token app2 (créé en mars 2025)

# 1. Vérifier expiration
docker exec kpi_php php /var/www/html/api2/bin/check_token_expiry.php 90

# 2. Rotation
docker exec kpi_php php /var/www/html/api2/bin/rotate_token.php 4aa7a96d 1825

# 3. Mettre à jour .env.production avec le nouveau token

# 4. Redéployer app2
cd /var/www/html/app2
git pull
npm run build
pm2 restart app2
```

### Cas 3 : Token Compromis (Urgence)

```bash
# 1. Désactivation immédiate
docker exec kpi_db mariadb -uroot -proot my_database -e \
  "UPDATE kp_staff_tokens SET active=0 WHERE token LIKE '4aa7a96d%';"

# 2. Génération nouveau token
docker exec kpi_php php /var/www/html/api2/bin/generate_token.php \
  app2-prod@kayak-polo.info staff 1825

# 3. Déploiement d'urgence avec nouveau token
# (selon votre procédure de déploiement d'urgence)
```

---

## 🔒 Bonnes Pratiques

### ✅ À Faire

1. **Tokens longue durée pour applications internes** (3-5 ans)
2. **Monitoring régulier** des expirations (cron hebdomadaire)
3. **Documentation** des tokens actifs (qui, quoi, quand)
4. **Variables d'environnement** (jamais dans Git)
5. **Rotation planifiée** lors de maintenances

### ❌ À Éviter

1. **Ne jamais** commit un token dans Git
2. **Ne pas** partager un token entre plusieurs applications
3. **Ne pas** utiliser de tokens courte durée pour app2/app3
4. **Ne pas** oublier de documenter les tokens générés

---

## 📚 Fichiers Créés

1. ✅ `sources/api2/bin/generate_token.php` - Génération de tokens
2. ✅ `sources/api2/bin/check_token_expiry.php` - Vérification expiration
3. ✅ `sources/api2/bin/rotate_token.php` - Rotation automatique
4. ✅ `SQL/api2_staff_tokens.sql` - Schéma de table
5. ✅ `DOC/developer/in-progress/implementations/API2_TOKEN_MANAGEMENT.md` - Ce guide

---

## 🎯 Résumé

**Question** : Dois-je générer un token par utilisateur ?
**Réponse** : **Non**, un token par application suffit.

**Question** : Dois-je renouveler le token tous les ans ?
**Réponse** : **Optionnel**. Pour app2/app3, utilisez des tokens 3-5 ans.

**Question** : Comment renouveler un token ?
**Réponse** : Utilisez `rotate_token.php` qui fait tout automatiquement.

**Question** : Comment éviter d'oublier de renouveler ?
**Réponse** : Monitoring automatique avec `check_token_expiry.php` + cron.

---

**Dernière mise à jour** : 2025-12-17 07:30 UTC
**Auteur** : Claude Code
**Statut** : ✅ Guide complet et testé
