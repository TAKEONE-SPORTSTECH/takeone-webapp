@props([
    /* The event, as a uuid — every address this sheet calls names it. */
    'event',
    /* The event's colour for the band. Hex only; whitelisted below. */
    'color' => null,
])

@php
    $tpColor = \App\Support\Palette::safe($color ?: '#7c6bf5');
@endphp

{{--
    THE TABLET PIN — `<x-draw-tablet-pin>`

    The organiser's sheet for the PIN that lets a scoring tablet edit the draw
    in an emergency (App\Events\Support\TabletDraw). Shows whether one is set
    and by whom, sets or changes it (typed twice), and removes it. The PIN is
    never read back — the server only ever says WHETHER one is set.

    Standalone: its own Alpine state, its own three requests
    (me.events.draw-pin[.update|.destroy]), teleported to <body> with the
    Design Rule #8 band. Opens on the window event `draw-pin:open`. Being able
    to open it is not permission: every request is organiser-only server-side.
--}}
<div x-data="{
        open: false, loading: false, saving: false,
        state: { set: false, by: null, mine: false },
        pin: '', pin2: '', error: '',
        async load() {
            this.loading = true; this.error = '';
            try {
                const r = await fetch(@js(route('me.events.draw-pin', $event)), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const j = await r.json();
                if (j.pin) this.state = j.pin;
            } catch (e) { /* the sheet still opens; saving will say what went wrong */ }
            this.loading = false;
        },
        async send(method, body) {
            this.saving = true; this.error = '';
            try {
                const r = await fetch(@js(route('me.events.draw-pin.update', $event)), {
                    method, credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    body: body ? JSON.stringify(body) : null,
                });
                const j = await r.json().catch(() => ({}));
                if (! r.ok || ! j.success) {
                    this.error = (j.errors && Object.values(j.errors)[0]?.[0]) || j.message || @js(__('events::events.action_failed'));
                    return;
                }
                this.state = j.pin; this.pin = ''; this.pin2 = '';
                window.showToast && window.showToast('success', j.message);
                window.dispatchEvent(new CustomEvent('draw-pin-changed', { detail: j.pin }));
            } catch (e) {
                this.error = @js(__('events::events.action_failed'));
            } finally { this.saving = false; }
        },
        save() { this.send('PUT', { pin: this.pin, pin_confirmation: this.pin2 }); },
        async remove() {
            const ok = window.confirmAction
                ? await window.confirmAction({ title: @js(__('events::events.tablet_pin_remove')), message: @js(__('events::events.tablet_pin_not_set')), type: 'danger', confirmText: @js(__('events::events.tablet_pin_remove')) })
                : true;
            if (ok) this.send('DELETE');
        },
        digits(v) { return String(v || '').replace(/\D/g, '').slice(0, 8); },
     }"
     @draw-pin:open.window="open = true; load()"
     @keydown.escape.window="open = false">

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70]" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/40" x-show="open" x-transition.opacity @click="open = false"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-lg max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                {{-- Band (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $tpColor }}, {{ $tpColor }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-tablet-landscape text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.tablet_pin_sheet_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('events::events.tablet_pin_sheet_sub') }}</p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                    <div class="flex items-center gap-3 rounded-xl px-3.5 py-3"
                         :class="state.set ? 'bg-green-50 text-green-800' : 'bg-muted text-muted-foreground'">
                        <i class="bi text-lg" :class="state.set ? 'bi-shield-lock-fill' : 'bi-eye'"></i>
                        <p class="text-sm font-semibold" x-show="loading">…</p>
                        <p class="text-sm font-semibold" x-show="! loading && state.set" x-cloak
                           x-text="@js(__('events::events.tablet_pin_is_set', ['name' => '__N__'])).replace('__N__', state.by || '—')"></p>
                        <p class="text-sm font-semibold" x-show="! loading && ! state.set" x-cloak>{{ __('events::events.tablet_pin_not_set') }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('events::events.tablet_pin_new') }}</label>
                        <input type="password" inputmode="numeric" autocomplete="new-password" maxlength="8"
                               x-model="pin" @input="pin = digits(pin)"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-lg tracking-[0.4em]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('events::events.tablet_pin_repeat') }}</label>
                        <input type="password" inputmode="numeric" autocomplete="new-password" maxlength="8"
                               x-model="pin2" @input="pin2 = digits(pin2)" @keydown.enter="save()"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-lg tracking-[0.4em]">
                    </div>

                    <p class="text-sm text-red-600" x-show="error" x-cloak x-text="error"></p>
                    <p class="text-xs text-muted-foreground flex items-start gap-1.5">
                        <i class="bi bi-info-circle mt-0.5"></i>{{ __('events::events.tablet_pin_note') }}
                    </p>
                </div>

                {{-- Footer: it SUBMITS, so it has one (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 flex items-center gap-3"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" x-show="state.set" x-cloak @click="remove()" :disabled="saving"
                            class="px-4 py-2.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 text-sm font-semibold transition-colors">
                        {{ __('events::events.tablet_pin_remove') }}
                    </button>
                    <button type="button" @click="save()" :disabled="saving || pin.length < 4 || pin !== pin2"
                            class="ms-auto bg-primary text-white px-5 py-2.5 rounded-lg hover:bg-primary/90 transition-colors font-semibold text-sm disabled:opacity-50">
                        <i class="bi bi-check2 me-1"></i>{{ __('events::events.tablet_pin_save') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
