# Admin Panel Testing Guide

## Pre-Testing Setup

### Step 1: Assign Super Admin Role

Run this command with your email:

```bash
php artisan admin:make-super your-email@example.com
```

### Step 2: Start the Development Server

```bash
php artisan serve
```

The server should start at: `http://localhost:8000`

---

## Testing Checklist

### ✅ Authentication & Authorization

- [ ] **Login as regular user**
  - Navigate to `/admin`
  - Should see 403 Forbidden error
  
- [ ] **Login as super admin**
  - Navigate to `/admin`
  - Should see admin dashboard

- [ ] **Test middleware protection**
  - Logout
  - Try to access `/admin` directly
  - Should redirect to login page

---

### ✅ Platform Dashboard (`/admin`)

- [ ] **Page loads successfully**
  - No errors in browser console
  - All stat cards display correctly
  
- [ ] **Statistics display**
  - Total Clubs count
  - Total Members count
  - Active Clubs count
  - Total Revenue (BHD)
  
- [ ] **Quick action cards**
  - "Manage Clubs" button works
  - "Manage Members" button works
  - "Database Backup" button works
  
- [ ] **Sidebar navigation**
  - All menu items visible
  - Active state highlights current page
  - Icons display correctly

---

### ✅ All Clubs Management (`/admin/clubs`)

- [ ] **Page loads successfully**
  - Grid layout displays
  - Search bar visible
  - "Add New Club" button visible
  
- [ ] **Empty state** (if no clubs exist)
  - Friendly message displays
  - "Add New Club" button works
  
- [ ] **Club cards display** (if clubs exist)
  - Cover image or placeholder
  - Club logo or initial
  - Club name
  - Address (if available)
  - Stats (members, packages, trainers)
  - Owner information
  - Edit and Delete buttons
  
- [ ] **Search functionality**
  - Enter search term
  - Results filter correctly
  - Clear search button works
  
- [ ] **Pagination**
  - Multiple pages display if >12 clubs
  - Page navigation works

---

### ✅ Create Club (`/admin/clubs/create`)

- [ ] **Form loads successfully**
  - All fields visible
  - Owner dropdown populated
  - Default values set (currency, timezone, country)
  
- [ ] **Auto-slug generation**
  - Type in club name
  - Slug field auto-fills
  - Special characters converted to hyphens
  
- [ ] **Form validation**
  - Submit empty form
  - Required field errors display
  - Invalid email shows error
  - Invalid GPS coordinates show error
  
- [ ] **File uploads**
  - Select logo image
  - Select cover image
  - File size validation (max 2MB)
  
- [ ] **Successful submission**
  - Fill all required fields
  - Submit form
  - Redirects to clubs list
  - Success message displays
  - New club appears in list
  
- [ ] **Cancel button**
  - Click cancel
  - Returns to clubs list
  - No data saved

---

### ✅ Edit Club (`/admin/clubs/{id}/edit`)

- [ ] **Form loads with existing data**
  - All fields pre-filled
  - Current logo displays (if exists)
  - Current cover image displays (if exists)
  
- [ ] **Update basic information**
  - Change club name
  - Change slug
  - Submit form
  - Changes saved successfully
  
- [ ] **Update contact information**
  - Change email
  - Change phone
  - Change currency/timezone/country
  - Submit and verify changes
  
- [ ] **Update location**
  - Change address
  - Change GPS coordinates
  - Submit and verify changes
  
- [ ] **Replace images**
  - Upload new logo
  - Upload new cover image
  - Old images deleted
  - New images display
  
- [ ] **Form validation**
  - Enter invalid data
  - Errors display correctly
  
- [ ] **Cancel button**
  - Click cancel
  - Returns to clubs list
  - No changes saved

---

### ✅ Delete Club

- [ ] **Delete confirmation**
  - Click delete button on club card
  - Confirmation dialog appears
  - Warning message clear
  
- [ ] **Cancel deletion**
  - Click cancel in dialog
  - Club not deleted
  - Remains in list
  
- [ ] **Confirm deletion**
  - Click delete button
  - Confirm in dialog
  - Club deleted successfully
  - Success message displays
  - Club removed from list
  - Associated files deleted

---

### ✅ All Members Management (`/admin/members`)

- [ ] **Page loads successfully**
  - Grid layout displays
  - Search bar visible
  
- [ ] **Empty state** (if no members)
  - Friendly message displays
  
- [ ] **Member cards display** (if members exist)
  - Avatar or initial
  - Full name
  - Adult/Child badge
  - Club count badge
  - Contact information
  - Gender, age, nationality
  - Horoscope and birthday
  - Member since date
  - View and Edit buttons
  
