@props([
    /* The event whose board this sits under — its uuid, because that is what
       every event route on this platform binds (CLAUDE.md → Unpredictable
       Resource Identifiers). */
    'event',

    /* The subject's colour for the band. Handed straight to x-action-sheet,
       which puts it through Palette::safe. */
    'color' => null,

    /* Which layer the sheet rises into — handed straight to x-action-sheet.
       The stage that renders this is `fixed inset-0 z-[60]` and opaque, so a
       sheet left on the stylesheet's own 50 opens behind it and tapping a bout
       looks like a control that does nothing. Null everywhere else, which is
       the stylesheet's answer. */
    'layer' => null,
])

{{--
    ⛳ TAP A BOUT, SAY WHO WON.

    The board has always dispatched `bracket:match` when a card is tapped and
    nothing had ever listened, so tapping a bout did nothing at all. An
    organiser correcting their brackets on the morning could move people
    between corners and could not record the one thing a bracket exists to
    carry — who beat whom.

    ── Why this is a listener and not a change to the board ─────────────────
    The board draws a draw. It does not know what a result IS, which sports
    award one, or who may declare it — and `x-tournament-bracket` is used by
    the public wall sheet and by a competitor looking up their own bout, where
    there is nothing to decide. So the capability arrives as a separate
    component that a host renders only when the viewer may manage the event,
    and the board stays exactly what it was. A page that does not render this
    has no winner sheet and needs no flag telling it so.

    ── Why it reuses x-action-sheet ───────────────────────────────────────
    Component-First: the gradient band, the drag handle, the ✕, the backdrop,
    Escape-to-close and the safe-area footer are all Design Rule #8's shape and
    are already built. All this owns is the rows, which are the only part that
    changes per bout — the two corners are different people every time, so they
    cannot be the `actions` array, which is server-rendered once.

    ── The one rule it enforces client-side ─────────────────────────────────
    An empty corner is not offered as a winner. The server refuses it anyway
    (Advancement::record → outcome_empty_side) and that refusal is the real one;
    this only avoids showing a button whose whole purpose is to be rejected.
--}}

<x-action-sheet id="boutOutcome" :color="$color" icon="bi-trophy-fill" :layer="$layer"
                :title="__('events.outcome_who_won')" subtitle="">
    <div id="bo-rows" class="space-y-2.5"></div>
</x-action-sheet>

