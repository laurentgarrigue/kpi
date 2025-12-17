#!/bin/bash

# Script de notification pour tokens expirant bientôt
# Usage: ./notify_token_expiry.sh [days_warning] [email]
#
# Par défaut : vérifie tokens expirant dans 90 jours
# Envoie un email SEULEMENT si des tokens expirent bientôt

DAYS_WARNING=${1:-90}
EMAIL=${2:-admin@kayak-polo.info}

# Exécuter la vérification
OUTPUT=$(docker exec kpi_php php /var/www/html/api2/bin/check_token_expiry.php "$DAYS_WARNING")

# Vérifier s'il y a des tokens à renouveler
if echo "$OUTPUT" | grep -q "TOKENS EXPIRANT BIENTÔT\|TOKENS EXPIRÉS"; then
    # Envoyer notification
    echo "$OUTPUT" | mail -s "⚠️ KPI API2 - Tokens à renouveler" "$EMAIL"

    # Log
    echo "[$(date)] Notification envoyée à $EMAIL" >> /var/log/kpi/token_monitoring.log

    # Code de sortie 1 = action requise
    exit 1
else
    # Log succès (optionnel)
    # echo "[$(date)] Tous les tokens sont valides" >> /var/log/kpi/token_monitoring.log

    # Code de sortie 0 = tout va bien
    exit 0
fi
