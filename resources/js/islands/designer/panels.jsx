/**
 * The side panels (spec §5, §7, §8): Position (Arrange | Layers), Colour, Font,
 * Text, Elements, Uploads, Fields, Size, Notes and the shortcut sheet. Each is
 * a plain component; SidePanel frames whichever one is open.
 */
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useDesigner, t } from './context';
import { useStore, useSelected } from './store';
import * as M from './model';
import { aabb, intersects, pxToMm, mmToPx, round } from './geometry';
import { GRAPHICS, SHAPES, DEFAULT_COLORS, shapeElement, lineElement, graphicElement, assetElement, fieldElement, textElement, shapeGeometry } from './library';
import { ElementView, GraphicSvg, FieldPlaceholder } from './Renderer';
import { ensureFont, ensureFonts, fontsToLoad, ensureFontPreview, fontGroup, fontList, scriptsOf, covers, companion, SCRIPTS } from './fonts';
import { Icon, Tip, NumberField, Slider, useConfirm, toast } from './ui';

export function SidePanel({ title, children, onClose, wide = false, tabs = null }) {
    return (
        <aside className={`dz-panel ${wide ? 'is-wide' : ''}`} aria-label={title}>
            <header className="dz-panel-head">
                {/* On a phone the panel is a bottom sheet: this is its drag handle (Design Rule #8). */}
                <span className="dz-sheet-handle" aria-hidden="true" />
                <h2>{title}</h2>
                <button type="button" className="dz-icon-btn" onClick={onClose} aria-label={t('close')}><Icon name="x-lg" /></button>
            </header>
            {tabs}
            <div className="dz-panel-body">{children}</div>
            <button type="button" className="dz-panel-collapse" onClick={onClose} aria-label={t('close')}><Icon name="chevron-left" /></button>
        </aside>
    );
}

const Section = ({ title, children, action }) => (
    <section className="dz-sec">
        {title && <div className="dz-sec-head"><h3>{title}</h3>{action}</div>}
        {children}
    </section>
);

/* ── Position ───────────────────────────────────────────────────────── */

function LayerThumb({ el, ctx }) {
    const k = Math.min(160 / Math.max(el.width, 1), 44 / Math.max(el.height, 1), 1);
    return (
        <div className="dz-layer-thumb">
            <div style={{ position: 'relative', width: el.width * k, height: el.height * k }}>
                <div style={{ transform: `scale(${k})`, transformOrigin: '0 0', position: 'absolute', inset: 0 }}>
                    <ElementView el={{ ...el, x: 0, y: 0, rotation: 0 }} ctx={{ ...ctx, measure: null, editingId: null }} />
                </div>
            </div>
        </div>
    );
}

