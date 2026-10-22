/**
 * The contextual toolbar — the floating pill at the top of the canvas whose
 * contents follow what is selected (spec §6). Colours and fonts open their
 * side panels; everything else opens a popover under its button.
 */
import React from 'react';
import { useDesigner, t } from './context';
import { useStore, useSelected } from './store';
import * as M from './model';
import { graphic } from './library';
import { Icon, Tip, PopButton, Slider, MenuList, NumberField } from './ui';

const Swatch = ({ color, label, onClick, active, kind = 'fill' }) => (
    <Tip label={label}>
        <button type="button" className={`dz-tb dz-swatch-btn ${active ? 'is-on' : ''}`} onClick={onClick} aria-label={label}>
            {kind === 'text'
                ? <span className="dz-textcolor"><b>A</b><i style={{ background: color || '#000' }} /></span>
                : <span className={`dz-swatch ${!color || color === 'transparent' ? 'is-empty' : ''}`} style={{ background: color && color !== 'transparent' ? color : undefined }} />}
        </button>
    </Tip>
);

const Sep = () => <span className="dz-tb-sep" />;

function Transparency({ els }) {
    const { actions, store } = useDesigner();
    const v = Math.round((els[0]?.opacity ?? 1) * 100);
    return (
        <PopButton icon="transparency" label={t('transparency')} width={260}>
            <div className="dz-pop-body">
                <Slider label={t('transparency')} value={v} min={0} max={100} onBegin={() => store.begin()} onLive={(n) => actions.live({ opacity: n / 100 })} onCommit={() => store.end()} />
            </div>
        </PopButton>
    );
}

function FlipMenu() {
    const { actions } = useDesigner();
    return (
        <PopButton icon="symmetry-vertical" label={t('flip')} width={200} render={() => <span className="dz-tb-text">{t('flip')} <Icon name="chevron-down" /></span>}>
            {(close) => <MenuList onClose={close} items={[
                { icon: 'symmetry-vertical', label: t('flip_h'), onClick: () => actions.flip('x') },
                { icon: 'symmetry-horizontal', label: t('flip_v'), onClick: () => actions.flip('y') },
            ]} />}
        </PopButton>
    );
}

function BorderPop({ el }) {
    const { actions, store } = useDesigner();
    const s = el.stroke;
    const set = (patch) => actions.update({ stroke: patch === null ? null : { color: s?.color || '#0f172a', width: s?.width || 2, style: s?.style || 'solid', ...patch } });
    return (
        <PopButton label={t('border')} width={280} active={Boolean(s)} render={() => <span className="dz-tb-text"><Icon name="border-style" /> {t('border_short')}</span>}>
            <div className="dz-pop-body">
                <div className="dz-seg">
                    {[['none', 'slash-circle'], ['solid', 'dash-lg'], ['dashed', 'three-dots'], ['dotted', 'dot']].map(([k, ic]) => (
                        <button key={k} type="button" className={(k === 'none' ? !s : s?.style === k) ? 'is-on' : ''} onClick={() => set(k === 'none' ? null : { style: k })} aria-label={t(`border_${k}`)}><Icon name={ic} /></button>
                    ))}
                </div>
                {s && (
                    <>
                        <Slider label={t('border_weight')} value={s.width} min={0} max={Math.max(40, s.width)} step={0.5} onBegin={() => store.begin()} onLive={(n) => actions.live({ stroke: { ...s, width: n } })} onCommit={() => store.end()} />
                        <button type="button" className="dz-row-btn" onClick={() => store.set({ panel: 'color', colorTarget: { prop: 'stroke' } })}>
                            <span className="dz-swatch" style={{ background: s.color }} /> {t('border_color')}
                        </button>
                    </>
                )}
            </div>
        </PopButton>
    );
}

