# Launcher icon + TV banner

The TAKEONE mark, generated from the platform's own `public/images/logo.png` so
the app, the pairing screen and the site cannot drift apart.

    php tools-make-icons.php ../public/images/logo.png android_shared_res

Composited onto the hall's near-black ink rather than left transparent: Android
draws a launcher icon over whatever wallpaper the device has, and a red mark on a
red home screen disappears. The mark occupies 74% of the tile so a circular or
squircle mask never clips it.

`build.sh` copies this directory into the generated `android/` tree for BOTH
variants — one product, one icon.
