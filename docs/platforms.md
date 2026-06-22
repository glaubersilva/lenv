# Platform differences

Internal notes on how **Windows + WSL2 (Ubuntu)** and **macOS** behave differently when running Lando environments created by `lenv`. This document justifies template decisions — it is **not** copied to generated projects.

For end-user troubleshooting, see `templates/docs/troubleshooting.md` (synced to projects via `lenv rebuild`).

---

## Supported platforms

| Platform | Status | Notes |
|---|---|---|
| Windows + WSL2 (Ubuntu) | Primary | Docker Desktop WSL integration required |
| macOS (Intel) | Supported | Native amd64 Docker images |
| macOS (Apple Silicon) | Supported | amd64 images via emulation; slower startup |
| Native Linux (no WSL) | Untested | May work but not officially validated |

Both platforms share the same templates. Differences are handled inside the container or documented in platform-specific troubleshooting — not by maintaining separate Landofiles.

### Web server options

| Server | Supported in lenv | How |
|---|---|---|
| Apache | Yes | `config.via: apache` (default) |
| Nginx | Yes | `config.via: nginx` |
| LiteSpeed | Experimental | `config.via: litespeed` — known to fail on Lando 3.26.x; lenv warns before applying |
| FrankenPHP | Yes | Custom `type: lando` template with `docker/frankenphp/` |

`lenv new` and `lenv update` support all four options. Switching to or from FrankenPHP replaces `.lando.yml` and syncs or removes `docker/`.

### Project names with dots

Lando's default recipe proxy strips dots from the app name (`local.mysite` → `localmysite.lndo.site`). lenv registers `proxy.appserver: __PROJECT_NAME__.lndo.site` in the wordpress recipe template so the documented URL matches WordPress `siteurl` / `home`. FrankenPHP already used an explicit appserver proxy.

### lenv CLI (host-side)

| Command | Notes |
|---|---|
| `lenv status [folder]` | Project config; Xdebug shows `configured` vs `runtime` |
| `lenv update [folder]` | Change PHP, database, webserver, Xdebug |
| `lenv rebuild [folder]` | Re-sync templates from this repo |
| `lenv remove [folder]` | `lando destroy` + delete project folder |
| `lenv xdebug on/off/status [folder]` | Runtime Xdebug toggle |

All except `new` accept optional `[folder]` — run inside the project or pass the folder name from the parent directory.

---

## Two layers of problems

Issues fall into two separate layers. Confusing them leads to misdiagnosis.

### Layer 1 — Lando host setup (before containers start)

Problems with Lando's own infrastructure: build engine, proxy, WSL ↔ Windows interop, Docker API connectivity.

| Symptom | Typical platform |
|---|---|
| `Could not automatically start Docker` (Docker is actually running) | WSL2 |
| `WSL ERROR: UtilAcceptVsock: accept4 failed 110` | WSL2 |
| Random `.exe` files (~500 MB) in project root | WSL2 only |
| `lando start` hangs after proxy starts | WSL2 (also seen after Docker Desktop updates) |
| Stale proxy / `network not found` | Both (after interrupted starts) |

**macOS users can usually skip WSL-specific troubleshooting.** These issues are documented in `templates/docs/troubleshooting.md` because they affect end users on Windows.

### Layer 2 — Container provisioning (inside appserver)

Problems with what ends up inside the running container: WP-CLI, Composer, WordPress core.

| Symptom | Typical platform |
|---|---|
| `wp: not found` / `composer: not found` | macOS (especially Apple Silicon) |
| `lando wp-install` fails on first run | macOS (when WP-CLI not provisioned by recipe) |
| Build step curl/github failures during `lando rebuild` | macOS (recipe internal steps) |

These are **template problems**, solved in `.lando.yml` — not something users should work around manually.

---

## Container layer: why macOS broke but WSL did not

### What we observed (macOS Apple Silicon, 2025)

On a fresh `lenv new` + `lando start` + `lando install`:

