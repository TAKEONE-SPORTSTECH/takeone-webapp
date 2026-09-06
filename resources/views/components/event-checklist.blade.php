{{--
    Run-day checklist — what has to be true before the competition starts, and
    the Start button it gates.

    On the page it is ONE button showing where preparation stands. Everything
    else — the items, adding, removing, ticking, and Start itself — lives in a
    sheet that opens from it (a centered dialog from `sm:` up). The list is the
    organiser's working surface, not something every official should have to
    scroll past to reach the roster and the draw.

    Standalone: all state, requests and DOM updates live in this file's Alpine
    component. Drop it into any view that can supply the props; it needs no page
    glue, no shared script and no surrounding markup.

    Who does what (mirrored server-side on every endpoint — what this component
    shows or hides is cosmetic):
      • organiser (canManage) — writes and removes items, and starts the event
      • any appointed official — clears an item and puts it back
      • everyone else         — never receives the list at all; the controller
                                sends an empty array, so there is nothing here
                                to hide in the first place

    The gate: with items outstanding, Start is refused by the server. The
    organiser can override, which starts anyway and records that they did —
    the override is a decision with a name on it, not a way around the list.

    Writes patch in place (No Page Reload rule) and dispatch
    `event-checklist-changed` on window with {action, item|uuid, outstanding} so
    anything else on the page can follow along. Starting dispatches
    `event-started`.

    Props:
      event       ClubEvent uuid (public key)
      items       array of {uuid,label,checked,by,at}
      canManage   bool — may write the list and start the event
      canCheck    bool — may tick items off (any official; organisers too)
      started     bool — already running, so the list is closed
      overridden  bool — it was started with items outstanding
      color       event colour, for the accents
--}}
@props([
    'event',
    'items' => [],
    'canManage' => false,
    'canCheck' => false,
    'started' => false,
    'overridden' => false,
    'color' => '#7c3aed',
])
@php
    // Reaches a style attribute — whitelisted like every other event surface.
    $ckColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $color) ? $color : '#7c3aed';
@endphp

