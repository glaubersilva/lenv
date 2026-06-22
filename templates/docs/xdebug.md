# Xdebug Setup Guide

## Prerequisites

- Lando running (`lando start`)

---

## PhpStorm

### Configuration

**Step 1** — Open **Settings** -> **PHP** -> **Servers** and create a new server:

| Field | Value |
|-------|-------|
| Name | `__PROJECT_NAME__` |
| Host | `__PROJECT_NAME__.lndo.site` |
| Port | `443` |
| Debugger | `Xdebug` |

Enable **"Use path mappings"** and map the project root to `/app`:

| Local path | Remote path |
|---|---|
| `__PROJECT_IDE_PATH__` | `/app` |

> The server name **must match** the `PHP_IDE_CONFIG` value set in the project's `.lando.yml`.

**Step 2** — In **Settings** -> **PHP** -> **Debug**, confirm:
- Xdebug **Debug port**: `9003,9000`
- **"Can accept external connections"**: checked

**Step 3** — Start listening by clicking the **"Start Listening for PHP Debug Connections"** phone icon in the toolbar.

**Step 4** — Set breakpoints and open the page in the browser — execution will stop at the breakpoint.

### How it works with WSL2

When Xdebug fires a connection from the container, it targets `host.lando.internal` which resolves to the Windows host gateway. Because PhpStorm runs natively on Windows, it listens directly on that interface:

```
Container -> host.lando.internal:9003 (Windows) -> PhpStorm
```

---

## VS Code

### Prerequisites

- [PHP Debug](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug) extension installed

### Configuration

The `"Listen for Xdebug"` configuration in `.vscode/launch.json` must include `pathMappings` so VS Code knows how to map the container file paths to your local files. Without it, the connection works but breakpoints are never hit.

```json
{
    "name": "Listen for Xdebug",
    "type": "php",
    "request": "launch",
    "port": 9003,
    "pathMappings": {
        "/app": "${workspaceFolder}"
    }
}
```

### General usage

1. Open the **Run and Debug** panel (`Ctrl+Shift+D`)
2. Select **"Listen for Xdebug"** and click play (or `F5`)
3. Set breakpoints and open the page in the browser

### WSL2

#### The problem

With the WSL2 backend, Xdebug connects to the Windows host at `192.168.65.254`. But VS Code's PHP Debug extension (running via Remote WSL) listens on the WSL2 network, not on Windows. The connection silently fails and breakpoints don't work.

```
Docker container -> Windows host (192.168.65.254:9003) -> WSL2 -> VS Code
```

Docker containers **cannot route directly to the WSL2 IP** (`172.x.x.x`), only to the Windows host. A **port proxy on Windows** is required to forward port `9003` from Windows to WSL2.

#### Setup

> **Note**: requires **PowerShell as Administrator on Windows**.

**Step 1** — Open PowerShell as Administrator (Win -> search PowerShell -> Run as administrator).

**Step 2** — Paste and run (auto-detects the WSL2 IP):

```powershell
$wslIP = (wsl hostname -I).Trim().Split(' ')[0]
netsh interface portproxy add v4tov4 listenport=9003 listenaddress=0.0.0.0 connectport=9003 connectaddress=$wslIP
netsh advfirewall firewall add rule name="Xdebug WSL2 Port 9003" dir=in action=allow protocol=TCP localport=9003
netsh interface portproxy show v4tov4
```

#### When the WSL2 IP changes

The WSL2 IP may change after restarting Windows. In PowerShell as Administrator:

```powershell
$wslIP = (wsl hostname -I).Trim().Split(' ')[0]
netsh interface portproxy delete v4tov4 listenport=9003 listenaddress=0.0.0.0
netsh interface portproxy add v4tov4 listenport=9003 listenaddress=0.0.0.0 connectport=9003 connectaddress=$wslIP
```

#### Remove the port proxy

If switching from VS Code to PhpStorm, **remove the proxy** — it interferes with PhpStorm's connection:

```powershell
netsh interface portproxy delete v4tov4 listenport=9003 listenaddress=0.0.0.0
netsh advfirewall firewall delete rule name="Xdebug WSL2 Port 9003"
```

---

## Enabling and disabling Xdebug

Xdebug slows PHP down. **Default for new lenv projects:** `off`.

### Persistent setting (survives rebuild)

```bash
lenv update
lando rebuild -y
```

**Apache / Nginx / LiteSpeed** — `config.xdebug` in `.lando.yml`:

```yaml
config:
  xdebug: off
  xdebug: debug
```

**FrankenPHP** — `# lenv-xdebug:` in `.lando.yml` (managed by `lenv new` / `lenv update`).

### Runtime toggle (no rebuild)

```bash
lenv xdebug on
lenv xdebug off
lenv xdebug status
```

Equivalent Lando commands (run inside the container):

```bash
lando xdebug-on
lando xdebug-off
```

Use `lenv xdebug status` to compare the configured (`.lando.yml`) and runtime (container) state:

```
Xdebug:
  configured:  off
  runtime:     disabled
```

When Lando is not running, runtime shows `n/a — lando not running`.

On FrankenPHP, the extension is installed in the image — `off` disables it at container start, but a previous `lenv xdebug on` lasts until restart or `lenv xdebug off`.

---

## Troubleshooting

### Breakpoints not being hit

1. Confirm Xdebug is loaded:
```bash
lando ssh -c "php -r \"echo extension_loaded('xdebug') ? 'OK' : 'NOT loaded';\""
```

2. Confirm `start_with_request` is `yes`:
```bash
lando ssh -c "php -i | grep start_with_request"
```

3. Test connectivity from the container to the IDE:
```bash
lando ssh -c "bash -c 'timeout 2 bash -c \"echo > /dev/tcp/host.lando.internal/9003\" && echo CONNECTED || echo FAILED'"
```

### Xdebug not loading after rebuild

```bash
lando rebuild -y
```

If still not working:

```bash
lando restart
```
