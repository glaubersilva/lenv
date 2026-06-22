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
| **Xdebug** | Enabled with `start_with_request=yes` — triggers on every request, no browser extension needed |
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
lenv new                  # interactive — name, PHP version, database
cd mysite.lndo.site
lando start
lando wp-install          # download WordPress, create DB, run initial setup
```

Then open `https://mysite.lndo.site` and clone your plugins into `wp-content/plugins/`.

## CLI commands

### `lenv new`

Create a new environment interactively — asks for project name, folder, PHP version, database engine, webserver, and Xdebug (default off).

### `lenv rebuild [folder]`

Re-apply the latest `.lando.yml`, `.lando/php.ini`, `.lando/*.sh`, and `docs/*.md` templates to an existing project, preserving its current settings. Asks whether to keep `README.md` (default: yes). Run `lando rebuild -y` inside the project afterward.

### `lenv update [folder]`

Change PHP version, database, webserver, or Xdebug interactively. Run `lando rebuild -y` inside the project afterward.

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
