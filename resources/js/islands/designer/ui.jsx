/**
 * The designer's small building blocks: icons, tooltips, popovers, menus,
 * sliders, number fields, a confirmation dialog. Built here rather than with
 * native controls — no native <select>, no confirm() (CLAUDE.md, Design Rule #4).
 */
import React, { useEffect, useLayoutEffect, useRef, useState, useCallback } from 'react';
import { createPortal } from 'react-dom';

export const Icon = ({ name, className = '' }) => <i className={`bi bi-${name} ${className}`} aria-hidden="true" />;

/** Hover tooltip with an optional shortcut. */
export function Tip({ label, kbd, children, side = 'bottom' }) {
    const [show, setShow] = useState(false);
    const [pos, setPos] = useState(null);
    const ref = useRef(null);
    const timer = useRef(null);

    const enter = () => {
        clearTimeout(timer.current);
        timer.current = setTimeout(() => {
            const r = ref.current?.firstElementChild?.getBoundingClientRect() || ref.current?.getBoundingClientRect();
            if (!r) return;
            setPos({ x: r.left + r.width / 2, y: side === 'top' ? r.top - 8 : side === 'right' ? r.top + r.height / 2 : r.bottom + 8, r });
            setShow(true);
        }, 450);
    };
    const leave = () => {
        clearTimeout(timer.current);
        setShow(false);
    };
    useEffect(() => () => clearTimeout(timer.current), []);

    return (
        <span ref={ref} className="dz-tipwrap" onPointerEnter={enter} onPointerLeave={leave} onPointerDown={leave}>
            {children}
            {show && pos && createPortal(
                <span className={`dz-tooltip is-${side}`} style={side === 'right' ? { left: pos.r.right + 10, top: pos.y } : { left: pos.x, top: pos.y }} role="tooltip">
                    {label}{kbd ? <kbd>{kbd}</kbd> : null}
                </span>,
                document.body,
            )}
        </span>
    );
}

/**
 * A panel anchored under (or over) a button. The first click outside only
 * closes it — it is not passed on to the canvas underneath.
 */
export function Popover({ anchor, open, onClose, children, width = 280, align = 'center', className = '' }) {
    const ref = useRef(null);
    const [pos, setPos] = useState(null);

    useLayoutEffect(() => {
        if (!open || !anchor) return;
        const place = () => {
            const r = anchor.getBoundingClientRect();
            const vw = window.innerWidth;
            let left = align === 'start' ? r.left : align === 'end' ? r.right - width : r.left + r.width / 2 - width / 2;
            left = Math.max(8, Math.min(left, vw - width - 8));
            const h = ref.current?.offsetHeight || 0;
            let top = r.bottom + 8;
            if (top + h > window.innerHeight - 8 && r.top - h - 8 > 8) top = r.top - h - 8;
            setPos({ left, top });
        };
        place();
        window.addEventListener('resize', place);
        return () => window.removeEventListener('resize', place);
    }, [open, anchor, width, align]);

    useEffect(() => {
        if (!open) return undefined;
        const down = (e) => {
            if (ref.current?.contains(e.target) || anchor?.contains(e.target) || e.target.closest?.('.dz-submenu')) return;
            e.stopPropagation();
            e.preventDefault();
            onClose();
        };
        const key = (e) => { if (e.key === 'Escape') { e.stopPropagation(); onClose(); } };
        window.addEventListener('pointerdown', down, true);
        window.addEventListener('keydown', key, true);
        return () => {
            window.removeEventListener('pointerdown', down, true);
            window.removeEventListener('keydown', key, true);
        };
    }, [open, onClose, anchor]);

    if (!open) return null;
    return createPortal(
        <div ref={ref} className={`dz-pop ${className}`} style={{ width, left: pos?.left ?? -9999, top: pos?.top ?? -9999 }} onPointerDown={(e) => e.stopPropagation()}>
            {children}
        </div>,
        document.body,
    );
}

