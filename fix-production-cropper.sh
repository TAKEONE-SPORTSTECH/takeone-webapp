#!/bin/bash

# CropperServiceProvider Production Fix Script
# Run this script on the production server to fix the cropper package issue

echo "=========================================="
echo "CropperServiceProvider Production Fix"
echo "=========================================="
echo ""

# Step 1: Clear all Laravel caches
echo "Step 1: Clearing Laravel caches..."
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan optimize:clear 2>/dev/null || true
echo "✓ Caches cleared"
echo ""

# Step 2: Remove bootstrap cache files
echo "Step 2: Removing bootstrap cache files..."
rm -f bootstrap/cache/services.php
rm -f bootstrap/cache/packages.php
echo "✓ Bootstrap cache removed"
echo ""

# Step 3: Clear composer cache
echo "Step 3: Clearing composer cache..."
composer clear-cache
echo "✓ Composer cache cleared"
echo ""

# Step 4: Install the package
echo "Step 4: Installing takeone/cropper package..."
composer require takeone/cropper:dev-main --no-scripts
echo "✓ Package installation attempted"
echo ""

# Step 5: Dump autoload
echo "Step 5: Regenerating autoload files..."
composer dump-autoload
echo "✓ Autoload regenerated"
echo ""

# Step 6: Fix namespace in vendor file
echo "Step 6: Fixing namespace in CropperServiceProvider..."
if [ -f "vendor/takeone/cropper/src/CropperServiceProvider.php" ]; then
    sed -i 's/\\takeone\\cropper\\Http\\Controllers\\ImageController/\\Takeone\\Cropper\\Http\\Controllers\\ImageController/g' vendor/takeone/cropper/src/CropperServiceProvider.php
    echo "✓ Namespace fixed"
else
    echo "⚠ Warning: CropperServiceProvider.php not found - package may not be installed"
fi
echo ""

# Step 7: Discover packages
echo "Step 7: Discovering packages..."
php artisan package:discover --ansi
echo "✓ Packages discovered"
echo ""

# Step 8: Verify installation
echo "Step 8: Verifying installation..."
php artisan about
echo ""

echo "=========================================="
echo "Fix completed!"
echo "=========================================="
echo ""
echo "If you still see errors, run these commands manually:"
echo "1. composer install"
echo "2. composer dump-autoload"
echo "3. php artisan optimize:clear"
echo "4. php artisan package:discover"
