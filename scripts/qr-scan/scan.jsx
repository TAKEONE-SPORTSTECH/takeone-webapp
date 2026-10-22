/**
 * The QR scan test (docs/qr-designer-study.md, Part 2 §6). Draws every body ×
 * eye-frame × eye-centre combination, every preset and every gradient through
 * the REAL QrSvg, rasterises each one and decodes it with jsQR. Run by
 * scripts/qr-scan/run.sh inside headless Chromium; never shipped to users.
 */
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import jsQR from 'jsqr';
import QrSvg, { PRESETS, CLASSIC } from '../../resources/js/islands/designer/qr/QrSvg.jsx';
import { BODY, FRAMES, BALLS } from '../../resources/js/islands/designer/qr/shapes.js';

const payloads = [
    'https://stage.takeone.bh/verify/Ab3dEf6hIj9kLm2nOp5qRs8tUv1wXy4z',
    'https://stage.takeone.bh/verify/badge/9f8e7d6c5b4a39281706f5e4d3c2b1a0ZZYYXXWW',
    'https://takeone.bh/people/2b0a1c9e-77d1-4f3a-9a51-3f0f6f0c2e11?n=محمد العربي',
];
const cases = [];
for (const text of payloads) {
    for (const ec of ['M', 'H']) {
        for (const body of BODY) for (const eyeFrame of FRAMES) for (const eyeBall of BALLS) {
            cases.push({ grid: `${body}|${eyeFrame}|${eyeBall}`, text, style: { ...CLASSIC, body, eyeFrame, eyeBall, ecLevel: ec } });
        }
    }
    for (const p of PRESETS) for (const ec of ['auto', 'L', 'H']) cases.push({ must: `preset ${p.key} ec=${ec}`, text, style: { ...p.style, ecLevel: ec } });
    for (const body of BODY) cases.push({ must: `radial gradient ${body}`, text, style: { ...CLASSIC, body, gradient: { type: 'radial', stops: [['#1e3a8a', 0], ['#7c6bf5', 1]] }, gradientOnEyes: true } });
}

const load = (svg) => new Promise((res, rej) => {
    const i = new Image();
    i.onload = () => res(i);
    i.onerror = rej;
    i.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
});

(async () => {
    const risky = new Set();
    const broken = [];
    const cv = document.createElement('canvas');
    cv.width = cv.height = 360;
    const g = cv.getContext('2d', { willReadFrequently: true });
    for (const c of cases) {
        const svg = renderToStaticMarkup(<QrSvg text={c.text} style={c.style} width={300} height={300} />).replace('<svg', '<svg xmlns="http://www.w3.org/2000/svg"');
        let ok = false;
        try {
            const img = await load(svg);
            g.fillStyle = '#fff';
            g.fillRect(0, 0, 360, 360);
            g.drawImage(img, 30, 30, 300, 300);
            ok = jsQR(g.getImageData(0, 0, 360, 360).data, 360, 360)?.data === c.text;
        } catch { ok = false; }
        if (!ok) (c.grid ? risky.add(c.grid) : broken.push(`${c.must} · ${c.text}`));
    }
    const pre = document.createElement('pre');
    pre.id = 'result';
    pre.textContent = JSON.stringify({ total: cases.length, broken, risky: [...risky].sort() });
    document.body.appendChild(pre);
})();
