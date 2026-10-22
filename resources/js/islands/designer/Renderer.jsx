/**
 * Draws a page as DOM — the SAME component the editor shows and the print view
 * prints, so what the organiser sees is what comes out of the printer.
 *
 *   <PageView page size ctx />
 *
 * ctx: {
 *   assets:   uuid → { url }
 *   fields:   key  → { label, sample, type }
 *   values:   key  → string (a real recipient's data; absent in the editor)
 *   preview:  show field samples instead of {tokens}
 *   editingId, onText, onEditKey, measure(id, height)   — editor only
 * }
 *
 * Every string a designer typed is rendered as a React text node, never as
 * HTML, so a design cannot inject markup into the page that prints it.
 */
import React, { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { shapeGeometry, graphic } from './library';
import { fontStack } from './fonts';
import QrSvg, { CLASSIC } from './qr/QrSvg';

/** What the editor's QR encodes before a real certificate fills it: a verify link of the real length. */
export const QR_SAMPLE = `${typeof location !== 'undefined' && /^https?:/.test(location.origin) ? location.origin : 'https://takeone.bh'}/verify/Ab3dEf6hIj9kLm2nOp5qRs8tUv1wXy4z`;

// The stack lives with the fonts (companions for letters a face lacks); kept exported from here too.
export { fontStack };

/** '#rrggbb' + 0..1 → rgba(), for shadows. */
export function rgba(hex, alpha = 1) {
    const n = parseInt(String(hex || '#000000').slice(1, 7), 16) || 0;
    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
}

const shadowCss = (s) => (s ? `${s.x}px ${s.y}px ${s.blur}px ${rgba(s.color, s.opacity)}` : null);

export function textStyle(el) {
    const outline = el.type === 'text' && el.outline?.width ? el.outline : null;
    return {
        // Text effects: a shadow under the letters and an outline round them (drawn behind the fill).
        textShadow: el.type === 'text' ? shadowCss(el.shadow) || undefined : undefined,
        WebkitTextStroke: outline ? `${outline.width}px ${outline.color}` : undefined,
        paintOrder: outline ? 'stroke fill' : undefined,
        fontFamily: fontStack(el.fontFamily || 'Inter'),
        fontSize: `${el.fontSize || 16}px`,
        fontWeight: el.fontWeight || 400,
        fontStyle: el.italic ? 'italic' : 'normal',
        textDecoration: [el.underline && 'underline', el.strike && 'line-through'].filter(Boolean).join(' ') || 'none',
        textTransform: el.uppercase ? 'uppercase' : 'none',
        color: el.color || '#000000',
        textAlign: el.align || 'left',
        lineHeight: el.lineHeight || 1.4,
        letterSpacing: `${(el.letterSpacing || 0) / 1000}em`,
        whiteSpace: 'pre-wrap',
        overflowWrap: 'break-word',
        wordBreak: 'break-word',
    };
}

const TOKEN = /(\{[a-z_]{1,40}\})/g;

/*
 * Words that belong to the word after them — "bin Ali", "Al Khalifa", "Abdul
 * Rahman", "van Dijk" — so a shortened name never keeps the particle and drops
 * what it belongs to.
 */
const PARTICLES = new Set(['bin', 'bint', 'ibn', 'ben', 'bn', 'al', 'el', 'abu', 'abd', 'abdul', 'abdel', 'van', 'von', 'de', 'da', 'del', 'della', 'di', 'du', 'la', 'le', 'bou',
    'بن', 'بنت', 'ابن', 'أبو', 'ابو', 'عبد', 'آل', 'ال']);

/**
 * A long name cut down to `max` names (2 or 3): the first name, then — for three —
 * the second, and always the family name; the middle names go. A name already
 * that short, or `max` unset, is left exactly as it is.
 */
export function shortenName(name, max) {
    const text = String(name ?? '');
    if (!max) return text;
    const units = [];
    let pending = [];
    text.trim().split(/\s+/).filter(Boolean).forEach((w) => {
        if (PARTICLES.has(w.toLowerCase())) { pending.push(w); return; }
        units.push([...pending, w].join(' '));
        pending = [];
    });
    if (pending.length) {
        if (units.length) units[units.length - 1] += ` ${pending.join(' ')}`;
        else units.push(pending.join(' '));
    }
    if (units.length <= max) return text;
    return (max >= 3 ? [units[0], units[1], units[units.length - 1]] : [units[0], units[units.length - 1]]).join(' ');
}

/** A line of text with its {tokens} shown as chips, samples or real values. */
function Tokens({ text, ctx, el }) {
    const parts = String(text).split(TOKEN);
    // The person's name, cut to the number of names this box allows.
    const shown = (key, v) => (key === 'name' && el?.nameWords ? shortenName(v, el.nameWords) : v);
    return parts.map((part, i) => {
        const m = /^\{([a-z_]{1,40})\}$/.exec(part);
        if (!m) return part;
        const key = m[1];
        if (ctx.values) return key in ctx.values ? shown(key, ctx.values[key]) : part;
        const f = ctx.fields?.[key];
        if (!f) return part;
        if (ctx.preview || ctx.print) return shown(key, f.sample || f.label);
        return <span key={i} className="dz-token">{part}</span>;
    });
}

function TextBody({ el, ctx }) {
    const text = el.text ?? '';
    if (el.list && el.list !== 'none') {
        const lines = text.split('\n');
        const Tag = el.list === 'number' ? 'ol' : 'ul';
        return (
            <Tag style={{ margin: 0, paddingInlineStart: '1.3em', listStyleType: el.list === 'number' ? 'decimal' : 'disc' }}>
                {lines.map((l, i) => <li key={i}><Tokens text={l} ctx={ctx} el={el} /></li>)}
            </Tag>
        );
    }
    return <Tokens text={text || '​'} ctx={ctx} el={el} />;
}

/**
 * Text that must stay on its line however long it comes out — a name on a
 * certificate. Laid out unwrapped and measured; when it is wider than its box
 * it is either squeezed sideways (same letter height) or scaled down whole, by
 * exactly as much as it overflows. Text that fits is left alone. A transform,
 * not a smaller font, so the box keeps its height and nothing below it moves.
 */
function FitLine({ el, width = el.width, children }) {
    const inner = useRef(null);
    const [k, setK] = useState(1);
    useLayoutEffect(() => {
        const node = inner.current;
        if (!node) return undefined;
        const measure = () => {
            const natural = node.offsetWidth;
            const next = natural > width + 0.5 ? Math.max(0.1, width / natural) : 1;
            setK((prev) => (Math.abs(prev - next) < 0.001 ? prev : next));
        };
        measure();
        const ro = new ResizeObserver(measure);
        ro.observe(node);
        // A web font arriving changes the width without resizing anything observed.
        document.fonts?.addEventListener?.('loadingdone', measure);
        return () => {
            ro.disconnect();
            document.fonts?.removeEventListener?.('loadingdone', measure);
        };
    });
    const over = k < 1;
    return (
        // Overflowing, the line starts at the box's left edge so the scale lands exactly on it.
        <div style={over ? { direction: 'ltr', textAlign: 'left' } : undefined}>
            <div ref={inner} dir="auto" style={{
                display: 'inline-block', whiteSpace: 'pre', transformOrigin: 'left center',
                transform: over ? (el.fit === 'squeeze' ? `scaleX(${k})` : `scale(${k})`) : undefined,
            }}>{children}</div>
        </div>
    );
}

/** The text being edited: a plain-text contenteditable that never holds markup. */
function EditableText({ el, ctx }) {
    const ref = useRef(null);
    useLayoutEffect(() => {
        const node = ref.current;
        node.innerText = el.text ?? '';
        node.focus({ preventScroll: true });
        const sel = window.getSelection();
        const range = document.createRange();
        if (ctx.caret && document.caretRangeFromPoint) {
            const r = document.caretRangeFromPoint(ctx.caret.x, ctx.caret.y);
            if (r && node.contains(r.startContainer)) {
                sel.removeAllRanges();
                sel.addRange(r);
                return;
            }
        }
        range.selectNodeContents(node);
        sel.removeAllRanges();
        sel.addRange(range);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [el.id]);

    return (
        <div
            ref={ref}
            className="dz-editable"
            contentEditable="plaintext-only"
            suppressContentEditableWarning
            spellCheck
            dir="auto"
            style={{ outline: 'none', minHeight: '1em', cursor: 'text' }}
            onInput={(e) => ctx.onText?.(el.id, e.currentTarget.innerText.replace(/\n$/, ''))}
            onKeyDown={(e) => ctx.onEditKey?.(e, el.id)}
            onPaste={(e) => {
                // Older engines ignore plaintext-only: keep the clipboard to plain text either way.
                e.preventDefault();
                document.execCommand('insertText', false, e.clipboardData.getData('text/plain'));
            }}
            onPointerDown={(e) => e.stopPropagation()}
        />
    );
}

function Measured({ el, ctx, children, style }) {
    const ref = useRef(null);
    useEffect(() => {
        if (!ctx.measure || !ref.current) return undefined;
        const node = ref.current;
        const report = () => ctx.measure(el.id, node.offsetHeight);
        report();
        const ro = new ResizeObserver(report);
        ro.observe(node);
        return () => ro.disconnect();
    }, [el.id, ctx.measure]);

    return <div ref={ref} style={style}>{children}</div>;
}

const VALIGN = { top: 'flex-start', middle: 'center', bottom: 'flex-end' };

function TextElement({ el, ctx }) {
    const editing = ctx.editingId === el.id;
    return (
        <Measured el={el} ctx={ctx} style={{
            ...textStyle(el), background: el.background || undefined, minHeight: el.minHeight || undefined,
            // In a box taller than its text, the text sits top, middle or bottom.
            display: 'flex', flexDirection: 'column', justifyContent: VALIGN[el.valign] || 'flex-start',
        }}>
            {editing ? <EditableText el={el} ctx={ctx} />
                : (el.fit === 'squeeze' || el.fit === 'shrink') && !(el.list && el.list !== 'none')
                    ? <FitLine el={el}><TextBody el={el} ctx={ctx} /></FitLine>
                    : <div dir="auto"><TextBody el={el} ctx={ctx} /></div>}
        </Measured>
    );
}

function Fill({ el, id }) {
    if (!el.fill2 || !el.fill || el.fill === 'transparent') return null;
    const a = ((el.gradientAngle ?? 90) * Math.PI) / 180;
    const x = Math.cos(a) / 2;
    const y = Math.sin(a) / 2;
    return (
        <defs>
            <linearGradient id={id} x1={0.5 - x} y1={0.5 - y} x2={0.5 + x} y2={0.5 + y}>
                <stop offset="0" stopColor={el.fill} />
                <stop offset="1" stopColor={el.fill2} />
            </linearGradient>
        </defs>
    );
}

const dash = (s) => (s?.style === 'dashed' ? `${s.width * 3} ${s.width * 2}` : s?.style === 'dotted' ? `0 ${s.width * 2}` : undefined);

function ShapeElement({ el, ctx }) {
    const geo = shapeGeometry(el);
    const gid = `g-${el.id}`;
    const fill = el.fill2 && el.fill && el.fill !== 'transparent' ? `url(#${gid})` : el.fill || 'transparent';
    const Tag = geo.tag;
    const editing = ctx.editingId === el.id;
    const hasText = editing || (el.text && el.text.length);

    return (
        <>
            <svg width={el.width} height={el.height} viewBox={`0 0 ${el.width} ${el.height}`} style={{ position: 'absolute', inset: 0, overflow: 'visible' }}>
                <Fill el={el} id={gid} />
                <Tag
                    {...geo.attrs}
                    fill={fill}
                    stroke={el.stroke?.color || 'none'}
                    strokeWidth={el.stroke?.width || 0}
                    strokeDasharray={dash(el.stroke)}
                    strokeLinecap={el.stroke?.style === 'dotted' ? 'round' : 'butt'}
                />
            </svg>
            {hasText ? (
                <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: VALIGN[el.valign || 'middle'], justifyContent: 'center', padding: '6%' }}>
                    <div style={{ ...textStyle(el), width: '100%' }}>
                        {editing ? <EditableText el={el} ctx={ctx} />
                            : (el.fit === 'squeeze' || el.fit === 'shrink') && !(el.list && el.list !== 'none')
                                // The text area inside a shape is its box less 6% padding each side.
                                ? <FitLine el={el} width={el.width * 0.88}><TextBody el={el} ctx={ctx} /></FitLine>
                                : <div dir="auto"><TextBody el={el} ctx={ctx} /></div>}
                    </div>
                </div>
            ) : null}
        </>
    );
}

