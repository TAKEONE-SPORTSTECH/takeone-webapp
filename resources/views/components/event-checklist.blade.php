{{--
    Run-day checklist — what has to be true before the competition starts, and
    the Start button it gates.

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
     class="space-y-3">

    {{-- Where it stands. One line that answers the only question this panel
         exists to answer, before any of the rows are read. --}}
    <div class="flex items-center gap-3 rounded-2xl p-4 text-white relative overflow-hidden"
         style="background: linear-gradient(135deg, {{ $ckColor }}, #1f2937);">
        <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
        <div class="relative w-11 h-11 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
            <i class="bi text-xl" :class="started ? 'bi-play-circle-fill' : (outstanding === 0 ? 'bi-check2-circle' : 'bi-list-check')"></i>
        </div>
        <div class="relative min-w-0 flex-1">
            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/70">{{ __('personal.event_check_title') }}</p>
            <p class="text-base font-black leading-tight mt-0.5"
               x-text="started
                        ? (overridden ? @js(__('personal.event_start_was_overridden')) : @js(__('personal.event_start_running')))
                        : (items.length === 0
                            ? @js(__('personal.event_check_all_clear'))
                            : (outstanding === 0
                                ? @js(__('personal.event_check_all_clear'))
                                : outstandingLabel()))"></p>
            <p class="text-[11px] text-white/80 mt-0.5" x-show="! started">{{ __('personal.event_check_sub') }}</p>
        </div>
        <div class="relative text-right flex-shrink-0" x-show="items.length">
            <p class="text-2xl font-black leading-none">
                <span x-text="items.length - outstanding"></span><span class="text-white/60">/<span x-text="items.length"></span></span>
            </p>
        </div>
    </div>

    {{-- The list. --}}
    <div class="space-y-2">
        <template x-for="item in items" :key="item.uuid">
            <div class="flex items-start gap-3 rounded-xl border p-3 transition-colors"
                 :class="item.checked ? 'border-green-200 bg-green-50/40' : 'border-gray-200'">

                {{-- The tick. A button for officials, a plain state dot once the
                     event has started or for anyone who may not clear items. --}}
                <button type="button"
                        x-show="canCheck && ! started"
                        @click="toggle(item)"
                        :disabled="busy === item.uuid"
                        class="m-press w-6 h-6 rounded-lg border-2 grid place-items-center flex-shrink-0 mt-0.5 disabled:opacity-50 transition-colors"
                        :class="item.checked ? 'border-transparent text-white' : 'border-gray-300 text-transparent hover:border-gray-400'"
                        :style="item.checked ? 'background: {{ $ckColor }};' : ''"
                        :aria-pressed="item.checked"
                        :aria-label="item.label">
                    <i class="bi bi-check-lg text-xs"></i>
                </button>

                <span x-show="! (canCheck && ! started)"
                      class="w-6 h-6 rounded-lg grid place-items-center flex-shrink-0 mt-0.5"
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

        <p x-show="! items.length" x-cloak class="text-[12px] text-muted-foreground text-center py-4">
            {{ $canManage ? __('personal.event_check_empty') : __('personal.event_check_empty_official') }}
        </p>
    </div>

    @if($canManage)
        {{-- Add an item. Enter submits, because the organiser is writing a list
             and reaching for the mouse between each line is the slow way. --}}
        <div x-show="! started" x-cloak class="flex items-center gap-2">
            <input type="text" x-model="draft" maxlength="160"
                   @keydown.enter.prevent="add()"
                   placeholder="{{ __('personal.event_check_placeholder') }}"
                   class="flex-1 h-11 px-3 rounded-xl border-2 border-gray-200 text-sm font-semibold text-foreground
                          focus:outline-none focus:border-current"
                   style="caret-color: {{ $ckColor }};">
            <button type="button" @click="add()" :disabled="busy === 'add' || ! draft.trim()"
                    class="m-press h-11 px-4 rounded-xl text-white text-xs font-black disabled:opacity-50 flex items-center gap-2 flex-shrink-0"
                    style="background: {{ $ckColor }};">
                <i class="bi" :class="busy === 'add' ? 'bi-arrow-repeat animate-spin' : 'bi-plus-lg'"></i>
                {{ __('personal.event_check_add') }}
            </button>
        </div>

        {{-- The start itself. Two buttons rather than one that changes meaning:
             a clean start and an override are different decisions and should not
             share a tap target. --}}
        <div x-show="! started" x-cloak class="pt-1 space-y-2">
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
    @endif
</div>

@once
    @push('scripts')
    <script>
        // Registered once per page; instantiated per instance.
        function eventChecklist(config) {
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
        }
    </script>
    @endpush
@endonce
