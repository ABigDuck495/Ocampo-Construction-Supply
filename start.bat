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

:: Start services
docker compose up -d

echo.
echo [+] Server online at http://localhost:8000
echo.
pause