function CornerPop({ el }) {
    const { actions, store } = useDesigner();
    const max = Math.round(Math.min(el.width, el.height) / 2);
    const sided = el.type === 'shape' && (el.shape === 'polygon' || el.shape === 'star');
    return (
        <PopButton label={t('corner_rounding')} width={280} render={() => <span className="dz-tb-text"><Icon name="bounding-box-circles" /> {t('corners')}</span>}>
            <div className="dz-pop-body">
                {(el.type === 'image' || el.shape === 'rect') && (
                    <Slider label={t('corner_rounding')} value={Math.round(el.radius || 0)} min={0} max={Math.max(max, 1)} onBegin={() => store.begin()} onLive={(n) => actions.live({ radius: n })} onCommit={() => store.end()} />
                )}
                {(el.type === 'shape') && (
                    <Slider label={t('sides')} value={sided ? el.sides || 5 : 4} min={3} max={12} onBegin={() => store.begin()} onLive={(n) => actions.live(n === 4 && el.shape !== 'star' ? { shape: 'rect' } : { shape: el.shape === 'star' ? 'star' : 'polygon', sides: n })} onCommit={() => store.end()} />
                )}
            </div>
        </PopButton>
    );
}

const EFFECT_COLORS = ['#000000', '#0f172a', '#475569', '#94a3b8', '#ffffff', '#1e3a8a', '#7c6bf5', '#d4a017', '#dc2626', '#16a34a'];

function ColorDots({ value, onPick }) {
    return (
        <div className="dz-dots">
            {EFFECT_COLORS.map((c) => <button key={c} type="button" className={value === c ? 'is-on' : ''} style={{ background: c }} onClick={() => onPick(c)} aria-label={c} />)}
        </div>
    );
}

/** Effects: a shadow (any element) and an outline round the letters (text). */
function EffectsPop({ el }) {
    const { actions, store } = useDesigner();
    const sh = el.shadow;
    const ol = el.outline;
    const size = Math.max(4, Math.round(Math.min(el.width, el.type === 'text' ? el.fontSize * 2 : el.height) / 10));
    const shadowOn = () => actions.update({ shadow: { x: Math.round(size / 2), y: Math.round(size / 2), blur: size, color: '#000000', opacity: 0.35 } });
    const liveShadow = (k) => (n) => actions.live({ shadow: { ...store.get().doc.pages[store.get().pageIndex].elements.find((x) => x.id === el.id).shadow, [k]: n } });
    const liveOutline = (n) => actions.live({ outline: { ...ol, width: n } });
    return (
        <PopButton label={t('effects')} width={300} render={() => <span className="dz-tb-text"><Icon name="magic" /> {t('effects')}</span>} active={Boolean(sh || ol)}>
            <div className="dz-pop-body">
                <label className="dz-toggle-row is-tight">
                    <span><Icon name="shadows" /> {t('shadow')}</span>
                    <button type="button" className={`dz-switch ${sh ? 'is-on' : ''}`} role="switch" aria-checked={Boolean(sh)} onClick={() => (sh ? actions.update({ shadow: null }) : shadowOn())}><span /></button>
                </label>
                {sh && (
                    <>
                        <Slider label={t('shadow_x')} value={sh.x} min={-100} max={100} onBegin={() => store.begin()} onLive={liveShadow('x')} onCommit={() => store.end()} />
                        <Slider label={t('shadow_y')} value={sh.y} min={-100} max={100} onBegin={() => store.begin()} onLive={liveShadow('y')} onCommit={() => store.end()} />
                        <Slider label={t('shadow_blur')} value={sh.blur} min={0} max={100} onBegin={() => store.begin()} onLive={liveShadow('blur')} onCommit={() => store.end()} />
                        <Slider label={t('shadow_strength')} value={Math.round(sh.opacity * 100)} min={0} max={100} onBegin={() => store.begin()} onLive={(n) => liveShadow('opacity')(n / 100)} onCommit={() => store.end()} />
                        <ColorDots value={sh.color} onPick={(c) => actions.update({ shadow: { ...sh, color: c } })} />
                    </>
                )}
                {el.type === 'text' && (
                    <>
                        <label className="dz-toggle-row is-tight" style={{ marginTop: 14 }}>
                            <span><Icon name="fonts" /> {t('outline')}</span>
                            <button type="button" className={`dz-switch ${ol ? 'is-on' : ''}`} role="switch" aria-checked={Boolean(ol)} onClick={() => actions.update({ outline: ol ? null : { width: Math.max(1, Math.round((el.fontSize || 16) / 16)), color: '#ffffff' } })}><span /></button>
                        </label>
                        {ol && (
                            <>
                                <Slider label={t('outline_width')} value={ol.width} min={0.5} max={Math.max(20, ol.width)} step={0.5} onBegin={() => store.begin()} onLive={liveOutline} onCommit={() => store.end()} />
                                <ColorDots value={ol.color} onPick={(c) => actions.update({ outline: { ...ol, color: c } })} />
                            </>
                        )}
                    </>
                )}
            </div>
        </PopButton>
    );
}

