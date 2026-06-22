#!/bin/sh
set -e

RUNTIME_INI=/usr/local/etc/php/conf.d/zzzz-lenv-xdebug-runtime.ini

rm -f "$RUNTIME_INI"
docker-php-ext-disable xdebug 2>/dev/null || rm -f /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini 2>/dev/null || true

if command -v apachectl >/dev/null 2>&1; then
  apachectl graceful 2>/dev/null || apachectl -k graceful 2>/dev/null || true
elif command -v apache2ctl >/dev/null 2>&1; then
  apache2ctl graceful 2>/dev/null || apache2ctl -k graceful 2>/dev/null || true
elif command -v frankenphp >/dev/null 2>&1; then
  kill -USR1 1 2>/dev/null || true
else
  kill -USR2 1 2>/dev/null || pkill -USR2 php-fpm 2>/dev/null || true
fi

echo "Xdebug disabled."
