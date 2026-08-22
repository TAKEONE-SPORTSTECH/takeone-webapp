<?php
/**
 * Generates the TV/tablet launcher icon set from public/images/logo.png.
 *
 * The mark is a transparent PNG, so it is composited onto the hall's near-black
 * ink rather than left transparent: Android draws a launcher icon on whatever
 * wallpaper the device has, and a red helmet on a red home screen disappears.
 * The inset keeps it clear of the circular/squircle mask every launcher applies.
 */
$src = imagecreatefrompng($argv[1]);
imagealphablending($src, true);
imagesavealpha($src, true);
$sw = imagesx($src);
$sh = imagesy($src);

$out = $argv[2];

// Launcher densities, plus the adaptive-icon foreground (432) and the TV banner.
$sizes = [
    'mipmap-mdpi' => 48,
    'mipmap-hdpi' => 72,
    'mipmap-xhdpi' => 96,
    'mipmap-xxhdpi' => 144,
    'mipmap-xxxhdpi' => 192,
];

// oklch(0.15 0.015 20) — the same ink as the pairing screen.
[$r, $g, $b] = [20, 16, 16];

foreach ($sizes as $dir => $size) {
    @mkdir("$out/$dir", 0775, true);

    $img = imagecreatetruecolor($size, $size);
    imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));

    // 74% of the tile: enough margin that a circular mask never clips the mark.
    $inner = (int) round($size * 0.74);
    $x = (int) round(($size - $inner) / 2);
    imagecopyresampled($img, $src, $x, $x, 0, 0, $inner, $inner, $sw, $sh);

    imagepng($img, "$out/$dir/ic_launcher.png", 9);
    imagedestroy($img);
    echo "$dir/ic_launcher.png {$size}x{$size}\n";
}

// The Android TV home-row banner: 320x180, mark on the left, wordmark drawn as
// the built-in font is far too small — so the mark alone, offset, reads better
// than a cramped label.
@mkdir("$out/drawable-xhdpi", 0775, true);
$banner = imagecreatetruecolor(320, 180);
imagefill($banner, 0, 0, imagecolorallocate($banner, $r, $g, $b));
imagecopyresampled($banner, $src, 30, 45, 0, 0, 92, 92, $sw, $sh);
$white = imagecolorallocate($banner, 240, 238, 233);

// A real face at a real size. GD's built-in bitmap font tops out around 9px,
// which on a 320x180 tile that a TV scales UP reads as a smudge next to the mark.
$face = '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf';
if (is_file($face)) {
    // Measured, not guessed: the label has to sit between the mark and the right
    // edge, and a size that overflows silently clips the last letter.
    $label = 'TAKEONE';
    $pt = 26;
    do {
        $box = imagettfbbox($pt, 0, $face, $label);
        $w = $box[2] - $box[0];
        $h = $box[1] - $box[7];
        if (136 + $w <= 300) break;
        $pt -= 1;
    } while ($pt > 12);

    imagettftext($banner, $pt, 0, 136, (int) round((180 + $h) / 2), $white, $face, $label);
} else {
    imagestring($banner, 5, 146, 88, 'TAKEONE', $white);
}
imagepng($banner, "$out/drawable-xhdpi/tv_banner.png", 9);
echo "drawable-xhdpi/tv_banner.png 320x180\n";
