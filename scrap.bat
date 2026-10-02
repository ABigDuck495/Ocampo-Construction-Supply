@echo off
:: Check for Administrative privileges
net session >nul 2>&1
if %errorLevel% NEQ 0 (
    echo ============================================================
    echo [ERROR] This script MUST be run as Administrator!
    echo Right-click 'install-native-env.bat' and select 'Run as administrator'.
    echo ============================================================
    pause
    exit /b
)

title Complete Cleanup and Native Environment Setup
echo ============================================================
echo   1. CLEANING UP DOCKER CONTAINERS AND IMAGES
echo ============================================================
cd /d "%~dp0"

docker compose down --volumes --remove-orphans >nul 2>&1
docker rmi ocampo-construction-supply-main-app:latest >nul 2>&1
docker system prune -f >nul 2>&1
echo [+] Docker resources removed.

echo.
echo ============================================================
echo   2. INSTALLING CHOCOLATEY (PACKAGE MANAGER)
echo ============================================================
where choco >nul 2>&1
if %errorLevel% NEQ 0 (
    echo [+] Installing Chocolatey...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -or 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))"
    set "PATH=%PATH%;%ALLUSERSPROFILE%\chocolatey\bin"
) else (
    echo [*] Chocolatey is already installed.
)

echo.
echo ============================================================
echo   3. INSTALLING PHP, COMPOSER, AND MYSQL
echo ============================================================
echo [+] Installing PHP...
choco install php -y

echo [+] Installing Composer...
choco install composer -y

echo [+] Installing MySQL Server...
choco install mysql -y

:: Refresh environment variables for current session
call RefreshEnv.cmd

echo.
echo ============================================================
echo   4. STARTING MYSQL SERVICE & CREATING DATABASE
echo ============================================================
echo [+] Starting MySQL Service...
net start MySQL >nul 2>&1

echo [+] Setting up database 'ocampo_construction_supply'...
mysql -u root -e "CREATE DATABASE IF NOT EXISTS ocampo_construction_supply;" >nul 2>&1

echo.
echo ============================================================
echo   5. INSTALLING DEPENDENCIES & RUNNING MIGRATIONS
echo ============================================================
if not exist .env (
    if exist .env.docker (
        copy .env.docker .env
    ) else if exist .env.example (
        copy .env.example .env
    )
)

echo [+] Installing composer packages locally...
call composer install

echo [+] Generating application key...
call php artisan key:generate

echo [+] Running migrations...
call php artisan migrate:fresh --seed

echo.
echo ============================================================
echo   INSTALLATION COMPLETE!
echo   You can now close this window and use 'run-local.bat'.
echo ============================================================
pausems:http://System.Net