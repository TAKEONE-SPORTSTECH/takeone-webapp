@props([
    /* What App\Events\Support\ExhibitionLink::picker() returned — never null
       here; the host renders this only when there is an exhibition to link. */
    'picker',

    /* The event's colour for the band — through Palette::safe. */
    'color' => null,
])

{{--
    🔗 LINK AN EXHIBITION BY TAPPING ITS BOUT ON THE DRAW.

    Owner, 2026-10-24: "when I want to link a bout take me to the draw view;
    when I select a bout ask me if I am sure or not to link it; if linked then
    that is the bout." The console's Exhibitions tab opens the draw with
    ?link_exhibition=<uuid>, and the stage renders this INSTEAD of the winner
    sheet, so a tap on a bout means one thing only: "this was it?".

    It listens for the board's own `bracket:match` — the board is not changed.
    It decides nothing: the sentence it shows comes from the server's own
    reading of that bout (ExhibitionLink::picker), and the link endpoint
    re-checks everything — who may link, the started day, the bout's state.
    Every value from the draw reaches the page through textContent.
--}}
@php
    $xlColor = \App\Support\Palette::safe($color);
@endphp

<div id="xl-banner" role="status"
     style="position:fixed;z-index:65;left:50%;transform:translateX(-50%);top:calc(64px + env(safe-area-inset-top, 0px));
            max-width:calc(100vw - 2rem);width:560px;border-radius:18px;color:#fff;overflow:hidden;
            background:linear-gradient(150deg, {{ $xlColor }}, {{ $xlColor }}b0);box-shadow:0 14px 40px rgba(0,0,0,.28);">
    <div style="position:absolute;right:-30px;top:-40px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.1);"></div>
    <div style="position:relative;display:flex;align-items:center;gap:12px;padding:12px 14px;">
        <span style="width:40px;height:40px;border-radius:14px;background:rgba(255,255,255,.2);display:grid;place-items:center;flex-shrink:0;">
            <i class="bi bi-link-45deg" style="font-size:20px;"></i>
        </span>
        <div style="min-width:0;flex:1;">
            <div style="font-weight:900;font-size:15px;line-height:1.2;">
                {{ __('events::events.xl_banner_title', ['code' => $picker['code']]) }}
            </div>
            <div style="font-size:12px;opacity:.88;margin-top:2px;overflow-wrap:anywhere;" id="xl-banner-pair"></div>
            <div style="font-size:12px;opacity:.88;margin-top:2px;">{{ __('events::events.xl_banner_hint') }}</div>
        </div>
        <a href="{{ $picker['back_url'] }}"
           style="flex-shrink:0;height:36px;padding:0 14px;border-radius:999px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);
                  color:#fff;font-size:12px;font-weight:800;display:inline-flex;align-items:center;text-decoration:none;">
            {{ __('events::events.xl_cancel') }}
        </a>
    </div>
</div>

<x-action-sheet id="xlConfirm" :color="$color" icon="bi-link-45deg" :layer="70"
                :title="__('events::events.xl_banner_title', ['code' => $picker['code']])" subtitle="">
    <div id="xl-rows" class="space-y-2.5"></div>
</x-action-sheet>

