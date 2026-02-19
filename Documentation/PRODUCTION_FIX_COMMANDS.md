# Production Server - Manual Fix Commands

## Current Issue
The package `takeone/cropper` is not installed on the production server. The `composer.json` was updated but `composer install` needs to be run.

## Run These Commands on Production Server (in order):

### 1. Clear Bootstrap Cache (Important!)
```bash
rm -f bootstrap/cache/services.php
rm -f bootstrap/cache/packages.php
```

### 2. Clear Composer Cache
```bash
composer clear-cache
```

### 3. Install Dependencies
```bash
composer install --no-scripts
```

### 4. If Step 3 Fails, Try This Instead
```bash
composer require takeone/cropper:dev-main --no-scripts
```

### 5. Regenerate Autoload
```bash
composer dump-autoload
```

### 6. Fix Namespace in Vendor File
```bash
# Check if file exists first
ls -la vendor/takeone/cropper/src/CropperServiceProvider.php

# If it exists, fix the namespace (Linux/Mac)
sed -i 's/\\takeone\\cropper\\Http\\Controllers\\ImageController/\\Takeone\\Cropper\\Http\\Controllers\\ImageController/g' vendor/takeone/cropper/src/CropperServiceProvider.php
```

**OR manually edit the file:**
- File: `vendor/takeone/cropper/src/CropperServiceProvider.php`
- Line 24: Change `\takeone\cropper\Http\Controllers\ImageController::class`
- To: `\Takeone\Cropper\Http\Controllers\ImageController::class`

### 7. Clear All Laravel Caches
```bash
php artisan optimize:clear
```

### 8. Discover Packages
```bash
php artisan package:discover --ansi
```

### 9. Verify Everything Works
```bash
php artisan about
```

### 10. Run Migrations (if needed)
```bash
php artisan migrate
```

## Alternative: If composer install keeps failing

If you get errors about the package not being found, try this sequence:

```bash
# 1. Remove any stale lock entries
composer remove takeone/cropper --no-scripts --no-update

# 2. Clear everything
rm -f bootstrap/cache/*.php
composer clear-cache

# 3. Update composer.lock
composer update --lock

# 4. Install fresh
composer install

# 5. If still failing, force require
composer require takeone/cropper:dev-main
```

## Quick One-Liner (Copy-Paste All at Once)

```bash
rm -f bootstrap/cache/services.php bootstrap/cache/packages.php && \
composer clear-cache && \
composer install --no-scripts && \
composer dump-autoload && \
php artisan optimize:clear && \
php artisan package:discover --ansi && \
php artisan about
```

Then manually fix the namespace in the vendor file if needed.

## Troubleshooting

### Error: "Failed to open stream: No such file or directory"
**Cause**: Package not installed
**Solution**: Run `composer install` or `composer require takeone/cropper:dev-main`

### Error: "Could not find a version of package takeone/cropper"
**Cause**: Wrong version constraint
**Solution**: Make sure composer.json has `"takeone/cropper": "dev-main"` (not `@dev`)

### Error: "Class 'Takeone\Cropper\CropperServiceProvider' not found"
**Cause**: Namespace issue or cache problem
**Solution**: 
1. Fix namespace in vendor file
2. Clear all caches
3. Run `composer dump-autoload`
