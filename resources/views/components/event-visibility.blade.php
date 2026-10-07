@props([
    'event',                  // event uuid
    'visibility' => 'listed', // club_events.visibility — listed | hidden
    'url' => null,            // the event's link (route('me.events.show', uuid))
    'color' => '#7c3aed',
    'title' => '',
])

{{--
    Listed or hidden — the organiser's switch, one row of the console
    (owner, 2026-10-04). A HIDDEN event appears in no list on the platform —
    Explore, the club's page, the federation, the spotlight — and announces
    nothing; it is reached only by its link, which anybody holding it may open.
    The link is offered right here for that reason.

    Standalone per the component contract (its own Alpine state, request, sheet
    and in-place patching), built on the same shape as <x-event-draw-visibility>.
    It decides nothing about who RUNS the event (EventAccess::canManage); the
    endpoint re-checks that on every write.
--}}

@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';

    // Label, icon and tone per setting — declared once, read by both the row and
    // the cards, so the console row can never disagree with the sheet it opens.
    $modes = [
        'listed' => [
            'icon' => 'bi-globe2',
            'label' => __('events::events.visibility_listed'),
            'sub' => __('events::events.visibility_listed_sub'),
            'hint' => __('events::events.visibility_listed_hint'),
            'tone' => 'green',
        ],
        'hidden' => [
            'icon' => 'bi-eye-slash-fill',
            'label' => __('events::events.visibility_hidden'),
            'sub' => __('events::events.visibility_hidden_sub'),
            'hint' => __('events::events.visibility_hidden_hint'),
            'tone' => 'rose',
        ],
    ];

    $current = array_key_exists($visibility, $modes) ? $visibility : 'listed';

    $tones = [
        'green' => ['bg-green-50 text-green-600', 'border-green-200 bg-green-50', 'bg-green-100 text-green-600'],
        'amber' => ['bg-amber-50 text-amber-600', 'border-amber-200 bg-amber-50', 'bg-amber-100 text-amber-600'],
        'rose'  => ['bg-rose-50 text-rose-600',   'border-rose-200 bg-rose-50',   'bg-rose-100 text-rose-600'],
    ];
@endphp

<div x-data="{
        open: false,
        mode: @js($current),
        saving: false,
        copied: false,
        modes: @js($modes),
        url: @js($url),

        copy() {
            const done = () => { this.copied = true; setTimeout(() => this.copied = false, 1800); window.showToast('success', @js(__('events::events.public_share_copied'))); };
            if (navigator.clipboard) { navigator.clipboard.writeText(this.url).then(done, () => window.showToast('info', this.url)); } else { window.showToast('info', this.url); }
        },

        async choose(next) {
            if (this.saving || next === this.mode) return;
            const previous = this.mode;
            /* Optimistic: the cards move under the finger and roll back if the
               server refuses, rather than sitting inert for a round trip. */
            this.mode = next;
            this.saving = true;
            try {
                const res = await fetch(@js(route('me.events.visibility', $event)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ visibility: next }),
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));
                this.mode = d.visibility;
                window.showToast('success', d.message);
                window.dispatchEvent(new CustomEvent('event-visibility-changed', {
                    detail: { event: @js($event), visibility: d.visibility },
                }));
            } catch (e) {
                this.mode = previous;
                window.showToast('error', e.message);
            } finally { this.saving = false; }
        },
     }">

    {{-- the row in the console --}}
    <button type="button" @click="open = true"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0"
              :class="mode === 'listed' ? 'text-white' : 'bg-rose-50 text-rose-600'"
              :style="mode === 'listed' ? 'background: {{ $c }}' : ''">
            <i class="bi text-lg" :class="'bi ' + modes[mode].icon"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('events::events.visibility') }}</span>
            <span class="block text-[11px] mt-0.5"
                  :class="mode === 'listed' ? 'text-green-600 font-semibold' : 'text-rose-600 font-semibold'"
                  x-text="modes[mode].sub"></span>
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
                 class="relative w-full max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                {{-- gradient header band (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-eye-slash-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.visibility') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ $title }}</p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <p class="relative mt-3 text-[11.5px] text-white/85 leading-snug flex items-start gap-1.5">
                        <i class="bi bi-info-circle mt-0.5 flex-shrink-0"></i>{{ __('events::events.visibility_hint') }}
                    </p>
                </div>

                {{-- Selection cards: two known options, each with a sentence saying
                     who will and will not find the event. --}}
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-2.5"
                     style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                    @foreach ($modes as $key => $m)
                        @php [$idle, $onBox, $onIcon] = $tones[$m['tone']]; @endphp
                        <button type="button" @click="choose(@js($key))" :disabled="saving"
                                class="m-press w-full text-start rounded-2xl border-2 p-4 transition-colors disabled:opacity-60"
                                :class="mode === @js($key) ? '{{ $onBox }}' : 'border-gray-200 bg-white'"
                                :aria-pressed="mode === @js($key)">
                            <div class="flex items-start gap-3">
                                <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                                      :class="mode === @js($key) ? '{{ $onIcon }}' : 'bg-muted text-muted-foreground'">
                                    <i class="bi {{ $m['icon'] }} text-lg"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-black text-foreground">{{ $m['label'] }}</p>
                                    <p class="text-[11.5px] text-muted-foreground leading-snug mt-0.5">{{ $m['hint'] }}</p>
                                </div>

                                {{-- radio-style check circle --}}
                                <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0 mt-0.5 transition-colors"
                                      :class="mode === @js($key) ? 'border-transparent text-white' : 'border-gray-300'"
                                      :style="mode === @js($key) ? 'background: {{ $c }}' : ''">
                                    <i class="bi bi-check-lg text-[11px]" x-show="mode === @js($key)" x-cloak></i>
                                </span>
                            </div>
                        </button>
                    @endforeach

                    {{-- The way people reach a hidden event: its link. --}}
                    <div x-show="mode === 'hidden' && url" x-cloak class="rounded-2xl bg-muted/60 p-3.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('events::events.visibility_link') }}</p>
                        <p class="text-[12px] text-foreground mt-1 break-all" x-text="url"></p>
                        <button type="button" @click="copy()"
                                class="m-press mt-2.5 w-full h-11 rounded-xl text-white text-sm font-bold flex items-center justify-center gap-2"
                                style="background: {{ $c }};">
                            <i class="bi" :class="copied ? 'bi-check2' : 'bi-link-45deg'"></i>
                            <span x-text="copied ? @js(__('events::events.public_share_copied')) : @js(__('events::events.visibility_copy'))"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
