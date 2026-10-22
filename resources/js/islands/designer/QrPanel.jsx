/**
 * The QR style panel: presets, the three shape families (body, eye frame, eye
 * ball), colours with an optional gradient, the quiet margin and the error
 * correction level. Every tile is drawn by the same QrSvg that prints, so a
 * tile shows exactly what the choice will look like on paper.
 */
import React, { useState } from 'react';
import { useDesigner, t } from './context';
import { useSelected } from './store';
import { SidePanel, ColorChooser } from './panels';
import { Icon, Slider } from './ui';
import QrSvg, { PRESETS, resolveStyle } from './qr/QrSvg';
import { BODY, FRAMES, BALLS, framePath, ballPath } from './qr/shapes';
import { RISKY } from './qr/risky';

const risky = (body, frame, ball) => RISKY.has(`${body}|${frame}|${ball}`);

// Tiles encode a short word (a 21×21 code) so thirty of them stay light.
const TILE_TEXT = 'TAKEONE';

const Section = ({ title, children, action }) => (
    <section className="dz-sec">
        <div className="dz-sec-head"><h3>{title}</h3>{action}</div>
        {children}
    </section>
);

function luminance(hex) {
    if (!/^#[0-9a-f]{6}/i.test(hex || '')) return 1;
    const [r, g, b] = [1, 3, 5].map((i) => {
        const v = parseInt(hex.slice(i, i + 2), 16) / 255;
        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** What could stop a phone reading this code — warnings, never a block. */
export function qrWarnings(s) {
    const out = [];
    const bg = s.bg && s.bg !== 'transparent' ? s.bg : '#ffffff';
    const inks = [s.gradient?.stops?.length > 1 ? s.gradient.stops.map(([c]) => c) : [s.bodyColor], s.eyeFrameColor, s.eyeBallColor].flat().filter(Boolean);
    const lb = luminance(bg);
    const worst = Math.min(...inks.map((c) => { const l = luminance(c); return (Math.max(l, lb) + 0.05) / (Math.min(l, lb) + 0.05); }));
    if (worst < 3) out.push('qr_warn_contrast');
    if (inks.some((c) => luminance(c) > lb)) out.push('qr_warn_inverted');
    if ((Number(s.margin) || 0) < 1) out.push('qr_warn_margin');
    if (s.bg === 'transparent') out.push('qr_warn_transparent');
    if (risky(s.body, s.eyeFrame, s.eyeBall)) out.push('qr_warn_combo');
    return out;
}

function ColorRow({ label, value, onPick, allowNone = false, allowTransparent = false }) {
    const { store } = useDesigner();
    const [open, setOpen] = useState(false);
    return (
        <div className="dz-qr-color">
            <button type="button" className="dz-qr-color-head" onClick={() => setOpen((v) => !v)} aria-expanded={open}>
                <span className={`dz-swatch ${!value || value === 'transparent' ? 'is-empty' : ''}`} style={{ background: value && value !== 'transparent' ? value : undefined }} />
                <span>{label}</span>
                <small>{value === 'transparent' ? t('qr_transparent') : value || t('qr_same_as_body')}</small>
                <Icon name={open ? 'chevron-up' : 'chevron-down'} />
            </button>
            {open && (
                <div className="dz-qr-color-body">
                    {/* The same chooser as text and shapes: search, eyedropper, the design's own colours, custom. */}
                    <ColorChooser
                        value={value && value !== 'transparent' ? value : null}
                        onPick={onPick}
                        onBegin={() => store.begin()}
                        onCommit={() => store.end()}
                        fallback="#000000"
                        extra={<>
                            {allowNone && <button type="button" className={`dz-sw is-tool ${!value ? 'is-on' : ''}`} onClick={() => onPick(null)} title={t('qr_same_as_body')} aria-label={t('qr_same_as_body')}><Icon name="link-45deg" /></button>}
                            {allowTransparent && <button type="button" className={`dz-sw is-empty ${value === 'transparent' ? 'is-on' : ''}`} onClick={() => onPick('transparent')} title={t('qr_transparent')} aria-label={t('qr_transparent')} />}
                        </>}
                    />
                </div>
            )}
        </div>
    );
}

export default function QrPanel() {
    const { store, actions } = useDesigner();
    const el = useSelected(store)[0];
    if (!el || el.field !== 'qr') return null;
    const styled = Boolean(el.qrStyle);
    const s = resolveStyle(el.qrStyle);
    const set = (patch, live = false) => {
        const next = { ...s, ...patch, v: 1 };
        return live ? actions.live({ qrStyle: next }) : actions.update({ qrStyle: next });
    };
    const warnings = styled ? qrWarnings(s) : [];
    const g = s.gradient;
    const same = (a, b) => JSON.stringify({ ...resolveStyle(a), eyes: {} }) === JSON.stringify({ ...resolveStyle(b), eyes: {} });

    const tile = (key, on, art, label, pick, warn = false) => (
        <button key={key} type="button" className={`dz-tile dz-qr-tile ${on ? 'is-on' : ''} ${warn ? 'is-risky' : ''}`} onClick={pick} title={warn ? `${label} — ${t('qr_tile_risky')}` : label} aria-pressed={on}>
            <span className="dz-tile-art">{art}</span>
            <small>{label}</small>
        </button>
    );
    const eyeArt = (frame, ball) => (
        <svg viewBox="-0.5 -0.5 8 8" width="44" height="44">
            <path d={framePath(frame)} fill={s.eyeFrameColor || s.bodyColor} fillRule="evenodd" />
            <path d={ballPath(ball)} fill={s.eyeBallColor || s.eyeFrameColor || s.bodyColor} />
        </svg>
    );
    const label = (k) => t(`qr_shape_${k.replace(/-/g, '_')}`);

    return (
        <SidePanel title={t('qr_style')} onClose={() => store.set({ panel: null })}>
            {!styled && <p className="dz-note">{t('qr_plain_note')}</p>}
            {warnings.map((w) => <p key={w} className="dz-note dz-qr-warn"><Icon name="exclamation-triangle" /> {t(w)}</p>)}

            <Section title={t('qr_presets')}>
                <div className="dz-tiles is-3">
                    {PRESETS.map((p) => tile(p.key, styled && same(p.style, s), <QrSvg text={TILE_TEXT} style={p.style} />, t(`qr_preset_${p.key}`),
                        () => actions.update({ qrStyle: { ...p.style, eyes: {} } })))}
                </div>
            </Section>

            <Section title={t('qr_body')}>
                <div className="dz-tiles is-4">
                    {BODY.map((k) => tile(k, s.body === k, <QrSvg text={TILE_TEXT} style={{ ...s, body: k, gradient: null, bg: '#ffffff', margin: 0 }} />, label(k), () => set({ body: k }), risky(k, s.eyeFrame, s.eyeBall)))}
                </div>
            </Section>

            <Section title={t('qr_eye_frame')}>
                <div className="dz-tiles is-4">
                    {FRAMES.map((k) => tile(k, s.eyeFrame === k, eyeArt(k, s.eyeBall), label(k), () => set({ eyeFrame: k }), risky(s.body, k, s.eyeBall)))}
                </div>
            </Section>

            <Section title={t('qr_eye_ball')}>
                <div className="dz-tiles is-4">
                    {BALLS.map((k) => tile(k, s.eyeBall === k, eyeArt(s.eyeFrame, k), label(k), () => set({ eyeBall: k }), risky(s.body, s.eyeFrame, k)))}
                </div>
            </Section>

            <Section title={t('qr_colors')}>
                <ColorRow label={t('qr_body_color')} value={s.bodyColor} onPick={(c, live) => set({ bodyColor: c }, live)} />
                <ColorRow label={t('qr_eye_frame_color')} value={s.eyeFrameColor} allowNone onPick={(c, live) => set({ eyeFrameColor: c }, live)} />
                <ColorRow label={t('qr_eye_ball_color')} value={s.eyeBallColor} allowNone onPick={(c, live) => set({ eyeBallColor: c }, live)} />
                <ColorRow label={t('qr_background')} value={s.bg} allowTransparent onPick={(c, live) => set({ bg: c }, live)} />
            </Section>

            <Section title={t('gradient')} action={
                <button type="button" className={`dz-switch ${g ? 'is-on' : ''}`} role="switch" aria-checked={Boolean(g)}
                    onClick={() => set({ gradient: g ? null : { type: 'linear', angle: 135, stops: [[s.bodyColor, 0], ['#7c6bf5', 1]] } })}><span /></button>
            }>
                {g && (
                    <>
                        <div className="dz-seg">
                            {['linear', 'radial'].map((k) => <button key={k} type="button" className={g.type === k ? 'is-on' : ''} onClick={() => set({ gradient: { ...g, type: k } })}>{t(`qr_gradient_${k}`)}</button>)}
                        </div>
                        <ColorRow label={t('qr_gradient_from')} value={g.stops[0][0]} onPick={(c, live) => set({ gradient: { ...g, stops: [[c, 0], g.stops[1]] } }, live)} />
                        <ColorRow label={t('qr_gradient_to')} value={g.stops[1][0]} onPick={(c, live) => set({ gradient: { ...g, stops: [g.stops[0], [c, 1]] } }, live)} />
                        {g.type === 'linear' && <Slider label={t('gradient_angle')} value={g.angle ?? 135} min={0} max={360} suffix="°" onBegin={() => store.begin()} onLive={(n) => set({ gradient: { ...g, angle: n } }, true)} onCommit={() => store.end()} />}
                        <label className="dz-qr-check"><input type="checkbox" checked={Boolean(s.gradientOnEyes)} onChange={(e) => set({ gradientOnEyes: e.target.checked })} /> {t('qr_gradient_on_eyes')}</label>
                    </>
                )}
            </Section>

            <Section title={t('qr_more')}>
                <Slider label={t('qr_margin')} value={s.margin} min={0} max={6} onBegin={() => store.begin()} onLive={(n) => set({ margin: n }, true)} onCommit={() => store.end()} />
                <p className="dz-qr-sub">{t('qr_ec')}</p>
                <div className="dz-seg">
                    {['auto', 'L', 'M', 'Q', 'H'].map((k) => <button key={k} type="button" className={(s.ecLevel || 'auto') === k ? 'is-on' : ''} onClick={() => set({ ecLevel: k })}>{k === 'auto' ? t('qr_ec_auto') : k}</button>)}
                </div>
                <p className="dz-qr-hint">{t('qr_ec_hint')}</p>
            </Section>

            {styled && (
                <button type="button" className="dz-tile-btn dz-qr-reset" onClick={() => actions.update({ qrStyle: null })}>
                    <Icon name="arrow-counterclockwise" /> {t('qr_reset')}
                </button>
            )}
        </SidePanel>
    );
}