export function PositionPanel({ ctx }) {
    const { store, actions } = useDesigner();
    const els = useSelected(store);
    const elements = useStore(store, (s) => M.page(s.doc, s.pageIndex).elements);
    const selection = useStore(store, (s) => s.selection);
    const tab = useStore(store, (s) => s.positionTab || 'arrange');
    const [filter, setFilter] = useState('all');
    const [lockRatio, setLockRatio] = useState(true);
    const [drag, setDrag] = useState(null);
    const el = els.length === 1 ? els[0] : null;
    const range = M.layerRange(elements, selection);
    const multi = els.length > 1;
    const mm = (v) => round(pxToMm(v), 2);

    const list = useMemo(() => {
        const ordered = elements.slice().reverse();
        if (filter !== 'overlap' || !els.length) return ordered;
        const boxes = els.map(aabb);
        return ordered.filter((e) => selection.includes(e.id) || boxes.some((b) => intersects(aabb(e), b)));
    }, [elements, filter, els, selection]);

    const tabs = (
        <div className="dz-tabs" role="tablist">
            {['arrange', 'layers'].map((k) => (
                <button key={k} type="button" role="tab" aria-selected={tab === k} className={tab === k ? 'is-on' : ''} onClick={() => store.set({ positionTab: k })}>{t(k)}</button>
            ))}
        </div>
    );

    return (
        <SidePanel title={t('position')} onClose={() => store.set({ panel: null })} tabs={tabs}>
            {tab === 'arrange' ? (
                <>
                    <Section title={t('layer_order')}>
                        <div className="dz-grid2">
                            <button type="button" className="dz-tile-btn" disabled={!range.front} onClick={() => actions.layer('forward')}><Icon name="layer-forward" /> {t('forward')}</button>
                            <button type="button" className="dz-tile-btn" disabled={!range.back} onClick={() => actions.layer('backward')}><Icon name="layer-backward" /> {t('backward')}</button>
                            <button type="button" className="dz-tile-btn" disabled={!range.front} onClick={() => actions.layer('front')}><Icon name="front" /> {t('to_front')}</button>
                            <button type="button" className="dz-tile-btn" disabled={!range.back} onClick={() => actions.layer('back')}><Icon name="back" /> {t('to_back')}</button>
                        </div>
                    </Section>
                    <Section title={multi ? t('align_elements') : t('align_to_page')}>
                        <div className="dz-grid2">
                            {[['top', 'align-top'], ['left', 'align-start'], ['middle', 'align-middle'], ['center', 'align-center'], ['bottom', 'align-bottom'], ['right', 'align-end']].map(([k, ic]) => (
                                <button key={k} type="button" className="dz-tile-btn" disabled={!els.length} onClick={() => actions.align(k)}><Icon name={ic} /> {t(`align_${k}`)}</button>
                            ))}
                        </div>
                        {els.length > 2 && (
                            <div className="dz-grid2" style={{ marginTop: 8 }}>
                                <button type="button" className="dz-tile-btn" onClick={() => actions.distribute('x')}><Icon name="distribute-horizontal" /> {t('distribute_h')}</button>
                                <button type="button" className="dz-tile-btn" onClick={() => actions.distribute('y')}><Icon name="distribute-vertical" /> {t('distribute_v')}</button>
                            </div>
                        )}
                    </Section>
                    {el && (
                        <Section title={t('advanced')}>
                            <div className="dz-adv">
                                <NumberField label={t('width')} value={mm(el.width)} suffix="mm" decimals={2} min={1} onCommit={(n) => actions.setGeometry('width', mmToPx(n), lockRatio)} />
                                <NumberField label={t('height')} value={mm(el.height)} suffix="mm" decimals={2} min={1} disabled={el.type === 'text'} onCommit={(n) => actions.setGeometry('height', mmToPx(n), lockRatio)} />
                                <Tip label={t('ratio')}>
                                    <button type="button" className={`dz-icon-btn dz-ratio ${lockRatio ? 'is-on' : ''}`} onClick={() => setLockRatio((v) => !v)} aria-pressed={lockRatio} aria-label={t('ratio')}><Icon name={lockRatio ? 'link-45deg' : 'link'} /></button>
                                </Tip>
                                <NumberField label="X" value={mm(el.x)} suffix="mm" decimals={2} onCommit={(n) => actions.setGeometry('x', mmToPx(n))} />
                                <NumberField label="Y" value={mm(el.y)} suffix="mm" decimals={2} onCommit={(n) => actions.setGeometry('y', mmToPx(n))} />
                                <NumberField label={t('rotate')} value={el.rotation || 0} suffix="°" decimals={1} onCommit={(n) => actions.setGeometry('rotation', n)} />
                            </div>
                        </Section>
                    )}
                </>
            ) : (
                <>
                    <div className="dz-pills">
                        {['all', 'overlap'].map((k) => <button key={k} type="button" className={filter === k ? 'is-on' : ''} onClick={() => setFilter(k)}>{t(`layers_${k}`)}</button>)}
                    </div>
                    <ol className="dz-layers">
                        {list.map((e) => (
                            <li
                                key={e.id}
                                className={`dz-layer ${selection.includes(e.id) ? 'is-on' : ''} ${drag?.over === e.id ? 'is-over' : ''}`}
                                draggable
                                onDragStart={(ev) => { ev.dataTransfer.effectAllowed = 'move'; setDrag({ id: e.id }); }}
                                onDragOver={(ev) => { ev.preventDefault(); if (drag && drag.over !== e.id) setDrag({ ...drag, over: e.id }); }}
                                onDrop={(ev) => {
                                    ev.preventDefault();
                                    if (drag && drag.id !== e.id) actions.moveLayer(drag.id, elements.findIndex((x) => x.id === e.id));
                                    setDrag(null);
                                }}
                                onDragEnd={() => setDrag(null)}
                                onClick={(ev) => (ev.shiftKey ? actions.toggleSelect(e.id) : actions.select([e.id]))}
                            >
                                <span className="dz-layer-grip"><Icon name="grip-vertical" /></span>
                                <LayerThumb el={e} ctx={ctx} />
                                {e.locked && <Icon name="lock-fill" className="dz-layer-lock" />}
                            </li>
                        ))}
                    </ol>
                </>
            )}
        </SidePanel>
    );
}

/* ── Colour ─────────────────────────────────────────────────────────── */

const NAMED = { black: '#000000', white: '#ffffff', red: '#ff3131', green: '#00bf63', blue: '#004aad', navy: '#1e3a8a', gold: '#d4a017', yellow: '#ffde59', orange: '#ff914d', purple: '#8c52ff', pink: '#ff66c4', grey: '#737373', gray: '#737373', teal: '#0097b2' };

function hsvToHex(h, s, v) {
    const f = (n) => {
        const k = (n + h / 60) % 6;
        return Math.round(255 * (v - v * s * Math.max(0, Math.min(k, 4 - k, 1))));
    };
    return `#${[f(5), f(3), f(1)].map((x) => x.toString(16).padStart(2, '0')).join('')}`;
}
function hexToHsv(hex) {
    const n = parseInt((hex || '#000000').slice(1, 7), 16);
    const r = (n >> 16) / 255; const g = ((n >> 8) & 255) / 255; const b = (n & 255) / 255;
    const max = Math.max(r, g, b); const d = max - Math.min(r, g, b);
    let h = 0;
    if (d) h = max === r ? ((g - b) / d) % 6 : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
    return { h: (h * 60 + 360) % 360, s: max ? d / max : 0, v: max };
}

