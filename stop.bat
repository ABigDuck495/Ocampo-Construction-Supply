@echo off
title Stop Laravel Backend
echo ===================================================
echo   Stopping Laravel Docker Containers...
echo ===================================================
echo.

:: 1. Navigate to the script's directory
cd /d "%~dp0"

:: 2. Check if Docker is active
docker info >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [!] Docker is not currently running or engine is stopped.
    goto END
)

:: 3. Gracefully stop containers
echo [+] Stopping containers cleanly...
docker compose stop

echo.
echo ===================================================
echo   All containers stopped successfully.
echo   Database and file changes are preserved.
echo ===================================================

:END
echo.
pause
