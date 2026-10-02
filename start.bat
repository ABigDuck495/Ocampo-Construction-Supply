@echo off
title Start Laravel Backend
echo ===================================================
echo   Starting Laravel Docker Backend...
echo ===================================================

:: Ensure Docker Desktop is active
docker info >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [!] Starting Docker Desktop...
    start "" "C:\Program Files\Docker\Docker\Docker Desktop.exe"
    timeout /t 20 /nobreak >nul
)

:: The image should only be built once when the project is first created.
:: After that, reuse the existing image and do not trigger a rebuild on every launch.
docker compose ps -q app >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [+] App container not found. Building image for first run...
    docker compose up -d --build
) else (
    echo [+] App container already exists. Reusing the existing image...
    docker compose up -d --no-build --no-recreate
)

echo.
echo [+] Server online at http://localhost:8000
echo.
pause
