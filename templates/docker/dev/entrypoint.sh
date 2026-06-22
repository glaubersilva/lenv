#!/bin/sh

test -x /usr/local/bin/wp || (echo "[lenv] Downloading WP-CLI..." && curl -fsSL --proto '=https' --tlsv1.2 https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp && chmod +x /usr/local/bin/wp) || echo "[lenv] Warning: WP-CLI download failed; lando wp will retry."
test -x /usr/local/bin/composer || (echo "[lenv] Downloading Composer..." && curl -fsSL --proto '=https' --tlsv1.2 https://getcomposer.org/download/latest-stable/composer.phar -o /usr/local/bin/composer && chmod +x /usr/local/bin/composer) || echo "[lenv] Warning: Composer download failed; lando composer will retry."

if command -v composer >/dev/null 2>&1; then
  composer config -g github-oauth.github.com "${GITHUB_TOKEN}" || true
fi

if [ "${LENV_XDEBUG:-off}" = "off" ]; then
  /bin/sh /app/.lando/xdebug-off.sh 2>/dev/null || true
fi

echo "[lenv] Ensuring database credentials..."
/bin/sh /app/.lando/ensure-db-creds.sh

echo "[lenv] Waiting for database..."
until php -r "new PDO('mysql:host=database;dbname=wordpress', 'admin', 'admin');" 2>/dev/null; do
  sleep 2
done

exec frankenphp run --config /etc/caddy/Caddyfile