@push('scripts')
<script>
(function () {
    'use strict';

    var P = @js($picker);
    var T = {
        sure: @js(__('events::events.xl_sure_title')),
        exhibition: @js(__('events::events.xl_row_exhibition')),
        bout: @js(__('events::events.xl_row_bout')),
        exact: @js(__('events::events.xl_kind_exact')),
        seat: @js(__('events::events.xl_kind_seat')),
        recorded: @js(__('events::events.xl_kind_recorded')),
        disagrees: @js(__('events::events.xl_kind_disagrees')),
        attach: @js(__('events::events.xl_kind_attach')),
        taken: @js(__('events::events.ex_link_taken')),
        yes: @js(__('events::events.xl_yes')),
        no: @js(__('events::events.xl_no')),
        done: @js(__('events::events.xl_done')),
        failed: @js(__('events::events.outcome_saved')),
        tbd: @js(__('events::events.xl_tbd')),
    };

    function el(tag, cls, txt) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (txt != null) { n.textContent = txt; }
        return n;
    }
    function sc(v) { return v == null || v === '' ? '–' : String(v); }
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    var pair = document.getElementById('xl-banner-pair');
    if (pair) {
        pair.textContent = (P.a.name || '?') + '  ' + sc(P.a.score) + ' : ' + sc(P.b.score) + '  ' + (P.b.name || '?');
    }

    var current = null;
    var busy = false;

    /* What linking THIS bout will do, in the server's own words. */
    function verdict(m) {
        if ((P.taken || []).indexOf(m.id) !== -1) { return { text: T.taken, ok: false }; }
        var c = (P.candidates || {})[m.id];
        if (!c) { return { text: T.attach, ok: true }; }
        if (c.kind === 'recorded' && !c.agrees) { return { text: T.disagrees, ok: false }; }
        return { text: c.kind === 'exact' ? T.exact : (c.kind === 'recorded' ? T.recorded : T.seat), ok: true };
    }

    function card(label, line, sub) {
        var b = el('div', 'rounded-2xl px-4 py-3.5 bg-white border border-gray-100');
        b.appendChild(el('span', 'block text-[10px] font-black uppercase tracking-wide text-muted-foreground', label));
        b.appendChild(el('span', 'block text-[14px] font-black text-foreground mt-1', line));
        if (sub) { b.appendChild(el('span', 'block text-[11.5px] text-muted-foreground mt-0.5', sub)); }
        return b;
    }

    function render() {
        var box = document.getElementById('xl-rows');
        if (!box || !current) { return; }
        box.innerHTML = '';
        var m = current;
        var v = verdict(m);

        box.appendChild(card(T.exhibition.replace(':code', P.code),
            (P.a.name || '?') + '  ' + sc(P.a.score) + ' : ' + sc(P.b.score) + '  ' + (P.b.name || '?')));

        var an = (m.a && m.a.name) || T.tbd, bn = (m.b && m.b.name) || T.tbd;
        var res = m.winner ? '  ' + sc(m.a && m.a.score) + ' : ' + sc(m.b && m.b.score) + (m.winner === 'a' ? '  (' + an + ' ✓)' : '  (' + bn + ' ✓)') : '';
        box.appendChild(card(T.bout.replace(':no', m.no == null ? '—' : m.no), an + '  vs  ' + bn, (m.round || '') + res));

        box.appendChild(el('p', 'text-[12.5px] px-1 ' + (v.ok ? 'text-foreground' : 'text-red-600 font-bold'), v.text));

        var row = el('div', 'grid grid-cols-2 gap-2.5 pt-1');
        var no = el('button', 'm-press rounded-2xl px-4 py-3.5 font-black text-[14px] bg-white border border-gray-200 text-foreground', T.no);
        no.type = 'button';
        no.addEventListener('click', function () { window.ActionSheet.close('xlConfirm'); });
        var yes = el('button', 'm-press rounded-2xl px-4 py-3.5 font-black text-[14px] text-white bg-primary', T.yes);
        yes.type = 'button';
        yes.disabled = !v.ok;
        if (!v.ok) { yes.style.opacity = '.4'; }
        yes.addEventListener('click', link);
        row.appendChild(no);
        row.appendChild(yes);
        box.appendChild(row);
    }

    function link() {
        if (busy || !current) { return; }
        busy = true;

        fetch(P.link_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            credentials: 'same-origin',
            // attach_only: a bout the system did not recognise is attached, the
            // draw untouched; a recognised one is linked as the console does.
            body: JSON.stringify({ match_id: current.id, attach_only: true }),
        })
        .then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (d) {
                if (!r.ok || d.success === false) { throw new Error(d.message || T.failed); }
                return d;
            });
        })
        .then(function (d) {
            window.ActionSheet.close('xlConfirm');
            if (window.showToast) { window.showToast('success', d.message || T.done); }
            // That is the bout now — back to the console it was opened from.
            setTimeout(function () { window.location.href = P.back_url; }, 900);
        })
        .catch(function (err) {
            busy = false;
            if (window.showToast) { window.showToast('error', err.message); }
        });
    }

    if (window.__xlPick) { window.removeEventListener('bracket:match', window.__xlPick); }
    window.__xlPick = function (e) {
        var m = (e.detail || {}).match;
        if (!m || busy) { return; }
        current = m;
        window.ActionSheet.open('xlConfirm', {
            title: T.sure.replace(':code', P.code).replace(':no', m.no == null ? '—' : m.no),
            subtitle: (m.round || '') + (m.court ? ' · ' + m.court : ''),
            photo: null,
        });
        render();
    };
    window.addEventListener('bracket:match', window.__xlPick);

    /* Opened with ?bout=<id> (a bout chosen in the console's Actions menu):
       once the board has drawn, that bout is marked and selected — the same
       tap a person would make, so it asks "was this it?" the same way. Only
       the first load; a realtime redraw never re-opens the sheet. */
    var wanted = new URLSearchParams(window.location.search).get('bout');
    if (window.__xlWanted) { window.removeEventListener('bracket:loaded', window.__xlWanted); }
    window.__xlWanted = null;
    if (wanted && /^\d+$/.test(wanted)) {
        window.__xlWanted = function () {
            window.removeEventListener('bracket:loaded', window.__xlWanted);
            var card = document.querySelector('.bk-match[data-match-id="' + wanted + '"]');
            if (!card) { return; }
            card.style.outline = '3px solid #ffe135';
            card.style.outlineOffset = '4px';
            card.style.borderRadius = '10px';
            card.style.boxShadow = '0 0 0 10px rgba(255,225,53,.18)';
            card.click();
        };
        window.addEventListener('bracket:loaded', window.__xlWanted);
    }
})();
</script>
@endpush
