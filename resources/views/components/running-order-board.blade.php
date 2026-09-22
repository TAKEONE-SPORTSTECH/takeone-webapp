@props([
    /* The event's uuid — every request this board makes names it. */
    'event',
    /* Whether the viewer may REARRANGE. False renders the same board, read
       only: the table and the officials read the day off it too. */
    'canArrange' => false,
    /* The event's colour, for the mat headers. */
    'color' => '#7c6bf5',
    /* THE FIRST PAINT, from the server.
       The board used to open empty and fetch itself, which meant a page that
       showed NOTHING whenever that one request did not come back — and said
       nothing about why, because the fetch swallowed its own error. The plan is
       cheap to build and the page is already building one, so it arrives with
       the HTML and the board is drawn before any network happens. The fetch is
       still there, for a reload after a move. */
    'plan' => null,
])

{{--
    THE RUNNING ORDER, AS A BOARD YOU CAN PICK UP.

    One column per mat, bouts in the order they will be called, and the whole of
    scheduling is moving them: drag one up its mat to run it sooner, drag it
    across to another mat, or press Auto to have the day laid out from scratch.

    ── Why a component and not two pages ──────────────────────────────────────
    The board is identical on a laptop and on a phone — it is a row of columns
    either way, and a phone scrolls them sideways. What differs is the page
    chrome around it, which is what the two host views own. Same division of
    labour as <x-tournament-bracket>, and for the same reason: the drag logic
    must exist once (CLAUDE.md → Shared Stays Shared).

    ── Two ways to move a bout, on purpose ────────────────────────────────────
    DRAG is the obvious one and is what a mouse does. TAP-TO-PICK then
    TAP-TO-PLACE is the other, and it is not a fallback — it is what actually
    works on a phone held in one hand at a weigh-in desk, and it is the keyboard
    path as well. The bracket arranger established both; this follows it.

    ── What it does NOT decide ────────────────────────────────────────────────
    Whether a move is legal. The server owns that: a bout cannot be placed ahead
    of the bouts that feed it, and the refusal comes back with the offending
    bout named (App\Events\Support\Tournament\OrderBoard). This board draws the
    answer; it never predicts it.
--}}

