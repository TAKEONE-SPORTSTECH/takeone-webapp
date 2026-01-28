#!/bin/bash
# Clear all Laravel caches

php artisan optimize:clear
# Or, if you prefer explicit commands:
# php artisan cache:clear
# php artisan config:clear
# php artisan route:clear
# php artisan view:clear
