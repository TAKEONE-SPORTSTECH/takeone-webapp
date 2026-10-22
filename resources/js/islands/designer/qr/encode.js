/**
 * Text → QR module matrix. The one place the encoder library is touched:
 * everything else (shapes, colours, drawing) is ours and works on the matrix.
 */
import qrcode from 'qrcode-generator';

// Byte mode in UTF-8, so an Arabic name in a link survives.
qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];

const LEVELS = ['L', 'M', 'Q', 'H'];
const cache = new Map();

/** The level a style asks for: 'auto' is M — robust in print, still a small code. */
export function levelOf(style) {
    const l = style?.ecLevel;
    return LEVELS.includes(l) ? l : 'M';
}

/**
 * @returns {{ n: number, dark: (r: number, c: number) => boolean } | null}
 */
export function encode(text, level = 'M') {
    if (!text) return null;
    const key = `${level}|${text}`;
    if (cache.has(key)) return cache.get(key);
    let out = null;
    try {
        const q = qrcode(0, LEVELS.includes(level) ? level : 'M');
        q.addData(String(text), 'Byte');
        q.make();
        const n = q.getModuleCount();
        const bits = new Uint8Array(n * n);
        for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) bits[r * n + c] = q.isDark(r, c) ? 1 : 0;
        out = { n, dark: (r, c) => r >= 0 && c >= 0 && r < n && c < n && bits[r * n + c] === 1 };
    } catch {
        out = null; // too long for a QR code
    }
    if (cache.size > 200) cache.clear();
    cache.set(key, out);
    return out;
}
