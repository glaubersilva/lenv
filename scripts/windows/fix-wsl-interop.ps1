# WSL interop recovery — run as Administrator (right-click → Run as administrator)
# Part of lenv: https://github.com/glaubersilva/lenv
# Fixes persistent UtilAcceptVsock / PowerShell interop failures when `wsl --shutdown` alone is not enough.

param(
    [string]$Distro = 'Ubuntu'
)

$ErrorActionPreference = 'Stop'

Write-Host ''
Write-Host '=== WSL Interop Recovery ===' -ForegroundColor Cyan
Write-Host "Distro: $Distro" -ForegroundColor Cyan
Write-Host ''

# 1. Stop WSL and Docker
Write-Host '[1/6] Shutting down WSL and Docker Desktop...' -ForegroundColor Yellow
wsl --shutdown 2>$null
Stop-Process -Name 'Docker Desktop' -Force -ErrorAction SilentlyContinue
Stop-Process -Name 'com.docker.backend' -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 5

# 2. Restart LxssManager (WSL service)
Write-Host '[2/6] Restarting LxssManager service...' -ForegroundColor Yellow
$lxss = Get-Service -Name 'LxssManager' -ErrorAction SilentlyContinue
if ($lxss) {
    if ($lxss.Status -eq 'Running') {
        Restart-Service LxssManager -Force
    } else {
        Start-Service LxssManager
    }
    Write-Host '  LxssManager: OK' -ForegroundColor Green
} else {
    Write-Host '  LxssManager not found — skipping' -ForegroundColor DarkYellow
}
Start-Sleep -Seconds 5

# 3. Update WSL
Write-Host '[3/6] Updating WSL...' -ForegroundColor Yellow
wsl --update 2>&1 | ForEach-Object { Write-Host "  $_" }
Start-Sleep -Seconds 3

# 4. Apply wsl.conf inside the distro (interop enabled)
Write-Host "[4/6] Writing /etc/wsl.conf in $Distro..." -ForegroundColor Yellow
$wslConf = @'
[interop]
enabled=true
appendWindowsPath=true
'@
$wslConf | wsl -d $Distro -u root -- tee /etc/wsl.conf > $null
Write-Host '  /etc/wsl.conf: OK' -ForegroundColor Green

# 5. Shutdown again so wsl.conf takes effect
Write-Host '[5/6] Applying wsl.conf (wsl --shutdown)...' -ForegroundColor Yellow
wsl --shutdown
Start-Sleep -Seconds 8

# 6. Start Docker Desktop
Write-Host '[6/6] Starting Docker Desktop...' -ForegroundColor Yellow
$dockerExe = 'C:\Program Files\Docker\Docker\Docker Desktop.exe'
if (Test-Path $dockerExe) {
    Start-Process $dockerExe
    Write-Host '  Docker Desktop starting — wait until the tray icon is ready.' -ForegroundColor Green
} else {
    Write-Host '  Docker Desktop not found at default path — start it manually.' -ForegroundColor DarkYellow
}

Write-Host ''
Write-Host '=== Done ===' -ForegroundColor Green
Write-Host 'Wait ~60s for Docker Desktop, then in WSL run:' -ForegroundColor Cyan
Write-Host '  powershell.exe -NoProfile -Command "Write-Output ok"' -ForegroundColor White
Write-Host '  lenv doctor' -ForegroundColor White
Write-Host '  cd <your-project> && lenv fix' -ForegroundColor White
Write-Host ''
Read-Host 'Press Enter to close'
