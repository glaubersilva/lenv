# lenv

CLI and templates for spinning up complete local WordPress development environments with [Lando](https://lando.dev).

Run `lenv new`, then `lando start` and `lando wp-install` — you get a working WordPress site with database tools, email capture, debugging, and WP-CLI ready to go.

## What each environment includes

Every project created by `lenv new` is a self-contained Lando stack:

| | |
|---|---|
| **WordPress** | Core installed via `lando wp-install`, pretty permalinks, `WP_DEBUG` enabled |
| **Database** | MySQL or MariaDB (configurable), plus a `wordpress_tests` database for PHPUnit |
| **phpMyAdmin** | Web UI at `http://phpmyadmin.mysite.lndo.site` — credentials `admin` / `admin` |
| **Mailhog** | Captures all outgoing email at `http://mailhog.mysite.lndo.site` |
| **Xdebug** | Off by default; enable with `lenv update` or `lenv xdebug on` |
| **IDE integration** | `PHP_IDE_CONFIG` and path mappings pre-configured for PhpStorm / VS Code |
| **WP-CLI & Composer** | `lando wp` and `lando composer` run inside the container with the correct PHP |
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

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [Lando](https://lando.dev)

**Windows:** WSL2 with **Ubuntu** and Docker Desktop WSL integration enabled (Settings → Resources → WSL Integration).

## Setup

**1. Clone the repository:**

```bash
git clone https://github.com/glaubersilva/lenv.git ~/Dev/tools/lenv
```

**2. Add `lenv` to your PATH** by editing your shell config file **directly in the WSL terminal** (not via Git Bash or any Windows tool):

```bash
# zsh (default on macOS, common on Ubuntu)
echo 'export PATH="$HOME/Dev/tools/lenv:$PATH"' >> ~/.zshrc
source ~/.zshrc

# bash
echo 'export PATH="$HOME/Dev/tools/lenv:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

Not sure which shell you use? Run `echo $SHELL` — it will say `/bin/zsh` or `/bin/bash`.

> **Warning:** Do not add this line using Git Bash (`echo` via `wsl -e sh -c "..."` or similar). Git Bash expands `$PATH` at write time and injects the entire Windows PATH into the file, corrupting the variable and breaking tools like `lando` that depend on it. Always edit the shell config from inside WSL.

## Quick start

```bash
lenv new                  # interactive — see Environment options below
cd mysite.lndo.site
lando start
lando wp-install          # download WordPress, create DB, run initial setup
```

Then open `https://mysite.lndo.site` and clone your plugins into `wp-content/plugins/`.

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

## CLI commands

### `lenv new`

Create a new environment interactively — prompts for all settings in [Environment options](#environment-options).

### `lenv rebuild [folder]`

Re-apply the latest `.lando.yml`, `.lando/php.ini`, `.lando/*.sh`, and `docs/*.md` templates to an existing project, preserving its current settings. Asks whether to keep `README.md` (default: yes). Run `lando rebuild -y` inside the project afterward.

### `lenv update [folder]`

Change any [Environment options](#environment-options) on an existing project. Run `lando rebuild -y` inside the project afterward.

### `lenv xdebug <on|off|status> [folder]`

Toggle or inspect Xdebug in the running container without rebuilding. Wraps `lando xdebug-on` / `lando xdebug-off`.

```bash
lenv xdebug on       # enable in the running container
lenv xdebug off      # disable in the running container
lenv xdebug status   # show .lando.yml setting and runtime state
```

Requires `lando start` to be running. To change the default for new containers, use `lenv update` and run `lando rebuild -y`.

## Documentation

- [docs/architecture.md](docs/architecture.md) — repository layout, templates, and how updates propagate to existing projects
- [docs/platforms.md](docs/platforms.md) — WSL vs macOS differences and why templates are built the way they are

Each generated project also includes its own `README.md` and `docs/` with troubleshooting, Xdebug setup, and environment configuration.