export function CustomPicker({ value, onBegin, onLive, onCommit }) {
    const [hsv, setHsv] = useState(() => hexToHsv(value));
    const [hex, setHex] = useState(value || '#000000');
    const sq = useRef(null);
    const drag = (e, fn) => {
        onBegin();
        const move = (ev) => fn(ev);
        const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); onCommit(); };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        fn(e);
    };
    const pick = (next) => {
        setHsv(next);
        const h = hsvToHex(next.h, next.s, next.v);
        setHex(h);
        onLive(h);
    };
    const onSq = (ev) => {
        const r = sq.current.getBoundingClientRect();
        pick({ ...hsv, s: Math.min(1, Math.max(0, (ev.clientX - r.left) / r.width)), v: 1 - Math.min(1, Math.max(0, (ev.clientY - r.top) / r.height)) });
    };

    return (
        <div className="dz-picker">
            <div ref={sq} className="dz-picker-sq" style={{ background: `hsl(${hsv.h} 100% 50%)` }} onPointerDown={(e) => drag(e, onSq)}>
                <span style={{ left: `${hsv.s * 100}%`, top: `${(1 - hsv.v) * 100}%`, background: hex }} />
            </div>
            <input className="dz-hue" type="range" min="0" max="360" value={hsv.h} onPointerDown={onBegin} onPointerUp={onCommit} onChange={(e) => pick({ ...hsv, h: Number(e.target.value) })} />
            <label className="dz-hex">
                <span className="dz-swatch" style={{ background: hex }} />
                <input value={hex} onChange={(e) => setHex(e.target.value)} onKeyDown={(e) => {
                    e.stopPropagation();
                    if (e.key === 'Enter' && /^#[0-9a-f]{6}$/i.test(hex)) { onBegin(); onLive(hex.toLowerCase()); onCommit(); setHsv(hexToHsv(hex)); }
                }} />
            </label>
        </div>
    );
}

/**
 * The one colour chooser — search by name or hex, the eyedropper, a custom
 * picker, the colours already in the document and the defaults. The colour
 * panel (text, fill, stroke, page) and the QR panel both draw it, so picking a
 * colour works the same everywhere. `extra` adds swatches at the front (none,
 * transparent); `middle` sits between the document's colours and the defaults.
 */
export function ColorChooser({ value, onPick, onBegin, onCommit, extra = null, middle = null, fallback = '#7c6bf5' }) {
    const { store } = useDesigner();
    const doc = useStore(store, (s) => s.doc);
    const [q, setQ] = useState('');
    const [custom, setCustom] = useState(false);
    const used = useMemo(() => M.documentColors(doc), [doc]);
    const query = q.trim().toLowerCase();
    const searchHit = /^#?[0-9a-f]{6}$/.test(query) ? `#${query.replace('#', '')}` : NAMED[query];
    const swatch = (c, key) => (
        <button key={key || c} type="button" className={`dz-sw ${value?.slice(0, 7) === c ? 'is-on' : ''} ${c === 'transparent' ? 'is-empty' : ''}`} style={{ background: c === 'transparent' ? undefined : c }} title={c} onClick={() => onPick(c)} aria-label={c} />
    );

    return (
        <>
            <div className="dz-search">
                <Icon name="search" />
                <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('color_search')} onKeyDown={(e) => { e.stopPropagation(); if (e.key === 'Enter' && searchHit) onPick(searchHit); }} />
            </div>
            {searchHit && <div className="dz-swatches" style={{ marginTop: 10 }}>{swatch(searchHit)}</div>}
            <Section title={t('document_colors')}>
                <div className="dz-swatches">
                    <Tip label={t('custom_color')}><button type="button" className={`dz-sw is-rainbow ${custom ? 'is-on' : ''}`} onClick={() => setCustom((v) => !v)} aria-label={t('custom_color')}><Icon name="plus-lg" /></button></Tip>
                    {'EyeDropper' in window && (
                        <Tip label={t('eyedropper')}>
                            <button type="button" className="dz-sw is-tool" aria-label={t('eyedropper')} onClick={async () => {
                                try { const r = await new window.EyeDropper().open(); onPick(r.sRGBHex); } catch { /* cancelled */ }
                            }}><Icon name="eyedropper" /></button>
                        </Tip>
                    )}
                    {extra}
                    {used.map((c) => swatch(c))}
                </div>
                {custom && <CustomPicker value={value?.startsWith('#') ? value.slice(0, 7) : fallback} onBegin={onBegin} onLive={(c) => onPick(c, true)} onCommit={onCommit} />}
            </Section>
            {middle}
            <Section title={t('default_colors')}>
                <div className="dz-swatches is-grid">{DEFAULT_COLORS.map((c) => swatch(c))}</div>
            </Section>
        </>
    );
}

export function ColorPanel() {
    const { store, actions } = useDesigner();
    const target = useStore(store, (s) => s.colorTarget) || { prop: 'color' };
    const els = useSelected(store);
    const pageBg = useStore(store, (s) => M.page(s.doc, s.pageIndex).background);
    const el = els[0];

    const current = target.prop === 'page' ? pageBg
        : target.prop === 'graphic' ? (el?.colors?.[target.from] || target.from)
            : target.prop === 'stroke' ? el?.stroke?.color
                : el?.[target.prop];

    const patchFor = (color) => (e) => {
        if (target.prop === 'stroke') return { stroke: { width: 2, style: 'solid', ...(e.stroke || {}), color } };
        if (target.prop === 'graphic') return { colors: { ...(e.colors || {}), [target.from]: color } };
        return { [target.prop]: color };
    };
    const apply = (color, live = false) => {
        if (target.prop === 'page') return actions.updatePage({ background: color }, !live);
        return live ? actions.live(patchFor(color)) : actions.update(patchFor(color));
    };
    const transparent = target.prop === 'fill' || target.prop === 'page';

    return (
        <SidePanel title={t('color')} onClose={() => store.set({ panel: null })}>
            <ColorChooser
                value={current}
                onPick={apply}
                onBegin={() => (target.prop === 'page' ? store.commit() : store.begin())}
                onCommit={() => store.end()}
                extra={transparent ? (
                    <button type="button" className={`dz-sw is-empty ${current === 'transparent' ? 'is-on' : ''}`} title="transparent" onClick={() => apply('transparent')} aria-label="transparent" />
                ) : null}
                middle={target.prop === 'fill' && el?.type === 'shape' ? (
                    <Section title={t('gradient')} action={
                        <button type="button" className={`dz-switch ${el.fill2 ? 'is-on' : ''}`} role="switch" aria-checked={Boolean(el.fill2)} onClick={() => actions.update({ fill2: el.fill2 ? null : '#ffffff' })}><span /></button>
                    }>
                        {el.fill2 && (
                            <>
                                <div className="dz-swatches">
                                    {DEFAULT_COLORS.slice(0, 14).map((c) => (
                                        <button key={c} type="button" className={`dz-sw ${el.fill2 === c ? 'is-on' : ''}`} style={{ background: c }} onClick={() => actions.update({ fill2: c })} aria-label={c} />
                                    ))}
                                </div>
                                <Slider label={t('gradient_angle')} value={el.gradientAngle ?? 90} min={0} max={360} suffix="°" onBegin={() => store.begin()} onLive={(n) => actions.live({ gradientAngle: n })} onCommit={() => store.end()} />
                            </>
                        )}
                    </Section>
                ) : null}
            />
        </SidePanel>
    );
}

