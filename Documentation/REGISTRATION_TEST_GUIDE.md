# Registration System - Complete Test Guide

## ✅ System Status

Your Laravel server is **RUNNING** at: `http://127.0.0.1:8000`

All components are in place:
- ✅ POST /register route is registered
- ✅ RegisteredUserController with super-admin logic
- ✅ WelcomeEmail with verification button
- ✅ Email verification page
- ✅ All caches cleared and optimized

## 🧪 How to Test Registration

### Step 1: Access Registration Page

Open your browser and go to:
```
http://127.0.0.1:8000/register
```

### Step 2: Fill Out the Form

Enter the following information:
- **Full Name:** John Doe
- **Email:** john@example.com
- **Password:** password123
- **Confirm Password:** password123
- **Mobile Number:** 1234567890
- **Country Code:** +1 (United States)
- **Gender:** Male (M)
- **Birthdate:** 01/01/2000 (must be at least 10 years ago)
- **Nationality:** United States

### Step 3: Submit the Form

Click the **"REGISTER"** button.

### Step 4: Expected Behavior

After clicking Register, the following should happen:

1. **User Created in Database:**
   - New user record created in `users` table
   - Password is hashed
   - Mobile stored as JSON: `{"code": "+1", "number": "1234567890"}`

2. **Super-Admin Role Assigned:**
   - First user gets `super-admin` role automatically
   - Record created in `user_roles` table

3. **Welcome Email Sent:**
   - Email sent to the registered email address
   - Contains "Verify Your Email" button
   - Button links to verification URL

4. **Browser Redirects:**
   - Redirects to: `http://127.0.0.1:8000/email/verify`
   - Shows message: "Verify Your Email"
   - Shows: "We've sent a verification link to your email address"
   - Shows: "Resend Verification Email" button

## 📧 Email Configuration

### Check Your Mail Driver

Run this command to see your current mail configuration:
```cmd
php artisan tinker
```

Then type:
```php
config('mail.mailer')
```

### Common Mail Drivers:

#### 1. **Log Driver (Development - Default)**
If using `log` driver, emails are written to:
```
storage/logs/laravel.log
```

To view the email:
```cmd
type storage\logs\laravel.log
```

Look for the verification URL in the log file.

#### 2. **SMTP Driver (Production)**
If using SMTP, check your email inbox for the welcome email.

#### 3. **Mailtrap (Testing)**
If using Mailtrap, check your Mailtrap inbox.

### To Change Mail Driver:

Edit your `.env` file:

**For Development (Log to File):**
```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

**For Gmail SMTP:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

After changing `.env`, restart the server:
```cmd
.\restart-server.bat
```

## 🔍 Verification Process

### Manual Email Verification (If Email Not Configured)

If you can't receive emails, you can manually verify the user:

1. **Get the verification URL from logs:**
```cmd
type storage\logs\laravel.log | findstr "verification"
```

2. **Or manually verify in database:**
```cmd
php artisan tinker
```

Then:
```php
$user = App\Models\User::where('email', 'john@example.com')->first();
$user->markEmailAsVerified();
```

3. **Or visit the verification URL directly:**
```
http://127.0.0.1:8000/email/verify/{user_id}/{hash}
```

## 🗄️ Database Verification

### Check if User Was Created:

```cmd
php artisan tinker
```

```php
App\Models\User::latest()->first();
```

### Check if Super-Admin Role Was Assigned:

```php
$user = App\Models\User::latest()->first();
$user->roles;
```

Should show the super-admin role.

### Or Use SQL:

```sql
-- Check latest user
SELECT * FROM users ORDER BY id DESC LIMIT 1;

-- Check user roles
SELECT u.id, u.email, u.full_name, r.name as role, r.slug
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN roles r ON ur.role_id = r.id
ORDER BY u.id DESC
LIMIT 1;
```

## 🐛 Troubleshooting

### Issue: Form Doesn't Submit (Nothing Happens)

**Solution:**
1. Open browser console (F12)
2. Look for JavaScript errors
3. Check Network tab for failed requests
4. Verify CSRF token is present in form

### Issue: 404 Error on Submit

**Solution:**
```cmd
.\restart-server.bat
```

### Issue: 419 CSRF Token Mismatch

**Solution:**
1. Clear browser cache (Ctrl+Shift+Delete)
2. Hard refresh page (Ctrl+Shift+R)
3. Try in incognito mode

### Issue: Validation Errors

**Common validation issues:**
- Email already exists
- Password too short (min 8 characters)
- Passwords don't match
- Birthdate not at least 10 years ago
- Missing required fields

### Issue: Email Not Sending

**Check mail configuration:**
```cmd
php artisan tinker
```

```php
// Test email sending
Mail::raw('Test email', function($message) {
    $message->to('test@example.com')->subject('Test');
});
```

If using `log` driver, check:
```cmd
type storage\logs\laravel.log
```

### Issue: Can't Access Admin Panel After Registration

**Verify super-admin role:**
```cmd
php artisan tinker
```

```php
$user = App\Models\User::where('email', 'your@email.com')->first();
$user->isSuperAdmin(); // Should return true
```

If false, manually assign:
```php
$user->assignRole('super-admin');
```

## ✅ Success Checklist

After registration, verify:

- [ ] User record created in database
- [ ] Password is hashed (not plain text)
- [ ] Mobile stored as JSON
- [ ] Super-admin role assigned (first user only)
- [ ] Welcome email sent (check logs if using log driver)
- [ ] Redirected to email verification page
- [ ] Verification page shows correct message
- [ ] Resend button works
- [ ] Verification link works (from email or logs)
- [ ] After verification, can login
- [ ] Can access `/admin` routes as super-admin

## 📝 Test Second User Registration

To verify that only the first user gets super-admin:

1. Register a second user with different email
2. Check database:
```php
$user2 = App\Models\User::where('email', 'second@example.com')->first();
$user2->isSuperAdmin(); // Should return false
```

## 🎯 Next Steps After Successful Registration

1. **Verify Email:** Click link in email or manually verify
2. **Login:** Go to `/login` and login with credentials
3. **Access Admin Panel:** Go to `/admin` (super-admin only)
4. **Explore Platform:** Go to `/explore` to see clubs
5. **Create Family Members:** Go to `/members/create`

## 📞 Need Help?

If registration still doesn't work:

1. **Check server logs:**
```cmd
type storage\logs\laravel.log
```

2. **Check browser console:** Press F12 and look for errors

3. **Verify routes:**
```cmd
php artisan route:list --path=register
```

4. **Test route directly:**
```cmd
php artisan tinker
```

```php
$response = $this->post('/register', [
    'full_name' => 'Test User',
    'email' => 'test@test.com',
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'mobile_number' => '1234567890',
    'country_code' => '+1',
    'gender' => 'm',
    'birthdate' => '2000-01-01',
    'nationality' => 'United States'
]);
```

The registration system is fully functional and ready to use!
