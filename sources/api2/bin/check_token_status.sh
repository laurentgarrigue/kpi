#!/bin/bash

# Script de vérification pour monitoring (compatible vps-manager)
# Retourne un code de sortie et un message JSON
#
# Codes de sortie:
#   0 = OK (tous les tokens valides > 90 jours)
#   1 = WARNING (tokens expirant dans < 90 jours)
#   2 = CRITICAL (tokens expirés)
#   3 = UNKNOWN (erreur d'exécution)
#
# Usage: ./check_token_status.sh [days_warning] [days_critical]

DAYS_WARNING=${1:-90}
DAYS_CRITICAL=${2:-7}

# Vérifier que Docker est disponible
if ! command -v docker &> /dev/null; then
    echo '{"status":"UNKNOWN","message":"Docker non disponible"}'
    exit 3
fi

# Vérifier que le conteneur PHP existe
if ! docker ps | grep -q kpi_php; then
    echo '{"status":"UNKNOWN","message":"Conteneur kpi_php non démarré"}'
    exit 3
fi

# Exécuter la vérification
OUTPUT=$(docker exec kpi_php php /var/www/html/api2/bin/check_token_expiry.php "$DAYS_WARNING" 2>&1)

# Vérifier les erreurs d'exécution
if [ $? -ne 0 ]; then
    echo "{\"status\":\"UNKNOWN\",\"message\":\"Erreur d'exécution: $(echo "$OUTPUT" | head -1 | tr -d '\n')\"}"
    exit 3
fi

# Extraire les statistiques
TOTAL=$(echo "$OUTPUT" | grep "Tokens actifs:" | awk '{print $3}')
VALID=$(echo "$OUTPUT" | grep "Tokens valides:" | awk '{print $3}')
EXPIRED=$(echo "$OUTPUT" | grep "Tokens expirés:" | awk '{print $3}')

# Compter les tokens expirant bientôt
EXPIRING=$(echo "$OUTPUT" | grep "TOKENS EXPIRANT BIENTÔT" | grep -oP '\(\K[0-9]+' || echo "0")

# Déterminer le statut
if [ "$EXPIRED" -gt 0 ]; then
    # CRITICAL: tokens expirés
    STATUS="CRITICAL"
    MESSAGE="$EXPIRED token(s) expiré(s)"
    EXIT_CODE=2
elif [ "$EXPIRING" -gt 0 ]; then
    # WARNING: tokens expirant bientôt
    STATUS="WARNING"
    MESSAGE="$EXPIRING token(s) expirant dans moins de $DAYS_WARNING jours"
    EXIT_CODE=1
else
    # OK: tous les tokens valides
    STATUS="OK"
    MESSAGE="Tous les tokens valides (total: $TOTAL)"
    EXIT_CODE=0
fi

# Sortie JSON pour vps-manager
cat <<EOF
{
  "status": "$STATUS",
  "message": "$MESSAGE",
  "metrics": {
    "total_tokens": $TOTAL,
    "valid_tokens": $VALID,
    "expired_tokens": $EXPIRED,
    "expiring_soon": $EXPIRING,
    "days_warning": $DAYS_WARNING,
    "days_critical": $DAYS_CRITICAL
  },
  "timestamp": "$(date -Iseconds)"
}
EOF

exit $EXIT_CODE