/* ── Font ───────────────────────────────────────────────────────────── */

// A line in the family's own script, so an Arabic or Japanese face shows what it is for.
const FONT_SAMPLES = { arabic: 'أبجد هوز حطي', japanese: 'あいうえお 日本語' };

function FontRow({ family, current, onPick, note }) {
    const ref = useRef(null);
    const [ready, setReady] = useState(false);
    useEffect(() => {
        const io = new IntersectionObserver(([en]) => {
            // One face is enough to draw the name; the whole family loads when it is picked.
            if (en.isIntersecting) { ensureFontPreview(family).then(() => setReady(true)); io.disconnect(); }
        });
        io.observe(ref.current);
        return () => io.disconnect();
    }, [family]);
    const sample = FONT_SAMPLES[fontGroup(family)];
    return (
        <button ref={ref} type="button" className={`dz-font ${current ? 'is-on' : ''}`} onClick={() => onPick(family)} style={{ fontFamily: ready ? `"${family}"` : undefined }}>
            <span>{family}{sample && <small style={{ display: 'block', fontSize: '0.85em', opacity: 0.7, marginTop: 2 }} dir="auto">{sample}</small>}
                {note && <small className="dz-font-note">{note}</small>}</span>{current && <Icon name="check2" />}
        </button>
    );
}

export function FontPanel() {
    const { store, actions } = useDesigner();
    const els = useSelected(store);
    const doc = useStore(store, (s) => s.doc);
    const [q, setQ] = useState('');
    const [swap, setSwap] = useState(null);
    const current = els[0]?.fontFamily;
    const fonts = fontList();
    const docFonts = useMemo(() => M.documentFonts(doc), [doc]);
    /*
     * Which script the selected text is in — its words, with its {tokens} read as
     * their samples (a {name} that will be an Arabic name counts as Arabic). Fonts
     * that draw that script come first; every other one says which companion will
     * draw those letters for it, so nothing changes silently.
     */
    const { boot } = useDesigner();
    const sampleOf = useMemo(() => Object.fromEntries((boot.fields || []).map((f) => [f.key, f.sample || ''])), [boot]);
    const selectedText = els.map((e) => String(e.text || '').replace(/\{([a-z_]{1,40})\}/g, (m, k) => sampleOf[k] ?? m)).join(' ');
    const found = scriptsOf(selectedText);
    const need = SCRIPTS.find((s) => found[s]) || null;
    const base = ['sans', 'serif', 'display', 'script', 'mono', 'arabic', 'japanese'];
    const groups = need ? [need, ...base.filter((g) => g !== need)] : base;
    const noteFor = (family) => (need && !covers(family, need) ? t('font_companion', { script: t(`font_group_${need}`), font: companion(family, need) }) : '');
    const match = (f) => f.family.toLowerCase().includes(q.trim().toLowerCase());

    const pick = (family) => {
        const before = current;
        ensureFont(family);
        actions.update({ fontFamily: family });
        // Offer to swap the rest of the design over too.
        const others = before && before !== family && M.documentFonts(store.get().doc).includes(before);
        setSwap(others ? { from: before, to: family } : null);
    };

    return (
        <SidePanel title={t('font')} onClose={() => store.set({ panel: null })}>
            <div className="dz-search">
                <Icon name="search" />
                <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('font_search')} onKeyDown={(e) => e.stopPropagation()} />
            </div>
            {need && <p className="dz-hint" style={{ margin: '0 0 10px' }}>{t(`font_need_${need}`)}</p>}
            {!q && docFonts.length > 0 && (
                <Section title={t('document_fonts')}>
                    {docFonts.map((f) => <FontRow key={f} family={f} current={f === current} onPick={pick} note={noteFor(f)} />)}
                </Section>
            )}
            {groups.map((g) => {
                const list = fonts.filter((f) => f.group === g && match(f));
                if (!list.length) return null;
                return (
                    <Section key={g} title={t(`font_group_${g}`)}>
                        {list.map((f) => <FontRow key={f.family} family={f.family} current={f.family === current} onPick={pick} note={noteFor(f.family)} />)}
                    </Section>
                );
            })}
            {swap && (
                <div className="dz-swapbar">
                    <span><b style={{ fontFamily: `"${swap.from}"` }}>{swap.from}</b> → <b style={{ fontFamily: `"${swap.to}"` }}>{swap.to}</b></span>
                    <button type="button" className="dz-btn is-primary is-sm" onClick={() => { actions.replaceFont(swap.from, swap.to); setSwap(null); }}>{t('change_all')}</button>
                </div>
            )}
        </SidePanel>
    );
}

