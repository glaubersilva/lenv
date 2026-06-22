# Architecture

How the `lenv` CLI and templates are organized.

## Repository layout

```
lenv/
  lenv              <- CLI executable (add to PATH)
  helpers.php       <- shared utilities
  commands/
    new.php         <- lenv new
    rebuild.php     <- lenv rebuild [folder]
    update.php      <- lenv update [folder]
    xdebug.php      <- lenv xdebug <on|off|status> [folder]
  templates/
    .lando.yml          <- WordPress recipe template (apache, nginx, litespeed)
    .lando.frankenphp.yml <- FrankenPHP custom appserver template
    docker/
      frankenphp/Dockerfile
      dev/Caddyfile
      dev/entrypoint.sh
    .lando/
      php.ini                    <- Xdebug config (empty stub when xdebug is off)
      install-wp-config-https.php <- inject HTTPS detection into wp-config.php
      xdebug-on.sh               <- runtime Xdebug enable script
      xdebug-off.sh              <- runtime Xdebug disable script
    README.md       <- project README template
    docs/
      troubleshooting.md <- Lando/Docker troubleshooting (synced by lenv rebuild)
      xdebug.md     <- Xdebug setup guide (copied to new projects)
      environment.md <- Environment config guide (copied to new projects)
  docs/
    architecture.md <- repository layout and template propagation
    platforms.md    <- WSL vs macOS differences and template rationale (dev only)
  README.md
  .gitignore
```

## How templates work

All files in `templates/` use `__PROJECT_NAME__` as a placeholder. Commands replace it with the actual project name when scaffolding a new environment.

| Placeholder | Replaced with |
|---|---|
| `__PROJECT_NAME__` | project name |
| `__PROJECT_NAME__.lndo.site` | `{name}.lndo.site` |
| `serverName=__PROJECT_NAME__` | `serverName={name}` |
| `__PROJECT_IDE_PATH__` | IDE-friendly path to the project folder |

`templates/docs/` is copied to each new project's `docs/` folder with the same substitutions applied.

## Updating templates

Changes to `templates/` only affect **future** projects. For existing projects, use:

```bash
lenv rebuild [folder]
```

Then inside the project: `lando rebuild -y`

> `lenv rebuild` overwrites `.lando.yml`, `.lando/php.ini`, `.lando/*.sh`, and `docs/*.md` from templates. It reapplies the project's current name, PHP, database, webserver, and Xdebug — but any other custom `.lando.yml` edits are lost. It prompts before overwriting `README.md` (default: keep existing file).

## Commands (implementation)

| Command | What it does |
|---|---|
| `lenv new` | Interactive wizard — creates project folder from templates |
| `lenv rebuild` | Re-applies `.lando.yml`, `.lando/php.ini`, `.lando/*.sh`, and `docs/*.md`; keeps `README.md` unless you opt in to replace it |
| `lenv update` | Interactive edit of PHP version, database, webserver, and Xdebug in `.lando.yml` |
| `lenv xdebug` | Toggle or inspect Xdebug in the running container (`on`, `off`, `status`) |

Validation helpers (`validate_project_name`, `validate_folder_name`) live in `helpers.php`. Project names allow dots (e.g. for `local.*` domains required by some plugin licenses).

## Web server templates: recipe vs FrankenPHP

`lenv update` and `lenv new` pick one of two Landofile templates via `write_project_lando()` in `helpers.php`:

| | **Recipe** (apache, nginx, litespeed) | **FrankenPHP** |
|---|---|---|
| Template | `templates/.lando.yml` | `templates/.lando.frankenphp.yml` |
| Lando type | `recipe: wordpress` | `type: lando` (custom compose service) |
| Appserver | Lando-managed `wordpress-php` image | Custom image from `docker/frankenphp/Dockerfile` |
| Web server | Apache, Nginx, or LiteSpeed inside recipe | FrankenPHP + Caddy (`docker/dev/Caddyfile`) |
| PHP / DB / Xdebug in YAML | `config.php`, `config.database`, `config.xdebug` | Comments `# lenv-php:`, `# lenv-database:`, `# lenv-xdebug:` |
| Extra files | None | `docker/` synced on create/update; removed when switching away |
| WP-CLI / Composer | Inline `curl` in `.lando.yml` (`appserver.run` + tooling) | Same pattern + download in `docker/dev/entrypoint.sh` on boot |
| Xdebug off | Lando recipe disables extension | `LENV_XDEBUG=off` env + `lenv-xdebug-off` in entrypoint (image still builds with Xdebug) |
| Site URL env | Proxy only | `WP_HOME` / `WP_SITEURL` in appserver environment |

Switching to or from FrankenPHP replaces `.lando.yml` and adds or removes `docker/`. Database data is preserved; only changing the **database engine** destroys data.

LiteSpeed remains in the recipe template but is **experimental** on Lando 3.26.x — `lenv` warns before applying (see `platforms.md`).

## HTTPS behind Lando proxy

Lando terminates TLS at the edge proxy. The browser uses `https://`, but the appserver often receives plain HTTP on an internal port (especially FrankenPHP on `:8080`).

Lando forwards `X-Forwarded-Proto: https`, but WordPress decides asset URLs via `is_ssl()`, which checks `$_SERVER['HTTPS']`. Without that flag, WordPress emits `http://` CSS/JS on an HTTPS page — **mixed content** and an unstyled site.

### Workaround: `install-wp-config-https.php`

**Location:** `templates/.lando/install-wp-config-https.php`  
**Synced by:** `lenv new`, `lenv rebuild` (via `sync_lando_scripts()`)  
**Run by:** `lando wp-install`, after `wp config create`:

```yaml
- php /app/.lando/install-wp-config-https.php
```

The script is idempotent: it prepends a small block to `wp-config.php` that sets `$_SERVER['HTTPS'] = 'on'` when `HTTP_X_FORWARDED_PROTO` contains `https`. It applies to **all webservers** — the bug is most visible on FrankenPHP, but the fix is the same behind Lando's proxy.

**Existing projects** created before this script shipped can run it once:

```bash
lando ssh -s appserver -c "php /app/.lando/install-wp-config-https.php"
```

Or re-run the HTTPS-related `wp-install` step after `lenv rebuild` copies the file into `.lando/`.
