@echo off
echo migrating and seeding database
php artisan migrate:fresh --seed