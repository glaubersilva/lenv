#!/bin/sh
# Flush WordPress rewrite rules after wp-install. When Apache is selected in
# .lando.yml, ensure .htaccess exists so pretty permalinks and /wp-json/ work.

set -e

WP="/usr/local/bin/wp"
WP_PATH="/app"
HTACCESS="/app/.htaccess"

if [ ! -f /app/wp-load.php ]; then
	echo "[lenv] WordPress not installed; skipping rewrite flush."
	exit 0
fi

echo "[lenv] Flushing WordPress rewrite rules..."
"$WP" rewrite flush --hard --allow-root --path="$WP_PATH" || true

if ! grep -q '^  via: apache' /app/.lando.yml 2>/dev/null; then
	exit 0
fi

if [ -f "$HTACCESS" ]; then
	echo "[lenv] Apache .htaccess present."
	exit 0
fi

echo "[lenv] Apache selected but .htaccess missing — writing standard WordPress rules..."
cat >"$HTACCESS" <<'EOF'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
EOF

echo "[lenv] Created $HTACCESS"
