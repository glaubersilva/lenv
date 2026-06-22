# Windows scripts for lenv / WSL2

Scripts to run **on Windows** (not inside WSL) when `lenv doctor` reports broken WSL ↔ Windows interop.

## When to use

Use the fix scripts when:

- `lenv doctor` shows **✖ PowerShell:** `WSL ↔ Windows interop broken (UtilAcceptVsock)`
- `powershell.exe -NoProfile -Command "Write-Output ok"` from WSL hangs or fails
- `wsl --shutdown` from Windows did **not** restore interop

Typical triggers: Docker Desktop restart/update, Windows update, sleep/hibernate, or a crashed `lando start`.

## fix-wsl-interop.bat + fix-wsl-interop.ps1

The `.bat` file is the **recommended entry point**. It requests Administrator elevation (UAC) and runs the PowerShell recovery script next to it.

Full recovery (via the `.ps1`): stops WSL and Docker, restarts the `LxssManager` service, runs `wsl --update`, writes `/etc/wsl.conf` with interop enabled, applies the config, and starts Docker Desktop again.

### How to run (recommended)

1. Copy **both files** to the same folder on Windows (e.g. Desktop). They must stay together — the `.bat` calls the `.ps1` beside it:
   - `fix-wsl-interop.bat`
   - `fix-wsl-interop.ps1`

   From your lenv clone via Explorer:
   ```
   \\wsl$\Ubuntu\home\<you>\Dev\tools\lenv\scripts\windows\
   ```

2. **Double-click `fix-wsl-interop.bat`** and accept the UAC prompt (Administrator).

3. Wait for the script to finish and for Docker Desktop to become ready (~60 seconds).

4. In WSL, verify and start your project:
   ```bash
   powershell.exe -NoProfile -Command "Write-Output ok"
   lenv doctor
   cd ~/Dev/sites/<project> && lenv fix
   ```

### Non-default WSL distro

If your distro is not named `Ubuntu`, pass `-Distro` when launching the `.bat` from an elevated Command Prompt:

```cmd
fix-wsl-interop.bat -Distro Debian
```

Or run the `.ps1` directly in **PowerShell as Administrator**:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
& 'C:\Users\<you>\Desktop\fix-wsl-interop.ps1' -Distro Debian
```

### What it changes

- Restarts Windows WSL services (no data loss in your distro)
- Ensures `/etc/wsl.conf` contains:
  ```ini
  [interop]
  enabled=true
  appendWindowsPath=true
  ```
- Restarts Docker Desktop

It does **not** modify lenv projects or Lando configuration.

## wslconfig.example / wslconfig.example-small

Optional WSL2 **resource ceilings** for Windows — maximum RAM, CPU, and swap the WSL2 VM may use. WSL grows on demand up to these limits; it does not reserve the full amount at startup.

Helps when Docker Desktop + Lando containers compete with Windows for memory. **Not** a fix for broken interop. **Not** the same as `/etc/wsl.conf` inside Ubuntu (interop — see `fix-wsl-interop.ps1`).

**File on Windows:** `C:\Users\<you>\.wslconfig`

| Template | Host RAM | Values |
|---|---|---|
| `wslconfig.example` | 16 GB+ | 8 GB / 4 CPUs / 4 GB swap |
| `wslconfig.example-small` | 8–12 GB | 4 GB / 2 CPUs / 4 GB swap |

1. Copy the template that matches your machine to `C:\Users\<you>\.wslconfig` and adjust if needed.
2. Run `wsl --shutdown` from Windows, then reopen WSL.

See `templates/docs/troubleshooting.md` → **WSL2 resource limits** for the full table and tuning notes.

## See also

- `templates/docs/troubleshooting.md` in the lenv repo — full WSL2 troubleshooting guide (copied to each project's `docs/troubleshooting.md`)
