# Environment Configuration

The easiest way to change environment settings is with the CLI:

```bash
lenv update              # from inside the project folder
lenv update mysite.lndo.site   # from the parent directory
```

This walks you through changing PHP version, database, webserver, and Xdebug interactively,
then updates `.lando.yml` and tells you when to run `lando rebuild -y`.

Other host-side commands:

| Command | Purpose |
|---|---|
| `lenv status [folder]` | Show current PHP, database, webserver, and Xdebug settings |
| `lenv update [folder]` | Change PHP, database, webserver, or Xdebug setting |
| `lenv rebuild [folder]` | Re-apply lenv templates; then run `lando rebuild -y` |
| `lenv remove [folder]` | Destroy the Lando app and delete the project folder |
| `lenv doctor [folder]` | Check Docker/Lando/WSL readiness before start |
| `lenv fix [folder]` | WSL2 recovery: clean orphans, `lando update`, then `lando start` |
| `lenv xdebug on/off/status [folder]` | Toggle or inspect runtime Xdebug |

Xdebug defaults to **off** in `lenv new`. Use `lenv update` to change it later.

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

The `wordpress` recipe supports Apache, Nginx, and LiteSpeed via the `via` key under `config`:

```yaml
config:
  via: apache   # default
```

Change interactively:

```bash
lenv update              # from inside the project folder
lenv update mysite.lndo.site   # from the parent directory
```

### Options

| Value | Server |
|---|---|
| `apache` | Apache (default) |
| `nginx` | Nginx |
| `litespeed` | LiteSpeed (experimental) |
| `frankenphp` | FrankenPHP (custom appserver) |

After changing, rebuild:

```bash
lando rebuild -y
```

### Apache vs Nginx

| | Apache | Nginx |
|---|---|---|
| Default | Yes | No |
| `.htaccess` support | Yes | No (rewrites handled by Lando config) |
| Performance (local dev) | Equivalent | Equivalent |

For local development, the choice has no practical impact. Apache is recommended unless you need to match a specific production Nginx configuration.

### LiteSpeed

Available in `lenv new` and `lenv update`, but **experimental**. The Lando wordpress recipe advertises `via: litespeed`, yet Lando 3.26.x currently fails at `lando rebuild` with:

```
TypeError: Cannot read properties of undefined (reading 'version')
```

lenv shows a warning and asks for confirmation before applying LiteSpeed. Use `nginx` or `frankenphp` unless you have verified LiteSpeed works on your Lando version.

### FrankenPHP

Select `frankenphp` in `lenv new` or `lenv update`. lenv scaffolds a custom `type: lando` appserver with:

- `docker/frankenphp/Dockerfile` — FrankenPHP image with WordPress extensions
- `docker/dev/Caddyfile` — webroot at `/app`
- `docker/dev/entrypoint.sh` — waits for database, starts FrankenPHP

FrankenPHP requires **PHP 8.0+**. After creating or switching to FrankenPHP:

```bash
lando rebuild -y
lando wp-install
```

Toggle Xdebug at runtime (all webservers):

```bash
lenv xdebug on
lenv xdebug off
lenv xdebug status
```

Set the persistent default with `lenv update`, then `lando rebuild -y`.

**Startup timing:** FrankenPHP may return **404** for 10–30 seconds right after `lando start`. Wait and refresh — this is normal. `lando start` may also show `[404]` in the appserver healthcheck during the same window.

---

## Project names with dots

Project names can include dots (e.g. `local.mysite`) when a plugin license requires a `local.*` domain. The site URL is always `https://{name}.lndo.site`.

lenv registers an explicit `proxy.appserver` hostname so Lando routes the dotted URL correctly. Always use the URL documented in this project's README — not Lando's auto-generated hostname with dots stripped (e.g. `localmysite` instead of `local.mysite`).

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
