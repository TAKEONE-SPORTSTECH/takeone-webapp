@props([
    'model'       => null,   // Alpine state path in a PARENT scope (e.g. "tx.transaction_date"). Omit for a standalone control.
    'value'       => null,   // initial YYYY-MM-DD when standalone (server-rendered forms)
    'name'        => null,   // hidden input name so it posts with a native form
    'nameExpr'    => null,   // Alpine expression for a LIVE name (wins over `name`)
    'min'         => null,   // 'YYYY-MM-DD' literal — earlier days are disabled
    'max'         => null,   // 'YYYY-MM-DD' literal — later days are disabled
    'minExpr'     => null,   // Alpine expression for a LIVE min (wins over `min`)
    'maxExpr'     => null,   // Alpine expression for a LIVE max (wins over `max`)
    'placeholder' => null,
    'error'       => null,
    'change'      => null,   // Alpine expression to run after a pick
    'dayOnly'     => false,  // trigger shows just the day number ("repeats on day 25")
    'variant'     => 'calendar',   // 'calendar' (month grid) | 'dropdown' (Day / Month / Year)
])
@php
    $selfManaged = $model === null;
    $expr = $model ?? 'sel';
    $minJs = $minExpr ?: Illuminate\Support\Js::from($min);
    $maxJs = $maxExpr ?: Illuminate\Support\Js::from($max);
    $placeholder = $placeholder ?? __('admin.fin_pick_date');
@endphp

