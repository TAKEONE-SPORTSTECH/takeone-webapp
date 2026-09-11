@props([
    'event',                 // event uuid
    'isPublic' => false,     // entry_mode === 'public'
    'url' => null,           // route('events.public', uuid) — always passed, shown only when on
    'color' => '#7c3aed',
    'title' => '',
    'autoAccept' => false,   // club_events.public_entry_auto_accept
])

{{--
    The event's public page — the switch and the link, in one row of the console.

    Documentation/EVENTS-PUBLIC-ENTRY.md, Phase B. Standalone per the component
    contract: it owns its own Alpine state, its own request, its own sheet, and
    patches itself in place (No-Reload). Drop it into either console — mobile or
    desktop — with no page glue.

    Deliberately a SHEET rather than an inline block: publishing an event is a
    decision, and a decision deserves a screen that says what it means, not a
    toggle sitting between two unrelated buttons.
--}}

@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
@endphp

<div x-data="{
        open: false,
        isPublic: {{ $isPublic ? 'true' : 'false' }},
        autoAccept: {{ $autoAccept ? 'true' : 'false' }},
        url: @js($url),
        saving: false,
        savingAuto: false,

        async toggle(on) {
            if (this.saving) return;
            this.saving = true;
            try {
                const res = await fetch(@js(route('me.events.public.toggle', $event)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ entry_mode: on ? 'public' : 'members' }),
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));
                this.isPublic = on;
                window.showToast('success', d.message);
            } catch (e) {
                window.showToast('error', e.message);
            } finally { this.saving = false; }
        },

        /* Publishing a page and opening an UNREVIEWED door are two decisions,
           so they are two switches. Turning the page off never silently
           forgives this one — it is remembered for the next time. */
        async setAuto(on) {
            if (this.savingAuto) return;
            this.savingAuto = true;
            try {
                const res = await fetch(@js(route('me.events.public.auto-accept', $event)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ auto_accept: on }),
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));
                this.autoAccept = on;
                window.showToast('success', d.message);
            } catch (e) {
                window.showToast('error', e.message);
            } finally { this.savingAuto = false; }
        },

        async copy() {
            try {
                await navigator.clipboard.writeText(this.url);
                window.showToast('success', @js(__('events.public_copied')));
            } catch (e) { window.showToast('info', @js(__('events.public_copy_manual'))); }
        },

        share() {
            if (navigator.share) { navigator.share({ title: @js($title), url: this.url }).catch(() => {}); return; }
            this.copy();
        },
     }">

    {{-- the row in the console --}}
    <button type="button" @click="open = true"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0"
              :class="isPublic ? 'text-white' : 'bg-muted text-muted-foreground'"
              :style="isPublic ? 'background: {{ $c }}' : ''">
            <i class="bi text-lg" :class="isPublic ? 'bi-globe2' : 'bi-eye-slash'"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('events.public_page') }}</span>
            <span class="block text-[11px] mt-0.5 truncate"
                  :class="isPublic ? 'text-green-600 font-semibold' : 'text-muted-foreground'"
                  x-text="isPublic ? @js(__('events.public_live')) : @js(__('events.public_off'))"></span>
        </span>
        <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
    </button>

    {{-- Teleported: the mobile shell leaves a transform on its children, which
         would make a fixed sheet resolve against a wrapper instead of the
         viewport and clip it. --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center"
             @keydown.escape.window="open = false" style="display:none;">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="open = false"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="relative w-full sm:max-w-lg max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                {{-- gradient header band (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-globe2 text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events.public_page') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $title }}</p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- The body is the LAST element: Design Rule #8 — the band's ✕
                     is the close control, and a sheet with nothing to submit
                     gets no footer repeating it under a scroll. So the safe
                     area is padded here instead. --}}
                <div class="flex-1 overflow-y-auto px-5 pt-4 space-y-3"
                     style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));">

                    {{-- what turning it on MEANS. Said plainly, because it is the
                         one control on the platform that shows something to
                         people who are not signed in. --}}
                    <div class="rounded-2xl border-2 p-4 transition-colors"
                         :class="isPublic ? 'border-green-200 bg-green-50' : 'border-gray-200'">
                        <div class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                                  :class="isPublic ? 'bg-green-100 text-green-600' : 'bg-muted text-muted-foreground'">
                                <i class="bi text-lg" :class="isPublic ? 'bi-globe2' : 'bi-eye-slash'"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-black text-foreground"
                                   x-text="isPublic ? @js(__('events.public_live')) : @js(__('events.public_off'))"></p>
                                <p class="text-[11.5px] text-muted-foreground leading-snug mt-0.5"
                                   x-text="isPublic ? @js(__('events.public_live_hint')) : @js(__('events.public_off_hint'))"></p>
                            </div>

                            {{-- the switch --}}
                            <button type="button" @click="toggle(! isPublic)" :disabled="saving"
                                    class="m-press relative w-12 h-7 rounded-full flex-shrink-0 transition-colors disabled:opacity-50"
                                    :class="isPublic ? '' : 'bg-gray-300'"
                                    :style="isPublic ? 'background: {{ $c }}' : ''"
                                    :aria-pressed="isPublic">
                                <span class="absolute top-1 w-5 h-5 rounded-full bg-white shadow transition-all"
                                      :class="isPublic ? 'start-6' : 'start-1'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Exactly what a stranger will see, so nobody has to guess.
                         GROUPED rather than one mixed list: the question an
                         organiser is actually asking before they flip the
                         switch is "what gets out?", and a flat column of five
                         rows with two icon colours makes them read each line to
                         find out. Two headed groups answer it at a glance. --}}
                    <div class="rounded-2xl bg-muted/50 p-4 space-y-3.5">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">{{ __('events.public_shows') }}</p>

                        @foreach ([
                            ['bi-check-lg', 'text-green-600', 'bg-green-100', __('events.public_shows_yes'), [
                                __('events.public_shows_poster'),
                                __('events.public_shows_rules'),
                                __('events.public_shows_draw'),
                            ]],
                            ['bi-x-lg', 'text-red-500', 'bg-red-100', __('events.public_shows_no'), [
                                __('events.public_hides_names'),
                                __('events.public_hides_money'),
                            ]],
                        ] as [$icon, $tone, $plate, $heading, $rows])
                            <div>
                                <p class="flex items-center gap-1.5 mb-1.5">
                                    <span class="w-4 h-4 rounded-full {{ $plate }} grid place-items-center flex-shrink-0">
                                        <i class="bi {{ $icon }} {{ $tone }} text-[9px]"></i>
                                    </span>
                                    <span class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">{{ $heading }}</span>
                                </p>
                                <ul class="space-y-1 ps-5">
                                    @foreach ($rows as $label)
                                        <li class="text-[12px] text-foreground leading-snug">{{ $label }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>

                    {{-- Who says yes. Off means the organiser looks at every
                         entry, which is the safe default for a link anybody can
                         forward. --}}
                    <div x-show="isPublic" x-cloak x-transition
                         class="rounded-2xl border-2 p-4"
                         :class="autoAccept ? 'border-amber-200 bg-amber-50' : 'border-gray-200'">
                        <div class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                                  :class="autoAccept ? 'bg-amber-100 text-amber-600' : 'bg-muted text-muted-foreground'">
                                <i class="bi text-lg" :class="autoAccept ? 'bi-lightning-charge-fill' : 'bi-person-check'"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-black text-foreground">{{ __('events.public_auto_accept') }}</p>
                                <p class="text-[11.5px] text-muted-foreground leading-snug mt-0.5">{{ __('events.public_auto_accept_hint') }}</p>
                            </div>
                            <button type="button" @click="setAuto(! autoAccept)" :disabled="savingAuto"
                                    class="m-press relative w-12 h-7 rounded-full flex-shrink-0 transition-colors disabled:opacity-50"
                                    :class="autoAccept ? 'bg-amber-500' : 'bg-gray-300'"
                                    :aria-pressed="autoAccept">
                                <span class="absolute top-1 w-5 h-5 rounded-full bg-white shadow transition-all"
                                      :class="autoAccept ? 'start-6' : 'start-1'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- ===== The link, and what to do with it =====
                         Only rendered once there IS one to give.

                         It was four controls in three shapes: a URL printed as
                         dead text, three grey chips in a row, a QR chip floating
                         on its own centre line, and the preview as borderless
                         blue text. Nothing said which one to reach for, and the
                         thing an organiser opens this sheet to do — take the
                         link — was the smallest target on it.

                         Now: the LINK PLATE is the copy button (the whole row,
                         so it is the biggest thing here), three equal tiles are
                         the ways to hand it on, and one outline button ends the
                         sheet. Nothing was added and nothing was dropped. --}}
                    <div x-show="isPublic" x-cloak x-transition class="space-y-2.5 pt-0.5">

                        {{-- The plate IS the copy control. `type="button"` and a
                             real <button>, so it is reachable by keyboard as
                             well as thumb — the old <p> was neither. --}}
                        <button type="button" @click="copy()"
                                class="m-press w-full text-start rounded-2xl border-2 p-2.5 flex items-center gap-3 transition-colors"
                                style="border-color: {{ $c }}33; background: {{ $c }}0d;"
                                aria-label="{{ __('events.public_copy') }}">
                            <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 text-white"
                                  style="background: {{ $c }};">
                                <i class="bi bi-link-45deg text-lg"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[10px] font-black uppercase tracking-wider text-muted-foreground">{{ __('events.public_link_label') }}</span>
                                <span class="block text-[11.5px] font-mono text-foreground truncate mt-0.5" x-text="url"></span>
                            </span>
                            <span class="w-9 h-9 rounded-xl bg-white border border-gray-200 grid place-items-center flex-shrink-0 text-muted-foreground">
                                <i class="bi bi-clipboard text-sm"></i>
                            </span>
                        </button>

                        <p class="text-[10.5px] text-muted-foreground text-center">{{ __('events.public_copy_hint') }}</p>

                        {{-- The three ways to hand it on, at equal weight —
                             including the QR, which is how most people meet an
                             event on a wall and had no business floating alone
                             under the row. Its component is dropped straight
                             into the third cell: a grid item is blockified, so
                             its `inline-block` wrapper fills the track. --}}
                        <div class="grid grid-cols-3 gap-2">
                            <a :href="'https://wa.me/?text=' + encodeURIComponent(@js($title) + ' — ' + url)"
                               target="_blank" rel="noopener"
                               class="m-press flex flex-col items-center justify-center gap-1.5 py-3 rounded-2xl border border-gray-200 bg-white no-underline">
                                <span class="w-9 h-9 rounded-xl bg-green-50 text-green-600 grid place-items-center">
                                    <i class="bi bi-whatsapp text-base"></i>
                                </span>
                                <span class="text-[11px] font-bold text-foreground">{{ __('events.public_whatsapp') }}</span>
                            </a>

                            <button type="button" @click="share()"
                                    class="m-press flex flex-col items-center justify-center gap-1.5 py-3 rounded-2xl border border-gray-200 bg-white w-full">
                                <span class="w-9 h-9 rounded-xl grid place-items-center"
                                      style="background: {{ $c }}1a; color: {{ $c }};">
                                    <i class="bi bi-share-fill text-base"></i>
                                </span>
                                <span class="text-[11px] font-bold text-foreground">{{ __('events.public_share_btn') }}</span>
                            </button>

                            <x-qr-code :url="$url"
                                       :title="$title"
                                       :caption="__('events.public_qr_caption')"
                                       :filename="'qr-event-public-'.$event"
                                       :label="__('events.public_qr')"
                                       icon="bi-qr-code"
                                       button-class="flex flex-col items-center justify-center gap-1.5 py-3 rounded-2xl border border-gray-200 bg-white w-full text-foreground">
                                {{-- The same tile as its two siblings: plate,
                                     then label. The slot only replaces what is
                                     inside the button. --}}
                                <x-slot:trigger>
                                    <span class="w-9 h-9 rounded-xl bg-muted text-foreground grid place-items-center">
                                        <i class="bi bi-qr-code text-base"></i>
                                    </span>
                                    <span class="text-[11px] font-bold text-foreground">{{ __('events.public_qr') }}</span>
                                </x-slot:trigger>
                            </x-qr-code>
                        </div>

                        {{-- The end of the sheet: see it the way a stranger
                             will. Outline, not filled — a filled button at the
                             foot of a sheet reads as Save, and there is nothing
                             here to save (both switches wrote when they moved). --}}
                        <a :href="url" target="_blank" rel="noopener"
                           class="m-press w-full py-3 rounded-2xl border-2 text-[12px] font-black flex items-center justify-center gap-2 no-underline"
                           style="border-color: {{ $c }}40; color: {{ $c }};">
                            <i class="bi bi-box-arrow-up-right"></i>{{ __('events.public_preview') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
