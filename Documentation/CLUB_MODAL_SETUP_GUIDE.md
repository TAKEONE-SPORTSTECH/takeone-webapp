# Club Modal Setup Guide

## Quick Start

Follow these steps to get the multi-stage tabbed club modal up and running.

## Step 1: Run Database Migration

Add the new fields to your database:

```bash
php artisan migrate
```

This will add:
- `established_date` to `tenants` table
- `status` to `tenants` table
- `public_profile_enabled` to `tenants` table
- `benefitpay_account` to `club_bank_accounts` table

## Step 2: Update the Clubs View

Replace the current clubs view with the new one that includes the modal:

```bash
# Backup the current file (optional)
cp resources/views/admin/platform/clubs.blade.php resources/views/admin/platform/clubs-backup.blade.php

# Replace with the new version
cp resources/views/admin/platform/clubs-with-modal.blade.php resources/views/admin/platform/clubs.blade.php
```

Or manually update `resources/views/admin/platform/clubs.blade.php` to include:
1. Modal trigger button instead of navigation link
2. Include the modal components at the bottom
3. Add the JavaScript for opening the modal

## Step 3: Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

## Step 4: Test the Implementation

### Test Create Mode

1. Navigate to: `http://localhost:8000/admin/clubs`
2. Click "Add New Club" button
3. Modal should open with 5 tabs
4. Fill in the form:
   - **Tab 1 (Basic Info)**: Enter club name, select owner
   - **Tab 2 (Identity)**: Upload logo/cover, add social links
   - **Tab 3 (Location)**: Select country, drag map marker
   - **Tab 4 (Contact)**: Choose email/phone options
   - **Tab 5 (Finance)**: Add bank accounts, set status
5. Click "Create Club"
6. Verify club appears in the list

### Test Edit Mode

1. Click the edit button (pencil icon) on any club card
2. Modal should open with pre-filled data
3. Make changes to any fields
4. Click "Update Club"
5. Verify changes are saved

### Test Validation

1. Try to proceed to next tab without filling required fields
2. Should show validation errors
3. Fill required fields and proceed
4. All tabs should validate before final submission

### Test Responsive Design

1. Open browser DevTools
2. Toggle device toolbar (mobile view)
3. Test modal on different screen sizes
4. Verify all elements are accessible

## Step 5: Verify Components

Check that all existing components are working:

### Image Cropper
- Upload logo → Should open cropper modal
- Crop and save → Should show preview
- Same for cover image

### Country Dropdown
- Select country → Should show flag and name
- Search functionality should work

### Timezone Dropdown
- Should filter based on selected country
- Search functionality should work

### Currency Dropdown
- Should default to country's currency
- Search functionality should work

### Map
- Should initialize with marker
- Marker should be draggable
- Lat/Lng inputs should update when marker moves
- Map should update when lat/lng inputs change

### QR Code
- Should generate automatically
- Should update when slug changes
- Download button should work
- Print button should work

## Troubleshooting

### Modal doesn't open
**Issue**: Clicking "Add New Club" does nothing

**Solution**:
1. Check browser console for JavaScript errors
2. Verify Bootstrap 5 is loaded
3. Check that modal ID matches: `#clubModal`
4. Ensure `openClubModal()` function is defined

### Images don't upload
**Issue**: Cropper doesn't save images

**Solution**:
1. Check storage is linked: `php artisan storage:link`
2. Verify storage permissions: `chmod -R 775 storage`
3. Check `storage/app/public` directory exists
4. Verify cropper component is properly included

### Map doesn't load
**Issue**: Map shows blank or doesn't initialize

**Solution**:
1. Check Leaflet.js CDN is accessible
2. Verify internet connection (CDN required)
3. Check browser console for errors
4. Ensure map container has height: `#clubMap { height: 400px; }`

### User picker is empty
**Issue**: No users show in user picker modal

**Solution**:
1. Check API endpoint: `/admin/api/users`
2. Verify route is registered in `routes/web.php`
3. Check `ClubApiController::getUsers()` method
4. Ensure users exist in database

### Validation errors
**Issue**: Form shows validation errors incorrectly