function LineElement({ el }) {
    const s = el.stroke || { color: '#000', width: 2, style: 'solid' };
    const h = Math.max(el.height, s.width);
    const y = h / 2;
    const head = s.width * 3 + 4;
    const arrow = (x, dir) => `${x},${y} ${x - dir * head},${y - head * 0.6} ${x - dir * head},${y + head * 0.6}`;
    const x1 = el.capStart === 'arrow' ? head * 0.8 : 0;
    const x2 = el.capEnd === 'arrow' ? el.width - head * 0.8 : el.width;

    return (
        <svg width={el.width} height={h} viewBox={`0 0 ${el.width} ${h}`} style={{ position: 'absolute', left: 0, top: (el.height - h) / 2, overflow: 'visible' }}>
            <line x1={x1} y1={y} x2={x2} y2={y} stroke={s.color} strokeWidth={s.width} strokeDasharray={dash(s)} strokeLinecap={s.style === 'dotted' ? 'round' : 'butt'} />
            {el.capEnd === 'arrow' && <polygon points={arrow(el.width, 1)} fill={s.color} />}
            {el.capStart === 'arrow' && <polygon points={arrow(0, -1)} fill={s.color} />}
            {el.capEnd === 'circle' && <circle cx={el.width - s.width * 1.5} cy={y} r={s.width * 1.5} fill={s.color} />}
            {el.capStart === 'circle' && <circle cx={s.width * 1.5} cy={y} r={s.width * 1.5} fill={s.color} />}
        </svg>
    );
}

