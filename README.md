# lenv - local WordPress dev environments with Lando.

> **Early development:** `lenv` is experimental and under active development. Expect breaking changes, rough edges, and docs that may lag behind the code.

CLI and templates for spinning up complete local WordPress development environments with [Lando](https://lando.dev).

Run `lenv new` to scaffold a project folder (e.g. `mysite.lndo.site`), then `cd` into it and run `lando start` and `lando wp-install` — you get a working WordPress site with database tools, email capture, debugging, and WP-CLI ready to go.

## What each environment includes

Every project created by `lenv new` is a self-contained Lando stack:

| Component | Description |
|---|---|
| **WordPress** | Core installed via `lando wp-install`, pretty permalinks, `WP_DEBUG` enabled |
| **Database** | MySQL or MariaDB (configurable), plus a `wordpress_tests` database for PHPUnit |
| **phpMyAdmin** | Web UI at `http://phpmyadmin.mysite.lndo.site` — credentials `admin` / `admin` |
| **Mailhog** | Captures all outgoing email at `http://mailhog.mysite.lndo.site` |
| **Xdebug** | Off by default; enable with `lenv update` or `lenv xdebug on` |
| **IDE integration** | `PHP_IDE_CONFIG` and path mappings pre-configured for PhpStorm / VS Code |
| **WP-CLI & Composer** | `lando wp` and `lando composer` run inside the container with the project's PHP version |
| **Dev PHP extensions** | `uopz` (for plugin test suites) installed automatically; Git `safe.directory` configured for WSL bind mounts |
| **Diagnostic pages** | `lenv-phpinfo.php` and `lenv-xdebuginfo.php` in the project root |
| **Project docs** | `README.md` with URLs and credentials; `docs/troubleshooting.md`, `docs/xdebug.md`, and `docs/environment.md` |

### URLs and credentials (example: `mysite`)

| Service | URL | Credentials |
|---|---|---|
| Site | `https://mysite.lndo.site` | — |
| WP Admin | `https://mysite.lndo.site/wp-admin` | `admin` / `admin` |
| phpMyAdmin | `http://phpmyadmin.mysite.lndo.site` | `admin` / `admin` |
| Mailhog | `http://mailhog.mysite.lndo.site` | — |

Project names can include dots (e.g. `local.mysite`) when a plugin requires a `local.*` domain for licensing.

## Requirements

- [PHP CLI](https://www.php.net) — the `lenv` script itself is a PHP script; only `php-cli` is needed on the host (WordPress runs inside Lando containers):
  - Ubuntu / Debian / Mint: `sudo apt install php-cli`
  - Fedora: `sudo dnf install php-cli`
  - macOS (Homebrew): `brew install php`
- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [Lando](https://lando.dev)

**Windows:** WSL2 with **Ubuntu** and Docker Desktop WSL integration enabled (Settings → Resources → WSL Integration). If WSL ↔ Windows interop breaks (`UtilAcceptVsock`), see [`scripts/windows/`](scripts/windows/) and each project's `docs/troubleshooting.md`.

> **Troubleshooting:** if `lenv` fails with ``/usr/bin/env: ‘php’: No such file or directory``, PHP is not installed on the host — see [Requirements](#requirements).

## Setup

> **Note:** `~/Dev/tools/lenv` below is an example install path. Clone the repo wherever you prefer, then use that same path in the `PATH` line in the next step.

**1. Clone the repository:**

```bash
git clone https://github.com/glaubersilva/lenv.git ~/Dev/tools/lenv
```

**2. Add `lenv` to your PATH:**

```bash
# zsh (default on macOS, common on Ubuntu)
echo 'export PATH="$HOME/Dev/tools/lenv:$PATH"' >> ~/.zshrc
source ~/.zshrc

# bash
echo 'export PATH="$HOME/Dev/tools/lenv:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

Not sure which shell you use? Run `echo $SHELL` — it will say `/bin/zsh` or `/bin/bash`.

> **Warning (Windows + WSL2):** Edit `~/.zshrc` or `~/.bashrc` **from inside your WSL terminal**, not via Git Bash (`echo` via `wsl -e sh -c "..."` or similar). Git Bash expands `$PATH` at write time and injects the entire Windows PATH into the file, corrupting the variable and breaking tools like `lando` that depend on it.

## Quick start

```bash
lenv new                  # interactive — see Environment options below
cd mysite.lndo.site
lando start               # day-to-day start
lando wp-install          # download WordPress, create DB, run initial setup
```

If `lando start` fails on WSL2 (especially after a Docker Desktop update), run `lenv doctor` then `lenv fix`.

Then open `https://mysite.lndo.site` to verify the install.

## Environment options

`lenv new` and `lenv update` prompt for the same settings. Press Enter to accept the default shown in brackets.

| Setting | Default (`lenv new`) | Options | Notes |
|---|---|---|---|
| **Project name** | — | lowercase letters, numbers, hyphens, dots | Becomes the Lando app name and `{name}.lndo.site` URL |
| **Folder name** | `{name}.lndo.site` | letters, numbers, hyphens, dots, underscores | Where the project is created on disk |
| **PHP** | `8.3` | `8.3`, `8.2`, `8.1`, `8.0`, `7.4` | FrankenPHP requires **8.0+** |
| **Database** | `mysql:8.0` | `mysql:8.0`, `mysql:5.7`, `mariadb:11.4`, `mariadb:10.6` | Changing engine in `lenv update` **destroys data** |
| **Webserver** | `apache` | `apache`, `nginx`, `litespeed`, `frankenphp` | See below |
| **Xdebug** | `off` | `off`, `debug` | Use `lenv xdebug on/off` at runtime without rebuild |

### Webservers

| Value | How it runs |
|---|---|
| `apache` | Lando `wordpress` recipe (`config.via: apache`) |
| `nginx` | Lando `wordpress` recipe (`config.via: nginx`) |
| `litespeed` | Lando `wordpress` recipe (`config.via: litespeed`) — **experimental** on Lando 3.26.x; lenv warns before applying |
| `frankenphp` | Custom `type: lando` appserver with `docker/frankenphp/` (Caddy + FrankenPHP) |

Switching to or from **FrankenPHP** replaces `.lando.yml` and adds or removes `docker/`. Other webserver changes replace `.lando.yml` only.

After `lenv update`, run `lando rebuild -y` inside the project. Use `lenv rebuild` first if you need the latest templates from this repo.

## Known issues

### LiteSpeed on Lando 3.26.x

The Lando `wordpress` recipe lists `litespeed` as a `via` option, but **Lando 3.26.x currently fails** when building a LiteSpeed environment. `lando rebuild` typically ends with:

```
TypeError: Cannot read properties of undefined (reading 'version')
```

`lenv new` and `lenv update` show a warning and ask for confirmation before applying LiteSpeed (default: no). Prefer **apache**, **nginx**, or **frankenphp** unless you have verified LiteSpeed works on your Lando version.

### FrankenPHP — 404 right after `lando start`

FrankenPHP takes a few seconds to become ready after containers start. Until then:

- Opening the site in the browser may return **404**
- `lando start` may report the appserver healthcheck as **`[404]`** in the vitals table

This is a **startup timing issue**, not a broken project. Wait 10–30 seconds and refresh — the site should load normally. If it still fails after a minute, check `lando logs -s appserver`.

## CLI commands

Most project commands accept an optional `[folder]` argument. Run them **from inside the project** (where `.lando.yml` lives) or **from the parent directory** with the folder name:

```bash
lenv status                  # from inside the project folder
lenv status mysite.lndo.site # from the parent directory
```

Commands that support `[folder]`: **`status`**, **`update`**, **`rebuild`**, **`remove`**, **`doctor`**, **`fix`**, and **`xdebug`** (folder comes after `on`, `off`, or `status`). **`new`** does not — it creates a project interactively.

### `lenv new`

Create a new environment interactively — prompts for all settings in [Environment options](#environment-options).

### `lenv status [folder]`

Show the current project configuration (PHP, database, webserver, Xdebug) and runtime Xdebug state when Lando is running.

### `lenv update [folder]`

Change any [Environment options](#environment-options) on an existing project. Run `lando rebuild -y` inside the project afterward.

### `lenv rebuild [folder]`

Re-apply the latest `.lando.yml`, `.lando/php.ini`, `.lando/*.sh`, and `docs/*.md` templates to an existing project, preserving its current settings. Asks whether to keep `README.md` (default: yes). Run `lando rebuild -y` inside the project afterward.

### `lenv remove [folder]`

Permanently remove a project: runs `lando destroy`, then deletes the project folder. Asks for confirmation (default: no).

### `lenv doctor [folder]`

Check host and project readiness: Docker, Lando version, PowerShell/wslvar on WSL2, and orphan `.exe` files (shown as a warning only — not a blocking error). Run this when `lando start` fails with *Could not automatically start Docker* even though Docker Desktop is running.

### `lenv fix [folder]`

WSL2 recovery wrapper — use when `lando start` fails or after a Docker Desktop update, not as a daily substitute for `lando start`. Before `lando start`, it:

1. Removes orphan build-engine `.exe` files from the project root (WSL2 only)
2. Verifies Docker responds and blocks if WSL ↔ Windows interop is broken
3. On WSL2, runs `lando update` to sync with Docker Desktop
4. Runs `lando start`

Pair with `lenv doctor` to diagnose first. For normal starts, use `lando start` directly.

### `lenv xdebug <on|off|status> [folder]`

Toggle or inspect Xdebug in the running container without rebuilding. Wraps `lando xdebug-on` / `lando xdebug-off`.

```bash
lenv xdebug on                      # from inside the project folder
lenv xdebug status mysite.lndo.site # from the parent directory
```

Requires `lando start` to be running. To change the default for new containers, use `lenv update` and run `lando rebuild -y`.

## Documentation

- [docs/architecture.md](docs/architecture.md) — repository layout, templates, and how updates propagate to existing projects
- [docs/platforms.md](docs/platforms.md) — WSL vs macOS differences and why templates are built the way they are

Each generated project also includes its own `README.md` and `docs/` with troubleshooting, Xdebug setup, and environment configuration.
