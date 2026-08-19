#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1}"
FAILURES=0

check_status() {
  local name="$1" url="$2" expected="$3"
  local status
  status="$(curl -k -sS -o /tmp/mim_sfa_smoke_body -w '%{http_code}' "$url" || true)"
  if [[ "$status" == "$expected" ]]; then
    printf 'PASS %-32s %s\n' "$name" "$status"
  else
    printf 'FAIL %-32s expected=%s got=%s\n' "$name" "$expected" "$status"
    FAILURES=$((FAILURES + 1))
  fi
}

# Public entry point must respond.
check_status "public index" "$BASE_URL/" "200"

# Unknown API endpoint must be rejected by the deployment adapter.
check_status "unknown api endpoint" "$BASE_URL/api/__mim_sfa_not_found__.php" "404"

# Private application directories must never become web-accessible.
check_status "config protection" "$BASE_URL/config/" "403"
check_status "database protection" "$BASE_URL/database/" "403"
check_status "tests protection" "$BASE_URL/tests/" "403"

# Traversal-like endpoint names must not reach the private API dispatcher.
check_status "api traversal protection" "$BASE_URL/api/..%2Fconfig.php" "404"

if [[ "$FAILURES" -ne 0 ]]; then
  echo "cPanel smoke test failed: $FAILURES check(s)."
  exit 1
fi

echo "cPanel smoke test passed."
