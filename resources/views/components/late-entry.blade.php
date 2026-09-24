{{--
    Admitting an athlete to a division that is already being fought.

    ── What this is for ──────────────────────────────────────────────────────
    A competitor walks up to the desk at ten in the morning and says he is meant
    to be in this division. He is entered, he is eligible, and the division is
    three bouts deep. Every other draw control refuses him, correctly, because
    they all re-cut the board and a re-cut deletes results. This one grafts him
    on: the server finds the one place he can stand without spending a result,
    and grows the ladder a round at the front when there is no such place.

    ── Standalone ────────────────────────────────────────────────────────────
    It owns its sheet, its search, its request and its own refresh of the board.
    The host gives it an event, a colour and which division is on screen; it
    needs nothing else on the page and nothing else on the page needs to know it
    is here. Opened from anywhere by dispatching

        window.dispatchEvent(new CustomEvent('late-entry:open',
            { detail: { division: 575, divisionName: 'Group E' } }))

    and it tells the board to re-read itself on success, so the graft appears
    without a reload.

    The Alpine component is REGISTERED rather than written inline, because the
    sheet lives inside an x-teleport template and a script inside one of those
    is inert — the scope would come up empty and every binding would fail
    separately.
--}}

@props([
    'event' => null,
    'color' => null,
    'division' => null,
])

@php
    // Reaches a style attribute, so it is whitelisted rather than trusted.
    $lcColor = \App\Support\Palette::safe($color, '#7c6bf5');
@endphp