@once
@push('scripts')
<script>
(function () {
    'use strict';

    /* The mobile shell re-runs inline scripts on every AJAX navigation, so the
       previous listener is removed before a new one is added — otherwise they
       stack and one tap records the same result three times (CLAUDE.md →
       Realtime, "dedup client listeners"). */
    if (window.__boutOutcome) {
        window.removeEventListener('bracket:match', window.__boutOutcome);
    }

    /* `unit` is the bout id. Built from a real route with a placeholder so the
       URL can never drift from the route table (Navigation Integrity). */
    var OUTCOME_URL = @js(route('me.events.outcome', ['event' => $event, 'unit' => 0]));
    var TEXT = {
        wins: @js(__('events.outcome_wins')),
        winsHint: @js(__('events.outcome_wins_hint')),
        walkover: @js(__('events.outcome_walkover_hint')),
        clear: @js(__('events.outcome_clear')),
        clearHint: @js(__('events.outcome_clear_hint')),
        nobody: @js(__('events.outcome_nobody')),
        bout: @js(__('events.outcome_bout_no')),
        decided: @js(__('events.outcome_decided_by_you')),
        failed: @js(__('events.outcome_saved')),
    };

    var current = null;      // the bout the sheet is open over
    var busy = false;

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function el(tag, cls) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        return n;
    }

    /* One row: a portrait tile, the sentence, a hint, a chevron. The same shape
       x-action-sheet gives its own rows, built here because the people in it
       change with every bout. */
    function row(opts) {
        var b = el('button', 'm-press m-card w-full rounded-2xl px-4 py-3.5 flex items-center gap-3 text-start bg-white border border-gray-100');
        b.type = 'button';

        var tile = el('span', 'w-9 h-12 rounded-xl overflow-hidden grid place-items-center flex-shrink-0 '
            + (opts.tone === 'danger' ? 'bg-red-50 text-red-600' : 'bg-accent text-primary'));

        if (opts.photo) {
            /* 3:4, never square — the crop the member chose is what is shown
               (CLAUDE.md → Profile Pictures Are Portrait 3:4). */
            var img = el('img', 'w-full h-full object-cover');
            img.src = opts.photo;
            img.alt = '';
            img.loading = 'lazy';
            tile.appendChild(img);
        } else {
            tile.appendChild(el('i', 'bi ' + (opts.icon || 'bi-person') + ' text-lg'));
        }

        var mid = el('span', 'min-w-0 flex-1');
        var lbl = el('span', 'block text-[14px] font-black ' + (opts.tone === 'danger' ? 'text-red-600' : 'text-foreground'));
        lbl.textContent = opts.label;            /* textContent: a name is somebody's
                                                    own words and is never markup. */
        mid.appendChild(lbl);

        if (opts.hint) {
            var h = el('span', 'block text-[11.5px] text-muted-foreground mt-0.5');
            h.textContent = opts.hint;
            mid.appendChild(h);
        }

        b.appendChild(tile);
        b.appendChild(mid);

        if (opts.badge) {
            var tag = el('span', 'text-[10px] font-black uppercase tracking-wide px-2 py-1 rounded-full bg-accent text-primary flex-shrink-0');
            tag.textContent = opts.badge;
            b.appendChild(tag);
        } else {
            b.appendChild(el('i', 'bi bi-chevron-right text-muted-foreground/50 text-xs'));
        }

        b.addEventListener('click', opts.onClick);
        return b;
    }

    function render() {
        var box = document.getElementById('bo-rows');
        if (! box) { return; }
        box.innerHTML = '';

        var m = current;
        if (! m) { return; }

        var sides = ['a', 'b'];
        var named = sides.filter(function (s) { return m[s] && m[s].name; });

        if (! named.length) {
            var none = el('p', 'text-[12.5px] text-muted-foreground px-1 pb-2');
            none.textContent = TEXT.nobody;
            box.appendChild(none);
            return;
        }

        named.forEach(function (side) {
            var p = m[side];
            /* One corner filled means declaring this is a WALKOVER, and the row
               says so — an organiser tapping it should know they are advancing
               somebody who has not fought, not recording a bout. */
            var alone = named.length === 1;

            box.appendChild(row({
                photo: p.photo,
                label: TEXT.wins.replace(':name', p.name),
                hint: alone ? TEXT.walkover : TEXT.winsHint,
                badge: m.winner === side ? TEXT.decided : null,
                onClick: function () { save({ winner: side }); },
            }));
        });

        if (m.winner) {
            box.appendChild(row({
                icon: 'bi-arrow-counterclockwise',
                tone: 'danger',
                label: TEXT.clear,
                hint: TEXT.clearHint,
                onClick: function () { save({ winner: '' }); },
            }));
        }
    }

    /* Saved through the endpoint that already owns this write
       (me.events.outcome → the event TYPE's recordOutcome): one authority, one
       set of rules about what a result does to the rounds above it. The board
       is re-read rather than patched — advancing a winner changes bouts this
       sheet never saw. */
    function save(payload) {
        if (busy || ! current) { return; }
        busy = true;

        fetch(OUTCOME_URL.replace(/\/0$/, '/' + current.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
        .then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (d) {
                if (! r.ok || d.success === false) { throw new Error(d.message || TEXT.failed); }
                return d;
            });
        })
        .then(function (d) {
            window.showToast('success', d.message || TEXT.failed);
            window.ActionSheet.close('boutOutcome');
            /* `true` keeps the pan and zoom: an organiser working through a
               division must not be thrown back to the fitted view after every
               result. */
            if (window.BracketBoard) { window.BracketBoard.reload(true); }
        })
        .catch(function (err) { window.showToast('error', err.message); })
        .finally(function () { busy = false; });
    }

    window.__boutOutcome = function (e) {
        var m = (e.detail || {}).match;
        if (! m) { return; }

        current = m;

        var bits = [];
        if (m.no) { bits.push(TEXT.bout.replace(':no', m.no)); }
        if (m.court) { bits.push(m.court); }

        window.ActionSheet.open('boutOutcome', {
            title: @js(__('events.outcome_who_won')),
            subtitle: bits.join(' · '),
            photo: null,
        });

        render();
    };

    window.addEventListener('bracket:match', window.__boutOutcome);
})();
</script>
@endpush
@endonce