/** A button that owns a popover. */
export function PopButton({ icon, label, kbd, children, width, align, active, className = '', render, disabled }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    const close = useCallback(() => setOpen(false), []);

    return (
        <>
            <Tip label={label} kbd={kbd}>
                <button ref={ref} type="button" disabled={disabled} className={`dz-tb ${open || active ? 'is-on' : ''} ${className}`} aria-label={label} aria-expanded={open} onClick={() => setOpen((o) => !o)}>
                    {render ? render() : <Icon name={icon} />}
                </button>
            </Tip>
            <Popover anchor={ref.current} open={open} onClose={close} width={width} align={align}>
                {typeof children === 'function' ? children(close) : children}
            </Popover>
        </>
    );
}

/**
 * A sub-menu, drawn on its own layer beside the row that opened it — never
 * inside the menu, whose box scrolls and would trap it. Opens to the right,
 * flips left when there is no room, and is kept on screen top to bottom.
 */
function SubMenu({ items, onClose }) {
    const ref = useRef(null);
    const anchor = useRef(null);
    const [pos, setPos] = useState(null);

    useLayoutEffect(() => {
        const row = anchor.current?.parentElement?.querySelector('.dz-menu-row');
        const me = ref.current;
        if (!row || !me) return;
        const r = row.getBoundingClientRect();
        const w = me.offsetWidth;
        const h = me.offsetHeight;
        let left = r.right + 4;
        if (left + w > window.innerWidth - 8) left = Math.max(8, r.left - w - 4);
        const top = Math.max(8, Math.min(r.top - 6, window.innerHeight - h - 8));
        setPos({ left, top });
    }, [items]);

    return (
        <>
            <span ref={anchor} hidden />
            {createPortal(
                <div ref={ref} className="dz-submenu" style={{ left: pos?.left ?? -9999, top: pos?.top ?? -9999 }} onPointerDown={(e) => e.stopPropagation()}>
                    <MenuList items={items} onClose={onClose} />
                </div>,
                document.body,
            )}
        </>
    );
}

/** A menu of rows: { icon, label, kbd, onClick, danger, disabled, divider, sub }. */
export function MenuList({ items, onClose }) {
    const [sub, setSub] = useState(null);
    return (
        <div className="dz-menu" role="menu">
            {items.filter(Boolean).map((it, i) => {
                if (it.divider) return <div key={i} className="dz-menu-sep" />;
                if (it.heading) return <div key={i} className="dz-menu-head">{it.heading}</div>;
                return (
                    <div key={i} className="dz-menu-rowwrap" onPointerEnter={() => setSub(it.sub ? i : null)}>
                        <button
                            type="button"
                            role="menuitem"
                            className={`dz-menu-row ${it.danger ? 'is-danger' : ''} ${it.checked ? 'is-checked' : ''}`}
                            disabled={it.disabled}
                            onClick={() => {
                                if (it.sub) { setSub(sub === i ? null : i); return; }
                                it.onClick?.();
                                onClose?.();
                            }}
                        >
                            {it.icon ? <Icon name={it.icon} className="dz-menu-ic" /> : <span className="dz-menu-ic" />}
                            <span className="dz-menu-label">{it.label}</span>
                            {it.checked && <Icon name="check2" className="dz-menu-check" />}
                            {it.kbd && <kbd>{it.kbd}</kbd>}
                            {it.sub && <Icon name="chevron-right" className="dz-menu-chev" />}
                        </button>
                        {it.sub && sub === i && <SubMenu items={it.sub} onClose={onClose} />}
                    </div>
                );
            })}
        </div>
    );
}

/** A slider with a live value: onLive while dragging, onCommit once released. */
export function Slider({ label, value, min, max, step = 1, onBegin, onLive, onCommit, suffix = '' }) {
    const [v, setV] = useState(value);
    useEffect(() => setV(value), [value]);
    const pct = ((v - min) / (max - min)) * 100;

    return (
        <div className="dz-slider">
            <div className="dz-slider-top">
                <span>{label}</span>
                <NumberField value={v} min={min} max={max} step={step} suffix={suffix} onCommit={(n) => { onBegin?.(); onLive?.(n); onCommit?.(n); }} small />
            </div>
            <input
                type="range"
                min={min}
                max={max}
                step={step}
                value={v}
                style={{ '--pct': `${pct}%` }}
                onPointerDown={() => onBegin?.()}
                onChange={(e) => { const n = Number(e.target.value); setV(n); onLive?.(n); }}
                onPointerUp={() => onCommit?.(v)}
                onKeyUp={() => onCommit?.(v)}
            />
        </div>
    );
}