- [ ] **Search functionality**
  - Search by name
  - Search by phone
  - Search by nationality
  - Results filter correctly
  
- [ ] **Pagination**
  - Multiple pages if >20 members
  - Navigation works
  
- [ ] **View member**
  - Click "View" button
  - Redirects to member profile
  
- [ ] **Edit member**
  - Click "Edit" button
  - Redirects to edit form

---

### ✅ Database Backup (`/admin/backup`)

- [ ] **Page loads successfully**
  - Three operation cards display
  - Warning message visible
  - Best practices section visible
  
- [ ] **Download Backup**
  - Click "Download Full Backup"
  - Confirmation dialog appears
  - JSON file downloads
  - File name includes timestamp
  - File contains all tables
  
- [ ] **Restore Database**
  - Click "Restore from Backup"
  - Modal opens
  - Warning messages display
  - File input accepts only JSON
  - Checkbox required
  - Cancel button works
  
- [ ] **Restore functionality** (⚠️ TEST IN STAGING ONLY)
  - Upload valid backup JSON
  - Check confirmation checkbox
  - Submit form
  - Final confirmation dialog
  - Database restored successfully
  - Success message displays
  
- [ ] **Export Auth Users**
  - Click "Export Users"
  - JSON file downloads
  - Contains user data with encrypted passwords

---

### ✅ UI/UX Elements

- [ ] **Sidebar navigation**
  - Fixed position on scroll
  - Active state highlights
  - All links work
  - "Back to Explore" link works
  
- [ ] **Top navbar**
  - User name displays
  - Dropdown menu works
  - Profile link works
  - Logout works
  
- [ ] **Alert messages**
  - Success messages display (green)
  - Error messages display (red)
  - Dismissible with X button
  - Auto-dismiss after 5 seconds (optional)
  
- [ ] **Responsive design**
  - Test on mobile (< 768px)
  - Sidebar collapses
  - Cards stack vertically
  - Forms remain usable
  - Tables scroll horizontally
  
- [ ] **Loading states**
  - Forms disable on submit
  - Loading indicators show (if implemented)
  
- [ ] **Empty states**
  - Friendly messages
  - Helpful icons
  - Call-to-action buttons

---

### ✅ Performance & Security

- [ ] **Page load times**
  - Dashboard loads < 2 seconds
  - Clubs list loads < 3 seconds
  - Members list loads < 3 seconds
  
- [ ] **Database queries**
  - Check Laravel Debugbar (if installed)
  - No N+1 query problems
  - Eager loading used
  
- [ ] **CSRF protection**
  - All forms have @csrf token
  - Forms fail without token
  
- [ ] **File upload security**
  - Only images accepted
  - File size limits enforced
  - Files stored securely
  
- [ ] **SQL injection prevention**
  - Try SQL in search fields
  - No errors or data leaks
  
- [ ] **XSS prevention**
  - Try JavaScript in text fields
  - Scripts not executed

---

## Browser Compatibility

Test in multiple browsers:

- [ ] Chrome/Edge (Chromium)
- [ ] Firefox
- [ ] Safari (if on Mac)

---

## Common Issues & Solutions

### Issue: 403 Forbidden on /admin

**Solution:**
```bash
php artisan admin:make-super your-email@example.com
```
Then logout and login again.

### Issue: Role not found

**Solution:**
```bash
php artisan db:seed --class=RolePermissionSeeder
```

### Issue: Images not displaying

**Solution:**
```bash
php artisan storage:link
```

### Issue: Validation errors not showing

**Check:**
- @error directives in blade files
- Form has @csrf token
- Input names match validation rules

---

## Test Data Creation

### Create Test Club via Tinker:

```bash
php artisan tinker
```

```php
$user = User::first();
$club = Tenant::create([
    'owner_user_id' => $user->id,
    'club_name' => 'Test Taekwondo Club',
    'slug' => 'test-taekwondo',
    'email' => 'test@club.com',
    'currency' => 'BHD',
    'timezone' => 'Asia/Bahrain',
    'country' => 'BH',
    'address' => 'Test Address, Manama',
    'gps_lat' => 26.0667,
    'gps_long' => 50.5577,
]);
```

---

## Reporting Issues

When reporting issues, include:

1. **Steps to reproduce**
2. **Expected behavior**
3. **Actual behavior**
4. **Browser and version**
5. **Screenshots** (if applicable)
6. **Error messages** (from browser console or Laravel log)

---

**Happy Testing! 🚀**