@if($variant === 'dropdown')
{{--
  Day / Month / Year variant — same contract as the calendar (model|value, name|nameExpr,
  min|minExpr, max|maxExpr, change), but the year is a typeable numeric field so a date
  decades away is reachable without paging. Styled per Design Rule #4 (no native select),
  self-contained x-data, panel expands in-flow (no clipping in a scroll container).
--}}
<div x-data="{
        @if($selfManaged) sel: {{ Illuminate\Support\Js::from((string) ($value ?? '')) }}, @endif
        dOpen: false, mOpen: false,
        day: '', month: '', year: '',
        months: [ 'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec' ]
            .map((s, i) => ({ v: String(i + 1).padStart(2, '0'), s })),

        get min() { return {{ $minJs }} },
        get max() { return {{ $maxJs }} },
        get minYear() { return this.min ? Number(this.min.slice(0, 4)) : new Date().getFullYear() - 100 },
        get maxYear() { return this.max ? Number(this.max.slice(0, 4)) : new Date().getFullYear() + 10 },
        get days() {
            const n = (this.year && this.month)
                ? new Date(Number(this.year), Number(this.month), 0).getDate() : 31;
            return Array.from({ length: n }, (_, i) => String(i + 1).padStart(2, '0'));
        },
        get monthLabel() { return this.months.find(m => m.v === this.month)?.s || '' },

        // Disable a part when EVERY date it could still form is out of range.
        dayOut(d)   { const iso = this.compose(d, this.month, this.year); return iso ? this.oob(iso) : false },
        monthOut(m) { if (!this.year) return false; const lo = `${this.year}-${m}-01`; const hi = `${this.year}-${m}-31`; return (this.max && lo > this.max) || (this.min && hi < this.min) },

        oob(iso) { return !!((this.min && iso < this.min) || (this.max && iso > this.max)) },
        compose(d, m, y) {
            if (!/^\d{4}$/.test(String(y)) || !m || !d) return '';
            if (Number(y) < this.minYear || Number(y) > this.maxYear) return '';
            // Reject an impossible day for the month (e.g. Feb 30) rather than let the
            // string form a date that silently wraps.
            const dim = new Date(Number(y), Number(m), 0).getDate();
            if (Number(d) < 1 || Number(d) > dim) return '';
            return `${y}-${m}-${String(d).padStart(2, '0')}`;
        },

        seed(v) {
            if (v && /^\d{4}-\d{2}-\d{2}$/.test(v)) { [this.year, this.month, this.day] = v.split('-'); }
            else { this.year = ''; this.month = ''; this.day = ''; }
        },
        push() {
            const iso = this.compose(this.day, this.month, this.year);
            const next = (iso && !this.oob(iso)) ? iso : '';
            if ({{ $expr }} !== next) { {{ $expr }} = next;{{ $change ? ' '.$change.';' : '' }} }
        },
        init() {
            this.seed({{ $expr }});
            this.$watch('{{ $expr }}', v => { if (v !== this.compose(this.day, this.month, this.year)) this.seed(v); });
        },
     }"
     class="w-full">

    <div class="grid grid-cols-3 gap-2">
        {{-- Day --}}
        <div class="relative" @click.outside="dOpen = false" @keydown.escape.stop="dOpen = false">
            <button type="button" @click="dOpen = !dOpen; mOpen = false"
                    class="w-full flex items-center justify-between gap-1 px-3 py-2.5 bg-white border rounded-xl text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent {{ $error ? 'border-red-400' : 'border-gray-200' }}"
                    :class="dOpen ? 'ring-2 ring-primary border-transparent' : ''">
                <span class="flex items-center gap-2 truncate">
                    <i class="bi bi-calendar-day text-primary/40" :class="day && 'text-primary'"></i>
                    <span :class="day ? 'text-foreground font-medium' : 'text-muted-foreground'" x-text="day || '{{ __('Day') }}'"></span>
                </span>
                <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform" :class="dOpen && 'rotate-180'"></i>
            </button>
            <div x-show="dOpen" x-cloak x-transition.opacity
                 class="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto bg-white border border-gray-100 rounded-xl shadow-lg p-1">
                <template x-for="d in days" :key="d">
                    <button type="button" :disabled="dayOut(d)" @click="day = d; dOpen = false; push()"
                            class="w-full text-start px-3 py-1.5 rounded-lg text-sm transition-colors"
                            :class="day === d ? 'bg-primary/5 font-semibold text-primary' : (dayOut(d) ? 'text-gray-300 cursor-not-allowed' : 'text-foreground hover:bg-muted/60')"
                            x-text="d"></button>
                </template>
            </div>
        </div>

        {{-- Month --}}
        <div class="relative" @click.outside="mOpen = false" @keydown.escape.stop="mOpen = false">
            <button type="button" @click="mOpen = !mOpen; dOpen = false"
                    class="w-full flex items-center justify-between gap-1 px-3 py-2.5 bg-white border rounded-xl text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent {{ $error ? 'border-red-400' : 'border-gray-200' }}"
                    :class="mOpen ? 'ring-2 ring-primary border-transparent' : ''">
                <span class="flex items-center gap-2 truncate">
                    <i class="bi bi-calendar-month text-primary/40" :class="month && 'text-primary'"></i>
                    <span :class="month ? 'text-foreground font-medium' : 'text-muted-foreground'" x-text="monthLabel || '{{ __('Month') }}'"></span>
                </span>
                <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform" :class="mOpen && 'rotate-180'"></i>
            </button>
            <div x-show="mOpen" x-cloak x-transition.opacity
                 class="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto bg-white border border-gray-100 rounded-xl shadow-lg p-1">
                <template x-for="mo in months" :key="mo.v">
                    <button type="button" :disabled="monthOut(mo.v)" @click="month = mo.v; if (Number(day) > Number(days.at(-1))) day = days.at(-1); mOpen = false; push()"
                            class="w-full text-start px-3 py-1.5 rounded-lg text-sm transition-colors"
                            :class="month === mo.v ? 'bg-primary/5 font-semibold text-primary' : (monthOut(mo.v) ? 'text-gray-300 cursor-not-allowed' : 'text-foreground hover:bg-muted/60')"
                            x-text="mo.s"></button>
                </template>
            </div>
        </div>

        {{-- Year — typeable. Flag it when a day/month is set but the year is still
             missing (the "almost done, but no year" trap). --}}
        <div class="relative">
            <i class="bi bi-calendar-event absolute start-3 top-1/2 -translate-y-1/2 text-primary/40 pointer-events-none"
               :class="year ? 'text-primary' : ((day || month) && 'text-amber-500')"></i>
            <input type="text" inputmode="numeric" maxlength="4" placeholder="{{ __('Year') }}"
                   :value="year"
                   @input="year = $event.target.value.replace(/\D/g, '').slice(0, 4); push()"
                   class="w-full ps-9 pe-3 py-2.5 bg-white border rounded-xl text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-colors"
                   :class="(day || month) && !year ? 'border-amber-400 ring-1 ring-amber-300 placeholder:text-amber-500' : '{{ $error ? 'border-red-400' : 'border-gray-200' }}'">
        </div>
    </div>
    <p x-show="(day || month) && !year" x-cloak class="mt-1.5 text-[11px] text-amber-600 flex items-center gap-1">
        <i class="bi bi-arrow-up"></i>{{ __('Enter the year to finish') }}
    </p>

    <input type="hidden"
           @if($nameExpr) :name="{{ $nameExpr }}" @elseif($name) name="{{ $name }}" @endif
           :value="{{ $expr }}" {{ $attributes }}>
    @if($error)<p class="mt-1 text-xs text-red-500">{{ $error }}</p>@endif
