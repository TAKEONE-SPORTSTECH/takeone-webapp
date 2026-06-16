@echo off
echo ========================================
echo Affiliations Enhancement Setup
echo ========================================
echo.

echo Step 1: Running migrations...
php artisan migrate
if %errorlevel% neq 0 (
    echo ERROR: Migration failed!
    pause
    exit /b %errorlevel%
)
echo ✓ Migrations completed successfully
echo.

echo Step 2: Seeding affiliations data...
php artisan db:seed --class=AffiliationsDataSeeder
if %errorlevel% neq 0 (
    echo ERROR: Seeding failed!
    pause
    exit /b %errorlevel%
)
echo ✓ Seeding completed successfully
echo.

echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo You can now view the enhanced affiliations tab at:
echo http://127.0.0.1:8000/profile
echo.
echo Click on the "Affiliations" tab to see:
echo - Club membership timeline
echo - Skills gained with instructor information
echo - Package history
echo - Cross-club skill progression
echo.
pause