export function GraphicSvg({ g, colors = {}, width = '100%', height = '100%', preserve = 'none' }) {
    return (
        <svg width={width} height={height} viewBox={g.viewBox} preserveAspectRatio={preserve === 'none' ? 'none' : 'xMidYMid meet'} style={{ display: 'block' }}>
            {g.parts.map((p, i) => {
                const base = g.palette[p.c];
                return <path key={i} d={p.d} fill={colors[base] || base} fillRule={p.rule || 'nonzero'} />;
            })}
        </svg>
    );
}

/** What a picture field looks like before it is filled with a real one. */
export function FieldPlaceholder({ field, label, qrStyle = null }) {
    if (field === 'qr') {
        return <div style={{ width: '100%', height: '100%', background: '#fff' }}><QrSvg text={QR_SAMPLE} style={qrStyle || CLASSIC} /></div>;
    }
    if (/_flag$/.test(field)) {
        // A flag still to come: three bands and a hoist, with whose flag it is written across.
        return (
            <div style={{ width: '100%', height: '100%', position: 'relative', overflow: 'hidden', borderRadius: 2, boxShadow: 'inset 0 0 0 1px rgba(15,23,42,.18)' }}>
                <svg viewBox="0 0 40 30" width="100%" height="100%" preserveAspectRatio="none" style={{ display: 'block' }}>
                    <rect width="40" height="10" fill="#c7d2fe" /><rect y="10" width="40" height="10" fill="#eef2ff" /><rect y="20" width="40" height="10" fill="#a5b4fc" />
                    <path d="M0 0L14 15L0 30Z" fill="#6366f1" />
                </svg>
                {label && <span style={{ position: 'absolute', inset: 'auto 0 0 0', padding: '1px 2px', font: '600 8px Inter, sans-serif', color: '#312e81', background: 'rgba(255,255,255,.75)', textAlign: 'center', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{label}</span>}
            </div>
        );
    }
    if (field === 'photo') {
        return (
            <svg viewBox="0 0 30 40" width="100%" height="100%" preserveAspectRatio="xMidYMid slice" style={{ display: 'block', background: 'hsl(250 55% 60%)' }}>
                <circle cx="15" cy="15" r="7" fill="rgba(255,255,255,.85)" />
                <path d="M2 40 C 3 27 9 24 15 24 C 21 24 27 27 28 40 Z" fill="rgba(255,255,255,.85)" />
            </svg>
        );
    }
    return (
        <div style={{ width: '100%', height: '100%', display: 'grid', placeItems: 'center', border: '2px dashed #a5b4fc', borderRadius: 8, color: '#6366f1', font: '600 12px Inter, sans-serif', textAlign: 'center' }}>
            {label || field}
        </div>
    );
}

function ImageElement({ el, ctx }) {
    const style = { position: 'absolute', inset: 0, borderRadius: el.radius || 0, overflow: 'hidden' };
    const border = el.stroke ? { boxShadow: `inset 0 0 0 ${el.stroke.width}px ${el.stroke.color}` } : null;
    let body = null;
    if (el.graphic) {
        const g = graphic(el.graphic);
        body = g ? <GraphicSvg g={g} colors={el.colors || {}} /> : null;
    } else if (el.field) {
        const value = ctx.values?.[el.field];
        // A styled QR is drawn from the link itself (vector); without a link to draw, the old picture prints.
        const qrText = el.field === 'qr' && el.qrStyle ? (ctx.values ? ctx.values.qr_text : QR_SAMPLE) : null;
        body = qrText
            ? <QrSvg text={qrText} style={el.qrStyle} />
            : value
            ? <img src={value} alt="" draggable={false} style={{ width: '100%', height: '100%', objectFit: el.fit === 'fill' ? 'fill' : el.fit || 'cover', display: 'block' }} />
            : ctx.values ? null : <FieldPlaceholder field={el.field} label={ctx.fields?.[el.field]?.label} qrStyle={el.qrStyle} />;
    } else if (el.asset) {
        const a = ctx.assets?.[el.asset];
        body = a ? <img src={a.url} alt="" draggable={false} style={{ width: '100%', height: '100%', objectFit: el.fit === 'fill' ? 'fill' : el.fit || 'cover', display: 'block' }} /> : <div className="dz-missing" />;
    }

    return (
        <div style={style}>
            {body}
            {border && <div style={{ position: 'absolute', inset: 0, borderRadius: el.radius || 0, ...border }} />}
        </div>
    );
}

export function ElementView({ el, ctx }) {
    const flip = `${el.flipX ? ' scaleX(-1)' : ''}${el.flipY ? ' scaleY(-1)' : ''}`;
    const isText = el.type === 'text';
    const style = {
        position: 'absolute',
        left: 0,
        top: 0,
        width: el.width,
        height: isText ? 'auto' : el.height,
        transform: `translate(${el.x}px, ${el.y}px) rotate(${el.rotation || 0}deg)${flip}`,
        transformOrigin: isText ? `${el.width / 2}px ${el.height / 2}px` : 'center',
        opacity: el.opacity ?? 1,
        // A shadow falls under the drawn shape or picture itself, not under its box.
        filter: el.type !== 'text' && el.shadow ? `drop-shadow(${shadowCss(el.shadow)})` : undefined,
    };

    let inner = null;
    if (el.type === 'text') inner = <TextElement el={el} ctx={ctx} />;
    else if (el.type === 'shape') inner = <ShapeElement el={el} ctx={ctx} />;
    else if (el.type === 'line') inner = <LineElement el={el} />;
    else if (el.type === 'image') inner = <ImageElement el={el} ctx={ctx} />;
    else if (el.type === 'group') inner = el.children.map((c) => <ElementView key={c.id} el={c} ctx={{ ...ctx, measure: null }} />);

    return (
        <div className="dz-el" data-id={el.id} data-type={el.type} style={style}>
            {inner}
        </div>
    );
}

export function PageView({ page, size, ctx, className = '', style }) {
    return (
        <div className={`dz-page ${className}`} style={{ width: size.w, height: size.h, background: page.background || '#fff', position: 'relative', overflow: 'hidden', ...style }}>
            {page.elements.map((el) => <ElementView key={el.id} el={el} ctx={ctx} />)}
        </div>
    );
}
