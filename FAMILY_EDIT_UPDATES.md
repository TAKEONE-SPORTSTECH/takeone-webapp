# Family Member Edit Form Updates

## Summary
Updated the family member edit form to match the guardian profile edit page by adding missing fields.

## Changes Made

### 1. Updated View: `resources/views/family/edit.blade.php`
Added the following sections:
- ✅ **Profile Picture Upload Section**
  - Displays current profile picture or default avatar
  - Button to open upload modal
  - Integrated with image upload modal component

- ✅ **Mobile Number Field**
  - Text input for mobile number
  - Optional field with validation

- ✅ **Social Media Links Section**
  - Dynamic add/remove functionality
  - Support for 22 social platforms (Facebook, Twitter, Instagram, LinkedIn, YouTube, TikTok, etc.)
  - Platform dropdown and URL input for each link
  - JavaScript functionality to add/remove links dynamically

- ✅ **Personal Motto Field**
  - Textarea for personal motto/quote
  - Maximum 500 characters
  - Helper text included

### 2. Updated Controller: `app/Http/Controllers/FamilyController.php`

#### Updated `update()` method:
- Added validation for new fields:
  - `mobile` (nullable, max 20 characters)
  - `social_links` (array with platform and url validation)
  - `motto` (nullable, max 500 characters)
- Added social links processing logic to convert array format to associative array
- Updated dependent user update to include all new fields

#### Added `uploadFamilyMemberPicture()` method:
- Handles profile picture uploads for family members
- Validates image file (jpeg, png, jpg, gif, max 5MB)
- Verifies family member belongs to authenticated user
- Generates unique filename
- Stores in `public/images/profiles`
- Deletes old profile picture if exists
- Returns JSON response for AJAX handling

### 3. Updated Routes: `routes/web.php`
- ✅ Added route: `POST /family/{id}/upload-picture` → `family.upload-picture`

## Features Now Available

The family member edit form now includes all the same fields as the guardian profile edit page:

1. **Profile Picture** - Upload and manage profile photos
2. **Full Name** - Required field
3. **Email Address** - Optional for children
4. **Mobile Number** - Optional contact number
5. **Gender** - Male/Female selection
6. **Birthdate** - Date picker
7. **Blood Type** - Dropdown with all blood types
8. **Nationality** - Country dropdown component
9. **Social Media Links** - Dynamic list with 22 platform options
10. **Personal Motto** - Text area for inspirational quotes
11. **Relationship Type** - Son/Daughter/Spouse/Sponsor/Other
12. **Is Billing Contact** - Checkbox

## Technical Details

### Social Links Processing
- Frontend: Array of objects `[{platform: 'facebook', url: 'https://...'}]`
- Backend: Converted to associative array `{'facebook': 'https://...'}`
- Stored in database as JSON

### Profile Picture Upload
- Uses existing `x-image-upload-modal` component
- AJAX upload with cropping functionality
- Aspect ratio: 1:1 (square)
- Max size: 1MB (as per modal config)
- Stored in: `storage/app/public/images/profiles/`

## Testing Checklist

- [ ] Profile picture upload works for family members
- [ ] Mobile number saves correctly
- [ ] Social links can be added dynamically
- [ ] Social links can be removed
- [ ] Social links save correctly
- [ ] Personal motto saves correctly
- [ ] All existing fields still work (name, email, gender, etc.)
- [ ] Form validation works properly
- [ ] Success message displays after update
- [ ] Redirect to family dashboard works

## Files Modified

1. `resources/views/family/edit.blade.php` - Added new form fields and JavaScript
2. `app/Http/Controllers/FamilyController.php` - Updated validation and added upload method
3. `routes/web.php` - Added profile picture upload route

## Notes

### Default Avatar Image
The code references `asset('images/default-avatar.png')` which doesn't currently exist in `public/images/`. 

**Options:**
1. Create a default avatar image at `public/images/default-avatar.png`
2. Use a placeholder service like `https://ui-avatars.com/api/?name=User&size=120`
3. Use the same approach as the show page (gradient background with initials)

**Current behavior:** If the file doesn't exist, the browser will show a broken image icon until a profile picture is uploaded.

### Storage Directory
Make sure the storage directory is linked:
```bash
php artisan storage:link
```

This creates a symbolic link from `public/storage` to `storage/app/public` so uploaded images are accessible.