{{--
    ⚠️ THE ENDPOINTS ARE RENDERED WHOLE, HERE — never assembled in JS.

    Inside the sealed event app every management screen lives under
    /e/{uuid}/admin/…, and SealEventPage rewrites the addresses in the page body
    to get there (PublicEventSkin::rewriteBody). It matches WHOLE addresses —
    including the bare prefix `…/me/events`, which it maps to the event poster.

    So a URL built as `'{{ url('me/events') }}/' + key + '/running-order'` had
    its prefix rewritten to the poster and the fetch went to
    `/e/{uuid}/{uuid}/running-order`: a 404 on every drop, inside the sealed app
    only. Rendered whole, the same rewriter maps it to the mirrored endpoint
    that actually exists — which is the mechanism working rather than being
    worked around.
--}}
<div x-data="runningOrderBoard(@js(route('me.events.order', $event)), @js(route('me.events.order.auto', $event)), @js((bool) $canArrange), @js($color), @js($plan))"
     @click="focus = null"
     class="ro-wrap">

    {{-- ── The bar: the day, what is picked up, and Auto ─────────────────── --}}
    <div class="flex items-center gap-2 flex-wrap mb-3">
        <template x-if="days > 1">
            <div class="flex items-center gap-1">
                <template x-for="d in days" :key="d">
                    <button type="button" @click="day = d; load()"
                            class="m-press px-3 py-1.5 rounded-lg text-[12px] font-bold border transition-colors"
                            :class="d === day ? 'text-white border-transparent' : 'bg-white text-foreground border-gray-200'"
                            :style="d === day ? `background:${color};` : ''"
                            x-text="@js(__('events.order_day'))+' '+d"></button>
                </template>
            </div>
        </template>

        <div class="flex-1"></div>

        {{-- What is in the hand. The board says so out loud, because a picked
             bout with nowhere obvious to go is the one state that reads as a
             frozen screen. --}}
        <template x-if="picked">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-accent text-primary text-[12px] font-black">
                <i class="bi bi-hand-index-thumb"></i>
                <span x-text="picked.a ? (picked.a + ' / ' + (picked.b || '—')) : ('#' + (picked.no ?? '?'))"></span>
                <button type="button" @click="picked = null" aria-label="{{ __('shared.cancel') }}"><i class="bi bi-x-lg"></i></button>
            </span>
        </template>

        @if($canArrange)
            <button type="button" @click="auto()" :disabled="busy"
                    class="m-press inline-flex items-center gap-2 h-9 px-3 rounded-xl bg-white border border-gray-200 text-[12px] font-black text-foreground disabled:opacity-50">
                <i class="bi" :class="busy ? 'bi-hourglass-split' : 'bi-magic'"></i>{{ __('events.order_auto') }}
            </button>
        @endif
    </div>

    {{-- ── The mats ─────────────────────────────────────────────────────── --}}
    <div class="ro-mats" :class="busy ? 'opacity-60' : ''">
        <template x-for="mat in mats" :key="mat.court">
            <div class="ro-mat"
                 @dragover.prevent="hover(mat.court, over.court === mat.court ? over.index : mat.bouts.length)"
                 @dragleave="leave(mat.court, $event)"
                 @drop.prevent="drop(mat.court, over.court === mat.court ? over.index : mat.bouts.length)"
                 :class="over.court === mat.court ? 'is-over' : ''">

                <div class="ro-mat-head" :style="`background:${color};`">
                    <span x-text="mat.court"></span>
                    <span class="ro-count" x-text="mat.bouts.length"></span>
                </div>

                <div class="ro-list">
                    <template x-for="(bout, i) in mat.bouts" :key="bout.id">
                        <div>
                            {{-- THE SLOT. It is what opens to make room: zero
                                 height until the dragged card is over this
                                 position, then it grows to a card's height and
                                 everything below moves down. That gap IS the
                                 answer to "where will this land", and it is why
                                 the list has to move rather than merely
                                 highlight. --}}
                            <div class="ro-slot"
                                 :class="{ 'is-open': slotOpen(mat.court, i), 'is-offered': picked && ! dragging }"
                                 @dragover.prevent.stop="hover(mat.court, i)"
                                 @drop.prevent.stop="drop(mat.court, i)"
                                 @click="picked && drop(mat.court, i)"></div>

                            <div class="ro-bout"
                                 :class="{ 'is-decided': bout.decided, 'is-waiting': ! bout.runnable,
                                           'is-picked': picked && picked.id === bout.id,
                                           'is-focus': focus && bout.division_id === focus,
                                           'is-dragging': dragging && dragging.id === bout.id }"
                                 :draggable="canArrange && ! bout.decided"
                                 @dragstart="startDrag($event, bout)"
                                 @dragend="endDrag()"
                                 {{-- Top half of a card means "above it", bottom
                                      half means "below it" — the ordinary rule,
                                      and what makes a long list draggable
                                      without aiming at 10px strips. --}}
                                 @dragover.prevent="hoverCard($event, mat.court, i)"
                                 @click="take(bout)">
                                <span class="ro-no" x-text="bout.no ?? '—'"></span>
                                <span class="ro-who">
                                    <span class="ro-names" x-text="(bout.a || @js(__('events.order_tbd'))) + '  v  ' + (bout.b || @js(__('events.order_tbd')))"></span>
                                    <span class="ro-meta" x-text="[bout.division, bout.round].filter(Boolean).join(' · ')"></span>
                                </span>
                                <i class="bi" :class="bout.decided ? 'bi-check-circle-fill' : (bout.runnable ? 'bi-grip-vertical' : 'bi-hourglass')"></i>
                            </div>
                        </div>
                    </template>

                    {{-- The tail slot: last on this mat. It is also the whole
                         target of an EMPTY mat. --}}
                    <div class="ro-slot is-tail"
                         :class="{ 'is-open': slotOpen(mat.court, mat.bouts.length), 'is-offered': picked && ! dragging }"
                         @dragover.prevent.stop="hover(mat.court, mat.bouts.length)"
                         @drop.prevent.stop="drop(mat.court, mat.bouts.length)"
                         @click="picked && drop(mat.court, mat.bouts.length)"></div>

                    <p x-show="! mat.bouts.length" class="ro-empty">{{ __('events.order_mat_empty') }}</p>
                </div>
            </div>
        </template>

        {{-- Bouts belonging to no mat. A bout that exists and is nowhere must
             never be invisible on the screen whose job is placing them. --}}
        <template x-if="unplaced.length">
            <div class="ro-mat is-unplaced">
                <div class="ro-mat-head" style="background:#64748b;">
                    <span>{{ __('events.order_unplaced') }}</span>
                    <span class="ro-count" x-text="unplaced.length"></span>
                </div>
                <div class="ro-list">
                    <template x-for="bout in unplaced" :key="bout.id">
                        <div class="ro-bout is-waiting"
                             :draggable="canArrange && ! bout.decided"
                             @dragstart="startDrag($event, bout)"
                             @dragend="endDrag()"
                             @click="take(bout)"
                             :class="{ 'is-picked': picked && picked.id === bout.id,
                                       'is-dragging': dragging && dragging.id === bout.id }">
                            <span class="ro-no">—</span>
                            <span class="ro-who">
                                <span class="ro-names" x-text="(bout.a || @js(__('events.order_tbd'))) + '  v  ' + (bout.b || @js(__('events.order_tbd')))"></span>
                                <span class="ro-meta" x-text="[bout.division, bout.round].filter(Boolean).join(' · ')"></span>
                            </span>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <p x-show="! mats.length && ! busy && ! error" class="text-[13px] text-muted-foreground text-center py-8">
        {{ __('events.order_nothing') }}
    </p>

    {{-- Whatever went wrong, ON the page. Silence here is what made a broken
         board and an empty day look identical. --}}
    <p x-show="error" x-cloak class="text-[13px] text-red-600 text-center py-6">
        <i class="bi bi-exclamation-triangle"></i>
        <span x-text="error"></span>
        <button type="button" @click="load()" class="underline font-bold ms-2">{{ __('events.order_retry') }}</button>
    </p>
