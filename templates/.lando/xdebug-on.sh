#!/bin/sh
set -e

RUNTIME_INI=/usr/local/etc/php/conf.d/zzzz-lenv-xdebug-runtime.ini

docker-php-ext-enable xdebug 2>/dev/null || true

cat > "$RUNTIME_INI" <<'EOF'
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_host=host.lando.internal
EOF

if command -v apachectl >/dev/null 2>&1; then
  apachectl graceful 2>/dev/null || apachectl -k graceful 2>/dev/null || true
elif command -v apache2ctl >/dev/null 2>&1; then
  apache2ctl graceful 2>/dev/null || apache2ctl -k graceful 2>/dev/null || true
elif command -v frankenphp >/dev/null 2>&1; then
  kill -USR1 1 2>/dev/null || true
else
  kill -USR2 1 2>/dev/null || pkill -USR2 php-fpm 2>/dev/null || true
fi

echo "Xdebug enabled."
