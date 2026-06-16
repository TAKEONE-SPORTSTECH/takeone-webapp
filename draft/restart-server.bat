@echo off
echo ========================================
echo Restarting Laravel Development Server
echo ========================================
echo.

echo Step 1: Clearing all caches...
call php artisan route:clear
call php artisan config:clear
call php artisan cache:clear
call php artisan view:clear
echo Caches cleared successfully!
echo.

echo Step 2: Optimizing application...
call php artisan config:cache
call php artisan route:cache
echo Optimization complete!
echo.

echo Step 3: Starting development server...
echo Server will start at http://127.0.0.1:8000
echo Press Ctrl+C to stop the server
echo.
call php artisan serve --host=127.0.0.1 --port=8000
