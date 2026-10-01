#!/usr/bin/env bash
# Builds the site and serves it with a local Workers runtime (no login, no deploy),
# then checks the pages that matter. Run from website/ with: npm run check:local
set -euo pipefail

port=8787
base="http://localhost:${port}"
body="$(mktemp)"
log="$(mktemp)"

npm run build

WRANGLER_SEND_METRICS=false node_modules/.bin/wrangler dev --port "$port" >"$log" 2>&1 &
wrangler_pid=$!
trap 'kill "$wrangler_pid" 2>/dev/null || true; rm -f "$body" "$log"' EXIT

for _ in $(seq 1 60); do
  if curl -s -o /dev/null "$base/"; then
    break
  fi

  sleep 1
done

failures=0

check() {
  local path="$1" expected="$2" code
  code="$(curl -sS -o "$body" -w '%{http_code}' "$base$path")"

  if [ "$code" = "$expected" ]; then
    echo "ok   GET $path -> $code"
  else
    echo "FAIL GET $path -> $code (expected $expected)"
    failures=$((failures + 1))
  fi
}

check / 200
check /reference/analyzer/ 200
check /sitemap-index.xml 200
check /robots.txt 200

# The marker proves not_found_handling serves the site's own 404 page, not the homepage.
check /no-such-page/ 404

if grep -q 'scored 0.0000 because it does not exist' "$body"; then
  echo "ok   404 body has the site's 404 marker"
else
  echo "FAIL 404 body is missing the site's 404 marker"
  failures=$((failures + 1))
fi

exit "$failures"
