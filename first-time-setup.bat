@echo off
title Laravel Docker First-Time Setup
echo ===================================================
echo   Laravel Docker Setup for Fresh Installations
echo ===================================================
echo.

:: 1. Check if Docker Desktop is running
docker info >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [!] Docker is not running. Starting Docker Desktop...
    start "" "C:\Program Files\Docker\Docker\Docker Desktop.exe"
    echo [!] Waiting 25 seconds for Docker engine to initialize...
    timeout /t 25 /nobreak >nul
)

:: 2. Create .env file if it doesn't exist
if not exist .env (
    echo [+] Creating .env file from .env.example...
    copy .env.example .env
) else (
    echo [*] .env file already exists.
)

:: 3. Run temporary Docker container to install composer dependencies
echo.
echo [+] Installing Composer dependencies via Docker (this may take 2-3 minutes)...
docker run --rm -v "%CD%:/var/www/html" -w /var/www/html laravelsail/php83-composer:latest composer install --ignore-platform-reqs

:: 4. Start Docker Containers
echo.
echo [+] Launching Docker containers in background...
docker compose up -d

:: 5. Generate Application Key and Migrate Database
echo.
echo [+] Generating Laravel Application Key...
docker compose exec -T laravel.test php artisan key:generate

echo [+] Running Database Migrations and Seeders...
docker compose exec -T laravel.test php artisan migrate:fresh --seed

echo.
echo ===================================================
echo   SETUP COMPLETE!
echo   Laravel is running at: http://localhost:8000
echo   Android Emulator API:  http://10.0.2.2:8000/api
echo   Physical Phone API:    http://YOUR_LAPTOP_IP:8000/api
echo ===================================================
echo.
pause
