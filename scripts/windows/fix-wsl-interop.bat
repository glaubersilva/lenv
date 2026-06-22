@echo off
:: WSL interop recovery — double-click this file, accept UAC (Administrator), wait for completion.
:: Part of lenv: https://github.com/glaubersilva/lenv
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0fix-wsl-interop.ps1" %*
