#!/bin/sh
set -e

CERT_DIR="./certs"
mkdir -p "$CERT_DIR"

if [ ! -f "$CERT_DIR/dev.key" ]; then
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "$CERT_DIR/dev.key" \
        -out "$CERT_DIR/dev.crt" \
        -subj "/C=ID/ST=Local/L=Local/O=PDAM Dev/CN=localhost" \
        -addext "subjectAltName=DNS:localhost,DNS:127.0.0.1,DNS:host.docker.internal"

    echo "Dev certificates generated at $CERT_DIR/"
else
    echo "Dev certificates already exist at $CERT_DIR/"
fi
