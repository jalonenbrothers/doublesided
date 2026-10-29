#!/bin/bash
# Real-Apache smoke (docker php:8.3-apache + mod_rewrite) — host curl edition.
# Usage: DS_HOST=127.0.0.1 DS_PORT=18098 bash tests/apache-smoke.sh
set -e
H="${DS_HOST:-127.0.0.1}"; P="${DS_PORT:-18098}"
base="http://$H:$P"
pass=0; fail=0
check() {
  local code path actual
  code=$1; path=$2
  actual=$(curl -s -o /dev/null -w '%{http_code}' "$base$path")
  if [ "$actual" = "$code" ]; then echo "ok  $path -> $actual"; pass=$((pass+1));
  else echo "FAIL $path -> $actual (expected $code)"; fail=$((fail+1)); fi
}
check 200 /
check 200 /issue/will-ai-take-our-jobs
check 404 /issue/does-not-exist
check 404 /issue/xss-attack-issue
check 404 /lib/config.example.php
check 404 /lib/db.php
check 404 /src/Importer.php
check 404 /tests/run.php
check 404 /tests/schema.sqlite.sql
check 404 /migrations/001_two_sides.sql
check 404 /fixtures/fixture-01.md
check 404 /docs/adr/ADR-001-schema-and-fixture.md
check 404 /bin/import-content.php
check 404 /router.php
check 404 /config.php
check 200 /public/assets/css/site.css
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]