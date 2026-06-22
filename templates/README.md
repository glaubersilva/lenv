# __PROJECT_NAME__ — Local Environment

Isolated WordPress development environment for **__PROJECT_NAME__**.

## Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running
- [Lando](https://lando.dev) installed

**Windows:** WSL2 is required. The recommended distro is **Ubuntu**. After installing Docker Desktop, enable WSL integration:
Docker Desktop → Settings → Resources → WSL Integration → enable Ubuntu.

> This setup is tested on **Windows with WSL2 (Ubuntu)** and **macOS**. Native Linux (without WSL2) may work but is not officially tested.

## URLs

| Service    | URL                                       | Credentials                     |
|------------|-------------------------------------------|---------------------------------|
| Site       | https://__PROJECT_NAME__.lndo.site             | —                               |
| WP Admin   | https://__PROJECT_NAME__.lndo.site/wp-admin    | admin / admin                   |
| phpMyAdmin | http://phpmyadmin.__PROJECT_NAME__.lndo.site   | admin / admin                   |
| Mailhog    | http://mailhog.__PROJECT_NAME__.lndo.site      | —                               |
| PHP info   | https://__PROJECT_NAME__.lndo.site/lenv-phpinfo.php | — |
| Xdebug info | https://__PROJECT_NAME__.lndo.site/lenv-xdebuginfo.php | — |

## Documentation

Extended guides are in `docs/` — check that folder for the most up-to-date references:

- [docs/troubleshooting.md](docs/troubleshooting.md) — Lando and Docker issues (`lando start`, proxy, WSL2)
- [docs/xdebug.md](docs/xdebug.md) — Xdebug setup for PhpStorm, VS Code and WSL2
- [docs/environment.md](docs/environment.md) — Changing PHP version, database engine and web server

---

## First time setup

**1. Start the environment:**
```bash
lando start
```
> Having trouble? See [docs/troubleshooting.md](docs/troubleshooting.md).

**2. Install WordPress:**
```bash
lando wp-install
```
This downloads WordPress core, creates `wp-config.php`, installs WordPress, sets up permalinks, and creates the `wordpress_tests` database.

> **Note:** Do not use `lando setup` or `lando install` — both are reserved Lando commands. Use `lando wp-install` for WordPress setup.

**3. Verify the site is working:**

Open https://__PROJECT_NAME__.lndo.site — it should load with the default WordPress theme.

**4. Clone the repositories into `wp-content/plugins/` or `wp-content/themes/`:**
```bash
cd wp-content/plugins
git clone <repo-url> <folder-name>
```

**5. Activate plugins/themes:**
```bash
lando wp plugin activate <plugin-name>
lando wp theme activate <theme-name>
```

---

## Databases

Two databases are created by `lando wp-install`:

| | Main | Tests |
|---|---|---|
| **Name** | `wordpress` | `wordpress_tests` |
| **User** | `admin` | `admin` |
| **Password** | `admin` | `admin` |
| **Host (inside container)** | `database` | `database` |

> The host `database` is used in `wp-config.php` and any PHP code running inside the container.

**Connecting from an external tool** (TablePlus, DBeaver, etc.):
```
Host:     127.0.0.1
Port:     run `lando info` to get the exposed port
User:     admin
Password: admin
Database: wordpress        (or wordpress_tests)
```

Or just use **phpMyAdmin** at http://phpmyadmin.__PROJECT_NAME__.lndo.site — no port lookup needed.

---

## Xdebug

Xdebug is **disabled by default** (`lenv new` prompts for `off` or `debug`). Change the persistent setting with:

```bash
lenv update
lando rebuild -y
```

Toggle at runtime **without rebuild** (any webserver):

```bash
lenv xdebug on
lenv xdebug off
lenv xdebug status
```

**Apache / Nginx / LiteSpeed** — stored in `.lando.yml`:

```yaml
config:
  xdebug: off    # default
  xdebug: debug  # enabled after rebuild
```

**FrankenPHP** — stored as a lenv comment in `.lando.yml`:

```yaml
# lenv-xdebug: off
```

FrankenPHP images include the Xdebug extension at build time; `off` disables it on container start. Use `lenv xdebug status` if configured and runtime do not match.

> See [docs/xdebug.md](docs/xdebug.md) for IDE setup (PhpStorm, VS Code, WSL2).

---

## Private packages (npm and Composer)

Some packages are hosted in private GitHub registries and require a GitHub token.

### Creating the token

1. Go to https://github.com/settings/tokens → **Generate new token (classic)**
2. Select scopes: `repo` and `read:packages`

### npm

Add to `~/.zshrc`: `export NPM_TOKEN=ghp_xxxxxxxxxxxx`

The project `.npmrc` already references it via `${NPM_TOKEN}`.

### Composer

Add to `~/.zshrc`: `export GITHUB_TOKEN=ghp_xxxxxxxxxxxx`

Then `lando rebuild -y` — the run script auto-configures Composer auth inside the container.

> Both tokens can be the same if it has both `repo` and `read:packages` scopes.

---

## lenv commands

Run these **from the host** to change project configuration (see [docs/environment.md](docs/environment.md)). Most commands accept an optional `[folder]` — run inside the project or pass the folder name from the parent directory (e.g. `lenv status __PROJECT_NAME__.lndo.site`).

| Command              | Description                                           |
|----------------------|-------------------------------------------------------|
| `lenv status`        | Show current project configuration                    |
| `lenv update`        | Change PHP, database, webserver, or Xdebug setting    |
| `lenv rebuild`       | Re-apply lenv templates; then run `lando rebuild -y`  |
| `lenv remove`        | Destroy the Lando app and delete this project folder  |
| `lenv xdebug on`     | Enable Xdebug in the running container (no rebuild)   |
| `lenv xdebug off`    | Disable Xdebug in the running container (no rebuild)  |
| `lenv xdebug status` | Show configured vs runtime Xdebug state               |

## Lando commands

Run these **inside the project folder** (day-to-day development):

| Command                    | Description                                                      |
|----------------------------|------------------------------------------------------------------|
| `lando start`              | Start this environment's services                                |
| `lando stop`               | Stop this environment's services (keeps containers)              |
| `lando restart`            | Restart this environment's services                              |
| `lando rebuild`            | Rebuild the environment — run after changing `.lando.yml`        |
| `lando poweroff`           | Stop **all** running Lando environments on the machine           |
| `lando info`               | Show service info and URLs                                       |
| `lando logs`               | Show container logs                                              |
| `lando ssh`                | Open a shell inside the appserver container                      |
| `lando composer <command>` | Run Composer inside the container, e.g. `lando composer install` |
| `lando wp <command>`       | Run WP-CLI, e.g. `lando wp plugin list`                          |
| `lando wp-install`         | Download and install WordPress (run once after first `lando start`) |

