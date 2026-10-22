/**
 * A styled QR code as one SVG — vector, so it is sharp at any size on screen
 * and in the printed PDF. Used by the canvas, Preview, the print page and the
 * QR style panel's tiles alike.
 */
import React, { useId, useMemo } from 'react';
import { encode, levelOf } from './encode';
import { bodyPath, framePath, ballPath, eyeTransforms } from './shapes';

/** Today's look: what an element with no style of its own resolves to. */
export const CLASSIC = Object.freeze({
    v: 1, body: 'square', eyeFrame: 'square', eyeBall: 'square',
    bodyColor: '#000000', eyeFrameColor: null, eyeBallColor: null, bg: '#ffffff',
    gradient: null, gradientOnEyes: false, margin: 1, ecLevel: 'auto', eyes: {},
});

export const PRESETS = [
    { key: 'classic', style: CLASSIC },
    { key: 'rounded', style: { ...CLASSIC, body: 'rounded', eyeFrame: 'rounded', eyeBall: 'rounded' } },
    { key: 'dots', style: { ...CLASSIC, body: 'dots', eyeFrame: 'extra-rounded', eyeBall: 'circle' } },
    { key: 'liquid', style: { ...CLASSIC, body: 'liquid', eyeFrame: 'extra-rounded', eyeBall: 'rounded' } },
    { key: 'classy', style: { ...CLASSIC, body: 'classy-rounded', eyeFrame: 'leaf', eyeBall: 'leaf' } },
    { key: 'takeone', style: { ...CLASSIC, body: 'rounded', eyeFrame: 'extra-rounded', eyeBall: 'circle', bodyColor: '#6d5bd0', eyeFrameColor: '#4c3fb0', eyeBallColor: '#4c3fb0', gradient: { type: 'linear', angle: 135, stops: [['#7c6bf5', 0], ['#4c3fb0', 1]] } } },
];

export function resolveStyle(s) {
    return { ...CLASSIC, ...(s || {}), eyes: s?.eyes || {} };
}

function Paint({ id, g }) {
    const stops = (g.stops || []).map(([c, o], i) => <stop key={i} offset={o} stopColor={c} />);
    if (g.type === 'radial') return <radialGradient id={id} cx="0.5" cy="0.5" r="0.7">{stops}</radialGradient>;
    const a = ((g.angle ?? 90) - 90) * (Math.PI / 180);
    const x = Math.cos(a) / 2; const y = Math.sin(a) / 2;
    return <linearGradient id={id} x1={0.5 - x} y1={0.5 - y} x2={0.5 + x} y2={0.5 + y}>{stops}</linearGradient>;
}

export default function QrSvg({ text, style, width = '100%', height = '100%' }) {
    const s = resolveStyle(style);
    const level = levelOf(s);
    const m = encode(text, level);
    const uid = useId().replace(/[^a-zA-Z0-9_-]/g, '');
    const paths = useMemo(() => (m ? {
        body: bodyPath(m, s.body),
        frame: framePath(s.eyeFrame),
        ball: ballPath(s.eyeBall),
        eyeAt: eyeTransforms(m.n, Object.fromEntries(Object.entries(s.eyes).map(([k, v]) => [k, Boolean(v?.flip)]))),
    } : null), [m, s.body, s.eyeFrame, s.eyeBall, JSON.stringify(s.eyes)]);
    if (!m) return null;

    const margin = Math.max(0, Math.min(8, Number(s.margin) || 0));
    const size = m.n + margin * 2;
    const gradient = s.gradient && Array.isArray(s.gradient.stops) && s.gradient.stops.length > 1 ? s.gradient : null;
    const bodyFill = gradient ? `url(#qrg${uid})` : s.bodyColor;
    const frameColor = s.eyeFrameColor || (gradient && s.gradientOnEyes ? bodyFill : s.bodyColor);
    const ballColor = s.eyeBallColor || s.eyeFrameColor || (gradient && s.gradientOnEyes ? bodyFill : s.bodyColor);

    return (
        <svg viewBox={`${-margin} ${-margin} ${size} ${size}`} width={width} height={height} preserveAspectRatio="xMidYMid meet" style={{ display: 'block' }} shapeRendering="geometricPrecision">
            {gradient && <defs><Paint id={`qrg${uid}`} g={gradient} /></defs>}
            {s.bg && s.bg !== 'transparent' && <rect x={-margin} y={-margin} width={size} height={size} fill={s.bg} />}
            {/* The gradient spans the whole code, not each module. */}
            <path d={paths.body} fill={bodyFill} fillRule="nonzero" />
            {['tl', 'tr', 'bl'].map((k) => {
                const own = s.eyes[k] || {};
                return (
                    <g key={k} transform={paths.eyeAt[k]}>
                        <path d={own.frame ? framePath(own.frame) : paths.frame} fill={own.frameColor || frameColor} fillRule="evenodd" />
                        <path d={own.ball ? ballPath(own.ball) : paths.ball} fill={own.ballColor || ballColor} fillRule="nonzero" />
                    </g>
                );
            })}
        </svg>
    );
}