function SpacingPop({ el }) {
    const { actions, store } = useDesigner();
    return (
        <PopButton icon="text-paragraph" label={t('spacing')} width={280}>
            <div className="dz-pop-body">
                <Slider label={t('letter_spacing')} value={el.letterSpacing || 0} min={-200} max={800} step={10} onBegin={() => store.begin()} onLive={(n) => actions.live({ letterSpacing: n })} onCommit={() => store.end()} />
                <Slider label={t('line_spacing')} value={el.lineHeight || 1.4} min={0.5} max={3} step={0.05} onBegin={() => store.begin()} onLive={(n) => actions.live({ lineHeight: n })} onCommit={() => store.end()} />
            </div>
        </PopButton>
    );
}

/**
 * What happens when the text comes out longer than its box (a long name on a
 * certificate): wrap onto more lines, squeeze it sideways, or shrink it to fit.
 * With a {name} in the box, how many of the person's names to print.
 */
function LongTextPop({ el }) {
    const { actions } = useDesigner();
    const fit = el.fit === 'squeeze' || el.fit === 'shrink' ? el.fit : 'none';
    const hasName = /\{name\}/.test(el.text || '');
    const fits = [
        ['none', 'text-wrap', t('text_fit_wrap'), t('text_fit_wrap_hint')],
        ['squeeze', 'arrows-collapse-vertical', t('text_fit_squeeze'), t('text_fit_squeeze_hint')],
        ['shrink', 'fullscreen-exit', t('text_fit_shrink'), t('text_fit_shrink_hint')],
    ];
    const names = [[0, t('name_words_all')], [3, t('name_words_3')], [2, t('name_words_2')]];
    return (
        <PopButton icon="arrows-collapse-vertical" label={t('long_text')} width={300} active={fit !== 'none' || Boolean(el.nameWords)}>
            <div className="dz-pop-body">
                <p className="dz-hint">{t('long_text_hint')}</p>
                <div className="dz-choices" role="radiogroup" aria-label={t('long_text')}>
                    {fits.map(([k, icon, label, hint]) => (
                        <button key={k} type="button" role="radio" aria-checked={fit === k} className={fit === k ? 'is-on' : ''} onClick={() => actions.update({ fit: k === 'none' ? null : k })}>
                            <Icon name={icon} /><span><b>{label}</b><small>{hint}</small></span>
                        </button>
                    ))}
                </div>
                {hasName && (
                    <>
                        <p className="dz-hint" style={{ margin: '14px 0 8px' }}>{t('name_words_hint')}</p>
                        <div className="dz-fitswitch" role="radiogroup" aria-label={t('name_words')} style={{ margin: 0 }}>
                            {names.map(([n, label]) => (
                                <button key={n} type="button" role="radio" aria-checked={(el.nameWords || 0) === n} className={(el.nameWords || 0) === n ? 'is-on' : ''} style={{ flex: 1, justifyContent: 'center' }}
                                    onClick={() => actions.update({ nameWords: n || null })}>{label}</button>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </PopButton>
    );
}

/** How a picture sits in its frame — picked here, and kept when the frame is resized. */
function FitSwitch({ el }) {
    const { actions } = useDesigner();
    const options = [
        ['fill', 'arrows-angle-expand', t('fit_fill_short'), t('fit_fill')],
        ['cover', 'crop', t('fit_cover_short'), t('fit_cover')],
        ['contain', 'fullscreen-exit', t('fit_contain_short'), t('fit_contain')],
    ];
    return (
        <div className="dz-fitswitch" role="radiogroup" aria-label={t('fit')}>
            {options.map(([k, icon, label, hint]) => (
                <Tip key={k} label={hint}>
                    <button type="button" role="radio" aria-checked={(el.fit || 'cover') === k} className={(el.fit || 'cover') === k ? 'is-on' : ''} onClick={() => actions.update({ fit: k })}>
                        <Icon name={icon} /> {label}
                    </button>
                </Tip>
            ))}
        </div>
    );
}

/** Where the text sits inside its box: nine squares, top-left to bottom-right. */
function PositionGrid({ el }) {
    const { actions } = useDesigner();
    const v = el.valign || (el.type === 'shape' ? 'middle' : 'top');
    const h = el.align === 'justify' ? 'left' : el.align || 'left';
    const rows = ['top', 'middle', 'bottom'];
    const cols = ['left', 'center', 'right'];
    return (
        <PopButton label={t('text_position')} width={200} render={() => <span className="dz-tb-text"><Icon name="grid-3x3-gap" /> {t('text_position')}</span>}>
            <div className="dz-pop-body">
                <div className="dz-posgrid" role="radiogroup" aria-label={t('text_position')}>
                    {rows.map((r) => cols.map((c) => (
                        <button key={`${r}-${c}`} type="button" role="radio" aria-checked={v === r && h === c} aria-label={t(`pos_${r}_${c}`)} title={t(`pos_${r}_${c}`)}
                            className={v === r && h === c ? 'is-on' : ''} onClick={() => actions.update({ valign: r, align: c })}><i /></button>
                    )))}
                </div>
                <p className="dz-hint" style={{ margin: '10px 0 0' }}>{t('text_position_hint')}</p>
            </div>
        </PopButton>
    );
}

function TextControls({ el }) {
    const { actions, store } = useDesigner();
    const panel = useStore(store, (s) => s.panel);
    const nextAlign = { left: 'center', center: 'right', right: 'justify', justify: 'left' };
    const nextList = { none: 'bullet', bullet: 'number', number: 'none' };
    const size = el.fontSize || 16;
    const step = (d) => actions.update({ fontSize: Math.max(1, Math.round((size + d) * 10) / 10) });

    return (
        <>
            <Tip label={t('font')}>
                <button type="button" className={`dz-tb dz-fontbtn ${panel === 'font' ? 'is-on' : ''}`} onClick={() => store.set({ panel: panel === 'font' ? null : 'font' })}>
                    <span style={{ fontFamily: `"${el.fontFamily}"` }}>{el.fontFamily}</span><Icon name="chevron-down" />
                </button>
            </Tip>
            <div className="dz-size">
                <Tip label={t('size_down')}><button type="button" className="dz-tb" onClick={() => step(-1)} aria-label={t('size_down')}><Icon name="dash-lg" /></button></Tip>
                <NumberField value={size} min={1} max={2000} step={1} small onCommit={(n) => actions.update({ fontSize: n })} />
                <Tip label={t('size_up')}><button type="button" className="dz-tb" onClick={() => step(1)} aria-label={t('size_up')}><Icon name="plus-lg" /></button></Tip>
            </div>
            <Swatch kind="text" color={el.color} label={t('text_color')} active={panel === 'color'} onClick={() => store.set({ panel: 'color', colorTarget: { prop: 'color' } })} />
            <Tip label={t('bold')} kbd="Ctrl+B"><button type="button" className={`dz-tb ${(el.fontWeight || 400) >= 600 ? 'is-on' : ''}`} onClick={() => actions.update({ fontWeight: (el.fontWeight || 400) >= 600 ? 400 : 700 })}><Icon name="type-bold" /></button></Tip>
            <Tip label={t('italic')} kbd="Ctrl+I"><button type="button" className={`dz-tb ${el.italic ? 'is-on' : ''}`} onClick={() => actions.update({ italic: !el.italic })}><Icon name="type-italic" /></button></Tip>
            <Tip label={t('underline')} kbd="Ctrl+U"><button type="button" className={`dz-tb ${el.underline ? 'is-on' : ''}`} onClick={() => actions.update({ underline: !el.underline })}><Icon name="type-underline" /></button></Tip>
            <Tip label={t('strike')}><button type="button" className={`dz-tb ${el.strike ? 'is-on' : ''}`} onClick={() => actions.update({ strike: !el.strike })}><Icon name="type-strikethrough" /></button></Tip>
            <Tip label={t('uppercase')}><button type="button" className={`dz-tb ${el.uppercase ? 'is-on' : ''}`} onClick={() => actions.update({ uppercase: !el.uppercase })}><span className="dz-tb-text">aA</span></button></Tip>
            <Tip label={t(`align_${el.align || 'left'}`)}><button type="button" className="dz-tb" onClick={() => actions.update({ align: nextAlign[el.align || 'left'] })}><Icon name={{ left: 'text-left', center: 'text-center', right: 'text-right', justify: 'justify' }[el.align || 'left']} /></button></Tip>
            <Tip label={t('list')}><button type="button" className={`dz-tb ${el.list && el.list !== 'none' ? 'is-on' : ''}`} onClick={() => actions.update({ list: nextList[el.list || 'none'] })}><Icon name={el.list === 'number' ? 'list-ol' : 'list-ul'} /></button></Tip>
            <PositionGrid el={el} />
            <SpacingPop el={el} />
            <LongTextPop el={el} />
        </>
    );
}

export default function Toolbar() {
    const { store, actions, boot } = useDesigner();
    const els = useSelected(store);
    const mode = useStore(store, (s) => s.mode);
    const editingId = useStore(store, (s) => s.editingId);
    const panel = useStore(store, (s) => s.panel);
    const pageBg = useStore(store, (s) => M.page(s.doc, s.pageIndex).background);
    const painting = useStore(store, (s) => s.painting);

    const position = (
        <Tip label={t('position')}>
            <button type="button" className={`dz-tb dz-tb-text-btn ${panel === 'position' ? 'is-on' : ''}`} onClick={() => store.set({ panel: panel === 'position' ? null : 'position' })}>{t('position')}</button>
        </Tip>
    );
    const paint = (
        <Tip label={t('copy_style')} kbd="Ctrl+Alt+C">
            <button type="button" className={`dz-tb ${painting ? 'is-on' : ''}`} onClick={() => (painting ? actions.cancelPaint() : actions.copyStyle())}><Icon name="paint-bucket" /></button>
        </Tip>
    );

    let body = null;
    if (!els.length) {
        if (mode !== 'page') return <div className="dz-toolbar is-empty" />;
        body = (
            <>
                <Swatch color={pageBg} label={t('background_color')} active={panel === 'color'} onClick={() => store.set({ panel: 'color', colorTarget: { prop: 'page' } })} />
                <Sep />
                <Tip label={t('resize')}><button type="button" className="dz-tb dz-tb-text-btn" onClick={() => store.set({ panel: 'size' })}><Icon name="aspect-ratio" /> {t('resize')}</button></Tip>
                <Tip label={t('notes')}><button type="button" className="dz-tb dz-tb-text-btn" onClick={() => store.set({ panel: 'notes' })}><Icon name="journal-text" /> {t('notes')}</button></Tip>
            </>
        );
    } else if (els.length > 1) {
        body = <>{position}<Sep /><FlipMenu /><Transparency els={els} />{paint}</>;
    } else {
        const el = els[0];
        if (el.type === 'text') {
            body = <><TextControls el={el} /><Sep /><EffectsPop el={el} /><Transparency els={els} />{position}{paint}</>;
        } else if (el.type === 'shape') {
            body = (
                <>
                    <Swatch color={el.fill} label={t('fill_color')} active={panel === 'color'} onClick={() => store.set({ panel: 'color', colorTarget: { prop: 'fill' } })} />
                    <BorderPop el={el} />
                    <CornerPop el={el} />
                    {(editingId === el.id || el.text) && <><Sep /><TextControls el={el} /></>}
                    <Sep /><EffectsPop el={el} /><FlipMenu /><Transparency els={els} />{position}{paint}
                </>
            );
        } else if (el.type === 'line') {
            body = (
                <>
                    <Swatch color={el.stroke?.color} label={t('line_color')} active={panel === 'color'} onClick={() => store.set({ panel: 'color', colorTarget: { prop: 'stroke' } })} />
                    <PopButton icon="border-width" label={t('line_style')} width={280}>
                        <div className="dz-pop-body">
                            <div className="dz-seg">
                                {[['solid', 'dash-lg'], ['dashed', 'three-dots'], ['dotted', 'dot']].map(([k, ic]) => (
                                    <button key={k} type="button" className={el.stroke?.style === k ? 'is-on' : ''} onClick={() => actions.update({ stroke: { ...el.stroke, style: k } })}><Icon name={ic} /></button>
                                ))}
                            </div>
                            <Slider label={t('border_weight')} value={el.stroke?.width || 2} min={0.5} max={60} step={0.5} onBegin={() => store.begin()} onLive={(n) => actions.live({ stroke: { ...el.stroke, width: n }, height: Math.max(1, n) })} onCommit={() => store.end()} />
                        </div>
                    </PopButton>
                    <PopButton icon="arrow-right" label={t('line_ends')} width={220}>
                        {(close) => <MenuList onClose={close} items={[
                            { heading: t('line_start') },
                            ...['none', 'arrow', 'circle'].map((k) => ({ label: t(`cap_${k}`), checked: (el.capStart || 'none') === k, onClick: () => actions.update({ capStart: k }) })),
                            { divider: true },
                            { heading: t('line_end') },
                            ...['none', 'arrow', 'circle'].map((k) => ({ label: t(`cap_${k}`), checked: (el.capEnd || 'none') === k, onClick: () => actions.update({ capEnd: k }) })),
                        ]} />}
                    </PopButton>
                    <Sep /><EffectsPop el={el} /><Transparency els={els} />{position}
                </>
            );
        } else if (el.type === 'image') {
            const g = el.graphic ? graphic(el.graphic) : null;
            body = (
                <>
                    {g && g.palette.map((c) => (
                        <Swatch key={c} color={el.colors?.[c] || c} label={t('graphic_color')} onClick={() => store.set({ panel: 'color', colorTarget: { prop: 'graphic', from: c } })} />
                    ))}
                    {el.field === 'qr' && boot.qrStyle && (
                        <Tip label={t('qr_style')}>
                            <button type="button" className={`dz-tb dz-tb-text-btn dz-tb-qr ${panel === 'qr' ? 'is-on' : ''}`} onClick={() => store.set({ panel: panel === 'qr' ? null : 'qr' })}><Icon name="qr-code" /> {t('qr_style')}</button>
                        </Tip>
                    )}
                    {el.field && <span className="dz-chip"><Icon name="magic" /> {t('field_picture', { name: (boot.fields || []).find((f) => f.key === el.field)?.label || el.field })}</span>}
                    {el.asset && <Tip label={t('replace')}><button type="button" className="dz-tb dz-tb-text-btn" onClick={() => store.set({ panel: 'uploads', replacing: el.id })}>{t('replace')}</button></Tip>}
                    {!g && <><FitSwitch el={el} /><CornerPop el={el} /><BorderPop el={el} /></>}
                    <Sep /><EffectsPop el={el} /><FlipMenu /><Transparency els={els} />{position}
                </>
            );
        } else {
            body = <><FlipMenu /><Transparency els={els} />{position}</>;
        }
    }

    return (
        <div className="dz-toolbar" role="toolbar" aria-label="Selected element common controls" onPointerDown={(e) => e.stopPropagation()}>
            {body}
        </div>
    );
}
