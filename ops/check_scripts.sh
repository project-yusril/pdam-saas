#!/usr/bin/env bash
# syntax check all ops scripts
for f in ops/tls/*.sh ops/backup/*.sh ops/mysql/*.sh ops/load/*.sh tests/security/*.sh; do
  if bash -n "$f" 2>/tmp/sherr; then echo "OK      $f"; else echo "SYNTAX  $f: $(cat /tmp/sherr)"; fi
done
