# CropperServiceProvider Production Server Fix Guide

## Problem
The error `Class "Takeone\Cropper\CropperServiceProvider" not found` occurs on the production server when trying to install/reinstall the `takeone/cropper` package.

## Root Causes
1. **Composer.json Issue**: Package was set to `@dev` instead of `dev-main`, causing conflicts with `minimum-stability: stable`
2. **Namespace Case Sensitivity**: The service provider had a lowercase namespace reference that needs to be fixed
3. **Cached Bootstrap Files**: Stale cache files referencing the old service provider

## Solution Steps for Production Server

### Step 1: Update composer.json (Already Done Locally)
The `composer.json` has been updated to change:
- From: `"takeone/cropper": "@dev"`
- To: `"takeone/cropper": "dev-main"`

**Action**: Commit and push this change to your repository, then pull it on the production server.

### Step 2: On Production Server - Clean Installation

Run these commands in order:

```bash
# 1. Clear all Laravel caches first
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# 2. Remove bootstrap cache files
rm -f bootstrap/cache/services.php
rm -f bootstrap/cache/packages.php

# 3. Remove the package completely (if it exists)
composer remove takeone/cropper --no-scripts

# 4. Clear composer cache
composer clear-cache

# 5. Install the package with the correct version
composer require takeone/cropper:dev-main

# 6. If step 5 fails, try updating composer.lock
composer update takeone/cropper

# 7. Regenerate autoload files
composer dump-autoload

# 8. Discover packages
php artisan package:discover --ansi

# 9. Verify installation
php artisan about
```

### Step 3: Fix Namespace Issue in Vendor Package

After successful installation, fix the namespace case sensitivity issue:

**File**: `vendor/takeone/cropper/src/CropperServiceProvider.php`

**Line 24** - Change from:
```php
Route::post('/image-upload', [\takeone\cropper\Http\Controllers\ImageController::class, 'upload'])->name('image.upload');
```

**To**:
```php
Route::post('/image-upload', [\Takeone\Cropper\Http\Controllers\ImageController::class, 'upload'])->name('image.upload');
```

**Note**: This is a vendor file change. Ideally, this should be fixed in the source repository at `https://git.innovator.bh/ghassan/laravel-image-cropper`

### Step 4: Final Verification

```bash
# Clear all caches again
php artisan optimize:clear

# Verify the application works
php artisan about

# Check if the package is discovered
php artisan package:discover --ansi
```

## Alternative: If Installation Still Fails

If the above steps don't work, try this alternative approach:

```bash
# 1. Temporarily allow dev stability
composer config minimum-stability dev
composer config prefer-stable true

# 2. Install the package
composer require takeone/cropper:dev-main

# 3. Restore stability settings
composer config minimum-stability stable

# 4. Update composer
composer update --lock
```

## Permanent Fix Recommendation

**For the Package Maintainer**:
1. Fix the namespace issue in the source repository
2. Create a stable release/tag for the package (e.g., v1.0.0)
3. Update composer.json to use a stable version instead of dev-main

**In composer.json**, change:
```json
"takeone/cropper": "dev-main"
```

**To** (once a stable version is released):
```json
"takeone/cropper": "^1.0"
```

## Troubleshooting

### Error: "Could not find a version of package takeone/cropper matching your minimum-stability"
**Solution**: Use `dev-main` instead of `@dev` in composer.json

### Error: "Class 'Takeone\Cropper\CropperServiceProvider' not found"
**Solution**: 
1. Clear all caches
2. Remove bootstrap cache files
3. Fix namespace in vendor file
4. Run `composer dump-autoload`

### Error: "Concurrent process failed with exit code [1]"
**Solution**: 
1. Don't run as root (if possible)
2. Use `--no-scripts` flag when removing packages
3. Clear bootstrap cache before operations

## Files Modified

1. **composer.json** - Changed package version from `@dev` to `dev-main`
2. **vendor/takeone/cropper/src/CropperServiceProvider.php** - Fixed namespace case sensitivity

## Verification Checklist

- [ ] composer.json updated with `dev-main`
- [ ] Package installed successfully
- [ ] Namespace fixed in CropperServiceProvider.php
- [ ] All caches cleared
- [ ] `php artisan about` runs without errors
- [ ] Package appears in `php artisan package:discover` output
- [ ] Application loads without CropperServiceProvider errors
