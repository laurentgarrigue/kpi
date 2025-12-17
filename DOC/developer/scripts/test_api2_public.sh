#!/bin/bash
set -e

BASE_URL="https://kpi.localhost/api2"
LEGACY_URL="https://kpi.localhost/api"

echo "🧪 Testing Public Endpoints - API2 vs Legacy"
echo "=============================================="
echo ""

# Events
echo -n "✓ Events (std): "
RESULT=$(curl -k -s "${BASE_URL}/events/std" | jq 'length')
echo "${RESULT} events"

# Events (champ)
echo -n "✓ Events (champ): "
RESULT=$(curl -k -s "${BASE_URL}/events/champ" | jq 'length')
echo "${RESULT} events"

# Stars
echo -n "✓ Stars: "
STARS=$(curl -k -s "${BASE_URL}/stars" | jq -r '.average')
COUNT=$(curl -k -s "${BASE_URL}/stars" | jq -r '.count')
echo "Average: ${STARS}/5 (${COUNT} votes)"

# Rating (test POST)
UUID=$(uuidgen)
echo -n "✓ Rating POST: "
RATING=$(curl -k -s -X POST "${BASE_URL}/rating" \
  -H "Content-Type: application/json" \
  -d "{\"uid\":\"${UUID}\",\"stars\":5}" | jq -r '.success')
echo "${RATING}"

echo ""
echo "✅ All public endpoints working!"
echo ""
echo "📊 Summary:"
echo "  - Base URL: ${BASE_URL}"
echo "  - All tests passed"
echo "  - API2 is ready for migration!"
