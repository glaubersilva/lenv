#!/bin/sh
# Ensure .lando.yml database creds (admin/admin) exist — needed when the MySQL
# data volume was created before lenv creds or with Lando defaults (wordpress).

set -e

i=0
until mysql -uroot -h database -e "SELECT 1" >/dev/null 2>&1; do
  i=$((i + 1))
  if [ "$i" -ge 30 ]; then
    echo "[lenv] Database not reachable; skipping credential sync."
    exit 1
  fi
  sleep 2
done

mysql -uroot -h database <<'SQL'
CREATE DATABASE IF NOT EXISTS wordpress;
CREATE DATABASE IF NOT EXISTS wordpress_tests;
CREATE USER IF NOT EXISTS 'admin'@'%' IDENTIFIED BY 'admin';
ALTER USER 'admin'@'%' IDENTIFIED BY 'admin';
GRANT ALL PRIVILEGES ON wordpress.* TO 'admin'@'%';
GRANT ALL PRIVILEGES ON wordpress_tests.* TO 'admin'@'%';
FLUSH PRIVILEGES;
SQL

echo "[lenv] Database credentials synced (admin/admin)."
