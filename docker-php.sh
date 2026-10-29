#!/bin/bash
# Run PHP CLI (with pdo_mysql) on ds-net with the doublesided repo mounted.
set -e
docker run --network ds-net \
  --entrypoint /bin/sh \
  -v /home/osteri/projects/doublesided:/app -w /app \
  php:8-cli -c 'docker-php-ext-install pdo_mysql >/dev/null 2>&1; "$@"' sh "$@"