/* ── Text ───────────────────────────────────────────────────────────── */

export function TextPanel() {
    const { store, actions, boot } = useDesigner();
    const size = useStore(store, (s) => s.size);
    const textFields = (boot.fields || []).filter((f) => f.type === 'text');
    const combos = [
        { a: ['Cinzel', 700, 'CERTIFICATE'], b: ['Montserrat', 500, 'OF ACHIEVEMENT'] },
        { a: ['Great Vibes', 400, 'Your Name'], b: ['Lora', 400, 'with gratitude and honour'] },
        { a: ['Bebas Neue', 400, 'CHAMPION'], b: ['Poppins', 600, 'Season 2026'] },
        { a: ['Playfair Display', 800, 'Excellence'], b: ['Raleway', 500, 'AWARD OF MERIT'] },
    ];
    const addCombo = (c) => {
        const h = textElement('heading', size, c.a[2], { fontFamily: c.a[0], fontWeight: c.a[1] });
        const sub = textElement('subheading', size, c.b[2], { fontFamily: c.b[0], fontWeight: c.b[1], letterSpacing: 120 });
        sub.y = h.y + h.height + 8;
        ensureFont(c.a[0]); ensureFont(c.b[0]);
        store.commit();
        store.set({ doc: M.addElements(store.get().doc, store.get().pageIndex, [h, sub]) });
        actions.select([h.id, sub.id]);
    };

    return (
        <SidePanel title={t('text')} onClose={() => store.set({ panel: null })}>
            <button type="button" className="dz-btn is-primary is-block" onClick={() => actions.addText('plain', t('placeholder_text'))}><Icon name="fonts" /> {t('add_text_box')}</button>
            <Section title={t('default_text_styles')}>
                <button type="button" className="dz-textstyle is-h" onClick={() => actions.addText('heading', t('add_heading'))}>{t('add_heading')}</button>
                <button type="button" className="dz-textstyle is-s" onClick={() => actions.addText('subheading', t('add_subheading'))}>{t('add_subheading')}</button>
                <button type="button" className="dz-textstyle is-b" onClick={() => actions.addText('body', t('add_body'))}>{t('add_body')}</button>
            </Section>
            {textFields.length > 0 && (
                <Section title={t('live_fields')}>
                    <p className="dz-hint">{t('live_fields_hint')}</p>
                    <div className="dz-chips">{textFields.map((f) => <button key={f.key} type="button" className="dz-field-chip" onClick={() => actions.insertToken(f.key)}>{`{${f.key}}`} <small>{f.label}</small></button>)}</div>
                </Section>
            )}
            <Section title={t('font_combinations')}>
                <div className="dz-combos">
                    {combos.map((c, i) => (
                        <button key={i} type="button" className="dz-combo" onClick={() => addCombo(c)} onPointerEnter={() => { ensureFont(c.a[0]); ensureFont(c.b[0]); }}>
                            <b style={{ fontFamily: `"${c.a[0]}"`, fontWeight: c.a[1] }}>{c.a[2]}</b>
                            <small style={{ fontFamily: `"${c.b[0]}"`, fontWeight: c.b[1] }}>{c.b[2]}</small>
                        </button>
                    ))}
                </div>
            </Section>
        </SidePanel>
    );
}

/* ── Elements ───────────────────────────────────────────────────────── */

function ShapeIcon({ shape }) {
    const el = { shape, width: 56, height: shape === 'arrow' ? 36 : 56, sides: shape === 'star' ? 5 : 6, radius: 0, stroke: null };
    const g = shapeGeometry(el);
    const Tag = g.tag;
    return <svg width="56" height="56" viewBox={`0 0 56 ${el.height}`}><Tag {...g.attrs} fill="#a5b4fc" /></svg>;
}

export function ElementsPanel() {
    const { store, actions, boot } = useDesigner();
    const size = useStore(store, (s) => s.size);
    const [q, setQ] = useState('');
    const query = q.trim().toLowerCase();
    const hit = (name) => !query || name.toLowerCase().includes(query);
    const pictureFields = (boot.fields || []).filter((f) => f.type === 'image');

    return (
        <SidePanel title={t('elements')} onClose={() => store.set({ panel: null })}>
            <div className="dz-search">
                <Icon name="search" />
                <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('elements_search')} onKeyDown={(e) => e.stopPropagation()} />
            </div>
            {pictureFields.length > 0 && hit(t('live_pictures')) && (
                <Section title={t('live_pictures')}>
                    <div className="dz-tiles is-3">
                        {pictureFields.map((f) => (
                            <button key={f.key} type="button" className="dz-tile" onClick={() => actions.add(fieldElement(f.key, size))} title={f.label}>
                                <span className="dz-tile-art" style={{ width: 44, height: f.key === 'photo' ? 58 : /_flag$/.test(f.key) ? 33 : 44 }}><FieldPlaceholder field={f.key} label={f.label} /></span>
                                <small>{f.label}</small>
                            </button>
                        ))}
                    </div>
                </Section>
            )}
            {hit(t('shapes')) && (
                <Section title={t('shapes')}>
                    <div className="dz-tiles is-4">
                        {SHAPES.map((s) => <button key={s} type="button" className="dz-tile" onClick={() => actions.add(shapeElement(s, size))} aria-label={t(`shape_${s}`)} title={t(`shape_${s}`)}><ShapeIcon shape={s} /></button>)}
                    </div>
                </Section>
            )}
            {hit(t('lines')) && (
                <Section title={t('lines')}>
                    <div className="dz-tiles is-4">
                        {['solid', 'dashed', 'dotted', 'arrow'].map((v) => (
                            <button key={v} type="button" className="dz-tile" onClick={() => actions.add(lineElement(v, size))} title={t(`line_${v}`)}>
                                <svg width="56" height="20" viewBox="0 0 56 20"><line x1="4" y1="10" x2={v === 'arrow' ? 44 : 52} y2="10" stroke="#475569" strokeWidth="3" strokeDasharray={v === 'dashed' ? '8 5' : v === 'dotted' ? '0 6' : undefined} strokeLinecap={v === 'dotted' ? 'round' : 'butt'} />{v === 'arrow' && <polygon points="54,10 44,4 44,16" fill="#475569" />}</svg>
                            </button>
                        ))}
                    </div>
                </Section>
            )}
            <Section title={t('graphics')}>
                <div className="dz-tiles is-3">
                    {GRAPHICS.filter((g) => hit(g.name)).map((g) => (
                        <button key={g.key} type="button" className="dz-tile" onClick={() => actions.add(graphicElement(g.key, size))} title={g.name}>
                            <span className="dz-tile-art"><GraphicSvg g={g} preserve="meet" /></span>
                        </button>
                    ))}
                </div>
            </Section>
        </SidePanel>
    );
}

