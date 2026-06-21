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

- [docs/xdebug.md](docs/xdebug.md) — Xdebug setup for PhpStorm, VS Code and WSL2
- [docs/environment.md](docs/environment.md) — Changing PHP version, database engine and web server

---

## First time setup

**1. Start the environment:**
```bash
lando start
```
> Having trouble? See [Troubleshooting lando start](#troubleshooting).

**2. Install WordPress:**
```bash
lando install
```
This downloads WordPress core, creates `wp-config.php`, installs WordPress, sets up permalinks, and creates the `wordpress_tests` database.

> **Note:** Do not use `lando setup` — that is a reserved Lando command for installing its own dependencies. Use `lando install` instead.

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

Two databases are created by `lando install`:

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

Xdebug is **disabled by default** to keep PHP fast during normal development.

Toggle it by editing `.lando.yml`:
```yaml
xdebug: debug   # enabled
xdebug: off     # disabled
```
Then rebuild: `lando rebuild -y`

> See [Documentation](#documentation) for the full Xdebug setup guide.

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

## Commands

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
| `lando install`            | Download and install WordPress (first time only)                 |

## Structure

```
__PROJECT_NAME__/
  .lando.yml
  wp-content/               <- created by `lando install`
    themes/
    plugins/
```

---

## Troubleshooting

### Keep Lando and Docker Desktop in sync

Lando and Docker Desktop must be kept up to date together. When Docker Desktop updates but Lando does not, Lando may fail to detect Docker, fail to install its build engine, or hang indefinitely on startup.

**Always run after a Docker Desktop update:**

```bash
lando update
```

Signs of a version mismatch during `lando start`:
- `Installing build engine... FAILED`
- `Could not automatically start Docker`
- `WSL ERROR: UtilAcceptVsock: accept4 failed`
- Lando hanging after the proxy container starts

---

### Random `.exe` files appearing in the project directory

> **Windows + WSL2 only.** On macOS the build engine is a Unix binary and this does not apply.

Symptom: after a failed `lando start`, files with random names and `.exe` extension appear in the project root, e.g.:

```
hDR32oQ_MSGxN4nlYtsHe.exe   (~500 MB)
VOJeMiZZO8n9SK4HqNk3E.exe   (~500 MB)
```

**Cause:** When Lando fails to install its build engine (triggered by the `UtilAcceptVsock` / `setup-build-engine FAILED` error), it leaves behind the partial download as a temp file in the current working directory. Each failed `lando start` attempt creates a new orphan file. They are identical binaries — the same Windows `.exe` downloaded repeatedly.

These files are harmless but are a clear signal that `setup-build-engine` is failing, usually because Lando and Docker Desktop are out of sync.

**Clean up:**
```bash
rm *.exe
```

**Fix the underlying cause:**
```bash
lando update
```

Then run `lando start` again. If the build engine installs successfully, no more `.exe` files will be left behind.

---

### `lando start` hangs after starting the proxy

Symptom: the output stops at `✔ Container landoproxyhyperion5000gandalfedition_proxy_1 Started` and nothing else happens for a long time.

This can happen on the first start after a reboot or after a Docker Desktop update, while Docker Desktop is still fully initializing its WSL integration. **Wait 1–2 minutes** — it usually resolves on its own.

If it doesn't recover after 2 minutes:
1. Cancel with `Ctrl+C`
2. Run `lando update` to ensure Lando is up to date
3. Run `lando start` again

---

### Proxy fails to start on `lando start`

Symptom: `Lando was not able to start the proxy` or `network not found` error.

This happens when Docker has a stale Lando proxy container referencing a deleted network. Fix:

```bash
docker rm -f landoproxyhyperion5000gandalfedition_proxy_1
docker network prune -f
lando start
```

### `lando start` shows "container is not connected to network"

Same stale state issue. Run the commands above.

---

### WSL crash + `network not found` during `lando start`

Symptom: `lando start` fails with two errors together:

```
WSL ERROR: UtilAcceptVsock: accept4 failed 110
```
```
Error response from daemon: failed to set up container networking: network <id> not found
```

**Cause:** WSL2 lost connectivity mid-operation. Docker was left with orphan containers and stale networks from the interrupted start.

**Fix:**

1. Shut down WSL to reset the connection:
```bash
wsl --shutdown
```

2. Wait a few seconds, then confirm WSL is back:
```bash
wsl -e uname -a
```

3. Remove orphan containers and stale networks:
```bash
docker rm -f $(docker ps -aq) 2>/dev/null || true
docker network prune -f
```

4. Run `lando start` again.

> Note: `docker rm -f $(docker ps -aq)` removes **all** stopped containers. If you have containers from other projects you want to keep, remove only the specific ones (e.g. `hacker_appserver_1`, `hacker_database_1`, etc. and `landoproxyhyperion5000gandalfedition_proxy_1`).

### Docker Desktop WSL integration not working

Symptom: `lando start` fails with `Could not automatically start Docker` or `failed to connect to the docker API at unix:///var/run/docker.sock`, even though Docker Desktop is open.

**Cause:** Docker Desktop and Windows updates are known to silently reset WSL integration settings. After an update, the integration with your WSL distro may be disabled without warning.

**Fix:**

1. Open Docker Desktop → **Settings** → **Resources** → **WSL Integration**
2. Check **"Enable integration with my default WSL distro"**
3. Enable the **Ubuntu** toggle
4. Click **Apply & Restart**

After Docker restarts, verify the connection from WSL:

```bash
docker info
```

If it returns engine info, the integration is working and `lando start` should succeed.
