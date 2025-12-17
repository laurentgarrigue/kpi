#!/bin/bash
# Compare legacy API vs API2 responses

ENDPOINT=$1
LEGACY_URL="https://kpi.localhost/api${ENDPOINT}"
API2_URL="https://kpi.localhost/api2${ENDPOINT}"

if [ -z "$ENDPOINT" ]; then
    echo "Usage: $0 <endpoint>"
    echo ""
    echo "Examples:"
    echo "  $0 /events/std"
    echo "  $0 /games/123"
    echo "  $0 /stars"
    exit 1
fi

echo "🔍 Comparing: ${ENDPOINT}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Legacy: ${LEGACY_URL}"
echo "API2:   ${API2_URL}"
echo ""

# Fetch responses
echo "📥 Fetching responses..."
LEGACY_RESPONSE=$(curl -k -s "${LEGACY_URL}")
API2_RESPONSE=$(curl -k -s "${API2_URL}")

# Check if responses are valid JSON
if ! echo "$LEGACY_RESPONSE" | jq empty 2>/dev/null; then
    echo "❌ Legacy response is not valid JSON"
    echo "$LEGACY_RESPONSE"
    exit 1
fi

if ! echo "$API2_RESPONSE" | jq empty 2>/dev/null; then
    echo "❌ API2 response is not valid JSON"
    echo "$API2_RESPONSE"
    exit 1
fi

# Compare JSON (sorted for consistency)
DIFF_OUTPUT=$(diff <(echo "$LEGACY_RESPONSE" | jq -S .) <(echo "$API2_RESPONSE" | jq -S .) 2>&1)

if [ $? -eq 0 ]; then
    echo "✅ Responses are identical!"
    echo ""
    echo "📊 Response info:"
    if echo "$API2_RESPONSE" | jq -e 'type == "array"' > /dev/null 2>&1; then
        COUNT=$(echo "$API2_RESPONSE" | jq 'length')
        echo "  - Type: Array"
        echo "  - Count: ${COUNT} items"
    else
        echo "  - Type: Object"
        KEYS=$(echo "$API2_RESPONSE" | jq 'keys | length')
        echo "  - Keys: ${KEYS}"
    fi
else
    echo "❌ Responses differ!"
    echo ""
    echo "📋 Differences:"
    echo "$DIFF_OUTPUT"
    exit 1
fi