/* ── Uploads ────────────────────────────────────────────────────────── */

export function UploadsPanel() {
    const { store, actions, env } = useDesigner();
    const assets = useStore(store, (s) => s.assets);
    const size = useStore(store, (s) => s.size);
    const replacing = useStore(store, (s) => s.replacing);
    const [busy, setBusy] = useState(0);
    const [over, setOver] = useState(false);
    const [ask, confirmNode] = useConfirm();
    const input = useRef(null);

    const send = async (files) => {
        for (const f of [...files].slice(0, 20)) {
            setBusy((n) => n + 1);
            try {
                const a = await env.upload(f);
                if (a && files.length === 1 && !replacing) actions.add(assetElement(a, size));
            } catch (e) {
                toast('error', e.message || t('upload_failed'));
            } finally {
                setBusy((n) => n - 1);
            }
        }
    };
    const use = (a) => {
        if (replacing) {
            actions.update({ asset: a.uuid }, { ids: [replacing] });
            store.set({ replacing: null });
        } else {
            actions.add(assetElement(a, size));
        }
    };

    return (
        <SidePanel title={t('uploads')} onClose={() => store.set({ panel: null, replacing: null })}>
            {replacing && <div className="dz-note"><Icon name="arrow-left-right" /> {t('pick_replacement')}</div>}
            <input ref={input} type="file" accept="image/png,image/jpeg,image/webp,image/gif" multiple hidden onChange={(e) => { send(e.target.files); e.target.value = ''; }} />
            <button type="button" className="dz-btn is-primary is-block" onClick={() => input.current.click()} disabled={busy > 0}>
                {busy ? <span className="dz-spin" /> : <Icon name="cloud-arrow-up" />} {busy ? t('uploading') : t('upload_files')}
            </button>
            <div
                className={`dz-drop ${over ? 'is-over' : ''}`}
                onDragOver={(e) => { e.preventDefault(); setOver(true); }}
                onDragLeave={() => setOver(false)}
                onDrop={(e) => { e.preventDefault(); setOver(false); send(e.dataTransfer.files); }}
            >
                <Icon name="images" /> {t('drop_here')}
                <small>{t('upload_rules')}</small>
            </div>
            <div className="dz-masonry">
                {assets.map((a) => (
                    <div key={a.uuid} className="dz-asset">
                        <button type="button" onClick={() => use(a)} draggable onDragStart={(e) => e.dataTransfer.setData('application/x-dz-asset', a.uuid)}>
                            <img src={a.url} alt="" loading="lazy" />
                        </button>
                        <button type="button" className="dz-asset-del" aria-label={t('delete')} onClick={async () => {
                            const inUse = store.get().doc.pages.some((p) => p.elements.some((e) => e.asset === a.uuid));
                            if (await ask({ title: t('delete_picture'), message: inUse ? t('delete_picture_used') : t('delete_picture_hint'), confirm: t('delete'), cancel: t('cancel'), danger: true })) env.deleteAsset(a.uuid);
                        }}><Icon name="trash3" /></button>
                    </div>
                ))}
            </div>
            {confirmNode}
        </SidePanel>
    );
}

/* ── Live fields ────────────────────────────────────────────────────── */

export function FieldsPanel() {
    const { store, actions, boot } = useDesigner();
    const preview = useStore(store, (s) => s.preview);
    const size = useStore(store, (s) => s.size);
    const editing = useStore(store, (s) => s.editingId);
    const text = (boot.fields || []).filter((f) => f.type === 'text');
    const pictures = (boot.fields || []).filter((f) => f.type === 'image');

    return (
        <SidePanel title={t('fields')} onClose={() => store.set({ panel: null })}>
            <p className="dz-hint">{t('fields_hint')}</p>
            <label className="dz-toggle-row">
                <span><Icon name="eye" /> {t('preview_samples')}</span>
                <button type="button" className={`dz-switch ${preview ? 'is-on' : ''}`} role="switch" aria-checked={preview} onClick={() => store.set({ preview: !preview })}><span /></button>
            </label>
            <Section title={t('live_text')}>
                <p className="dz-hint">{editing ? t('fields_insert_caret') : t('fields_insert_new')}</p>
                <div className="dz-fieldlist">
                    {text.map((f) => (
                        <button key={f.key} type="button" className="dz-fieldrow" onPointerDown={(e) => e.preventDefault()} onClick={() => actions.insertToken(f.key)}>
                            <code>{`{${f.key}}`}</code><span>{f.label}</span><small>{f.sample}</small>
                        </button>
                    ))}
                </div>
            </Section>
            {pictures.length > 0 && (
                <Section title={t('live_pictures')}>
                    <div className="dz-tiles is-3">
                        {pictures.map((f) => (
                            <button key={f.key} type="button" className="dz-tile" onClick={() => actions.add(fieldElement(f.key, size))}>
                                <span className="dz-tile-art" style={{ width: 44, height: f.key === 'photo' ? 58 : /_flag$/.test(f.key) ? 33 : 44 }}><FieldPlaceholder field={f.key} label={f.label} /></span>
                                <small>{f.label}</small>
                            </button>
                        ))}
                    </div>
                </Section>
            )}
        </SidePanel>
    );
}