<div x-data="eventChecklist({
        items: @js(array_values($items)),
        canManage: @js((bool) $canManage),
        canCheck: @js((bool) $canCheck),
        started: @js((bool) $started),
        overridden: @js((bool) $overridden),
        storeUrl: @js(route('me.events.checklist.store', $event)),
        startUrl: @js(route('me.events.start', $event)),
        base: @js(url('me/events/'.$event.'/checklist')),
     })"
     @keydown.escape.window="open = false">

    {{-- On the page: one button, nothing else. It still answers the question the
         panel exists to answer — where the preparation stands — but the list
         itself lives in the sheet, one tap away, instead of pushing the roster
         and the draw down the page for everyone who officiates. --}}
    <button type="button" @click="open = true"
            class="m-press w-full text-start flex items-center gap-3 rounded-2xl p-4 text-white relative overflow-hidden"
            style="background: linear-gradient(135deg, {{ $ckColor }}, #1f2937);">
        <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
        <div class="relative w-11 h-11 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
            <i class="bi text-xl" :class="started ? 'bi-play-circle-fill' : (outstanding === 0 ? 'bi-check2-circle' : 'bi-list-check')"></i>
        </div>
        <div class="relative min-w-0 flex-1">
            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/70">{{ __('personal.event_check_title') }}</p>
            {{-- Eyebrow + one value line, and nothing else. The third line
                 ("press to review the list") said what the chevron already
                 says, and it made this card taller than every tile under it
                 (asked for 2026-09-06: one line per button, no expanding). --}}
            <p class="text-base font-black leading-tight mt-0.5"
               x-text="started
                        ? (overridden ? @js(__('personal.event_start_was_overridden')) : @js(__('personal.event_start_running')))
                        : (items.length === 0
                            ? @js(__('personal.event_check_all_clear'))
                            : (outstanding === 0
                                ? @js(__('personal.event_check_all_clear'))
                                : outstandingLabel()))"></p>
        </div>
        <div class="relative flex items-center gap-2 flex-shrink-0">
            <p class="text-2xl font-black leading-none" x-show="items.length">
                <span x-text="items.length - outstanding"></span><span class="text-white/60">/<span x-text="items.length"></span></span>
            </p>
            <i class="bi bi-chevron-right text-white/70"></i>
        </div>
    </button>

    {{-- The list itself — a sheet on a phone, a centered dialog on a wide screen.
         Teleported to <body> so a transformed ancestor (the mobile shell's
         stagger animation) can't clip a fixed overlay. --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex flex-col justify-end sm:items-center sm:justify-center sm:p-4">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="open = false"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
                 x-transition:enter-end="translate-y-0 sm:scale-100 sm:opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 sm:scale-100 sm:opacity-100"
                 x-transition:leave-end="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
                 class="relative max-h-[88vh] w-full sm:max-w-lg flex flex-col bg-background rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden">

                {{-- Header: the same standing, so opening the sheet never loses it --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl sm:rounded-t-2xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $ckColor }}, {{ $ckColor }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-check2-square text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('personal.event_check_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5"
                               x-text="started
                                        ? (overridden ? @js(__('personal.event_start_was_overridden')) : @js(__('personal.event_start_running')))
                                        : (items.length === 0 || outstanding === 0
                                            ? @js(__('personal.event_check_all_clear'))
                                            : outstandingLabel())"></p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>

                {{-- The list, scrolling on its own so the actions below stay put --}}
                <div class="flex-1 overflow-y-auto min-h-0 px-5 py-4 space-y-2">
                    <template x-for="item in items" :key="item.uuid">
                        {{-- Centered, not top-aligned: the label is a 20px line next to a
                             24px box, so aligning tops leaves the text visibly riding high.
                             Rows that grow a second line (who cleared it) centre just as well. --}}
                        <div class="flex items-center gap-3 rounded-xl border p-3 transition-colors"
                             :class="item.checked ? 'border-green-200 bg-green-50/40' : 'border-gray-200'">

                            {{-- The tick. A button for officials, a plain state dot once the
                                 event has started or for anyone who may not clear items. --}}
                            <button type="button"
                                    x-show="canCheck && ! started"
                                    @click="toggle(item)"
                                    :disabled="busy === item.uuid"
                                    class="m-press w-6 h-6 rounded-lg border-2 grid place-items-center flex-shrink-0 disabled:opacity-50 transition-colors"
                                    :class="item.checked ? 'border-transparent text-white' : 'border-gray-300 text-transparent hover:border-gray-400'"
                                    :style="item.checked ? 'background: {{ $ckColor }};' : ''"
                                    :aria-pressed="item.checked"
                                    :aria-label="item.label">
                                <i class="bi bi-check-lg text-xs"></i>
                            </button>

                            <span x-show="! (canCheck && ! started)"
                                  class="w-6 h-6 rounded-lg grid place-items-center flex-shrink-0"
                                  :class="item.checked ? 'text-green-600' : 'text-muted-foreground'">
                                <i class="bi" :class="item.checked ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-foreground"
                                   :class="item.checked ? 'line-through opacity-60' : ''"
                                   x-text="item.label"></p>
                                {{-- Who cleared it. The reason a signed list beats a ticked
                                     one: there is someone to ask. --}}
                                <p class="text-[11px] text-muted-foreground mt-0.5" x-show="item.checked && item.by" x-cloak>
                                    <span x-text="item.at"></span> · <span x-text="byLabel(item.by)"></span>
                                </p>
                            </div>

                            <button type="button" x-show="canManage && ! started" @click="remove(item)"
                                    class="w-7 h-7 rounded-lg grid place-items-center flex-shrink-0 text-muted-foreground hover:text-red-600 hover:bg-red-50 transition-colors"
                                    :aria-label="'{{ __('personal.event_check_remove_confirm') }}'">
                                <i class="bi bi-x-lg text-xs"></i>
                            </button>
                        </div>
                    </template>

                    <p x-show="! items.length" x-cloak class="text-[12px] text-muted-foreground text-center py-8">
                        {{ $canManage ? __('personal.event_check_empty') : __('personal.event_check_empty_official') }}
                    </p>
                </div>

                @if($canManage)
                    <div class="flex-shrink-0 border-t border-gray-100 bg-background px-5 pt-3 space-y-2"
                         style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">

                        {{-- Add an item. Enter submits, because the organiser is writing a list
                             and reaching for the mouse between each line is the slow way. --}}
                        {{-- ⚠️ `min-w-0` on the input is load-bearing. A flex item's
                             default `min-width:auto` never shrinks below its
                             INTRINSIC width, and a text input's intrinsic width is
                             its ~20-character default size — so `flex-1` alone left
                             the field at ~180px and pushed the button clean out of
                             the sheet (reported 2026-09-04, in Arabic, where the
                             label is widest). `w-full` gives it a basis to shrink
                             from; `whitespace-nowrap` keeps the button's own label
                             on one line rather than growing it taller instead. --}}
                        <div x-show="! started" x-cloak class="flex items-center gap-2">
                            <input type="text" x-model="draft" maxlength="160"
                                   @keydown.enter.prevent="add()"
                                   placeholder="{{ __('personal.event_check_placeholder') }}"
                                   class="flex-1 min-w-0 w-full h-11 px-3 rounded-xl border-2 border-gray-200 text-sm font-semibold text-foreground
                                          focus:outline-none focus:border-current"
                                   style="caret-color: {{ $ckColor }};">
                            <button type="button" @click="add()" :disabled="busy === 'add' || ! draft.trim()"
                                    class="m-press h-11 px-3.5 rounded-xl text-white text-xs font-black disabled:opacity-50
                                           flex items-center gap-1.5 flex-shrink-0 whitespace-nowrap"
                                    style="background: {{ $ckColor }};">
                                <i class="bi" :class="busy === 'add' ? 'bi-arrow-repeat animate-spin' : 'bi-plus-lg'"></i>
                                {{ __('personal.event_check_add') }}
                            </button>
                        </div>

                        {{-- The start itself. Two buttons rather than one that changes meaning:
                             a clean start and an override are different decisions and should not
                             share a tap target. --}}
                        <div x-show="! started" x-cloak class="space-y-2">
                            <button type="button" @click="start(false)" :disabled="busy === 'start' || outstanding > 0"
                                    class="m-press w-full h-12 rounded-xl text-white text-sm font-black flex items-center justify-center gap-2
                                           disabled:opacity-40 disabled:cursor-not-allowed transition-opacity"
                                    style="background: {{ $ckColor }};">
                                <i class="bi" :class="busy === 'start' ? 'bi-arrow-repeat animate-spin' : 'bi-play-fill'"></i>
                                {{ __('personal.event_start_cta') }}
                            </button>

                            <button type="button" x-show="outstanding > 0" @click="start(true)" :disabled="busy === 'start'"
                                    class="m-press w-full h-11 rounded-xl border-2 border-dashed text-xs font-black disabled:opacity-50
                                           text-muted-foreground hover:text-foreground transition-colors"
                                    style="border-color: {{ $ckColor }}55;">
                                <i class="bi bi-exclamation-triangle"></i> {{ __('personal.event_start_override_cta') }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </template>
</div>

@once
    {{-- Deliberately INLINE, not @push('scripts').
         Pushed scripts render into #shell-scripts, which sits OUTSIDE
         <main id="shell-content"> — and the mobile shell navigator only swaps and
         re-runs scripts found inside #shell-content. So after an in-shell
         navigation to this page the pushed definition never arrived, x-data
         called an undefined eventChecklist(), and the whole panel (tick, add,
         Start) was inert until a hard refresh. Inline, it ships with the content
         and re-runs on every swap. --}}
    <script>
        // Defined once per document; instantiated per instance. Guarded because a
        // shell swap re-executes this tag, and Alpine keeps a reference to the
        // function it already has.
        window.eventChecklist = window.eventChecklist || function (config) {
            return {
                items: config.items || [],
                canManage: !!config.canManage,
                canCheck: !!config.canCheck,
                started: !!config.started,
                overridden: !!config.overridden,
                storeUrl: config.storeUrl,
                startUrl: config.startUrl,
                base: config.base,
                draft: '',
                busy: null,
                // The list lives in a sheet; the page carries only the button.
                open: false,

                get outstanding() { return this.items.filter(i => ! i.checked).length; },

                {{-- Both plural forms are resolved server-side (trans_choice is
                     not available in the browser) and the count is substituted
                     here, so Arabic gets its own forms rather than an English
                     plural rule applied to Arabic words. --}}
                outstandingLabel() {
                    const n = this.outstanding;

                    return n === 1
                        ? @js(trans_choice('personal.event_check_outstanding', 1, ['count' => 1]))
                        : @js(trans_choice('personal.event_check_outstanding', 2, ['count' => ':n'])).replace(':n', n);
                },
                byLabel(name) {
                    return @js(__('personal.event_check_by', ['name' => ':n'])).replace(':n', name || '');
                },

                _csrf() {
                    const m = document.querySelector('meta[name="csrf-token"]');
                    return m ? m.content : '';
                },

                async _send(url, method, body) {
                    const res = await fetch(url, {
                        method,
                        headers: {
                            'X-CSRF-TOKEN': this._csrf(),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: body ? JSON.stringify(body) : null,
                    });
                    const data = await res.json().catch(() => ({}));

                    return { ok: res.ok && data.success, data };
                },

                _announce(detail) {
                    window.dispatchEvent(new CustomEvent('event-checklist-changed', {
                        detail: { ...detail, outstanding: this.outstanding },
                    }));
                },

                async add() {
                    const label = this.draft.trim();
                    if (this.busy || ! label) return;
                    this.busy = 'add';

                    try {
                        const { ok, data } = await this._send(this.storeUrl, 'POST', { label });
                        if (! ok) { window.showToast('error', data.message || '{{ __('personal.event_verify_failed') }}'); return; }

                        this.items.push(data.item);
                        this.draft = '';
                        window.showToast('success', data.message);
                        this._announce({ action: 'created', item: data.item });
                    } catch (e) {
                        window.showToast('error', '{{ __('personal.event_verify_failed') }}');
                    } finally { this.busy = null; }
                },

                async toggle(item) {
                    if (this.busy) return;
                    this.busy = item.uuid;

                    try {
                        const { ok, data } = await this._send(`${this.base}/${item.uuid}`, 'PUT', { checked: ! item.checked });
                        if (! ok) { window.showToast('error', data.message || '{{ __('personal.event_verify_failed') }}'); return; }

                        Object.assign(item, data.item);
                        this._announce({ action: 'toggled', item: data.item });
                    } catch (e) {
                        window.showToast('error', '{{ __('personal.event_verify_failed') }}');
                    } finally { this.busy = null; }
                },

                async remove(item) {
                    if (this.busy) return;

                    const ok = await window.confirmAction({
                        title: '{{ __('personal.event_check_remove_confirm') }}',
                        message: '{{ __('personal.event_check_remove_msg') }}',
                        type: 'danger',
                        confirmText: '{{ __('personal.event_show_remove_btn') }}',
                    });
                    if (! ok) return;

                    this.busy = item.uuid;
                    try {
                        const r = await this._send(`${this.base}/${item.uuid}`, 'DELETE', null);
                        if (! r.ok) { window.showToast('error', r.data.message || '{{ __('personal.event_verify_failed') }}'); return; }

                        this.items = this.items.filter(i => i.uuid !== item.uuid);
                        window.showToast('success', r.data.message);
                        this._announce({ action: 'deleted', uuid: item.uuid });
                    } catch (e) {
                        window.showToast('error', '{{ __('personal.event_verify_failed') }}');
                    } finally { this.busy = null; }
                },

                async start(override) {
                    if (this.busy) return;

                    // Starting locks the draw and closes entries, and an override
                    // does it with work outstanding. Both get asked out loud.
                    const ok = await window.confirmAction({
                        title: override
                            ? '{{ __('personal.event_start_override_title') }}'
                            : '{{ __('personal.event_start_confirm_title') }}',
                        message: override
                            ? '{{ __('personal.event_start_override_msg') }}'
                            : '{{ __('personal.event_start_confirm_msg') }}',
                        type: override ? 'danger' : 'primary',
                        confirmText: override
                            ? '{{ __('personal.event_start_override_cta') }}'
                            : '{{ __('personal.event_start_cta') }}',
                    });
                    if (! ok) return;

                    this.busy = 'start';
                    try {
                        const { ok: sent, data } = await this._send(this.startUrl, 'POST', { override: !! override });
                        if (! sent) { window.showToast('error', data.message || '{{ __('personal.event_verify_failed') }}'); return; }

                        this.started = true;
                        this.overridden = !! data.overridden;
                        window.showToast('success', data.message);
                        window.dispatchEvent(new CustomEvent('event-started', { detail: data }));
                    } catch (e) {
                        window.showToast('error', '{{ __('personal.event_verify_failed') }}');
                    } finally { this.busy = null; }
                },
            };
        };
    </script>
@endonce
