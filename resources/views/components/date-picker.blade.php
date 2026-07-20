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
])
@php
    $selfManaged = $model === null;
    $expr = $model ?? 'sel';
    $minJs = $minExpr ?: Illuminate\Support\Js::from($min);
    $maxJs = $maxExpr ?: Illuminate\Support\Js::from($max);
    $placeholder = $placeholder ?? __('admin.fin_pick_date');
@endphp
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

        init() { this.cursor = this.monthOf({{ $expr }}) },
        toggle() { this.open = ! this.open; if (this.open) this.cursor = this.monthOf({{ $expr }}) },

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
            <button type="button" @click="shift(-1)" class="w-8 h-8 rounded-lg grid place-items-center text-muted-foreground hover:bg-white hover:text-primary transition-colors">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </button>
            <span class="text-sm font-bold text-foreground" x-text="monthLabel()"></span>
            <button type="button" @click="shift(1)" class="w-8 h-8 rounded-lg grid place-items-center text-muted-foreground hover:bg-white hover:text-primary transition-colors">
                <i class="bi bi-chevron-right rtl:rotate-180"></i>
            </button>
        </div>

        <div class="px-3 pt-2.5">
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