/** A number box: select-all on focus, applies on Enter or blur, ↑/↓ step. */
export function NumberField({ value, onCommit, min = -1e9, max = 1e9, step = 1, suffix = '', small = false, disabled = false, label, decimals = 1 }) {
    const [text, setText] = useState('');
    const [focused, setFocused] = useState(false);
    const fmt = (n) => (Number.isFinite(n) ? String(Math.round(n * 10 ** decimals) / 10 ** decimals) : '');
    useEffect(() => { if (!focused) setText(fmt(value)); }, [value, focused]); // eslint-disable-line react-hooks/exhaustive-deps

    const apply = () => {
        const n = parseFloat(String(text).replace(',', '.'));
        if (Number.isFinite(n)) onCommit(Math.max(min, Math.min(max, n)));
        else setText(fmt(value));
    };

    return (
        <label className={`dz-num ${small ? 'is-small' : ''} ${disabled ? 'is-disabled' : ''}`}>
            {label && <span className="dz-num-label">{label}</span>}
            <input
                type="text"
                inputMode="decimal"
                value={focused ? text : `${fmt(value)}${suffix ? ` ${suffix}` : ''}`}
                disabled={disabled}
                onFocus={(e) => { setFocused(true); setText(fmt(value)); requestAnimationFrame(() => e.target.select()); }}
                onBlur={() => { apply(); setFocused(false); }}
                onChange={(e) => setText(e.target.value)}
                onKeyDown={(e) => {
                    e.stopPropagation();
                    if (e.key === 'Enter') { apply(); e.currentTarget.blur(); }
                    if (e.key === 'Escape') { setText(fmt(value)); e.currentTarget.blur(); }
                    if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        const n = (parseFloat(text) || 0) + (e.key === 'ArrowUp' ? step : -step) * (e.shiftKey ? 10 : 1);
                        setText(fmt(n));
                        onCommit(Math.max(min, Math.min(max, n)));
                    }
                }}
            />
        </label>
    );
}

/** In-page confirmation (no native confirm()). Render once; call ask({title, message, confirm, danger}). */
export function useConfirm() {
    const [req, setReq] = useState(null);
    const ask = useCallback((opts) => new Promise((resolve) => setReq({ ...opts, resolve })), []);
    const close = (v) => { req?.resolve(v); setReq(null); };
    const node = req ? createPortal(
        <div className="dz-modal-back" onPointerDown={() => close(false)}>
            <div className="dz-modal" role="alertdialog" aria-modal="true" onPointerDown={(e) => e.stopPropagation()}>
                <h3>{req.title}</h3>
                {req.message && <p>{req.message}</p>}
                <div className="dz-modal-actions">
                    <button type="button" className="dz-btn" onClick={() => close(false)}>{req.cancel || 'Cancel'}</button>
                    <button type="button" className={`dz-btn ${req.danger ? 'is-danger' : 'is-primary'}`} onClick={() => close(true)} autoFocus>{req.confirm || 'OK'}</button>
                </div>
            </div>
        </div>,
        document.body,
    ) : null;

    return [ask, node];
}

/** A short-lived message at the bottom of the screen. */
export function toast(type, message) {
    if (window.showToast) { window.showToast(type, message); return; }
    const n = document.createElement('div');
    n.className = `dz-toast is-${type}`;
    n.textContent = message;
    document.body.appendChild(n);
    requestAnimationFrame(() => n.classList.add('is-in'));
    setTimeout(() => { n.classList.remove('is-in'); setTimeout(() => n.remove(), 300); }, 3200);
}
