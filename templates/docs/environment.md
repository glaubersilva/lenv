# Environment Configuration

The easiest way to change environment settings is with the CLI:

```bash
lenv update <project-folder>
```

This walks you through changing PHP version, database, webserver and Xdebug interactively,
then updates `.lando.yml` and tells you when to run `lando rebuild -y`.

---

## Manual changes

All changes below are made by editing `.lando.yml` and running:

```bash
lando rebuild -y
```

---

## PHP version

Edit the `php` key under `config`:

```yaml
config:
  php: '8.2'   # change to '8.1', '8.3', '7.4', etc.
```

Supported versions: `7.4`, `8.0`, `8.1`, `8.2`, `8.3`

No data is lost when changing PHP versions — only the PHP runtime is rebuilt.

---

## Database

Edit the `database` key under `config`:

```yaml
config:
  database: mysql:8.0
```

### MySQL versions

| Value | Version |
|---|---|
| `mysql:5.7` | MySQL 5.7 |
| `mysql:8.0` | MySQL 8.0 (default) |

### MariaDB (lighter alternative to MySQL)

| Value | Version |
|---|---|
| `mariadb:10.6` | MariaDB 10.6 |
| `mariadb:11.4` | MariaDB 11.4 |

### Data migration warning

Changing the database engine or version **destroys the existing volume** and resets all data. Export first:

```bash
lando wp db export --allow-root backup.sql
```

Then rebuild and reimport:

```bash
lando rebuild -y
lando wp db import --allow-root backup.sql
```

---

## Web server

The `wordpress` recipe supports both Apache and Nginx via the `via` key under `config`:

```yaml
config:
  via: apache   # default
```

### Switch to Nginx

```yaml
config:
  via: nginx
```

No code changes required — Lando configures Nginx with the correct WordPress rewrite rules automatically.

#### Apache vs Nginx in this stack

| | Apache | Nginx |
|---|---|---|
| Default | Yes | No |
| `.htaccess` support | Yes | No (rewrites handled by Lando config) |
| Performance (local dev) | Equivalent | Equivalent |

For local development, the choice has no practical impact. Apache is recommended unless you need to match a specific production Nginx configuration.

---

## Full example

```yaml
config:
  webroot: .
  php: '8.3'
  database: mariadb:11.4
  via: nginx
  xdebug: off
```
