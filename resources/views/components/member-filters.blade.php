@props([
    'filters'       => [],        // current state, from MemberFilters::toArray()
    'count'         => 0,         // how many filters are active, from MemberFilters::count()
    'clubs'         => [],        // [['id' => '1', 'name' => '…'], …]
    'nationalities' => [],        // [['code' => 'BH', 'label' => 'Bahrain', 'count' => 93], …]
    'variant'       => 'panel',   // 'panel' (desktop dropdown) | 'sheet' (mobile bottom sheet)
    'id'            => 'memberFilters',
])
{{--
    ADVANCED FILTER for a members list.

    Standalone: it owns its own state, its own markup and its own open/close
    behaviour, and it talks to the page through ONE documented output — a window
    event carrying the chosen filters:

        window.addEventListener('member-filters:apply', e => e.detail /* {missing:[], gender:'', …} */)

    It never fetches anything and never touches the results region itself, so it
    can be dropped onto any list that knows how to re-query. The host decides
    what "apply" means; this decides what may be asked.

    Two faces, one field set: a dropdown panel on desktop, a bottom sheet on
    mobile (teleported to <body> — the mobile shell leaves a transform on
    #shell-content's children, which would otherwise become the containing block
    for a `fixed` sheet and clip it).
--}}
@php
    $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $id) ?: 'memberFilters';

    $state = array_merge([
        'missing' => [], 'gender' => '', 'age_min' => null, 'age_max' => null,
        'nationality' => '', 'club' => '', 'verified' => 'any', 'flags' => [],
        'joined_from' => '', 'joined_to' => '',
    ], (array) $filters);

    $missingOptions = [
        ['key' => 'phone',       'icon' => 'bi-telephone',      'label' => __('platform.filter_no_phone')],
        ['key' => 'email',       'icon' => 'bi-envelope',       'label' => __('platform.filter_no_email')],
        ['key' => 'birthdate',   'icon' => 'bi-calendar-event', 'label' => __('platform.filter_no_birthdate')],
        ['key' => 'gender',      'icon' => 'bi-person',         'label' => __('platform.filter_no_gender')],
        ['key' => 'nationality', 'icon' => 'bi-flag',           'label' => __('platform.filter_no_nationality')],
        ['key' => 'photo',       'icon' => 'bi-camera',         'label' => __('platform.filter_no_photo')],
        ['key' => 'club',        'icon' => 'bi-building',       'label' => __('platform.filter_no_club_link')],
    ];

    $flagOptions = [
        ['key' => 'unclaimed', 'icon' => 'bi-person-dash',   'label' => __('platform.filter_unclaimed')],
        ['key' => 'trainer',   'icon' => 'bi-stopwatch',     'label' => __('platform.filter_trainer')],
        ['key' => 'hidden',    'icon' => 'bi-eye-slash',     'label' => __('platform.filter_hidden')],
    ];

    $genderOptions = [
        ['value' => '',       'label' => __('platform.filter_any')],
        ['value' => 'Male',   'label' => __('platform.filter_male')],
        ['value' => 'Female', 'label' => __('platform.filter_female')],
    ];

    $verifiedOptions = [
        ['value' => 'any', 'label' => __('platform.filter_any')],
        ['value' => 'yes', 'label' => __('platform.filter_verified_yes')],
        ['value' => 'no',  'label' => __('platform.filter_verified_no')],
    ];
@endphp