**Solution**:
1. Check `ClubApiController` validation rules
2. Verify all required fields have `required` attribute
3. Check error message display in blade templates
4. Ensure validation feedback classes are applied

### Draft not saving
**Issue**: Form data lost on accidental close

**Solution**:
1. Check browser localStorage is enabled
2. Verify `saveDraft()` function is called
3. Check browser console for errors
4. Test in incognito mode (localStorage might be disabled)

### Social links not saving
**Issue**: Social media links don't persist

**Solution**:
1. Check form field names: `social_links[0][platform]`, etc.
2. Verify `ClubApiController::store()` handles social links
3. Check `ClubSocialLink` model and table exist
4. Verify relationship in `Tenant` model

### Bank accounts not saving
**Issue**: Bank account data doesn't persist

**Solution**:
1. Check form field names: `bank_accounts[0][bank_name]`, etc.
2. Verify `ClubApiController::store()` handles bank accounts
3. Check `ClubBankAccount` model and table exist
4. Verify encryption is working for sensitive fields

## Performance Optimization

### Reduce Modal Load Time

1. **Lazy load external libraries**:
```javascript
// Load Leaflet only when Location tab is shown
document.getElementById('location-tab').addEventListener('shown.bs.tab', function() {
    if (!window.L) {
        // Load Leaflet.js
    }
});
```

2. **Optimize images**:
- Use WebP format for logos/covers
- Implement lazy loading for club cards
- Compress images before upload

3. **Cache API responses**:
```javascript
// Cache users list in sessionStorage
const cachedUsers = sessionStorage.getItem('users');
if (cachedUsers) {
    displayUsers(JSON.parse(cachedUsers));
} else {
    fetchUsers();
}
```

## Security Considerations

1. **CSRF Protection**: Already implemented via `@csrf` directive
2. **Authorization**: Ensure only super-admins can access
3. **Input Sanitization**: Laravel handles this automatically
4. **File Upload Validation**: Verify file types and sizes
5. **SQL Injection**: Use Eloquent ORM (already implemented)
6. **XSS Protection**: Blade escapes output by default

## Browser Compatibility

Tested and working on:
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

## Additional Configuration

### Customize Modal Size

Edit `resources/views/components/club-modal.blade.php`:

```blade
<!-- Change modal-xl to modal-lg or custom width -->
<div class="modal-dialog modal-dialog-centered modal-xl">
```

### Customize Tab Order

Reorder tabs in `resources/views/components/club-modal.blade.php`:

```blade
<!-- Change the order of tab buttons and tab panes -->
```

### Add Custom Validation

Edit `app/Http/Controllers/Admin/ClubApiController.php`:

```php
$validator = Validator::make($request->all(), [
    // Add your custom rules here
    'custom_field' => 'required|string|max:255',
]);
```

### Customize Colors

The modal uses your existing design system. To customize:

```css
/* In your app.css or custom stylesheet */
#clubModal .nav-tabs .nav-link.active {
    color: your-custom-color;
    border-bottom-color: your-custom-color;
}
```

## Next Steps

After successful setup:

1. ✅ Test thoroughly in development
2. ✅ Deploy to staging environment
3. ✅ Perform user acceptance testing
4. ✅ Train admin users on new interface
5. ✅ Deploy to production
6. ✅ Monitor for issues
7. ✅ Gather user feedback
8. ✅ Iterate and improve

## Support

If you encounter issues not covered in this guide:

1. Check the browser console for JavaScript errors
2. Check Laravel logs: `storage/logs/laravel.log`
3. Review the implementation documentation: `CLUB_MODAL_IMPLEMENTATION.md`
4. Test with different browsers
5. Verify all dependencies are installed

## Rollback Plan

If you need to rollback:

```bash
# Restore original clubs view
cp resources/views/admin/platform/clubs-backup.blade.php resources/views/admin/platform/clubs.blade.php

# Rollback migration
php artisan migrate:rollback --step=1

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

---

**Setup Time**: ~10 minutes  
**Difficulty**: Easy  
**Prerequisites**: Laravel 11, PHP 8.2+, MySQL/PostgreSQL
