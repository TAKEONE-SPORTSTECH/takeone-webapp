// Regenerate Android launcher icons: the TAKEONE logo, larger, on a TRANSPARENT
// background (no purple). Foreground fills ~80% (big but safe from mask cropping);
// legacy icons fill ~92%.
import sharp from 'sharp';

const LOGO = '../public/images/logo.png';
const RES = 'android/app/src/main/res';
const TRANSPARENT = { r: 0, g: 0, b: 0, alpha: 0 };

const foreground = { ldpi: 81, mdpi: 108, hdpi: 162, xhdpi: 216, xxhdpi: 324, xxxhdpi: 432 };
const legacy = { ldpi: 36, mdpi: 48, hdpi: 72, xhdpi: 96, xxhdpi: 144, xxxhdpi: 192 };

async function gen(size, frac, out) {
    const s = Math.round(size * frac);
    const logo = await sharp(LOGO).resize(s, s, { fit: 'contain', background: TRANSPARENT }).png().toBuffer();
    await sharp({ create: { width: size, height: size, channels: 4, background: TRANSPARENT } })
        .composite([{ input: logo, gravity: 'center' }])
        .png()
        .toFile(out);
}

// Foreground fills only the adaptive "safe zone" (~60% of 108dp) so no launcher
// mask (circle/squircle) ever crops the logo's edges.
for (const [d, size] of Object.entries(foreground)) {
    await gen(size, 0.60, `${RES}/mipmap-${d}/ic_launcher_foreground.png`);
}
for (const [d, size] of Object.entries(legacy)) {
    await gen(size, 0.82, `${RES}/mipmap-${d}/ic_launcher.png`);
    await gen(size, 0.82, `${RES}/mipmap-${d}/ic_launcher_round.png`);
}
console.log('icons regenerated: transparent background, larger logo');
