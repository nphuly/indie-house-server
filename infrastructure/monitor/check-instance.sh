#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."

# Run on the Compose host. Exit 1 for an external scheduler/alert manager.
edge_url="${EDGE_HEALTH_URL:-http://127.0.0.1:8080}"
window="${MONITOR_LOG_WINDOW:-5m}"
max_401="${MONITOR_MAX_401:-50}"
max_429="${MONITOR_MAX_429:-20}"
max_5xx="${MONITOR_MAX_5XX:-1}"

curl --fail --silent --show-error --max-time 8 "${edge_url}/up" >/dev/null
curl --fail --silent --show-error --max-time 8 "${edge_url}/api/v1/stream/healthz" >/dev/null

health_json="$(docker compose exec -T control-plane php artisan instance:health --json)"
printf '%s\n' "$health_json" | jq -e '.status == "ok"' >/dev/null

log_output="$(docker logs indie_house_edge --since "$window" 2>&1)"
statuses="$(printf '%s\n' "$log_output" | jq -Rr 'fromjson? | select(.status? | type == "number") | .status')"
count_401="$(printf '%s\n' "$statuses" | awk '$1 == 401 { n++ } END { print n + 0 }')"
count_429="$(printf '%s\n' "$statuses" | awk '$1 == 429 { n++ } END { print n + 0 }')"
count_5xx="$(printf '%s\n' "$statuses" | awk '$1 >= 500 && $1 <= 599 { n++ } END { print n + 0 }')"

jq -n \
    --argjson unauthenticated "$count_401" \
    --argjson limited "$count_429" \
    --argjson server_errors "$count_5xx" \
    --arg window "$window" \
    '{window: $window, http_401: $unauthenticated, http_429: $limited, http_5xx: $server_errors}'

if (( count_401 > max_401 || count_429 > max_429 || count_5xx >= max_5xx )); then
    exit 1
fi