</div>
@else
{{--
  Calendar field — the styled replacement for <input type="date"> (Design Rule #4).

  The panel expands INSIDE the normal flow instead of floating absolutely, so a
  scrolling bottom-sheet body can never clip it (Mobile Pattern Language §3), and
  all behaviour lives in this file's own x-data — no page glue, no registration
  order to get wrong when the mobile shell swaps content in.

  Contract
    • state  — with `model` it reads/writes that parent-Alpine property; otherwise it
               keeps its own, seeded from `value`.
    • value  — always an ISO 'YYYY-MM-DD' string (or '' when cleared).
    • extra attributes land on the hidden input, so a dynamic `:name` binding works.
--}}
<div x-data="{
        @if($selfManaged) sel: {{ Illuminate\Support\Js::from((string) ($value ?? '')) }}, @endif
        open: false,
        cursor: null,
        weekdays: Array.from({ length: 7 }, (_, i) => new Date(Date.UTC(2024, 0, 7 + i))
            .toLocaleDateString(document.documentElement.lang || undefined, { weekday: 'narrow', timeZone: 'UTC' })),

        get min() { return {{ $minJs }} },
        get max() { return {{ $maxJs }} },

        // 'days' | 'months' | 'years' — tapping the header title zooms out, so a date
        // years away is 3 taps instead of dozens of prev-month clicks.
        view: 'days',
        yearPage: null,

        init() { this.cursor = this.monthOf({{ $expr }}) },
        toggle() {
            this.open = ! this.open;
            if (this.open) { this.cursor = this.monthOf({{ $expr }}); this.view = 'days'; this.yearPage = null }
        },

        /** Header title click: days -> months -> years. */
        zoomOut() {
            if (this.view === 'days') { this.view = 'months'; return }
            if (this.view === 'months') { this.yearPage = this.yearPage ?? this.cursor.getFullYear(); this.view = 'years' }
        },

        months() {
            return Array.from({ length: 12 }, (_, m) => ({
                m,
                label: new Date(2024, m, 1).toLocaleDateString(document.documentElement.lang || undefined, { month: 'short' }),
            }));
        },
        pickMonth(m) {
            this.cursor = new Date(this.cursor.getFullYear(), m, 1);
            this.view = 'days';
        },

        /** Selectable year window: bounded by min/max when given, otherwise a wide
         *  span that covers birthdates as well as future scheduling. */
        get yearFrom() { return this.min ? Number(this.min.slice(0, 4)) : new Date().getFullYear() - 100 },
        get yearTo() { return this.max ? Number(this.max.slice(0, 4)) : new Date().getFullYear() + 10 },
        years() {
            const anchor = this.yearPage ?? this.cursor.getFullYear();
            const start = Math.max(this.yearFrom, anchor - 6);
            const out = [];
            for (let y = start; y < start + 12 && y <= this.yearTo; y++) out.push(y);
            return out;
        },
        pickYear(y) {
            this.cursor = new Date(y, this.cursor.getMonth(), 1);
            this.yearPage = y;
            this.view = 'months';
        },
        canPageYears(dir) {
            const ys = this.years();
            return dir < 0 ? ys[0] > this.yearFrom : ys[ys.length - 1] < this.yearTo;
        },

        /** A month/year is unreachable when its whole span falls outside min/max. */
        monthDisabled(m) {
            const y = this.cursor.getFullYear();
            const first = this.iso(new Date(y, m, 1));
            const last = this.iso(new Date(y, m + 1, 0));
            return (this.min && last < this.min) || (this.max && first > this.max);
        },
        yearDisabled(y) {
            return (this.min && `${y}-12-31` < this.min) || (this.max && `${y}-01-01` > this.max);
        },

        /** Header arrows: step by month, or by year page when zoomed out. */
        step(n) {
            if (this.view === 'days') { this.shift(n); return }
            if (this.view === 'months') { this.cursor = new Date(this.cursor.getFullYear() + n, this.cursor.getMonth(), 1); return }
            this.yearPage = (this.yearPage ?? this.cursor.getFullYear()) + n * 12;
        },
        get headerLabel() {
            if (this.view === 'days') return this.monthLabel();
            if (this.view === 'months') return String(this.cursor.getFullYear());
            const ys = this.years();
            return ys.length ? `${ys[0]} – ${ys[ys.length - 1]}` : '';
        },

        /** Local ISO date — toISOString() would shift the day in non-UTC timezones. */
        iso(d) { return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` },
        get todayIso() { return this.iso(new Date()) },
        monthOf(v) { const d = v ? new Date(v + 'T00:00:00') : new Date(); return new Date(d.getFullYear(), d.getMonth(), 1) },
        shift(n) { this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + n, 1) },

        /** Leading blanks so day 1 lands on its weekday, then every day of the month. */
        cells() {
            if (! this.cursor) return [];
            const first = this.cursor, out = Array.from({ length: first.getDay() }, () => null);
            const days = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
            for (let n = 1; n <= days; n++) {
                const day = this.iso(new Date(first.getFullYear(), first.getMonth(), n));
                out.push({ n, iso: day, today: day === this.todayIso });
            }
            return out;
        },

        disabled(day) { return (this.max && day > this.max) || (this.min && day < this.min) },
        pick(day) { if (this.disabled(day)) return; {{ $expr }} = day; this.open = false;{{ $change ? ' '.$change.';' : '' }} },

        monthLabel() { return this.cursor ? this.cursor.toLocaleDateString(document.documentElement.lang || undefined, { month: 'long', year: 'numeric' }) : '' },
        label() {
            if (! {{ $expr }}) return '';
            const d = new Date({{ $expr }} + 'T00:00:00');
            return {{ $dayOnly ? 'true' : 'false' }}
                ? String(d.getDate())
                : d.toLocaleDateString(document.documentElement.lang || undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        },
     }"
     @keydown.escape="open = false">

    <button type="button" @click="toggle()"
            class="w-full flex items-center gap-2.5 px-3 py-2.5 bg-white border rounded-xl text-sm text-left transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent {{ $error ? 'border-red-400' : 'border-gray-200' }}"
            :class="open ? 'ring-2 ring-purple-500 border-transparent' : ''">
        <i class="bi bi-calendar3 text-primary shrink-0"></i>
        <span class="truncate flex-1" :class="{{ $expr }} ? 'text-foreground font-medium' : 'text-muted-foreground'"
              x-text="label() || @js($placeholder)"></span>
        <i class="bi bi-chevron-down text-muted-foreground transition-transform shrink-0" :class="open && 'rotate-180'"></i>
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2"
         class="mt-2 rounded-2xl border border-gray-100 bg-white shadow-lg overflow-hidden"
         style="display:none;">

        <div class="flex items-center justify-between px-3 py-2.5 bg-muted/50 border-b border-gray-100">
            <button type="button" @click="step(-1)"
                    :disabled="view === 'years' && !canPageYears(-1)"
                    class="w-8 h-8 rounded-lg grid place-items-center text-muted-foreground hover:bg-white hover:text-primary disabled:opacity-30 disabled:hover:bg-transparent transition-colors">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </button>

            {{-- Tap the title to zoom out: month grid, then year grid. --}}
            <button type="button" @click="zoomOut()" :disabled="view === 'years'"
                    class="group flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-sm font-bold text-foreground hover:bg-white transition-colors disabled:hover:bg-transparent">
                <span x-text="headerLabel"></span>
                <i class="bi bi-chevron-down text-[10px] text-muted-foreground group-hover:text-primary transition-transform"
                   :class="view !== 'days' && 'rotate-180'" x-show="view !== 'years'"></i>
            </button>

            <button type="button" @click="step(1)"
                    :disabled="view === 'years' && !canPageYears(1)"
                    class="w-8 h-8 rounded-lg grid place-items-center text-muted-foreground hover:bg-white hover:text-primary disabled:opacity-30 disabled:hover:bg-transparent transition-colors">
                <i class="bi bi-chevron-right rtl:rotate-180"></i>
            </button>
        </div>

        <div class="px-3 pt-2.5" x-show="view === 'days'">
            <div class="grid grid-cols-7 gap-1 mb-1">
                <template x-for="(w, i) in weekdays" :key="i">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground text-center py-1" x-text="w"></span>
                </template>
            </div>
            <div class="grid grid-cols-7 gap-1 pb-2">
                <template x-for="(cell, i) in cells()" :key="i">
                    <div>
                        <template x-if="cell">
                            <button type="button" :disabled="disabled(cell.iso)" @click="pick(cell.iso)"
                                    class="w-full aspect-square rounded-xl text-sm font-semibold tabular-nums grid place-items-center transition-all"
                                    :class="{{ $expr }} === cell.iso
                                        ? 'bg-primary text-white shadow-sm scale-105'
                                        : (disabled(cell.iso)
                                            ? 'text-gray-300 cursor-not-allowed'
                                            : (cell.today ? 'text-primary ring-1 ring-primary/40 hover:bg-accent' : 'text-foreground hover:bg-muted/70 active:scale-95'))"
                                    x-text="cell.n"></button>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Month chooser --}}
        <div class="px-3 py-2.5 grid grid-cols-3 gap-1.5" x-show="view === 'months'" x-cloak>
            <template x-for="mo in months()" :key="mo.m">
                <button type="button" :disabled="monthDisabled(mo.m)" @click="pickMonth(mo.m)"
                        class="py-2.5 rounded-xl text-sm font-semibold transition-all"
                        :class="cursor.getMonth() === mo.m
                            ? 'bg-primary text-white shadow-sm'
                            : (monthDisabled(mo.m)
                                ? 'text-gray-300 cursor-not-allowed'
                                : 'text-foreground hover:bg-muted/70 active:scale-95')"
                        x-text="mo.label"></button>
            </template>
        </div>

        {{-- Year chooser --}}
        <div class="px-3 py-2.5 grid grid-cols-3 gap-1.5" x-show="view === 'years'" x-cloak>
            <template x-for="y in years()" :key="y">
                <button type="button" :disabled="yearDisabled(y)" @click="pickYear(y)"
                        class="py-2.5 rounded-xl text-sm font-semibold tabular-nums transition-all"
                        :class="cursor.getFullYear() === y
                            ? 'bg-primary text-white shadow-sm'
                            : (yearDisabled(y)
                                ? 'text-gray-300 cursor-not-allowed'
                                : 'text-foreground hover:bg-muted/70 active:scale-95')"
                        x-text="y"></button>
            </template>
        </div>

        <div class="flex items-center gap-2 px-3 py-2.5 border-t border-gray-100 bg-muted/30">
            <button type="button" @click="pick(todayIso)" :disabled="disabled(todayIso)"
                    class="flex-1 py-2 rounded-xl bg-white border border-gray-200 text-xs font-semibold text-foreground disabled:opacity-40 transition-colors hover:border-primary hover:text-primary">
                {{ __('admin.fin_today') }}
            </button>
            <button type="button" @click="{{ $expr }} = ''; open = false"
                    class="px-3 py-2 rounded-xl text-xs font-semibold text-muted-foreground hover:text-destructive transition-colors">
                {{ __('admin.fin_clear') }}
            </button>
        </div>
    </div>

    <input type="hidden"
           @if($nameExpr) :name="{{ $nameExpr }}" @elseif($name) name="{{ $name }}" @endif
           :value="{{ $expr }}" {{ $attributes }}>
    @if($error)<p class="mt-1 text-xs text-red-500">{{ $error }}</p>@endif
</div>
@endif
