#!/bin/bash
# OWASP ZAP Automated Security Scan Script
# Usage: bash tests/security/zap_scan.sh [target-url] [api-key]
# Prerequisites: ZAP running in daemon mode or Docker

TARGET="${1:-http://localhost:8080}"
APIKEY="${2:-}"
ZAP_PORT=8081
ZAP_URL="http://localhost:$ZAP_PORT"
REPORT_DIR="tests/security/reports/zap_$(date +%Y%m%d_%H%M%S)"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

mkdir -p "$REPORT_DIR"

if command -v docker &> /dev/null && docker ps --format '{{.Names}}' | grep -q "zap"; then
    ZAP_URL="http://zap:$ZAP_PORT"
fi

echo "OWASP ZAP Scan"
echo "Target: $TARGET"
echo "================================================"

if ! curl -s "$ZAP_URL" > /dev/null 2>&1; then
    echo -e "${YELLOW}ZAP not running. Starting via Docker...${NC}"
    docker run -d --name zap-scan -p $ZAP_PORT:$ZAP_PORT \
        -v "$(pwd)/$REPORT_DIR:/zap/wrk:rw" \
        owasp/zap2docker-stable zap.sh -daemon -host 0.0.0.0 -port $ZAP_PORT \
        -config api.addrs.addr.name=.* -config api.addrs.addr.regex=true \
        -config api.disablekey=true
    sleep 15
fi

echo "Starting spider scan..."
curl -s "$ZAP_URL/JSON/spider/action/scan/?apikey=$APIKEY&url=$TARGET&maxChildren=10" > /dev/null

SPIDER_ID=""
while [ -z "$SPIDER_ID" ] || [ "$SPIDER_STATUS" != "100" ]; do
    sleep 3
    SPIDER_ID=$(curl -s "$ZAP_URL/JSON/spider/view/status/?apikey=$APIKEY" | grep -o '"scanId":"[^"]*"' | head -1 | cut -d'"' -f4)
    SPIDER_STATUS=$(curl -s "$ZAP_URL/JSON/spider/view/status/?apikey=$APIKEY&scanId=$SPIDER_ID" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
    echo "Spider progress: ${SPIDER_STATUS:-0}%"
done
echo -e "${GREEN}Spider complete${NC}"

echo "Starting active scan..."
curl -s "$ZAP_URL/JSON/ascan/action/scan/?apikey=$APIKEY&url=$TARGET&recurse=true" > /dev/null

SCAN_ID=""
while [ -z "$SCAN_ID" ] || [ "$SCAN_STATUS" != "100" ]; do
    sleep 5
    SCAN_ID=$(curl -s "$ZAP_URL/JSON/ascan/view/scans/?apikey=$APIKEY" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
    SCAN_STATUS=$(curl -s "$ZAP_URL/JSON/ascan/view/status/?apikey=$APIKEY&scanId=$SCAN_ID" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
    echo "Active scan progress: ${SCAN_STATUS:-0}%"
done
echo -e "${GREEN}Active scan complete${NC}"

echo "Generating reports..."
curl -s "$ZAP_URL/OTHER/core/other/htmlreport/?apikey=$APIKEY" -o "$REPORT_DIR/zap-report.html"
curl -s "$ZAP_URL/OTHER/core/other/jsonreport/?apikey=$APIKEY" -o "$REPORT_DIR/zap-report.json"
curl -s "$ZAP_URL/OTHER/core/other/mdreport/?apikey=$APIKEY" -o "$REPORT_DIR/zap-report.md"

ALERT_COUNT=$(curl -s "$ZAP_URL/JSON/core/view/alertsSummary/?apikey=$APIKEY&baseurl=$TARGET" | grep -o '"high":"[^"]*"' | cut -d'"' -f4)
HIGH_ALERTS=${ALERT_COUNT:-0}

echo ""
echo -e "${YELLOW}================================================"
echo "ZAP Scan Complete"
echo "High severity alerts: $HIGH_ALERTS"
echo "Reports saved to: $REPORT_DIR/"
echo -e "================================================${NC}"

if [ "$HIGH_ALERTS" -gt 0 ]; then
    echo -e "${RED}WARNING: $HIGH_ALERTS high severity alerts found!${NC}"
    exit 1
else
    echo -e "${GREEN}No high severity alerts.${NC}"
fi
