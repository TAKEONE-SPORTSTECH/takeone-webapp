// Generates the source icon + splash for @capacitor/assets by compositing
// the TAKEONE logo mark centered on the brand purple (#7F6CE0).
import sharp from 'sharp';

const PURPLE = { r: 127, g: 108, b: 224, alpha: 1 };
const LOGO = 'www/icon.png';

async function make(out, size, logoScale) {
    const logoSize = Math.round(size * logoScale);
    const logo = await sharp(LOGO).resize(logoSize, logoSize, { fit: 'contain' }).png().toBuffer();
    await sharp({ create: { width: size, height: size, channels: 4, background: PURPLE } })
        .composite([{ input: logo, gravity: 'center' }])
        .png()
        .toFile(out);
    console.log('wrote', out, `${size}x${size}`);
}

// icon: logo fills ~55% of the tile. splash: logo ~22% of the (large) canvas.
await make('resources/icon.png', 1024, 0.55);
await make('resources/splash.png', 2732, 0.22);
await make('resources/splash-dark.png', 2732, 0.22);