</div>

@once
@push('styles')
<style>
    .ro-mats { display:flex; gap:12px; overflow-x:auto; padding-bottom:6px; align-items:flex-start; }
    .ro-mat { flex:0 0 clamp(240px, 78vw, 320px); background:#fff; border:1px solid #f1f2f5;
              border-radius:16px; overflow:hidden; transition:box-shadow .15s; }
    .ro-mat.is-over { box-shadow:0 0 0 2px rgba(124,107,245,.55); }
    .ro-mat-head { color:#fff; font-weight:800; font-size:12px; letter-spacing:.12em; text-transform:uppercase;
                   padding:10px 12px; display:flex; align-items:center; justify-content:space-between; }
    .ro-count { background:rgba(255,255,255,.22); border-radius:999px; padding:1px 8px; font-size:11px; }
    .ro-list { padding:8px; max-height:62vh; overflow-y:auto; }
    .ro-bout { display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:12px;
               border:1px solid #f1f2f5; background:#fff; cursor:grab; user-select:none; }
    .ro-bout + .ro-bout { margin-top:6px; }
    .ro-bout.is-picked { border-color:#7c6bf5; background:#f5f3ff; }
    .ro-bout.is-decided { opacity:.5; cursor:default; }
    .ro-bout.is-waiting { background:#fffbeb; border-color:#fde68a; }
    .ro-no { font-weight:900; font-size:13px; min-width:26px; text-align:center; color:#6b7280; }
    .ro-who { min-width:0; flex:1; display:flex; flex-direction:column; }
    .ro-names { font-size:12.5px; font-weight:700; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ro-meta { font-size:10.5px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    /* THE SLOT — closed by default, and what opens to make room.
       Height is animated rather than toggled: a gap that appears instantly
       reads as the list glitching, while one that grows reads as the cards
       moving aside, which is the whole point of it. */
    .ro-slot { height:0; border-radius:10px; margin:0; overflow:hidden;
               transition:height .16s ease, margin .16s ease, opacity .16s ease; opacity:0; }
    /* Offered: a bout is in the hand (tap-to-place), so every landing place
       shows itself — a touch screen has no hover to discover them with. */
    .ro-slot.is-offered { height:10px; opacity:1; margin:3px 0;
                          border:1px dashed rgba(124,107,245,.45); }
    /* Open: this is where it will land. */
    .ro-slot.is-open { height:46px; opacity:1; margin:6px 0;
                       border:2px dashed rgba(124,107,245,.85); background:rgba(124,107,245,.08); }
    .ro-bout.is-dragging { opacity:.35; }
    /* Arrived here from the day board, looking for one division. A ring rather
       than a fill: the card's own state (decided, waiting) still has to read
       through it. */
    .ro-bout.is-focus { box-shadow:0 0 0 2px rgba(124,107,245,.9); }
    /* The tail of an empty mat still has to be a target you can hit. */
    .ro-list > .ro-slot.is-tail { min-height:0; }
    .ro-empty { text-align:center; font-size:12px; color:#9ca3af; padding:14px 0; }
    @media (prefers-reduced-motion: reduce) {
        .ro-mat, .ro-slot { transition:none; }
    }
</style>
@endpush

@push('scripts')
<script>
/* Registered with Alpine.data rather than written inline: this board is
   rendered inside pages the mobile shell swaps, where an inline <script> is
   inert (CLAUDE.md → Alpine state must be REGISTERED). */
(function () {
    function board(dataUrl, autoUrl, canArrange, color, seed) {
        return {
            dataUrl, autoUrl, canArrange, color,
            day: (seed && seed.day) || 0,
            days: (seed && seed.days) || 1,
            mats: (seed && seed.mats) || [],
            unplaced: (seed && seed.unplaced) || [],
            picked: null, dragging: null, busy: false,
            /* WHERE IT WOULD LAND — the mat and the index. One object rather
               than two flags, because "over mat 2, between bouts 3 and 4" is a
               single fact and half of it is never true on its own. */
            over: { court: null, index: null },
            /* Said out loud rather than swallowed. A board that cannot reach
               its own endpoint must not look like a board with nothing on it. */
            error: null,
            /* ONE DIVISION, ASKED FOR FROM ELSEWHERE. The day board's rows link
               here — "this division, on the mats" — and what arrives is a
               division id to lift out of a board that may be showing eight of
               them. It highlights rather than filters: the question is where
               this division sits AMONG the others, so hiding the others answers
               a different question. Cleared on the next tap anywhere, because a
               highlight nobody can turn off becomes furniture. */
            focus: null,

            init() {
                this._focus = (e) => {
                    const id = e && e.detail ? e.detail.division : null;
                    if (! id) { return; }
                    this.focus = id;
                    this.$nextTick(() => {
                        const el = this.$el.querySelector('.ro-bout.is-focus');
                        if (el && el.scrollIntoView) {
                            el.scrollIntoView({ block: 'nearest', inline: 'center',
                                                behavior: 'smooth' });
                        }
                    });
                };
                /* Deduped: this board rides inside pages the mobile shell swaps
                   in place, and a stacked listener would scroll twice and fight
                   itself (CLAUDE.md → Realtime, §6). */
                if (window.__roBoardFocus) {
                    window.removeEventListener('running-order:focus', window.__roBoardFocus);
                }
                window.__roBoardFocus = this._focus;
                window.addEventListener('running-order:focus', this._focus);
            },

            async load() {
                this.busy = true;
                try {
                    const res = await fetch(this.dataUrl + (this.day ? ('?day=' + this.day) : ''), {
                        headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
                    });
                    const d = await res.json().catch(() => ({}));
                    if (res.ok && d.success) { this.adopt(d); this.error = null; return; }
                    this.error = d.message || ('HTTP ' + res.status);
                } catch (e) { this.error = e.message; }
                finally { this.busy = false; }
            },

            adopt(d) {
                this.day = d.day; this.days = d.days;
                this.mats = d.mats || []; this.unplaced = d.unplaced || [];
            },

            /* ---- The drag itself ---- */

            startDrag(e, bout) {
                if (! this.canArrange || bout.decided) return;
                this.dragging = bout;
                this.picked = null;
                /* Firefox refuses to start a drag without data on it, and the
                   move payload is our own state — so the id is written purely
                   to satisfy that. */
                try { e.dataTransfer.setData('text/plain', String(bout.id)); e.dataTransfer.effectAllowed = 'move'; } catch (err) {}
            },

            endDrag() {
                this.dragging = null;
                this.over = { court: null, index: null };
            },

            /* The pointer is over a SLOT: that is the position, exactly. */
            hover(court, index) {
                if (! this.canArrange || ! (this.dragging || this.picked)) return;
                if (this.over.court !== court || this.over.index !== index) {
                    this.over = { court, index };
                }
            },

            /* The pointer is over a CARD: top half means above it, bottom half
               below. Without this a 320px column is aimed at through 10px
               strips, which is not a thing anybody can do with a mouse, let
               alone standing at a desk. */
            hoverCard(e, court, i) {
                const box = e.currentTarget.getBoundingClientRect();
                this.hover(court, (e.clientY - box.top) > box.height / 2 ? i + 1 : i);
            },

            /* Left the column entirely — not merely moved onto a child of it,
               which fires dragleave too and would make the gap flicker shut
               between every card. */
            leave(court, e) {
                const to = e.relatedTarget;
                if (to && e.currentTarget.contains(to)) return;
                if (this.over.court === court) this.over = { court: null, index: null };
            },

            slotOpen(court, index) {
                return !! (this.dragging || this.picked)
                    && this.over.court === court && this.over.index === index;
            },

            /* TAP-TO-PICK. A decided bout is history and is not picked up. */
            take(bout) {
                if (! this.canArrange || bout.decided) return;
                this.picked = (this.picked && this.picked.id === bout.id) ? null : bout;
            },

            /* The one write. Whether it is a drag or a tap, this is what lands. */
            async drop(court, position) {
                const bout = this.dragging || this.picked;
                this.dragging = null;
                this.over = { court: null, index: null };
                if (! bout || ! this.canArrange || this.busy) return;

                /*
                 * ⚠️ THE SLOT INDEX IS NOT THE SERVER'S POSITION.
                 *
                 * On screen the card is still in the list while you drag it, so
                 * the fourth gap down a mat is index 3 with the card counted.
                 * The server takes the bout OUT and then inserts it, so its
                 * positions are the list without it. Moving a card DOWN its own
                 * mat therefore lands one place higher than it was dropped —
                 * off by exactly one, every time, and only in that direction,
                 * which is the hardest kind of bug to see by eye.
                 */
                const mat = this.mats.find(m => m.court === court);
                const from = mat ? mat.bouts.findIndex(b => b.id === bout.id) : -1;

                if (from !== -1 && position > from) position -= 1;

                this.busy = true;
                try {
                    const res = await fetch(this.dataUrl, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json', 'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ match: bout.id, court, position, day: this.day }),
                    });
                    const d = await res.json().catch(() => ({}));

                    if (! res.ok || ! d.success) {
                        /* A refusal is the SERVER's sentence — it names the bout
                           this one has to run after. Never invented here. */
                        window.showToast('error', d.message || 'Could not move that bout.');
                        return;
                    }

                    this.picked = null;
                    this.adopt(d);
                } catch (e) {
                    window.showToast('error', e.message);
                } finally { this.busy = false; }
            },

            async auto() {
                if (! this.canArrange || this.busy) return;
                const ok = await window.confirmAction({
                    title: @js(__('events.order_auto')),
                    message: @js(__('events.order_auto_confirm')),
                    type: 'danger',
                    confirmText: @js(__('events.order_auto')),
                });
                if (! ok) return;

                this.busy = true;
                try {
                    const res = await fetch(this.autoUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json', 'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ day: this.day }),
                    });
                    const d = await res.json().catch(() => ({}));
                    if (! res.ok || ! d.success) throw new Error(d.message || 'Failed');
                    this.adopt(d);
                    window.showToast('success', d.message);
                } catch (e) { window.showToast('error', e.message); }
                finally { this.busy = false; }
            },
        };
    }

    if (window.Alpine) window.Alpine.data('runningOrderBoard', board);
    else document.addEventListener('alpine:init', () => window.Alpine.data('runningOrderBoard', board));
})();
</script>
@endpush
@endonce
