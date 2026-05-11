#!/bin/sh
set -e

# Bind mount from host: ensure Symfony can write cache/logs (Twig, container, etc.).
cd /application 2>/dev/null || cd "$(dirname "$0")/../.." || true

if [ -d /application ]; then
    # Symfony expects var/cache/<env>/ before dumping Container*; mkdir only var/cache is not enough.
    mkdir -p /application/var/cache/dev /application/var/cache/prod /application/var/cache/test /application/var/log
    # Writable by php-fpm workers (www-data). Bind mounts from the host are often root-owned.
    chown -R www-data:www-data /application/var 2>/dev/null || true
    chmod -R a+rwX /application/var 2>/dev/null || true
    # Corrupted Deprecations.log breaks the profiler on kernel.terminate (LoggerDataCollector::unserialize).
    find /application/var/cache -name Deprecations.log -type f -delete 2>/dev/null || true
    # Logical EasyAdmin URLs in dev (no hashed filenames); public/bundles/ is gitignored.
    php /application/bin/console app:easyadmin-dev-assets --no-interaction 2>/dev/null || true
fi

exec docker-php-entrypoint "$@"
