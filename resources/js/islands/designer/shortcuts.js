/**
 * Keyboard shortcuts (spec §11). None of them fire while text is being edited
 * or while focus is in any input — keystrokes go to the text, never into the
 * document as a stray character.
 */
import { shapeElement, lineElement, textElement } from './library';
import { t } from './context';

export function bindShortcuts(store, actions, env) {
    const onKey = (e) => {
        const s = store.get();
        const tgt = e.target;
        const typing = s.editingId || tgt?.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(tgt?.tagName);
        if (typing) return;
        if (s.mode === 'viewing' && !['Escape'].includes(e.key)) return;

        const mod = e.ctrlKey || e.metaKey;
        const k = e.key.toLowerCase();
        const size = { w: s.size.w, h: s.size.h };
        const run = (fn) => { e.preventDefault(); fn(); };

        if (mod && k === 'z' && !e.shiftKey) return run(() => store.undo());
        if ((mod && k === 'y') || (mod && e.shiftKey && k === 'z')) return run(() => store.redo());
        if (mod && e.altKey && k === 'c') return run(actions.copyStyle);
        if (mod && e.altKey && k === 'v') return run(() => actions.pasteStyle());
        if (mod && k === 'c') return run(actions.copy);
        if (mod && k === 'x') return run(actions.cut);
        if (mod && k === 'v') return actions.hasClipboard() ? run(actions.paste) : undefined; // else let a pasted picture through
        if (mod && k === 'd') return run(actions.duplicate);
        if (mod && k === 'a') return run(actions.selectAll);
        if (mod && e.shiftKey && k === 'g') return run(actions.ungroup);
        if (mod && k === 'g') return run(actions.group);
        if (mod && e.shiftKey && (e.key === ']' || e.key === '}')) return run(() => actions.layer('front'));
        if (mod && e.shiftKey && (e.key === '[' || e.key === '{')) return run(() => actions.layer('back'));
        if (mod && e.key === ']') return run(() => actions.layer('forward'));
        if (mod && e.key === '[') return run(() => actions.layer('backward'));
        if (mod && k === 'p') return run(() => env.print());
        if (mod && k === 's') return run(() => env.persistence.flush());
        if (mod && (k === '=' || k === '+')) return run(() => env.zoomBy(1.1));
        if (mod && k === '-') return run(() => env.zoomBy(1 / 1.1));
        if (mod && k === '0') return run(() => store.set({ zoomMode: 'fit' }));
        if (e.altKey && e.shiftKey && k === 'l') return run(actions.toggleLock);
        if (mod || e.altKey) return undefined;

        if (e.key === 'Escape') return run(() => (s.panel ? store.set({ panel: null }) : s.menu ? store.set({ menu: null }) : actions.escape()));
        if (e.key === 'Delete' || e.key === 'Backspace') return s.selection.length ? run(actions.remove) : undefined;
        if (e.key === 'Enter' && s.selection.length === 1) return run(() => actions.startEditing(s.selection[0]));
        if (e.key.startsWith('Arrow') && s.selection.length) {
            const unit = env.unitPx() * (e.shiftKey ? 10 : 1);
            const d = { ArrowLeft: [-unit, 0], ArrowRight: [unit, 0], ArrowUp: [0, -unit], ArrowDown: [0, unit] }[e.key];
            return run(() => actions.nudge(d[0], d[1]));
        }
        if (k === 't' && !e.shiftKey) return run(() => actions.add(textElement('plain', size, t('placeholder_text')), { edit: true, below: false }));
        if (k === 'r' && !e.shiftKey) return run(() => actions.add(shapeElement('rect', size)));
        if (k === 'c' && !e.shiftKey) return run(() => actions.add(shapeElement('ellipse', size)));
        if (k === 'l' && !e.shiftKey) return run(() => actions.add(lineElement('solid', size)));
        return undefined;
    };

    // A picture on the clipboard pastes into the design.
    const onPaste = (e) => {
        const s = store.get();
        if (s.editingId || ['INPUT', 'TEXTAREA'].includes(e.target?.tagName)) return;
        const file = [...(e.clipboardData?.files || [])].find((f) => f.type.startsWith('image/'));
        if (file) {
            e.preventDefault();
            env.uploadAndAdd(file);
        }
    };

    window.addEventListener('keydown', onKey);
    window.addEventListener('paste', onPaste);
    return () => {
        window.removeEventListener('keydown', onKey);
        window.removeEventListener('paste', onPaste);
    };
}