<div class="relative shrink-0"
     x-data="{
        open: false,
        f: {{ Illuminate\Support\Js::from($state) }},
        applied: {{ (int) $count }},
        clubs: {{ Illuminate\Support\Js::from($clubs) }},
        nationalities: {{ Illuminate\Support\Js::from($nationalities) }},
        missingOptions: {{ Illuminate\Support\Js::from($missingOptions) }},
        flagOptions: {{ Illuminate\Support\Js::from($flagOptions) }},
        genderOptions: {{ Illuminate\Support\Js::from($genderOptions) }},
        verifiedOptions: {{ Illuminate\Support\Js::from($verifiedOptions) }},

        /* Mirrors MemberFilters::count() so the badge agrees with the server. */
        get count() {
            return this.f.missing.length + this.f.flags.length
                + (this.f.gender ? 1 : 0)
                + (this.f.age_min !== null && this.f.age_min !== '' || this.f.age_max !== null && this.f.age_max !== '' ? 1 : 0)
                + (this.f.nationality ? 1 : 0)
                + (this.f.club ? 1 : 0)
                + (this.f.verified !== 'any' ? 1 : 0)
                + (this.f.joined_from || this.f.joined_to ? 1 : 0);
        },

        toggle(group, key) {
            const list = this.f[group];
            const i = list.indexOf(key);
            i === -1 ? list.push(key) : list.splice(i, 1);
        },

        /* The CSS class for our own flag file — never an emoji, which draws
           the ISO letters instead of a flag on Windows (CLAUDE.md -> Flags
           Come From One Local Source). Empty for a code we cannot place, so
           nothing is drawn rather than a wrong flag.

           ⚠️ No double quotes anywhere in this x-data — the attribute is
           delimited by them, so one inside ENDS the attribute and the rest of
           the object renders on the page as text. That shipped. */
        flagClass(code) {
            code = String(code || '').toLowerCase();
            return /^[a-z]{2}$/.test(code) ? 'fi fi-' + code + ' rounded-[2px]' : '';
        },

        clear() {
            this.f = { missing: [], gender: '', age_min: null, age_max: null, nationality: '',
                       club: '', verified: 'any', flags: [], joined_from: '', joined_to: '' };
            this.apply();
        },

        apply() {
            this.applied = this.count;
            this.open = false;
            window.dispatchEvent(new CustomEvent('member-filters:apply', {
                detail: JSON.parse(JSON.stringify(this.f)),
            }));
        },
     }"
     @keydown.escape.window="open = false"
     @if($variant === 'panel') @click.outside="open = false" @endif>

    {{-- ===== Trigger ===== --}}
    <button type="button" @click="open = !open"
            :aria-expanded="open" aria-haspopup="dialog"
            class="flex items-center gap-2 px-4 py-2.5 border rounded-lg text-sm font-medium bg-white transition-colors whitespace-nowrap"
            :class="applied ? 'border-primary text-primary' : 'border-gray-200 text-gray-700 hover:bg-gray-50'">
        <i class="bi bi-sliders"></i>
        <span class="hidden sm:inline">{{ __('platform.filter_button') }}</span>
        <span x-show="applied" x-cloak
              class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-primary text-white text-[11px] font-bold"
              x-text="applied"></span>
        <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform" :class="open && 'rotate-180'"></i>
    </button>

    @if($variant === 'panel')
        {{-- ===== Desktop: anchored dropdown panel ===== --}}
        <div x-show="open" x-cloak role="dialog" aria-modal="false"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="absolute end-0 mt-2 w-96 max-w-[calc(100vw-2rem)] bg-white border border-gray-100 rounded-2xl shadow-lg z-50 flex flex-col max-h-[80vh]">

            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 shrink-0">
                <p class="text-sm font-bold text-foreground">{{ __('platform.filter_title') }}</p>
                <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                        class="w-8 h-8 rounded-full grid place-items-center text-gray-400 hover:bg-muted transition-colors">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4">
                @include('components.member-filters.fields')
            </div>

            <div class="flex items-center gap-2 px-5 py-3 border-t border-gray-100 shrink-0">
                <button type="button" @click="clear()"
                        class="px-4 py-2 rounded-lg text-sm font-medium text-muted-foreground hover:bg-muted transition-colors">
                    {{ __('platform.filter_clear') }}
                </button>
                <button type="button" @click="apply()"
                        class="ms-auto px-5 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary/90 transition-colors">
                    {{ __('platform.filter_apply') }}
                    <span x-show="count" x-cloak x-text="'(' + count + ')'"></span>
                </button>
            </div>
        </div>
    @else
        {{-- ===== Mobile: bottom sheet, teleported out of the shell's transform ===== --}}
        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-[60]" role="dialog" aria-modal="true">
                <div x-show="open" x-transition.opacity @click="open = false"
                     class="absolute inset-0 bg-black/50"></div>

                <div x-show="open"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                     class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-background rounded-t-3xl overflow-hidden shadow-2xl">

                    {{-- Gradient band (Design Rule #8) --}}
                    <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                         style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                        <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                        <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                        <div class="relative flex items-start gap-3">
                            <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                                <i class="bi bi-sliders text-xl"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-lg font-black leading-tight">{{ __('platform.filter_title') }}</h3>
                                <p class="text-[12px] text-white/85 mt-0.5">{{ __('platform.filter_subtitle') }}</p>
                            </div>
                            <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                    class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>

                        <div class="relative mt-3 flex flex-wrap gap-1.5" x-show="count" x-cloak>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                                <i class="bi bi-funnel-fill"></i>
                                <span x-text="count"></span> {{ __('platform.filter_active') }}
                            </span>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto px-5 py-4">
                        @include('components.member-filters.fields')
                    </div>

                    <div class="flex-shrink-0 flex items-center gap-2 px-5 pt-3 bg-white border-t border-gray-100"
                         style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                        <button type="button" @click="clear()"
                                class="m-press px-4 py-3 rounded-xl text-sm font-semibold text-muted-foreground">
                            {{ __('platform.filter_clear') }}
                        </button>
                        <button type="button" @click="apply()"
                                class="m-press flex-1 py-3 rounded-xl bg-primary text-white text-sm font-bold">
                            {{ __('platform.filter_apply') }}
                            <span x-show="count" x-cloak x-text="'(' + count + ')'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>
