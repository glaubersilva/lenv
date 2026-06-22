# Troubleshooting

> **Windows + WSL2:** Most issues below are specific to running Lando inside WSL2 with Docker Desktop on Windows. This combination is [officially supported by Lando](https://docs.lando.dev/install/wsl.html), but WSL2 ↔ Windows interop is fragile — especially after Docker Desktop or Windows updates. macOS users can skip the WSL-specific sections.

---

## Conflict with LocalWP

Lando's Traefik proxy binds ports 80 and 443, which prevents LocalWP from starting its own Nginx.

- To work in LocalWP: run `lando poweroff` first
- To return to this project: stop all LocalWP sites and quit the app, then `lando start`

> **Important:** Before running `lando start` or `lando rebuild`, stop all sites in LocalWP and quit the app. If LocalWP is running during startup, the port conflict can interfere with Lando's initialization.

---

## `Could not automatically start Docker` but Docker Desktop is running

This is one of the **most common Lando + WSL2 problems**. Docker Desktop may show containers running normally while `lando start` fails with:

```
WSL ERROR: UtilAcceptVsock:273: accept4 failed 110
setup-build-engine FAILED
Could not automatically start Docker. Please manually start it to continue.
```

**What is actually happening:** `lando start` runs two separate phases:

1. **Lando internal setup** — installs/updates the build engine, development CA, and Landonet. On WSL2 this requires WSL ↔ Windows communication (vsock).
2. **Start app containers** — only runs if phase 1 succeeds.

When phase 1 fails, Lando reports that Docker is not running — even though `docker info` works fine from the same terminal and other containers are already up. **Docker is OK; Lando setup failed.**

### Common causes

| Cause | Why it happens |
|---|---|
| WSL2 vsock timeout | Temporary loss of WSL ↔ Windows connectivity (`accept4 failed 110` = connection timed out) |
| Lando / Docker Desktop version mismatch | After a Docker Desktop update, Lando tries to reinstall the build engine and the download fails |
| WSL integration disabled | Docker Desktop reset integration settings after an update — Docker works from the GUI but WSL interop breaks |
| Stale Docker networks/containers | Interrupted `lando start` left orphan state from a previous WSL crash |

### Quick fix (try in order)

**Step 1 — Clean up and sync versions** (from the project directory):

```bash
rm -f *.exe          # remove partial build-engine downloads (see section below)
lando update
lando start
```

**Step 2 — Reset WSL** (run in **PowerShell or CMD on Windows**, not inside WSL):

```powershell
wsl --shutdown
```

Wait a few seconds, reopen your WSL terminal, confirm Docker responds:

```bash
docker info
```

Then run `lando start` again.

**Step 3 — Prune stale Docker networks:**

```bash
docker network prune -f
lando start
```

**Step 4 — Verify Docker Desktop WSL integration:**

Docker Desktop → **Settings** → **Resources** → **WSL Integration** → enable **Ubuntu** → **Apply & Restart**

Then from WSL:

```bash
docker info
lando start
```

**Step 5 — If it still fails**, run setup in debug mode and check the [Lando logs docs](https://docs.lando.dev/help/logs.html):

```bash
lando setup -vvv
```

### References

- [Lando on WSL — official install guide](https://docs.lando.dev/install/wsl.html)
- [Lando build engine on WSL (lando/core#308)](https://github.com/lando/core/issues/308) — explains why Lando installs a build engine on WSL and the `setup-build-engine` step
- [Could not automatically start Docker on WSL2 (lando/lando#3604)](https://github.com/lando/lando/issues/3604) — same misleading error with Docker actually running
- [UtilAcceptVsock / WSL interop failures (lando/core#315)](https://github.com/lando/core/issues/315) — WSL2 connectivity issues during Lando setup

---

## Keep Lando and Docker Desktop in sync

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

See also: [`Could not automatically start Docker` but Docker Desktop is running](#could-not-automatically-start-docker-but-docker-desktop-is-running) above.

---

## Random `.exe` files appearing in the project directory

> **Windows + WSL2 only.** On macOS the build engine is a Unix binary and this does not apply.

Symptom: after a failed `lando start`, files with random names and `.exe` extension appear in the project root, e.g.:

```
hDR32oQ_MSGxN4nlYtsHe.exe   (~500 MB)
K58YAZfWBRjnr_zB_06NR.exe   (~500 MB)
```

**What they are:** Lando downloads a Windows build engine binary (~500 MB) during its internal setup step (`setup-build-engine`). When the download fails — usually due to the WSL `UtilAcceptVsock` error described above — the partial file is left in the **current working directory** (your project folder). Each failed attempt creates another orphan file with a random name. They are identical copies of the same binary, not malware.

**They are harmless** but are a clear signal that `setup-build-engine` is failing. Do not commit them — add `*.exe` to `.gitignore` if needed.

**Clean up:**

```bash
rm -f *.exe
```

**Fix the underlying cause:** follow the [quick fix steps](#quick-fix-try-in-order) above, starting with `lando update` and `wsl --shutdown`.

---

## `lando start` hangs after starting the proxy

Symptom: the output stops at `✔ Container landoproxyhyperion5000gandalfedition_proxy_1 Started` and nothing else happens for a long time.

This can happen on the first start after a reboot or after a Docker Desktop update, while Docker Desktop is still fully initializing its WSL integration. **Wait 1–2 minutes** — it usually resolves on its own.

If it doesn't recover after 2 minutes:
1. Cancel with `Ctrl+C`
2. Run `lando update` to ensure Lando is up to date
3. Run `lando start` again

---

## Proxy fails to start on `lando start`

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

## WSL crash + `network not found` during `lando start`

Symptom: `lando start` fails with two errors together:

```
WSL ERROR: UtilAcceptVsock: accept4 failed 110
```
```
Error response from daemon: failed to set up container networking: network <id> not found
```

**Cause:** WSL2 lost connectivity mid-operation. Docker was left with orphan containers and stale networks from the interrupted start.

**Fix:**

1. Shut down WSL to reset the connection (**PowerShell/CMD on Windows**):
```powershell
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

4. Clean up any orphan `.exe` files in the project directory:
```bash
rm -f *.exe
```

5. Run `lando update`, then `lando start` again.

> Note: `docker rm -f $(docker ps -aq)` removes **all** containers. If you have containers from other projects you want to keep, remove only the specific ones (e.g. `mysite_appserver_1`, `mysite_database_1`, and `landoproxyhyperion5000gandalfedition_proxy_1`).

---

## Docker Desktop WSL integration not working

Symptom: `lando start` fails with `Could not automatically start Docker` or `failed to connect to the docker API at unix:///var/run/docker.sock`, even though Docker Desktop is open and shows running containers.

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