```
/bin/sh: 1: wp: not found
```

Inside the container:

- `/usr/local/bin/wp` did not exist
- `/usr/local/bin/composer` did not exist
- `lando wp` and `lando composer` both failed
- `lando rebuild` showed `One of your v3 build steps failed` and occasional `curl: Failed to connect to github.com port 80`

On WSL/Ubuntu (amd64), the same template often worked because the Lando `wordpress` recipe successfully completed its build steps that install WP-CLI and Composer.

### Root causes

| Factor | WSL2 (Ubuntu) | macOS (Apple Silicon) |
|---|---|---|
| Container image arch | `linux/amd64` (native) | `linux/amd64` (emulated via Rosetta/QEMU) |
| Lando recipe build steps | Usually complete | Often fail or skip tool installation |
| Network during container build | Stable | Intermittent failures (curl to GitHub) |
| `run:` step with bare `composer` | Failed after recipe also failed | Same — compounded failure |

The template originally assumed the `wordpress` recipe would provision `wp` and `composer`. That assumption held on WSL but not on macOS.

### Why we did not fix this with `build_as_root` + curl

We tried installing via `build_as_root` with HTTPS curl. It failed on macOS during the build phase with the same GitHub connectivity errors, while manual `lando ssh` + curl worked seconds later.

Build-time networking inside Lando is less reliable than runtime networking. Downloading WP-CLI and Composer on first container start (or before tooling commands) avoids build-phase failures and keeps versions current without vendoring binaries in the repo.

---

## Template decisions (and why)

### Runtime download of WP-CLI and Composer

**Location:** `.lando.yml` / `.lando.frankenphp.yml` (`appserver.run` and tooling), `docker/dev/entrypoint.sh` (FrankenPHP)

**Copied by:** `lenv new`, `lenv rebuild`

**Installed by:** inline `curl` in the Landofile on first `lando start`, and before `lando wp`, `lando composer`, and `lando wp-install`:

```yaml
appserver:
  run:
    - test -x /usr/local/bin/wp || (curl -fsSL ... -o /usr/local/bin/wp && chmod +x /usr/local/bin/wp)
    - test -x /usr/local/bin/composer || (curl -fsSL ... -o /usr/local/bin/composer && chmod +x /usr/local/bin/composer)
```

```yaml
wp:
  cmd: /bin/sh -c 'test -x /usr/local/bin/wp || (curl ...); exec /usr/local/bin/wp --allow-root "$@"' _
```

**Why:**

- Deterministic across WSL and macOS when the Lando recipe skips tool installation
- No vendored binaries and no extra helper scripts — logic lives in the Landofile
- Always fetches current stable releases from official sources
- `test -x` guard makes subsequent starts instant (no re-download)

**Trade-off:** Requires network on first use. Build-phase curl remains unreliable on macOS, but runtime curl works.

### Explicit binary paths in tooling

```yaml
wp:
  cmd: /usr/local/bin/wp --allow-root

wp-install:
  cmd:
    - /usr/local/bin/wp core download ...
```

**Why:** Lando tooling commands run in a minimal shell context. Even when `wp` exists, relying on `$PATH` inside multi-step tooling is fragile. Absolute paths match how production Dockerfiles install CLI tools.

### `lando wp-install` instead of `lando install`

**Why:**

- `lando setup` — reserved (Lando internal dependencies)
- `lando install` — reserved (Lando package install)
- `lando wp-install` — clearly scoped to WordPress initial setup

Naming is a usability choice, not platform-specific — but it prevents confusion on all platforms.

### `appserver.run` instead of `build_as_root`

**Why:**

| Approach | WSL | macOS |
|---|---|---|
| Recipe build (default) | Works | Unreliable |
| `build_as_root` + curl | Works sometimes | Failed during build phase |
| `run` + inline curl in Landofile | Works | Works |

`run` executes when the container has network (for other steps) and the project volume is mounted at `/app`. Copying local files is platform-agnostic.

