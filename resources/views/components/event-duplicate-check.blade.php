@props([
    'event',                // event uuid
    'color' => '#7c3aed',
    'title' => '',
    'variant' => 'row',     // row (mobile console) · desktop (desktop console) · chip (the draw page)
])

{{--
    The last check before the draw: is anybody in this competition twice?

    App\Events\Support\EventDuplicates finds every likely pair among the
    event's entrants — the same name, or a similar one with the same birthday,
    phone or club — and this lays each pair out side by side for the organiser
    to settle: SAME PERSON (keep one; the other's entries move onto them, or
    are removed where they would double up) or DIFFERENT PEOPLE (never asked
    again). A duplicate in a draw is found on the mat; this finds it first.

    Standalone per the component contract: its own state, its own requests
    (me.events.duplicates / .resolve, which re-check the organiser every call),
    its own teleported sheet, in-place patching (No-Reload), and a deduped
    `realtime:events` listener that re-fetches on a refresh signal. Renders
    NOTHING until it has found a pair, so an event with none shows no row.

    Three faces of one component, because the same question is asked in three
    places: the mobile console row, the desktop console row, and a chip on the
    full-screen draw page, where the draw is actually built.
--}}

@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
    $variant = in_array($variant, ['row', 'desktop', 'chip'], true) ? $variant : 'row';
    /* The draw page's board is its own fixed layer (z 70, its picker 80), so
       there the chip and the sheet go above both. Inline: the prebuilt
       Tailwind bundle has no z-[85]/z-[90]. */
    $sheetZ = $variant === 'chip' ? 90 : 70;
    $reasons = [
        'name' => __('events::events.dup_reason_name'),
        'similar' => __('events::events.dup_reason_similar'),
        'birthday' => __('events::events.dup_reason_birthday'),
        'phone' => __('events::events.dup_reason_phone'),
        'club' => __('events::events.dup_reason_club'),
    ];
@endphp

