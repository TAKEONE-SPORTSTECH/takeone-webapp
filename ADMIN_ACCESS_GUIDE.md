# Admin Panel Access Guide

## How to Access the Admin Dashboard

### Step 1: Make Your User a Super Admin

Run this command in your terminal (replace with your email):

```bash
php artisan admin:make-super your-email@example.com
```

**Example:**
```bash
php artisan admin:make-super admin@takeone.com
```

You should see:
```
✅ Successfully made 'Your Name' (your-email@example.com) a super admin!
They can now access the admin panel at: http://localhost:8000/admin
```

---

### Step 2: Login to Your Account

1. Go to: `http://localhost:8000/login`
2. Login with your credentials
3. After successful login, you'll be redirected to the explore page

---

### Step 3: Access the Admin Panel

Once logged in as a super admin, navigate to:

```
http://localhost:8000/admin
```

Or click on "Admin Panel" link in the user dropdown menu (if you add it to the main layout).

---

## Admin Panel Features

### Available Routes:

1. **Dashboard** - `/admin`
   - Platform statistics
   - Quick action cards

2. **All Clubs** - `/admin/clubs`
   - View all clubs in grid layout
   - Search clubs
   - Create new club
   - Edit existing clubs
   - Delete clubs

3. **All Members** - `/admin/members`
   - View all platform members
   - Search members
   - View member details

4. **Database Backup** - `/admin/backup`
   - Download full database backup (JSON)
   - Restore from backup
   - Export authentication users

---

## Troubleshooting

### "403 Unauthorized" Error

If you see a 403 error when accessing `/admin`, it means:
- You're not logged in, OR
- Your user doesn't have the super-admin role

**Solution:**
1. Make sure you're logged in
2. Run the command: `php artisan admin:make-super your-email@example.com`
3. Logout and login again
4. Try accessing `/admin` again

### "Role not found" Error

If the command fails with "Role not found":

**Solution:**
Run the seeder to create roles:
```bash
php artisan db:seed --class=RolePermissionSeeder
```

Then try the make-super command again.

---

## Quick Test Checklist

After getting access, test these features:

- [ ] Dashboard loads with statistics
- [ ] Navigate to All Clubs page
- [ ] Search for clubs (if any exist)
- [ ] Click "Add New Club" button
- [ ] Navigate to All Members page
- [ ] Search for members
- [ ] Navigate to Database Backup page
- [ ] Check sidebar navigation works
- [ ] Test responsive design (resize browser)

---

## Adding Admin Link to Main Navigation

To make it easier to access the admin panel, you can add a link in the main layout.

Edit `resources/views/layouts/app.blade.php` and add this in the user dropdown:

```blade
@if(Auth::user()->isSuperAdmin())
<a class="dropdown-item small" href="{{ route('admin.platform.index') }}">
    <i class="bi bi-shield me-2"></i>Admin Panel
</a>
<div class="dropdown-divider"></div>
@endif
```

---

## Security Notes

- Only users with the `super-admin` role can access `/admin` routes
- All admin routes are protected with `role:super-admin` middleware
- Destructive actions (delete, restore) have confirmation dialogs
- Bank account information is encrypted in the database

---

**Need Help?** Check the ADMIN_PANEL_PROGRESS.md file for implementation details.
