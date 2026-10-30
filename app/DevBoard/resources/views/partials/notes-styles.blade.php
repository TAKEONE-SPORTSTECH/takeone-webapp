{{--
    Margin's notes: tiles on the card sheet, and the note itself — a page of
    warm paper with a rose margin rule (the board is named for it), serif
    headings, and an ink toolbar that rides on top of the phone's keyboard.
--}}
<style>
/* ── Notes on the card sheet ──────────────────────────────────────────── */
.notes-box { margin-top: 20px; }
.notes-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
.notes-head .field-label { margin: 0; }
.note-add {
    height: 30px; padding: 0 12px; border-radius: 999px; background: var(--ink); color: #fff;
    font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; transition: transform .15s var(--ease);
}
.note-add:active { transform: scale(.95); }
.notes { display: flex; flex-direction: column; gap: 8px; }
.note-tile {
    position: relative; display: flex; gap: 12px; padding: 11px 12px 10px 18px; border-radius: 14px;
    background: #fff; border: 1px solid #ece4d5; color: var(--ink); cursor: pointer; outline: none; touch-action: manipulation;
    box-shadow: 0 6px 16px -12px rgba(29,21,48,.5); transition: transform .3s var(--spring), box-shadow .3s var(--ease);
}
[dir=rtl] .note-tile { padding: 11px 18px 10px 12px; }
.note-tile::before { /* the margin rule, in the author's ink */
    content: ''; position: absolute; inset-block: 11px; inset-inline-start: 8px; width: 3px; border-radius: 3px; background: var(--rule); opacity: .9;
}
.note-tile:active { transform: scale(.985); }
.note-tile:focus-visible { box-shadow: 0 0 0 2px var(--primary); }
.nt-text { flex: 1; min-width: 0; }
.nt-title {
    margin: 0; font-family: var(--serif); font-size: 19px; line-height: 1.15; overflow-wrap: anywhere;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.nt-title.is-blank { font-style: italic; color: var(--ink-soft); }
.nt-excerpt {
    margin: 3px 0 0; font-size: 12.5px; line-height: 1.4; color: var(--ink-soft); overflow-wrap: anywhere;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.nt-meta { display: flex; align-items: center; gap: 9px; margin-top: 7px; font-size: 11px; color: var(--ink-soft); }
.nt-meta .m { display: inline-flex; align-items: center; gap: 3px; }
.nt-meta .age { margin-inline-start: auto; }
.nt-thumb { width: 56px; height: 56px; flex-shrink: 0; align-self: center; border-radius: 10px; object-fit: cover; background: var(--paper-2); box-shadow: 0 0 0 1px rgba(29,21,48,.06); }
.note-tile.is-ghost { opacity: .4; border-style: dashed; box-shadow: none; }
.note-tile.is-lifted { opacity: 1 !important; z-index: 9999 !important; transform: rotate(-1.6deg) scale(1.03); box-shadow: 0 26px 44px -18px rgba(0,0,0,.6), 0 0 0 1px rgba(124,107,245,.35); }
[dir=rtl] .note-tile.is-lifted { transform: rotate(1.6deg) scale(1.03); }
.notes-empty {
    width: 100%; padding: 18px 16px; border-radius: 14px; border: 1.5px dashed #e2d8c4; color: var(--ink-soft);
    display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center;
    font-family: var(--serif); font-style: italic; font-size: 16px; line-height: 1.3;
}
.notes-empty .bi { font-style: normal; font-size: 18px; color: var(--primary); }
.notes-loading { height: 66px; border-radius: 14px; background: linear-gradient(100deg, #fff 30%, var(--paper-2) 50%, #fff 70%) 0 0/300% 100%; animation: shimmer 1.2s linear infinite; }

/* ── The note: a page of paper ────────────────────────────────────────── */
.sheet.is-note { z-index: 82; height: calc(100dvh - 10px - var(--safe-t)); max-height: none; overflow: hidden; }
.sheet.is-note .band { padding-bottom: 12px; }
.band-chips { position: relative; margin-top: 10px; display: flex; gap: 6px; flex-wrap: wrap; }
.band-chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; background: rgba(255,255,255,.2); font-size: 11px; font-weight: 700; transition: background .2s, color .2s; }
.band-chip.is-warn { background: #fef3c7; color: #92400e; }
.band-chip .spin { width: 9px; height: 9px; border-radius: 50%; border: 1.5px solid currentColor; border-top-color: transparent; animation: spin .8s linear infinite; }

.paper-foot {
    margin-top: 30px; padding-top: 14px; border-top: 1px dashed #e2d8c4;
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-size: 11.5px; color: var(--ink-soft);
}
.paper-foot .del {
    margin-inline-start: auto; height: 32px; padding: 0 12px; border-radius: 999px; border: 1px solid #fecaca;
    color: #dc2626; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;
}
.paper-foot .del:active { background: #fef2f2; }

/* A wider page on a desk; the phone layout above is the design. */
@media (min-width: 900px) {
    .sheet.is-note { max-width: 760px; height: calc(100dvh - 40px); bottom: 20px; border-radius: 24px; }
    .sheet.is-note .tools { border-radius: 0 0 24px 24px; padding-bottom: 8px; }
}
</style>
