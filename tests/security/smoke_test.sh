#!/bin/bash
# Quick Security Smoke Test — Fast pre-commit security checks
# Usage: bash tests/security/smoke_test.sh [target-url]

TARGET="${1:-http://localhost:8080}"
PASS=0
FAIL=0

check() {
    local desc="$1"
    local expected="$2"
    local actual="$3"
    if [ "$actual" = "$expected" ]; then
        echo "  [PASS] $desc"
        PASS=$((PASS + 1))
    else
        echo "  [FAIL] $desc (expected $expected, got $actual)"
        FAIL=$((FAIL + 1))
    fi
}

echo "=== PDAM SaaS Security Smoke Test ==="
echo "Target: $TARGET"
echo ""

# 1. HTTPS redirect
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$(echo "$TARGET" | sed 's/https/http/')")
check "HTTP → HTTPS redirect" "301" "$HTTP_CODE"

# 2. HSTS header
HSTS=$(curl -sI "$TARGET" 2>/dev/null | grep -i "strict-transport-security" | head -1 || echo "MISSING")
check "HSTS header present" "PRESENT" "$( [ -n "$HSTS" ] && echo "PRESENT" || echo "MISSING")"

# 3. X-Frame-Options
XFO=$(curl -sI "$TARGET" 2>/dev/null | grep -i "x-frame-options" | grep -o "DENY\|SAMEORIGIN" || echo "MISSING")
check "X-Frame-Options set" "PRESENT" "$( [ -n "$XFO" ] && echo "PRESENT" || echo "MISSING")"

# 4. X-Content-Type-Options
XCTO=$(curl -sI "$TARGET" 2>/dev/null | grep -i "x-content-type-options" | grep -o "nosniff" || echo "MISSING")
check "X-Content-Type-Options: nosniff" "nosniff" "$XCTO"

# 5. CSP header
CSP=$(curl -sI "$TARGET" 2>/dev/null | grep -i "content-security-policy" || echo "MISSING")
check "CSP header present" "PRESENT" "$( [ -n "$CSP" ] && echo "PRESENT" || echo "MISSING")"

# 6. No PHP version disclosure
PHP_HDR=$(curl -sI "$TARGET" 2>/dev/null | grep -i "X-Powered-By" || echo "OK")
check "No PHP version in headers" "OK" "$( [ -z "$PHP_HDR" ] && echo "OK" || echo "LEAKED")"

# 7. API rate limiting
for i in 1 2 3 4 5 6; do
    RATE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$TARGET/api/v1/login" \
        -H "Content-Type: application/json" \
        -d "{\"email\":\"test$i@test.com\",\"password\":\"wrong\"}")
done
check "API rate limiting active" "429" "$RATE"

# 8. CORS restricted
CORS=$(curl -s -o /dev/null -w "%{http_code}" "$TARGET/api/v1/login" -H "Origin: http://evil.com" -X OPTIONS)
check "CORS blocks unknown origins" "403" "$( [ "$CORS" != "200" ] && echo "403" || echo "200")"

# 9. .env not accessible
ENV=$(curl -s -o /dev/null -w "%{http_code}" "$TARGET/.env")
check ".env not accessible" "403" "$( [ "$ENV" = "404" ] || [ "$ENV" = "403" ] && echo "403" || echo "$ENV")"

# 10. Git directory not accessible
GIT=$(curl -s -o /dev/null -w "%{http_code}" "$TARGET/.git/config")
check ".git/ not accessible" "403" "$( [ "$GIT" = "404" ] || [ "$GIT" = "403" ] && echo "403" || echo "$GIT")"

echo ""
echo "================================================"
echo "Results: $PASS passed, $FAIL failed"
if [ "$FAIL" -gt 0 ]; then
    echo "SMOKE TEST: FAILED"
    exit 1
else
    echo "SMOKE TEST: PASSED"
fi