/* ── Real person ────────────────────────────────────────────────────── */

const getJson = async (url) => {
    const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
    if (!res.ok) throw new Error(String(res.status));
    return res.json();
};

/**
 * Preview the design with one REAL person instead of the samples: pick
 * somebody who holds this certificate and every field on the page — name,
 * division, club, photo, flags, QR — shows exactly what prints for them, while
 * the design stays editable. The server fills the values the same way the
 * printed page is filled (CertificatePrintable::realValues).
 */
export function RealPersonPanel() {
    const { store, boot } = useDesigner();
    const real = useStore(store, (s) => s.real);
    const [q, setQ] = useState('');
    const [people, setPeople] = useState(null);
    const [loadingId, setLoadingId] = useState(null);

    useEffect(() => {
        let gone = false;
        const timer = setTimeout(() => {
            const url = new URL(boot.endpoints.people, window.location.origin);
            if (q.trim()) url.searchParams.set('q', q.trim());
            getJson(url).then((d) => { if (!gone) setPeople(d.people || []); }).catch(() => { if (!gone) setPeople([]); });
        }, q ? 250 : 0);
        return () => { gone = true; clearTimeout(timer); };
    }, [q, boot]);

    const pick = async (p) => {
        setLoadingId(p.id);
        try {
            const d = await getJson(boot.endpoints.person.replace('__ID__', encodeURIComponent(p.id)));
            const values = d.values || {};
            // Their name may be in a script the design's fonts lack (an Arabic name): load its companion first.
            await ensureFonts(fontsToLoad(M.documentFonts(store.get().doc), Object.values(values).filter((v) => !String(v).startsWith('data:')))).catch(() => {});
            store.set({ real: { id: p.id, label: p.label, values } });
        } catch {
            toast('error', t('real_failed'));
        } finally {
            setLoadingId(null);
        }
    };

    return (
        <SidePanel title={t('real_person')} onClose={() => store.set({ panel: null })}>
            <p className="dz-hint">{t('real_person_hint')}</p>
            {real && (
                <div className="dz-real-on">
                    <span><Icon name="person-check-fill" /> {t('real_showing', { name: real.label })}</span>
                    <button type="button" className="dz-real-off" onClick={() => store.set({ real: null })}><Icon name="arrow-counterclockwise" /> {t('real_off')}</button>
                </div>
            )}
            <div className="dz-search">
                <Icon name="search" />
                <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('real_search')} onKeyDown={(e) => e.stopPropagation()} />
            </div>
            <div className="dz-fieldlist" style={{ marginTop: 10 }}>
                {people === null && <p className="dz-hint"><Icon name="hourglass-split" /> …</p>}
                {people && people.length === 0 && <p className="dz-hint">{q.trim() ? t('real_no_match') : t('real_none')}</p>}
                {(people || []).map((p) => (
                    <button key={p.id} type="button" className={`dz-realrow ${real?.id === p.id ? 'is-on' : ''}`} disabled={loadingId !== null} onClick={() => pick(p)}>
                        <span>{p.label}</span>
                        {p.sub && <small>{p.sub}</small>}
                        <Icon name={loadingId === p.id ? 'hourglass-split' : real?.id === p.id ? 'check-circle-fill' : 'chevron-right'} className="dz-realrow-i rtl-flip" />
                    </button>
                ))}
            </div>
        </SidePanel>
    );
}

/* ── Canvas size ────────────────────────────────────────────────────── */

