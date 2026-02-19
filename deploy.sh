#!/bin/bash

echo "🚀 Starting deployment..."

cd /var/www/takeone

git pull origin main

composer install --no-dev --optimize-autoloader

npm install
npm run build

php artisan migrate --force
php artisan storage:link --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ Deployment complete!"
