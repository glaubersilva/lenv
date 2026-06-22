#!/bin/sh
# Idempotent dev setup: PHP extensions for plugin test suites + Git safe.directory on WSL bind mounts.

if ! php -m 2>/dev/null | grep -qx uopz; then
  echo '[lenv] Installing uopz PHP extension (first start only)...'
  pecl channel-update pecl.php.net || true
  if php -r 'exit(PHP_MAJOR_VERSION >= 8 ? 0 : 1);'; then
    pecl install uopz-7.1.1
  else
    pecl install uopz-6.1.2
  fi
  docker-php-ext-enable uopz
fi

git config --global --add safe.directory '*' 2>/dev/null || true
