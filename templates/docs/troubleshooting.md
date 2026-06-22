# Troubleshooting

> **Windows + WSL2:** Docker Desktop and WSL2 interop issues are covered in the [last section](#wsl2-and-docker). macOS users can skip that section entirely.

---

## Conflict with LocalWP

Lando's Traefik proxy binds ports 80 and 443, which prevents LocalWP from starting its own Nginx.

- To work in LocalWP: run `lando poweroff` first
- To return to this project: stop all LocalWP sites and quit the app, then `lando start`

> **Important:** Before running `lando start` or `lando rebuild`, stop all sites in LocalWP and quit the app. If LocalWP is running during startup, the port conflict can interfere with Lando's initialization.

---

## Site 404, CORS errors, or can't log in (dotted project name)

Symptom: project name contains a dot (e.g. `local.mysite`), the README says `https://local.mysite.lndo.site`, but:

- `wp-login.php` returns **404**, or
- the site loads but the browser console shows **CORS** errors loading CSS/JS from a different hostname (often with dots stripped: `localmysite.lndo.site` vs `local.mysite.lndo.site`)

**Cause:** Lando's default recipe proxy strips dots from the app name. WordPress is installed with the documented URL, but the proxy may only route the sanitized hostname unless `proxy.appserver` is set explicitly (current lenv templates do this).

**Fix:**

```bash
lenv rebuild
lando rebuild -y
```

Confirm `lando info` lists `https://{your-name}.lndo.site` under appserver URLs. Always browse the **documented** URL from this README.

---

## FrankenPHP — 404 right after `lando start`

Symptom: FrankenPHP environment returns **404** in the browser or `[404]` in the `lando start` vitals table immediately after startup.

**Cause:** FrankenPHP takes a few seconds to become ready. This is a startup timing issue, not a broken project.

**Fix:** Wait 10–30 seconds and refresh. If it still fails after a minute:

```bash
lando logs -s appserver
```

---

## Database `1045 Access denied` after `lando rebuild`

Symptom: `lando wp-install`, phpMyAdmin healthcheck, or `lando start` fails with:

```
Access denied for user 'admin'@'...' (using password: YES)
```

**Cause:** The MySQL **data volume persisted** from an older setup (often with Lando's default user `wordpress`, not `admin`). Changing `services.database.creds` in `.lando.yml` does not update users inside an existing volume.

**Fix:** Current lenv templates run `.lando/ensure-db-creds.sh` on appserver start and at the beginning of `lando wp-install` to create/sync the `admin` user. After `lenv rebuild`, run:

```bash
lando rebuild -y
lando wp-install
```

If the script is missing from an older project, run `lenv rebuild` to sync `.lando/ensure-db-creds.sh`, then rebuild Lando again.

---

## `composer install` fails — missing `ext-uopz` or Git dubious ownership

Symptom: inside a cloned plugin (e.g. The Events Calendar), `lando composer install` or host `composer install` fails with:

```
fatal: detected dubious ownership in repository at '/app/wp-content/plugins/...'
```

and/or:

```
slope-it/clock-mock requires ext-uopz >=6.1.1 -> it is missing from your system
```

**Cause:**

- Plugin test dependencies require the **`uopz`** PHP extension, which lenv installs in the container — not on the host.
- On WSL, Git repos bind-mounted from the host have different ownership inside the container.

**Fix:**

1. Use **`lando composer install`**, never host `composer` inside plugin folders.
2. Sync the latest lenv templates and rebuild:

```bash
lenv rebuild
lando rebuild -y
```

On the **first start** after rebuild, lenv runs `.lando/ensure-dev-extensions.sh`, which compiles `uopz` once (may take ~1 minute) and configures Git `safe.directory`.

Verify:

```bash
lando php -m | grep uopz
```

If the script is missing from an older project, `lenv rebuild` copies `.lando/ensure-dev-extensions.sh` into the project.

---

## WSL2 and Docker

> **Windows + WSL2 only.** These issues apply to running Lando inside WSL2 with Docker Desktop on Windows — [officially supported by Lando](https://docs.lando.dev/install/wsl.html), but WSL2 ↔ Windows interop is fragile, especially after Docker Desktop or Windows updates.

### Diagnose and recover

Start here when **any** of these happen on WSL2:

| Symptom | Typical check |
|---|---|
| Site loads and `docker ps` shows containers, but `lando rebuild` hangs on *It seems Docker is not running* | `docker info` ✔, `lenv doctor` → ✖ PowerShell |
| `Could not automatically start Docker` but Docker Desktop is running | `docker info` works; `lando start` still fails |
| `WSL ERROR: UtilAcceptVsock: accept4 failed 110` | `lenv doctor` → ✖ PowerShell |
| `setup-build-engine FAILED` | Orphan `.exe` files (~500 MB) in the project root |
| `failed to connect to the docker API at unix:///var/run/docker.sock` | `lenv doctor` → ✖ Docker |
| Docker Desktop **WSL integration error** in the GUI | Often paired with both doctor failures above |
| `network <id> not found` after a WSL crash | Interop failure **plus** stale Docker networks |

These often share the same root cause: a stale WSL ↔ Windows bridge (vsock). Docker Desktop may look healthy while Lando setup cannot reach Windows.

**Site still works?** Existing containers keep running — you can browse the site and run `docker exec` — but the **Lando CLI** cannot complete its setup phase until interop is restored. Fix PowerShell first; do not assume Docker is the problem.

#### Diagnose first

```bash
lenv doctor
powershell.exe -NoProfile -Command "Write-Output ok"   # should print: ok
```

> **Do not run `lenv fix` or `lando start` while interop is broken** — Lando will download a ~500 MB Windows build-engine `.exe` into the project and fail anyway. `lenv fix` blocks in this state; bare `lando start` does not.

#### Why this happens (three-layer stack)

| Layer | What it does | Symptom when broken |
|---|---|---|
| Ubuntu (WSL distro) | Your terminal, `lando`, `docker` CLI | Commands run, but cannot reach Windows |
| WSL ↔ Windows bridge (vsock) | Lets Linux call Windows (`powershell.exe`, etc.) | `UtilAcceptVsock: accept4 failed 110` |
| Docker Desktop (Windows) | Runs the engine; mounts `docker.sock` into WSL | `/var/run/docker.sock` does not exist |

After a Docker Desktop restart or update, it tries to reconnect to distros that are **already running**. If the vsock bridge is stale — common after sleep/hibernate, a crashed `lando start`, or a partial recovery — Docker cannot inject `docker.sock` **and** PowerShell calls from WSL time out.

Docker Desktop's **Restart WSL integration** button only retries Docker's side; it does **not** reset vsock state inside your running Ubuntu distro.

#### Why Lando says Docker is not running

`lando start` runs two phases:

1. **Lando internal setup** — build engine, development CA, Landonet. On WSL2 this needs WSL ↔ Windows interop (vsock).
2. **Start app containers** — only runs if phase 1 succeeds.

When phase 1 fails, Lando reports Docker is not running even though `docker info` works. **Docker is OK; Lando setup failed.**

Lando on WSL is a **hybrid** — your terminal is Linux, but setup calls Windows binaries through vsock:

| What you run | Where it runs |
|---|---|
| `lando` CLI, `docker` CLI | Ubuntu (WSL) |
| Docker engine | Docker Desktop (Windows) |
| Lando build engine, CA install | Windows (`.exe` + PowerShell via interop) |

**lenv cannot disable this** — it is not controlled by `.lando.yml` or project templates.

#### Recovery ladder

Work through these levels **in order**. Re-run `lenv doctor` after Level 2 and Level 4 before starting Lando.

**Level 1: Project cleanup** (WSL, from the project directory)

```bash
lenv doctor
lenv fix                 # removes orphan .exe, runs lando update, then lando start
```

Or manually:

```bash
find . -maxdepth 1 -name '*.exe' -delete   # not rm -f *.exe — names can start with -
lando update
lando start
```

**Level 2: Reset WSL** (Windows PowerShell or CMD, not inside Ubuntu)

```powershell
wsl --shutdown
```

Wait 5–10 seconds, reopen WSL, then verify:

```bash
lenv doctor             # Docker and PowerShell should both be ✔
docker info             # optional
lando start             # or lenv fix for cleanup + lando update on WSL2
```

**Level 3: Docker Desktop WSL integration**

Only if `lenv doctor` still shows ✖ Docker after Level 2.

Docker Desktop → **Settings** → **Resources** → **WSL Integration** → enable **Ubuntu** → **Apply & Restart**

Then from WSL:

```bash
lenv doctor
docker info
lando start
```

**Level 4: Deep recovery script**

Only if `lenv doctor` still shows ✖ PowerShell after Level 2 (e.g. `powershell.exe` hangs or times out).

A normal `wsl --shutdown` stops distros but does not restart the `LxssManager` service, update the WSL kernel, or re-apply `/etc/wsl.conf`.

Copy **both** scripts from the lenv repo to the same Windows folder (e.g. Desktop) — they must stay together:

- `fix-wsl-interop.bat` — **double-click** and accept UAC (Administrator)
- `fix-wsl-interop.ps1` — called by the `.bat`

```
\\wsl$\Ubuntu\home\<you>\Dev\tools\lenv\scripts\windows\
```

If your distro is not named `Ubuntu`, run from **Command Prompt as Administrator**:

```cmd
cd C:\Users\<you>\Desktop
fix-wsl-interop.bat -Distro Ubuntu
```

The script: shuts down WSL and Docker → restarts `LxssManager` → `wsl --update` → writes `/etc/wsl.conf` with interop enabled → shuts down WSL again → starts Docker Desktop.

Wait ~60 seconds for Docker Desktop, then in WSL:

```bash
powershell.exe -NoProfile -Command "Write-Output ok"
lenv doctor
lenv fix                # or lando start if everything is healthy
```

**Level 5: Stale Docker networks**

Only after `lenv doctor` passes and errors mention `network not found` or a disconnected proxy.

```bash
docker rm -f landoproxyhyperion5000gandalfedition_proxy_1
docker network prune -f
lando start
```

If a WSL crash left many orphan containers:

```bash
docker rm -f $(docker ps -aq) 2>/dev/null || true   # removes ALL containers
docker network prune -f
find . -maxdepth 1 -name '*.exe' -delete
lando update
lando start
```

> To keep containers from other projects, remove only the specific ones instead of `docker ps -aq`.

**Level 6: Debug**

```bash
lando setup -vvv
```

See [Lando logs docs](https://docs.lando.dev/help/logs.html).

#### Keep Lando in sync after Docker Desktop updates

After every Docker Desktop update, run `lando update` before `lando start`. A version mismatch can trigger build-engine reinstall failures even when interop is healthy.

#### References

- [Lando on WSL — official install guide](https://docs.lando.dev/install/wsl.html)
- [Lando build engine on WSL (lando/core#308)](https://github.com/lando/core/issues/308)
- [Could not automatically start Docker on WSL2 (lando/lando#3604)](https://github.com/lando/lando/issues/3604)
- [UtilAcceptVsock / WSL interop failures (lando/core#315)](https://github.com/lando/core/issues/315)

### Random `.exe` files in the project directory

Symptom: after a failed `lando start`, random `.exe` files (~500 MB each) appear in the project root:

```
hDR32oQ_MSGxN4nlYtsHe.exe
K58YAZfWBRjnr_zB_06NR.exe
```

**What they are:** partial downloads of Lando's Windows build engine from a failed `setup-build-engine` step — usually caused by broken WSL interop (see [Recovery ladder](#recovery-ladder) above). Each failed attempt can leave another copy. They are harmless, not malware — do not commit them to git (add `*.exe` to your own `.gitignore` if needed).

**Clean up:** `find . -maxdepth 1 -name '*.exe' -delete` or run `lenv fix`.

### `lando start` hangs after starting the proxy

Symptom: output stops at `✔ Container landoproxyhyperion5000gandalfedition_proxy_1 Started` for a long time.

Common on the first start after reboot or a Docker Desktop update while integration is still initializing. **Wait 1–2 minutes.**

If it does not recover:

1. `Ctrl+C`
2. `lando update`
3. `lando start`

If interop may be broken, run `lenv doctor` and follow the [Recovery ladder](#recovery-ladder).

### Proxy fails to start on `lando start`

Symptom: `Lando was not able to start the proxy`, `network not found`, or `container is not connected to network`.

Stale Lando proxy container referencing a deleted network. After `lenv doctor` passes, run **Level 5: Stale Docker networks** in the [Recovery ladder](#recovery-ladder).

### WSL2 resource limits (`.wslconfig` on Windows)

Optional **one-time setup** on Windows — not part of the recovery ladder above and **not** a fix for broken interop.

By default, WSL2 can grow to use most of your RAM. With Docker Desktop and several Lando containers, that can make Windows sluggish or contribute to failed starts. Setting limits in `.wslconfig` defines **maximums** for the WSL2 VM so Windows and Docker Desktop keep headroom.

**Do not confuse with `/etc/wsl.conf`** inside Ubuntu — that file controls interop and distro settings (see Level 4 recovery script). **`.wslconfig` lives on Windows** and applies to the WSL2 virtual machine globally.

**File:** `C:\Users\<you>\.wslconfig` (create it if missing)

#### Ceilings, not fixed reservations

| Setting | What it means |
|---|---|
| `memory` | **Maximum** RAM the WSL2 VM may grow to. WSL allocates on demand and can release idle memory back to Windows — it does not lock the full amount at startup. |
| `processors` | **Maximum** logical CPUs available to WSL2. Cores are used when workloads need them, not pegged at 100% constantly. |
| `swap` | Maximum swap space **inside** the WSL2 VM if RAM fills up. |

Under load (`lando rebuild`, database imports, Composer), WSL can approach these limits and compete with Windows and Docker Desktop. The limits prevent WSL from consuming the entire machine; they do not guarantee Windows always has enough — pick values that fit your **host RAM**.

#### Suggested values by host RAM

Check host RAM in Windows: Task Manager → **Performance** → **Memory**, or Settings → System → About.

| Host RAM (Windows) | Suggested `.wslconfig` | Notes |
|---|---|---|
| **8 GB** | `memory=4GB`, `processors=2`, `swap=4GB` | Tight machine — prioritize Windows + Docker Desktop. |
| **12 GB** | `memory=6GB`, `processors=3`, `swap=4GB` | Balanced middle ground. |
| **16 GB+** | `memory=8GB`, `processors=4`, `swap=4GB` | Comfortable for Lando with several containers. |
| **32 GB+** | `memory=8–12GB`, `processors=4–6`, `swap=4GB` | Raise `memory` only if `lando rebuild` hits OOM and Windows still has spare RAM. |

**Rule of thumb:** host RAM ≈ Windows (~4 GB) + Docker Desktop (~2–4 GB) + WSL (`memory` ceiling). If the total exceeds what you have, lower `memory` or `processors`.

Copy-paste templates in the lenv repo:

- `scripts/windows/wslconfig.example` — 16 GB+ hosts (8 / 4 / 4)
- `scripts/windows/wslconfig.example-small` — 8–12 GB hosts (4 / 2 / 4)

Example for a **16 GB+** machine:

```ini
[wsl2]
memory=8GB
processors=4
swap=4GB
```

Example for an **8–12 GB** machine:

```ini
[wsl2]
memory=4GB
processors=2
swap=4GB
```

#### Apply

1. Save the file on Windows (Notepad is fine).
2. From **PowerShell or CMD on Windows**:
   ```powershell
   wsl --shutdown
   ```
3. Reopen WSL. Verify **ceilings** from Ubuntu (`free -h` shows current use, not necessarily the max):
   ```bash
   free -h
   nproc
   ```

**When to change:** lower `memory` if Docker Desktop or Windows itself runs out of RAM. Raise it only if containers are OOM-killed during `lando rebuild` and the host still has spare RAM. Lower `processors` if builds make Windows unusable; raise it if compiles are CPU-bound and the host has cores to spare.