<div x-data="lateEntry({
        candidatesUrl: '{{ $event ? route('me.events.bracket.candidates', $event) : '' }}',
        admitUrl: '{{ $event ? route('me.events.bracket.admit', $event) : '' }}',
        division: {{ (int) $division }},
     })"
     @late-entry:open.window="openFor($event.detail)">

    <template x-teleport="body">
        <div x-show="isOpen" x-cloak class="fixed inset-0 z-[70]" role="dialog" aria-modal="true">

            <div x-show="isOpen" x-transition.opacity
                 class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"
                 @click="close()"></div>

            <div x-show="isOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl overflow-hidden"
                 @keydown.escape.window="close()">

                {{-- The band (Design Rule #8). The alpha suffix is hex-only. --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $lcColor }}, {{ $lcColor }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-person-plus text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.late_entry_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="divisionName || '{{ __('events::events.late_entry_subtitle') }}'"></p>
                        </div>
                        <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="relative mt-3 flex flex-wrap gap-1.5">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                            <i class="bi bi-shield-check"></i> {{ __('events::events.late_entry_safe_chip') }}
                        </span>
                    </div>
                </div>

                {{-- Body: search, then the people. --}}
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">

                    <div class="relative">
                        <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="search" x-model="term" @input.debounce.300ms="load()"
                               placeholder="{{ __('events::events.late_entry_search') }}"
                               class="w-full ps-9 pe-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    </div>

                    <template x-if="loading">
                        <div class="py-10 text-center text-muted-foreground text-sm">
                            <i class="bi bi-arrow-repeat animate-spin text-xl block mb-2"></i>
                            {{ __('events::events.late_entry_loading') }}
                        </div>
                    </template>

                    <template x-if="! loading && candidates.length === 0">
                        <div class="py-10 text-center">
                            <span class="w-14 h-14 rounded-2xl bg-muted grid place-items-center mx-auto mb-3">
                                <i class="bi bi-people text-2xl text-muted-foreground"></i>
                            </span>
                            <p class="text-sm font-bold text-foreground">{{ __('events::events.late_entry_none') }}</p>
                            <p class="text-xs text-muted-foreground mt-1">{{ __('events::events.late_entry_none_hint') }}</p>
                        </div>
                    </template>

                    <template x-for="person in candidates" :key="person.id">
                        <button type="button" @click="picked = person.id"
                                class="w-full text-start p-3 rounded-xl border bg-white flex items-center gap-3 transition-colors"
                                :class="picked === person.id ? 'border-primary bg-primary/5' : 'border-gray-100 hover:bg-muted/60'">
                            <span class="w-9 h-12 rounded-lg bg-muted grid place-items-center flex-shrink-0 overflow-hidden">
                                <i class="bi bi-person text-muted-foreground"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold text-sm text-foreground truncate" x-text="person.name"></span>
                                <span class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                    <template x-if="! person.division">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold">
                                            <i class="bi bi-exclamation-triangle-fill"></i> {{ __('events::events.late_entry_no_division') }}
                                        </span>
                                    </template>
                                    <template x-if="person.division">
                                        <span class="text-[11px] text-muted-foreground truncate" x-text="'{{ __('events::events.late_entry_currently') }} ' + person.division"></span>
                                    </template>
                                    <template x-if="person.weight">
                                        <span class="text-[11px] text-muted-foreground" x-text="person.weight + ' kg'"></span>
                                    </template>
                                </span>
                            </span>
                            <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                                  :class="picked === person.id ? 'border-primary' : 'border-gray-300'">
                                <span x-show="picked === person.id" class="w-2.5 h-2.5 rounded-full bg-primary"></span>
                            </span>
                        </button>
                    </template>
                </div>

                {{-- Footer: the sheet has something to submit, so it has one. --}}
                <div class="flex-shrink-0 border-t border-gray-100 bg-white px-4 pt-3"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="admit()" :disabled="! picked || saving"
                            class="w-full py-3 rounded-xl text-white font-bold text-sm transition-opacity disabled:opacity-40 flex items-center justify-center gap-2"
                            :style="'background: {{ $lcColor }}'">
                        <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-person-plus'"></i>
                        <span x-text="saving ? '{{ __('events::events.late_entry_working') }}' : '{{ __('events::events.late_entry_submit') }}'"></span>
                    </button>
                    <p class="text-[11px] text-muted-foreground text-center mt-2">{{ __('events::events.late_entry_note') }}</p>
                </div>
            </div>
        </div>
    </template>
</div>

@once
@push('scripts')
<script>
(function () {
    const register = () => {
        if (! window.Alpine || window.Alpine.__lateEntry) return;
        window.Alpine.__lateEntry = true;

        window.Alpine.data('lateEntry', (cfg) => ({
            isOpen: false,
            loading: false,
            saving: false,
            term: '',
            candidates: [],
            picked: null,
            division: cfg.division || null,
            divisionName: '',

            openFor(detail) {
                detail = detail || {};
                if (detail.division) this.division = detail.division;
                this.divisionName = detail.divisionName || '';
                this.picked = null;
                this.term = '';
                this.candidates = [];
                this.isOpen = true;
                document.body.style.overflow = 'hidden';
                this.load();
            },

            close() {
                this.isOpen = false;
                document.body.style.overflow = '';
            },

            async load() {
                if (! this.division) return;
                this.loading = true;
                try {
                    const url = new URL(cfg.candidatesUrl, window.location.origin);
                    url.searchParams.set('category_id', this.division);
                    if (this.term) url.searchParams.set('q', this.term);

                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    const data = await res.json().catch(() => ({}));
                    this.candidates = (data && data.candidates) || [];
                } catch (e) {
                    this.candidates = [];
                } finally {
                    this.loading = false;
                }
            },

            async admit() {
                if (! this.picked || this.saving) return;
                this.saving = true;
                try {
                    const res = await fetch(cfg.admitUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            _method: 'PUT',
                            category_id: this.division,
                            competitor_id: this.picked,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));

                    if (! res.ok || data.success === false) {
                        window.showToast('error', data.message || 'That did not work.');
                        return;
                    }

                    window.showToast('success', data.message || '');
                    this.close();

                    if (window.BracketBoard && window.BracketBoard.reload) {
                        window.BracketBoard.reload();
                    }
                } catch (e) {
                    window.showToast('error', 'That did not work.');
                } finally {
                    this.saving = false;
                }
            },
        }));
    };

    if (window.Alpine) register();
    document.addEventListener('alpine:init', register);
})();
</script>
@endpush
@endonce