export function SizePanel() {
    const { store, actions, boot } = useDesigner();
    const size = useStore(store, (s) => s.size);
    const offered = boot.formats || { formats: {}, custom: null };
    const [format, setFormat] = useState(size.format);
    const [orientation, setOrientation] = useState(size.orientation);
    const [w, setW] = useState(size.wMm);
    const [h, setH] = useState(size.hMm);
    const [scale, setScale] = useState(true);
    const groups = ['paper', 'card', 'poster'];

    const dims = () => {
        if (format === 'custom') return [w, h];
        const f = offered.formats[format];
        if (!f) return [size.wMm, size.hMm];
        return orientation === 'landscape' ? [f.h, f.w] : [f.w, f.h];
    };
    const [dw, dh] = dims();
    const changed = format !== size.format || orientation !== size.orientation || Math.abs(dw - size.wMm) > 0.01 || Math.abs(dh - size.hMm) > 0.01;

    return (
        <SidePanel title={t('resize')} onClose={() => store.set({ panel: null })}>
            <p className="dz-hint">{t('resize_hint')}</p>
            <div className="dz-seg is-wide">
                {['portrait', 'landscape'].map((o) => (
                    <button key={o} type="button" className={orientation === o ? 'is-on' : ''} disabled={format === 'custom'} onClick={() => setOrientation(o)}>
                        <Icon name={o === 'portrait' ? 'file-earmark' : 'file-earmark-richtext'} /> {t(o)}
                    </button>
                ))}
            </div>
            {groups.map((g) => {
                const list = Object.entries(offered.formats).filter(([, f]) => f.group === g);
                if (!list.length) return null;
                return (
                    <Section key={g} title={t(`format_group_${g}`)}>
                        <div className="dz-formats">
                            {list.map(([k, f]) => {
                                const pw = orientation === 'landscape' ? f.h : f.w;
                                const ph = orientation === 'landscape' ? f.w : f.h;
                                const kk = 34 / Math.max(pw, ph);
                                return (
                                    <button key={k} type="button" className={`dz-format ${format === k ? 'is-on' : ''}`} onClick={() => setFormat(k)}>
                                        <span className="dz-format-art"><i style={{ width: pw * kk, height: ph * kk }} /></span>
                                        <b>{f.label}</b><small>{Math.round(pw * 10) / 10} × {Math.round(ph * 10) / 10} mm</small>
                                    </button>
                                );
                            })}
                        </div>
                    </Section>
                );
            })}
            {offered.custom && (
                <Section title={t('custom_size')}>
                    <button type="button" className={`dz-format is-row ${format === 'custom' ? 'is-on' : ''}`} onClick={() => { setFormat('custom'); setW(dw); setH(dh); }}>
                        <Icon name="rulers" /> <b>{t('custom_size')}</b>
                    </button>
                    {format === 'custom' && (
                        <div className="dz-adv is-2">
                            <NumberField label={t('width')} value={w} suffix="mm" min={offered.custom.min} max={offered.custom.max} onCommit={setW} />
                            <NumberField label={t('height')} value={h} suffix="mm" min={offered.custom.min} max={offered.custom.max} onCommit={setH} />
                        </div>
                    )}
                </Section>
            )}
            <label className="dz-toggle-row">
                <span>{t('scale_content')}</span>
                <button type="button" className={`dz-switch ${scale ? 'is-on' : ''}`} role="switch" aria-checked={scale} onClick={() => setScale((v) => !v)}><span /></button>
            </label>
            <button type="button" className="dz-btn is-primary is-block" disabled={!changed} onClick={() => { actions.resize(format, format === 'custom' ? (dw > dh ? 'landscape' : 'portrait') : orientation, dw, dh, scale); store.set({ panel: null, zoomMode: 'fit' }); }}>
                {t('resize_apply', { w: Math.round(dw * 10) / 10, h: Math.round(dh * 10) / 10 })}
            </button>
        </SidePanel>
    );
}

/* ── Notes ──────────────────────────────────────────────────────────── */

export function NotesPanel() {
    const { store, actions } = useDesigner();
    const pageIndex = useStore(store, (s) => s.pageIndex);
    const p = useStore(store, (s) => M.page(s.doc, s.pageIndex));
    const [title, setTitle] = useState(p.title || '');
    const [notes, setNotes] = useState(p.notes || '');
    useEffect(() => { setTitle(p.title || ''); setNotes(p.notes || ''); }, [pageIndex]); // eslint-disable-line react-hooks/exhaustive-deps
    const save = () => { if (title !== (p.title || '') || notes !== (p.notes || '')) actions.updatePage({ title, notes }); };

    return (
        <SidePanel title={t('notes')} onClose={() => { save(); store.set({ panel: null }); }}>
            <input className="dz-input" value={title} maxLength={120} placeholder={t('page_title_placeholder', { n: pageIndex + 1 })} onChange={(e) => setTitle(e.target.value)} onBlur={save} onKeyDown={(e) => e.stopPropagation()} />
            <textarea className="dz-input dz-notes" value={notes} maxLength={5000} placeholder={t('notes_placeholder')} onChange={(e) => setNotes(e.target.value)} onBlur={save} onKeyDown={(e) => e.stopPropagation()} />
            <small className="dz-count">{notes.length}/5000</small>
        </SidePanel>
    );
}

/* ── Keyboard shortcuts ─────────────────────────────────────────────── */

export function ShortcutsPanel() {
    const { store } = useDesigner();
    const rows = [
        ['T', 'sc_text'], ['R', 'sc_rect'], ['C', 'sc_circle'], ['L', 'sc_line'],
        ['Ctrl+Z', 'undo'], ['Ctrl+Shift+Z', 'redo'], ['Ctrl+D', 'duplicate'], ['Ctrl+C / V / X', 'sc_clipboard'],
        ['Ctrl+Alt+C / V', 'sc_style'], ['Ctrl+G', 'group'], ['Ctrl+Shift+G', 'ungroup'], ['Ctrl+] / [', 'sc_layer'],
        ['Ctrl+Shift+] / [', 'sc_layer_end'], ['Alt+Shift+L', 'lock'], ['Ctrl+A', 'sc_select_all'], ['Del', 'delete'],
        ['← ↑ → ↓', 'sc_nudge'], ['Shift+←', 'sc_nudge10'], ['Enter', 'sc_edit'], ['Esc', 'sc_escape'],
        ['Alt+click', 'sc_cycle'], ['Ctrl+wheel', 'sc_zoom'], ['Ctrl+P', 'print'],
    ];
    return (
        <SidePanel title={t('shortcuts')} onClose={() => store.set({ panel: null })}>
            <dl className="dz-shortcuts">
                {rows.map(([k, l]) => <React.Fragment key={k}><dt>{t(l)}</dt><dd><kbd>{k}</kbd></dd></React.Fragment>)}
            </dl>
        </SidePanel>
    );
}
