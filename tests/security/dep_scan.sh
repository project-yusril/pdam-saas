#!/bin/bash
# Dependency Vulnerability Scanner
# Usage: bash tests/security/dep_scan.sh

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

ISSUES=0
REPORT_DIR="tests/security/reports/depscan_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$REPORT_DIR"

echo "PDAM SaaS Dependency Security Scan"
echo "================================================"

echo ""
echo "=== PHP Composer Audit ==="
cd backend
if composer audit --format=json > "../$REPORT_DIR/composer-audit.json" 2>&1; then
    echo -e "${GREEN}PASS${NC} - No PHP vulnerabilities"
else
    ISSUES=$((ISSUES + 1))
    echo -e "${RED}FAIL${NC} - PHP vulnerabilities found (see $REPORT_DIR/composer-audit.json)"
    cat "../$REPORT_DIR/composer-audit.json" | python3 -m json.tool 2>/dev/null || echo "(not JSON)"
fi
cd ..

echo ""
echo "=== NPM Audit ==="
cd backend
if npm audit --json > "../$REPORT_DIR/npm-audit.json" 2>&1; then
    echo -e "${GREEN}PASS${NC} - No JS vulnerabilities"
else
    HIGH_COUNT=$(python3 -c "import json; d=json.load(open('../$REPORT_DIR/npm-audit.json')); print(d.get('metadata',{}).get('vulnerabilities',{}).get('high',0) + d.get('metadata',{}).get('vulnerabilities',{}).get('critical',0))" 2>/dev/null || echo "unknown")
    if [ "$HIGH_COUNT" != "0" ] && [ "$HIGH_COUNT" != "unknown" ]; then
        ISSUES=$((ISSUES + 1))
        echo -e "${RED}FAIL${NC} - $HIGH_COUNT high/critical JS vulns (see $REPORT_DIR/npm-audit.json)"
    else
        echo -e "${YELLOW}WARN${NC} - Low severity JS vulns only"
    fi
fi
cd ..

echo ""
echo "=== Python ML Dependencies ==="
if [ -d "ml" ]; then
    cd ml
    if pip list --format=json 2>/dev/null | python3 -c "
import json, sys
deps = json.load(sys.stdin)
# Check for packages with known suffix patterns
for d in deps:
    if '-' in d['name']:
        print(f'Check: {d[\"name\"]}=={d[\"version\"]}')
" > "../$REPORT_DIR/pip-check.txt" 2>&1; then
        echo -e "${GREEN}INFO${NC} - Python deps listed (manual review needed)"
    else
        echo -e "${YELLOW}WARN${NC} - Could not scan Python deps"
    fi
    cd ..
else
    echo -e "${YELLOW}SKIP${NC} - No ML directory"
fi

echo ""
echo "=== Secret Scanning ==="
echo "Scanning for hardcoded secrets..."
SECRET_FOUND=0

check_file() {
    if grep -rn "$2" --include="$1" backend/ ml/ 2>/dev/null | grep -v ".env.example" | grep -v "vendor/" | grep -v "node_modules/" | grep -v ".git/" > "$REPORT_DIR/secret-scan.txt"; then
        if [ -s "$REPORT_DIR/secret-scan.txt" ]; then
            echo -e "${RED}FAIL${NC} - Found $3 pattern in codebase"
            head -5 "$REPORT_DIR/secret-scan.txt"
            SECRET_FOUND=1
        fi
    fi
}

check_file "*.php" "AKIA[A-Z0-9]{16}" "AWS Access Key"
check_file "*.php" "sk-[a-zA-Z0-9]{32,}" "OpenAI/Stripe Key"
check_file "*.php" "password\s*=\s*['\"][A-Za-z0-9@#\$%^&*]{8,}['\"]" "Hardcoded password"
check_file "*.php" "private_key.*=|-----BEGIN" "Private key"
check_file "*.yaml" "password\s*:\s*[A-Za-z0-9@#\$%^&*]{5,}" "YAML password"

if [ "$SECRET_FOUND" -eq 0 ]; then
    echo -e "${GREEN}PASS${NC} - No hardcoded secrets found"
fi

echo ""
echo "================================================"
if [ "$ISSUES" -eq 0 ]; then
    echo -e "${GREEN}All dependency security checks passed!${NC}"
else
    echo -e "${RED}$ISSUES issue(s) found. Check reports in $REPORT_DIR/${NC}"
    exit 1
fi
