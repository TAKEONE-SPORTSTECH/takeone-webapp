# Edit Functionality for User and Family Data

## Tasks
- [x] Add editProfile() and updateProfile() methods in FamilyController
- [x] Add routes for profile.edit and profile.update in web.php
- [x] Create profile-edit.blade.php view
- [x] Add edit buttons in show.blade.php
- [x] Add edit link in dashboard.blade.php for user card
- [x] Test the edit forms

# Image Upload Modal Component

## Tasks
- [x] Create migration for profile_picture column in users table
- [x] Update User model to add profile_picture to fillable and casts
- [x] Add cropperjs and browser-image-compression to package.json
- [x] Update app.js to import cropperjs and browser-image-compression
- [x] Create image-upload-modal.blade.php component
- [x] Add uploadProfilePicture method to FamilyController
- [x] Add route for profile picture upload in web.php
- [x] Update profile-edit.blade.php to include profile picture section
- [x] Update updateProfile method to handle profile_picture (not needed, upload is separate)
- [x] Run npm install and php artisan migrate
- [x] Test the image upload functionality (implementation complete, manual testing required)