@if(\App\Events\Support\KnownPerson::enabled())
<div x-data="{
        open: false, pairs: [], busy: null, picking: null, loaded: false, err: '',
        reasons: @js($reasons),
        get count() { return this.pairs.length },
        async refresh() {
            try {
                const r = await fetch(@js(route('me.events.duplicates', $event)), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                const d = await r.json().catch(() => ({}));
                if (r.ok && d.success) { this.pairs = d.pairs || []; this.err = ''; }
            } catch (e) { /* a failed refresh leaves the list as it was */ }
            this.loaded = true;
        },
        suggest(p) {
            // The account they sign in with, else the one with more entries.
            if (p.a.signs_in !== p.b.signs_in) return p.a.signs_in ? p.a.key : p.b.key;
            return p.b.entries > p.a.entries ? p.b.key : p.a.key;
        },
        async settle(p, verdict, keep) {
            if (this.busy) return;
            this.busy = p.key; this.err = '';
            try {
                const r = await fetch(@js(route('me.events.duplicates.resolve', $event)), {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    body: JSON.stringify({ a: p.a.key, b: p.b.key, verdict, keep: keep || null }),
                });
                const d = await r.json().catch(() => ({}));
                if (! r.ok || ! d.success) throw new Error(d.message || @js(__('events::events.dup_failed')));
                this.picking = null;
                window.showToast && window.showToast('success', d.message);
                if (typeof d.going === 'number') window.dispatchEvent(new CustomEvent('event-entrants-changed', { detail: { going: d.going } }));
                await this.refresh();
            } catch (e) {
                this.err = e.message;
                window.showToast && window.showToast('error', e.message);
            }
            this.busy = null;
        },
     }"
     x-init="
        refresh();
        window.removeEventListener('realtime:events', window.__eventDupCheck{{ $variant }});
        window.__eventDupCheck{{ $variant }} = (ev) => { if (ev.detail?.event === @js($event)) refresh(); };
        window.addEventListener('realtime:events', window.__eventDupCheck{{ $variant }});
     "
     @open-event-duplicates.window="if (count) open = true"
     x-show="count > 0" x-cloak>

    @if($variant === 'chip')
        {{-- On the draw page: a warning floating over the foot of the board.
             Teleported, because a fixed element inside the shell's transformed
             wrapper is laid out in the wrapper's box and never appears. --}}
        <template x-teleport="body">
            <button type="button" @click="open = true" x-show="count > 0" x-cloak
                    class="fixed left-1/2 -translate-x-1/2 inline-flex items-center gap-2 px-4 py-2.5 rounded-full text-[12.5px] font-black shadow-lg m-press"
                    style="z-index:85; bottom: calc(18px + env(safe-area-inset-bottom, 0px)); background:#fffbeb; color:#92400e; border:1px solid #fcd34d;">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span x-text="@js(__('events::events.dup_chip', ['n' => ':n'])).replace(':n', count)"></span>
                <i class="bi bi-chevron-right rtl:rotate-180 text-[10px]"></i>
            </button>
        </template>
    @elseif($variant === 'desktop')
        <button type="button" @click="open = true"
                class="block w-full text-start bg-white rounded-xl shadow-sm border border-amber-200 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <span class="w-11 h-11 rounded-xl grid place-items-center flex-shrink-0 bg-amber-50 text-amber-600 relative">
                    <i class="bi bi-people-fill text-lg"></i>
                    <span class="absolute -top-1 -end-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-black grid place-items-center" x-text="count"></span>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-gray-900 truncate">{{ __('events::events.dup_row_title') }}</p>
                    <p class="text-[12px] text-amber-700 font-semibold mt-0.5 truncate" x-text="@js(__('events::events.dup_row_sub', ['n' => ':n'])).replace(':n', count)"></p>
                </div>
                <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
            </div>
        </button>
    @else
        <button type="button" @click="open = true"
                class="m-card m-press w-full text-start bg-white rounded-2xl border border-amber-200 shadow-sm p-3.5 flex items-center gap-3">
            <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-amber-50 text-amber-600 relative">
                <i class="bi bi-people-fill text-lg"></i>
                <span class="absolute -top-1 -end-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-black grid place-items-center" x-text="count"></span>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-foreground">{{ __('events::events.dup_row_title') }}</span>
                <span class="block text-[11px] mt-0.5 text-amber-600 font-semibold" x-text="@js(__('events::events.dup_row_sub', ['n' => ':n'])).replace(':n', count)"></span>
            </span>
            <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
        </button>
    @endif

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 flex items-end sm:items-center justify-center"
             @keydown.escape.window="open = false" style="display:none; z-index: {{ $sheetZ }};">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="open = false"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="relative w-full sm:max-w-2xl max-h-[92vh] flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl"
                 role="dialog" aria-modal="true" aria-label="{{ __('events::events.dup_row_title') }}">

                {{-- gradient header band (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #b45309, #d97706b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-people-fill text-xl"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.dup_sheet_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $title }}</p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="relative mt-3 flex flex-wrap gap-1.5">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold"
                              x-text="@js(__('events::events.dup_row_sub', ['n' => ':n'])).replace(':n', count)"></span>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                    <p class="text-[12px] text-muted-foreground leading-snug">{{ __('events::events.dup_intro') }}</p>

                    <template x-if="loaded && count === 0">
                        <div class="text-center py-10">
                            <span class="mx-auto w-14 h-14 rounded-full bg-green-50 text-green-600 grid place-items-center"><i class="bi bi-person-check text-2xl"></i></span>
                            <p class="text-sm font-bold text-foreground mt-3">{{ __('events::events.dup_none') }}</p>
                            <p class="text-[12px] text-muted-foreground mt-1">{{ __('events::events.dup_none_hint') }}</p>
                        </div>
                    </template>

                    <template x-for="p in pairs" :key="p.key">
                        <div class="rounded-2xl border p-3.5 transition-opacity"
                             :class="[p.strong ? 'border-amber-300 bg-amber-50/40' : 'border-gray-200', busy === p.key && 'opacity-50']">
                            <div class="flex flex-wrap gap-1 mb-3">
                                <span x-show="p.strong" class="px-2 py-0.5 rounded-full bg-amber-500 text-white text-[10px] font-bold"><i class="bi bi-lightning-charge-fill"></i> {{ __('events::events.dup_strong') }}</span>
                                <template x-for="why in p.reasons" :key="why"><span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold" x-text="reasons[why] || why"></span></template>
                                <span x-show="p.different_birthdays" class="px-2 py-0.5 rounded-full bg-muted text-gray-600 text-[10px] font-bold">{{ __('events::events.dup_different_birthdays') }}</span>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="(u, n) in [p.a, p.b]" :key="p.key + u.key">
                                    <div class="rounded-xl border p-2.5 min-w-0"
                                         :class="picking === p.key ? 'border-primary/40 bg-white' : 'border-gray-100 bg-white'">
                                        <div class="flex gap-2.5 min-w-0">
                                            <span class="relative w-12 h-16 flex-shrink-0">
                                                <span class="block w-full h-full rounded-lg overflow-hidden bg-muted"><img :src="u.photo" alt="" class="w-full h-full object-cover object-top" loading="lazy"></span>
                                                <span class="absolute -top-1.5 -left-1.5 w-5 h-5 rounded-full bg-amber-500 text-white text-[10px] font-black grid place-items-center ring-2 ring-white" x-text="n === 0 ? 'A' : 'B'"></span>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-[13px] font-black text-gray-900 leading-tight break-words" x-text="u.name"></span>
                                                <span class="block text-[11px] text-muted-foreground mt-0.5" x-text="u.birthdate || '{{ __('events::events.dup_no_birthday') }}'"></span>
                                                <span class="block text-[11px] text-muted-foreground break-words" x-text="u.phone || '{{ __('events::events.dup_no_phone') }}'"></span>
                                                <span class="block text-[11px] text-muted-foreground break-words" x-show="u.club" x-text="u.club"></span>
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap gap-1 mt-2">
                                            <template x-for="dv in u.divisions" :key="dv"><span class="px-1.5 py-0.5 rounded-md bg-muted text-[10px] font-semibold text-gray-700" x-text="dv"></span></template>
                                            <span x-show="! u.divisions.length" class="px-1.5 py-0.5 rounded-md bg-muted text-[10px] font-semibold text-muted-foreground">{{ __('events::events.dup_no_division') }}</span>
                                            <span x-show="u.signs_in" class="px-1.5 py-0.5 rounded-md bg-green-50 text-green-700 text-[10px] font-bold"><i class="bi bi-key-fill"></i> {{ __('events::events.dup_signs_in') }}</span>
                                        </div>
                                        <template x-if="picking === p.key">
                                            <button type="button" @click="settle(p, 'same', u.key)" :disabled="busy === p.key"
                                                    class="m-press mt-2.5 w-full py-2 rounded-lg text-white text-[11.5px] font-bold"
                                                    :style="'background:' + (suggest(p) === u.key ? '{{ $c }}' : '#6b7280')">
                                                <span x-text="@js(__('events::events.dup_keep', ['who' => ':w'])).replace(':w', n === 0 ? 'A' : 'B')"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <p x-show="picking === p.key" class="text-[11.5px] text-muted-foreground mt-2.5 leading-snug">{{ __('events::events.dup_keep_which') }}</p>

                            <div class="grid grid-cols-2 gap-2 mt-3">
                                <button type="button" @click="picking = null; settle(p, 'different')" :disabled="busy === p.key"
                                        class="m-press py-2.5 rounded-xl border border-gray-200 bg-white text-gray-700 text-[12px] font-bold">
                                    <i class="bi bi-person-x me-1"></i>{{ __('events::events.dup_different') }}
                                </button>
                                <button type="button" @click="picking = picking === p.key ? null : p.key" :disabled="busy === p.key"
                                        class="m-press py-2.5 rounded-xl text-white text-[12px] font-bold" style="background: {{ $c }}">
                                    <i class="bi bi-person-check me-1"></i><span x-text="picking === p.key ? @js(__('events::events.dup_cancel')) : @js(__('events::events.dup_same'))"></span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
@endif
