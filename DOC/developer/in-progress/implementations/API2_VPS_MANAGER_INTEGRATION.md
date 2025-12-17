# Intégration API2 Token Monitoring avec vps-manager

> **Date** : 2025-12-17
> **Objectif** : Intégrer la surveillance des tokens API2 dans le système de monitoring vps-manager

---

## 📋 Script de Monitoring

### Fichier : `sources/api2/bin/check_token_status.sh`

**Description** : Script compatible avec vps-manager pour surveiller l'état des tokens API2.

**Sortie** : JSON avec statut et métriques

**Codes de sortie** :
- `0` = OK (tous les tokens valides > 90 jours)
- `1` = WARNING (tokens expirant dans < 90 jours)
- `2` = CRITICAL (tokens expirés)
- `3` = UNKNOWN (erreur d'exécution)

### Usage

```bash
# Vérification standard (warning: 90 jours, critical: 7 jours)
./sources/api2/bin/check_token_status.sh

# Personnalisation des seuils
./sources/api2/bin/check_token_status.sh 180 30
```

### Exemple de Sortie

**Statut OK** :
```json
{
  "status": "OK",
  "message": "Tous les tokens valides (total: 2)",
  "metrics": {
    "total_tokens": 2,
    "valid_tokens": 2,
    "expired_tokens": 0,
    "expiring_soon": 0,
    "days_warning": 90,
    "days_critical": 7
  },
  "timestamp": "2025-12-17T07:44:55+01:00"
}
```

**Statut WARNING** :
```json
{
  "status": "WARNING",
  "message": "1 token(s) expirant dans moins de 90 jours",
  "metrics": {
    "total_tokens": 2,
    "valid_tokens": 2,
    "expired_tokens": 0,
    "expiring_soon": 1,
    "days_warning": 90,
    "days_critical": 7
  },
  "timestamp": "2025-12-17T07:44:55+01:00"
}
```

**Statut CRITICAL** :
```json
{
  "status": "CRITICAL",
  "message": "1 token(s) expiré(s)",
  "metrics": {
    "total_tokens": 2,
    "valid_tokens": 1,
    "expired_tokens": 1,
    "expiring_soon": 0,
    "days_warning": 90,
    "days_critical": 7
  },
  "timestamp": "2025-12-17T07:44:55+01:00"
}
```

---

## 🔧 Intégration dans vps-manager

### Option 1 : Check Basique

Si vps-manager supporte l'exécution de scripts shell :

```bash
# Ajouter dans la configuration de vps-manager
check_name: "KPI API2 Tokens"
check_type: "script"
check_script: "/path/to/kpi/sources/api2/bin/check_token_status.sh"
check_interval: "weekly"  # ou "86400" pour quotidien
warning_threshold: 90     # jours
critical_threshold: 7     # jours
```

### Option 2 : Check avec Métriques

Si vps-manager collecte des métriques :

```bash
# Script wrapper pour Prometheus/Grafana
#!/bin/bash
OUTPUT=$(/path/to/kpi/sources/api2/bin/check_token_status.sh)

# Extraire les métriques
TOTAL=$(echo "$OUTPUT" | jq -r '.metrics.total_tokens')
VALID=$(echo "$OUTPUT" | jq -r '.metrics.valid_tokens')
EXPIRED=$(echo "$OUTPUT" | jq -r '.metrics.expired_tokens')
EXPIRING=$(echo "$OUTPUT" | jq -r '.metrics.expiring_soon')

# Exporter en format Prometheus
cat <<EOF
# HELP kpi_api2_tokens_total Total number of tokens
# TYPE kpi_api2_tokens_total gauge
kpi_api2_tokens_total $TOTAL

# HELP kpi_api2_tokens_valid Number of valid tokens
# TYPE kpi_api2_tokens_valid gauge
kpi_api2_tokens_valid $VALID

# HELP kpi_api2_tokens_expired Number of expired tokens
# TYPE kpi_api2_tokens_expired gauge
kpi_api2_tokens_expired $EXPIRED

# HELP kpi_api2_tokens_expiring_soon Number of tokens expiring soon
# TYPE kpi_api2_tokens_expiring_soon gauge
kpi_api2_tokens_expiring_soon $EXPIRING
EOF
```

### Option 3 : Intégration avec Notifications

**Fichier : `vps-manager/checks/kpi_api2_tokens.sh`** (exemple)

```bash
#!/bin/bash

# Configuration
SCRIPT_PATH="/var/www/html/kpi/sources/api2/bin/check_token_status.sh"
NOTIFICATION_EMAIL="admin@kayak-polo.info"
NOTIFICATION_SLACK="https://hooks.slack.com/services/YOUR/WEBHOOK/URL"

# Exécuter le check
RESULT=$($SCRIPT_PATH 90 7)
EXIT_CODE=$?

# Parser le résultat
STATUS=$(echo "$RESULT" | jq -r '.status')
MESSAGE=$(echo "$RESULT" | jq -r '.message')

# Envoyer notification selon le statut
case $EXIT_CODE in
    0)
        # OK - pas de notification
        ;;
    1)
        # WARNING - notification email
        echo "$RESULT" | mail -s "⚠️ KPI API2 - $MESSAGE" "$NOTIFICATION_EMAIL"
        ;;
    2)
        # CRITICAL - notification email + Slack
        echo "$RESULT" | mail -s "🚨 CRITICAL: KPI API2 - $MESSAGE" "$NOTIFICATION_EMAIL"
        curl -X POST "$NOTIFICATION_SLACK" \
            -H 'Content-Type: application/json' \
            -d "{\"text\":\"🚨 CRITICAL: KPI API2 - $MESSAGE\"}"
        ;;
    3)
        # UNKNOWN - log erreur
        echo "[$(date)] ERROR: $MESSAGE" >> /var/log/vps-manager/kpi_api2_check.log
        ;;
esac

# Retourner le code pour vps-manager
exit $EXIT_CODE
```

---

## 📊 Seuils Recommandés

### Pour Applications de Production (app2, app3)

**Tokens longue durée (5 ans)** :

| Seuil | Valeur | Action |
|-------|--------|--------|
| **Warning** | 180 jours | Planifier la rotation |
| **Critical** | 30 jours | Rotation urgente requise |

**Configuration** :
```bash
./check_token_status.sh 180 30
```

### Pour Tokens Temporaires (événements)

**Tokens courte durée (7-30 jours)** :

| Seuil | Valeur | Action |
|-------|--------|--------|
| **Warning** | 7 jours | Informer l'organisateur |
| **Critical** | 1 jour | Renouvellement urgent |

**Configuration** :
```bash
./check_token_status.sh 7 1
```

---

## 🔄 Fréquence de Vérification Recommandée

### Applications Internes (app2, app3)

**Tokens 5 ans** :
- **Fréquence** : Hebdomadaire
- **Justification** : Tokens longue durée, pas d'urgence
- **Cron** : `0 9 * * 1` (tous les lundis à 9h)

### Tokens Temporaires (événements)

**Tokens < 30 jours** :
- **Fréquence** : Quotidienne
- **Justification** : Expiration rapide
- **Cron** : `0 9 * * *` (tous les jours à 9h)

---

## 🛠️ Configuration vps-manager

### Exemple de Configuration YAML

```yaml
# vps-manager/config/checks/kpi_api2.yml
checks:
  - name: "KPI API2 - Token Status"
    type: script
    enabled: true

    # Script
    script: "/var/www/html/kpi/sources/api2/bin/check_token_status.sh"
    args: [180, 30]  # warning: 180 jours, critical: 30 jours

    # Planification
    schedule:
      type: cron
      expression: "0 9 * * 1"  # Lundis à 9h

    # Notifications
    notifications:
      on_warning:
        - type: email
          to: "admin@kayak-polo.info"
          subject: "⚠️ KPI API2 - Tokens à renouveler"

      on_critical:
        - type: email
          to: "admin@kayak-polo.info"
          subject: "🚨 CRITICAL: KPI API2 - Tokens expirés"
        - type: slack
          webhook: "${SLACK_WEBHOOK_URL}"
          message: "🚨 CRITICAL: Tokens API2 expirés - Action immédiate requise"

    # Métriques (optionnel)
    metrics:
      enabled: true
      labels:
        service: "kpi"
        component: "api2"
        check: "tokens"
```

### Exemple de Configuration INI

```ini
[check_kpi_api2_tokens]
enabled = true
name = KPI API2 - Token Status
type = script
script = /var/www/html/kpi/sources/api2/bin/check_token_status.sh
args = 180 30
schedule = 0 9 * * 1
notification_email = admin@kayak-polo.info
notification_slack = ${SLACK_WEBHOOK_URL}
warning_threshold = 180
critical_threshold = 30
```

---

## 📝 Actions de Remédiation

### Quand WARNING (tokens expirant < 180 jours)

1. **Planifier la rotation** lors de la prochaine maintenance
2. **Vérifier** quels tokens sont concernés :
   ```bash
   docker exec kpi_php php /var/www/html/api2/bin/check_token_expiry.php 180
   ```
3. **Préparer** les nouveaux tokens en avance

### Quand CRITICAL (tokens expirés ou < 30 jours)

1. **Rotation immédiate** :
   ```bash
   docker exec kpi_php php /var/www/html/api2/bin/rotate_token.php <token_prefix> 1825
   ```

2. **Mise à jour** de la configuration :
   ```bash
   # Mettre à jour .env.production
   vim sources/app2/.env.production
   ```

3. **Redéploiement** de l'application :
   ```bash
   cd /var/www/html/app2
   npm run build
   pm2 restart app2
   ```

4. **Vérification** :
   ```bash
   ./sources/api2/bin/check_token_status.sh
   ```

---

## 🎯 Résumé

### Pour Intégrer dans vps-manager

**Étape 1** : Tester le script
```bash
/var/www/html/kpi/sources/api2/bin/check_token_status.sh 180 30
```

**Étape 2** : Ajouter le check dans vps-manager
- Utiliser la configuration YAML/INI ci-dessus
- Adapter selon votre système de monitoring

**Étape 3** : Configurer les notifications
- Email pour WARNING
- Email + Slack pour CRITICAL

**Étape 4** : Tester les notifications
```bash
# Simuler un WARNING (vérifier tokens expirant dans 365 jours)
./check_token_status.sh 365 30
```

**Étape 5** : Déployer en production

---

## 📚 Fichiers Créés

1. ✅ `sources/api2/bin/check_token_status.sh` - Script de monitoring compatible vps-manager
2. ✅ `DOC/developer/in-progress/implementations/API2_VPS_MANAGER_INTEGRATION.md` - Ce guide

**Fichiers existants à utiliser** :
- `sources/api2/bin/check_token_expiry.php` - Vérification détaillée (utilisé par le script shell)
- `sources/api2/bin/rotate_token.php` - Rotation automatique des tokens

---

**Dernière mise à jour** : 2025-12-17 07:45 UTC
**Statut** : ✅ Prêt pour intégration dans vps-manager
