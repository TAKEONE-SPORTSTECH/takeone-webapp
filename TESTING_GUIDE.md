# Laravel Image Cropper - Testing Guide

## Installation Complete ✅

The Laravel Image Cropper package has been successfully installed and integrated into your project.

## What Was Installed

1. **Package**: `takeone/cropper:@dev`
2. **Cropper Library**: Cropme.js (lightweight image cropping)
3. **Portrait Rectangle Configuration**: 300px × 400px (3:4 ratio)
4. **Storage Directory**: `storage/app/public/images/profiles/`

## Files Modified

### 1. composer.json
- Added VCS repository
- Added package dependency

### 2. resources/views/layouts/app.blade.php
- Added `@stack('modals')` for modal injection

### 3. resources/views/family/profile-edit.blade.php
- Replaced old image upload modal with `<x-takeone-cropper>` component
- Configured for portrait rectangle cropping
- Added image display with fallback to default avatar

### 4. app/Http/Controllers/FamilyController.php
- Updated `uploadProfilePicture()` method to handle base64 images
- Saves cropped images to `storage/app/public/images/profiles/`
- Updates user's `profile_picture` field in database

### 5. resources/views/vendor/takeone/components/widget.blade.php
- Published and customized widget component
- Added custom upload URL support
- Added page reload after successful upload
- Improved error handling

## How to Test

### Step 1: Start the Development Server
```bash
php artisan serve
```

### Step 2: Navigate to Profile Edit Page
Open your browser and go to:
```
http://localhost:8000/profile/edit
```

### Step 3: Test the Cropper

1. **Click "Change Photo" button**
   - A modal should popup with a file selector

2. **Select an Image**
   - Click "Choose File" and select any image from your computer
   - The image should load in the cropping interface

3. **Crop the Image**
   - You'll see a **portrait rectangle** overlay (300×400px)
   - Use the **Zoom Level** slider to zoom in/out
   - Use the **Rotation** slider to rotate the image
   - Drag the image to position it within the rectangle

4. **Save the Image**
   - Click "Crop & Save Image" button
   - Button should show "Uploading..." while processing
   - You should see "Saved successfully!" alert
   - Modal should close automatically
   - Page should reload
   - Your new profile picture should appear in the profile picture box

### Step 4: Verify the Upload

Check that the image was saved:
```bash
dir storage\app\public\images\profiles\
```

You should see a file named `profile_{user_id}.png`

### Step 5: Verify Database Update

The `users` table should have the `profile_picture` field updated with:
```
images/profiles/profile_{user_id}.png
```

## Expected Behavior

### ✅ Success Indicators
- Modal opens when clicking "Change Photo"
- Image loads in cropper after selection
- Portrait rectangle viewport is visible (taller than wide)
- Zoom and rotation sliders work smoothly
- Image can be dragged/positioned
- "Crop & Save Image" button uploads successfully
- Success alert appears
- Page reloads automatically
- New profile picture displays in the profile box

### ❌ Potential Issues

**Modal doesn't appear:**
- Check browser console (F12) for JavaScript errors
- Verify `@stack('modals')` is in `resources/views/layouts/app.blade.php`
- Ensure jQuery and Bootstrap are loaded

**Upload fails:**
- Check `storage/app/public/images/profiles/` directory exists
- Verify storage link: `php artisan storage:link`
- Check file permissions on storage directory
- Review Laravel logs: `storage/logs/laravel.log`

**Image doesn't display after upload:**
- Verify storage is linked
- Check the `profile_picture` field in database
- Ensure the file exists in `public/storage/images/profiles/`
- Clear browser cache

**Cropper shows square instead of rectangle:**
- Verify the component has `width="300"` and `height="400"`
- Check that `shape="square"` (not "circle")

## Package Bug Note

⚠️ **Known Issue**: The package's service provider has a namespace bug in the route registration. This doesn't affect functionality because we're using our own custom route (`profile.upload-picture`) instead of the package's default route.

The error you might see when running `php artisan route:list`:
```
Class "takeone\cropper\Http\Controllers\ImageController" does not exist
```

This can be safely ignored as we're not using that route.

## Customization Options

### Change Aspect Ratio

Edit `resources/views/family/profile-edit.blade.php`:

```html
<!-- Current: 3:4 portrait -->
<x-takeone-cropper 
    width="300" 
    height="400"
/>

<!-- Square: 1:1 -->
<x-takeone-cropper 
    width="300" 
    height="300"
/>

<!-- Wide rectangle: 16:9 -->
<x-takeone-cropper 
    width="400" 
    height="225"
/>

<!-- Tall portrait: 2:3 -->
<x-takeone-cropper 
    width="300" 
    height="450"
/>
```

### Change to Circular Crop

```html
<x-takeone-cropper 
    width="300" 
    height="300"
    shape="circle"
/>
```

### Change Storage Folder

```html
<x-takeone-cropper 
    folder="avatars"
/>
```

### Custom Filename Pattern

```html
<x-takeone-cropper 
    filename="user_{{ auth()->id() }}_{{ time() }}"
/>
```

## File Structure

```
takeone/
├── storage/
│   └── app/
│       └── public/
│           └── images/
│               └── profiles/          ← Uploaded images here
│                   └── profile_1.png
├── public/
│   └── storage/                       ← Symlink to storage/app/public
│       └── images/
│           └── profiles/
│               └── profile_1.png      ← Publicly accessible
├── resources/
│   └── views/
│       ├── family/
│       │   └── profile-edit.blade.php ← Profile edit page
│       ├── layouts/
│       │   └── app.blade.php          ← Main layout with @stack('modals')
│       └── vendor/
│           └── takeone/
│               └── components/
│                   └── widget.blade.php ← Customized cropper widget
└── app/
    └── Http/
        └── Controllers/
            └── FamilyController.php   ← Upload handler
```

## Support

If you encounter any issues:

1. Check the browser console (F12) for JavaScript errors
2. Review Laravel logs: `storage/logs/laravel.log`
3. Verify all files were modified correctly
4. Ensure storage permissions are correct
5. Clear all caches: `php artisan config:clear && php artisan route:clear && php artisan view:clear`

## Next Steps

1. Test the cropper functionality
2. Upload a test image
3. Verify it displays correctly
4. Customize the aspect ratio if needed
5. Add additional validation if required
6. Consider adding image optimization/compression

---

**Installation Date**: January 26, 2026  
**Package Version**: dev-main  
**Laravel Version**: 12.0  
**Status**: ✅ Ready for Testing
