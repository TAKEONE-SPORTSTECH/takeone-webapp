{{--
    The filter fields themselves — shared verbatim by the desktop panel and the
    mobile sheet, so the two can never drift into offering different filters.

    Every control is Alpine-bound against `f` in the enclosing scope (see
    member-filters.blade.php). No native <select> and no native date input
    (Design Rule #4); the option sets here are short and known, so they are
    selection chips rather than dropdowns — an absolutely-positioned panel would
    be clipped by the sheet's own scroll container.
--}}
<div class="space-y-5">

    {{-- ===== Missing data — the question this filter exists to answer ===== --}}
    <div>
        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">
            {{ __('platform.filter_missing_data') }}
        </p>
        <div class="flex flex-wrap gap-2">
            <template x-for="opt in missingOptions" :key="opt.key">
                <button type="button" @click="toggle('missing', opt.key)"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border text-xs font-semibold transition-colors"
                        :class="f.missing.includes(opt.key)
                            ? 'border-primary bg-primary/5 text-primary'
                            : 'border-gray-200 text-gray-700 hover:bg-muted/60'">
                    <i class="bi" :class="f.missing.includes(opt.key) ? 'bi-check-circle-fill' : opt.icon"></i>
                    <span x-text="opt.label"></span>
                </button>
            </template>
        </div>
        <p class="text-[11px] text-muted-foreground mt-2">{{ __('platform.filter_missing_hint') }}</p>
    </div>

    {{-- ===== Gender ===== --}}
    <div>
        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">
            {{ __('platform.filter_gender') }}
        </p>
        <div class="flex flex-wrap gap-2">
            <template x-for="opt in genderOptions" :key="opt.value">
                <button type="button" @click="f.gender = opt.value"
                        class="px-3 py-2 rounded-xl border text-xs font-semibold transition-colors"
                        :class="f.gender === opt.value
                            ? 'border-primary bg-primary/5 text-primary'
                            : 'border-gray-200 text-gray-700 hover:bg-muted/60'"
                        x-text="opt.label"></button>
            </template>
        </div>
    </div>

    {{-- ===== Age range ===== --}}
    <div>
        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">
            {{ __('platform.filter_age') }}
        </p>
        <div class="flex items-center gap-2">
            <input type="number" min="0" max="120" inputmode="numeric" x-model="f.age_min"
                   placeholder="{{ __('platform.filter_age_min') }}"
                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            <span class="text-muted-foreground text-xs shrink-0">{{ __('platform.filter_to') }}</span>
            <input type="number" min="0" max="120" inputmode="numeric" x-model="f.age_max"
                   placeholder="{{ __('platform.filter_age_max') }}"
                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        </div>
        <p class="text-[11px] text-muted-foreground mt-2">{{ __('platform.filter_age_hint') }}</p>
    </div>

    {{-- ===== Club ===== --}}
    <div x-show="clubs.length">
        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">
            {{ __('platform.filter_club') }}
        </p>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="f.club = ''"
                    class="px-3 py-2 rounded-xl border text-xs font-semibold transition-colors"
                    :class="f.club === '' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-gray-700 hover:bg-muted/60'">
                {{ __('platform.filter_any') }}
            </button>
            <button type="button" @click="f.club = 'none'"
                    class="px-3 py-2 rounded-xl border text-xs font-semibold transition-colors"
                    :class="f.club === 'none' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-gray-700 hover:bg-muted/60'">
                <i class="bi bi-slash-circle me-1"></i>{{ __('platform.filter_no_club') }}
            </button>
            <template x-for="club in clubs" :key="club.id">
                <button type="button" @click="f.club = club.id"
                        class="px-3 py-2 rounded-xl border text-xs font-semibold transition-colors"
                        :class="f.club === club.id ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-gray-700 hover:bg-muted/60'"
                        x-text="club.name"></button>
            </template>
        </div>
    </div>

    {{-- ===== Nationality — only the ones members actually have ===== --}}
    <div x-show="nationalities.length">
        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">
            {{ __('platform.filter_nationality') }}
        </p>
        <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto">
            <button type="button" @click="f.nationality = ''"
                    class="px-3 py-2 rounded-xl border text-xs font-semibold transition-colors"
                    :class="f.nationality === '' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-gray-700 hover:bg-muted/60'">
                {{ __('platform.filter_any') }}
            </button>
            <template x-for="nat in nationalities" :key="nat.code">
                <button type="button" @click="f.nationality = nat.code"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border text-xs font-semibold transition-colors"
                        :class="f.nationality === nat.code ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-gray-700 hover:bg-muted/60'">
                    <span :class="flagClass(nat.code)" x-show="flagClass(nat.code)"></span>
                    <span x-text="nat.label"></span>
                    <span class="text-[10px] text-muted-foreground" x-text="nat.count"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- ===== Email verification ===== --}}
    <div>
        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">
            {{ __('platform.filter_email_verified') }}
        </p>
        <div class="flex flex-wrap gap-2">
            <template x-for="opt in verifiedOptions" :key="opt.value">
                <button type="button" @click="f.verified = opt.value"
                        class="px-3 py-2 rounded-xl border text-xs font-semibold transition-colors"
                        :class="f.verified === opt.value
                            ? 'border-primary bg-primary/5 text-primary'
                            : 'border-gray-200 text-gray-700 hover:bg-muted/60'"
                        x-text="opt.label"></button>
            </template>
        </div>
    </div>

    {{-- ===== Account flags ===== --}}
    <div>
        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">
            {{ __('platform.filter_account') }}
        </p>
        <div class="flex flex-wrap gap-2">
            <template x-for="opt in flagOptions" :key="opt.key">
                <button type="button" @click="toggle('flags', opt.key)"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border text-xs font-semibold transition-colors"
                        :class="f.flags.includes(opt.key)
                            ? 'border-primary bg-primary/5 text-primary'
                            : 'border-gray-200 text-gray-700 hover:bg-muted/60'">
                    <i class="bi" :class="f.flags.includes(opt.key) ? 'bi-check-circle-fill' : opt.icon"></i>
                    <span x-text="opt.label"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- ===== Joined between ===== --}}
    <div>
        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">
            {{ __('platform.filter_joined') }}
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <x-fields.date model="f.joined_from" :placeholder="__('platform.filter_from')" />
            <x-fields.date model="f.joined_to" :placeholder="__('platform.filter_to_date')" />
        </div>
    </div>
</div>