### IDE path placeholder (`__PROJECT_IDE_PATH__`)

**In `lenv new`:**

```php
$ideProjectPath = $isWsl
    ? trim(shell_exec('wslpath -w ' . escapeshellarg($projectDir)) ?? $projectDir)
    : $projectDir;
```

**Why:**

- **WSL:** PhpStorm/VS Code on Windows need `\\wsl$\Ubuntu\...` UNC paths for path mappings
- **macOS:** POSIX path is correct as-is

Same template, different substitution at scaffold time.

### Troubleshooting split

| Document | Audience | Updated by |
|---|---|---|
| `docs/platforms.md` (this file) | lenv developers | Manual — repo only |
| `templates/docs/troubleshooting.md` | End users | `lenv rebuild` → projects |

**Why:** WSL-specific Lando host issues are long and change with Docker Desktop updates. They belong in project docs users can refresh. Platform rationale and template design choices belong here.

---

## Platform-specific user issues (not template bugs)

These are real differences users encounter but are outside what templates can fix.

### WSL2 only

- **Lando build engine** downloads a Windows `.exe` on failure — see troubleshooting doc
- **Docker WSL integration** silently disabled after Windows/Docker updates
- **vsock timeouts** between WSL and Windows host
- **PATH corruption** if shell config edited via Git Bash instead of WSL terminal
- **Xdebug + VS Code** needs Windows port proxy (`192.168.65.254` → WSL) — see `templates/docs/xdebug.md`

### macOS only

- **Apple Silicon emulation** — `linux/amd64` images run slower; platform mismatch warnings in Docker output are expected and harmless
- **Docker Desktop version warnings** — Lando may flag untested versions (e.g. 4.52+); usually non-blocking
- **Xdebug** — connects directly to host; no port proxy needed

### Both platforms

- **LocalWP conflict** — both bind ports 80/443 via Lando proxy; not lenv-specific
- **Stale Lando proxy** after interrupted `lando start` — `docker rm` + `network prune`
- **Keep Lando and Docker Desktop in sync** — run `lando update` after Docker Desktop updates
- **FrankenPHP startup 404** — appserver may return 404 for 10–30 seconds after `lando start`; wait and refresh (cosmetic healthcheck `[404]` is the same timing issue)

---

## What is identical on all platforms

Once `lando start` completes successfully:

- URLs: `https://{name}.lndo.site`, phpMyAdmin, Mailhog (dots in `{name}` preserved when using current templates)
- Credentials: `admin` / `admin`
- Commands: `lando wp`, `lando composer`, `lando wp-install`
- Host CLI: `lenv status`, `lenv update`, `lenv rebuild`, `lenv remove`, `lenv xdebug`
- Databases: `wordpress` + `wordpress_tests`
- Xdebug toggle via `.lando.yml` or `lenv xdebug on/off`
- Private package auth via `GITHUB_TOKEN` env var

The goal of the runtime-download approach is that **Layer 2 behaves the same everywhere**. Remaining differences are Layer 1 (Lando host) or IDE setup.

---

## Forcing WP-CLI / Composer re-download

Binaries are cached in the container at `/usr/local/bin/wp` and `/usr/local/bin/composer`. To fetch fresh versions:

```bash
lando ssh -s appserver -u root -c "rm -f /usr/local/bin/wp /usr/local/bin/composer"
lando restart
```

Or after `lando rebuild -y`, the next `lando wp` / `lando composer` / `lando start` will download again.

---

## References

- [Lando on WSL](https://docs.lando.dev/install/wsl.html)
- [lando/wordpress#15 — WP CLI not installed](https://github.com/lando/wordpress/issues/15)
- [lando/wordpress#102 — composer/wp not found](https://github.com/lando/wordpress/issues/102)
- `templates/docs/troubleshooting.md` — end-user fixes for Layer 1 issues
- `templates/docs/xdebug.md` — IDE setup including WSL2 port proxy
