{{-- How the points are counted — the WKF formula in five lines. $factor, $color. --}}
<div x-data="{ open: false }" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <button type="button" @click="open = ! open" class="m-press w-full flex items-center gap-3 p-4 text-start">
        <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 text-white" style="background: {{ $color }};"><i class="bi bi-calculator"></i></span>
        <span class="flex-1 font-bold text-foreground text-sm">{{ __('rankings::messages.how_title') }}</span>
        <i class="bi bi-chevron-down text-muted-foreground transition-transform" :class="open && 'rotate-180'"></i>
    </button>
    <ul x-show="open" x-cloak x-transition.opacity class="px-4 pb-4 space-y-2 text-[13px] text-muted-foreground">
        <li class="flex gap-2"><i class="bi bi-calendar-check text-primary mt-0.5"></i><span>{{ __('rankings::messages.how_events') }}</span></li>
        <li class="flex gap-2"><i class="bi bi-plus-slash-minus text-primary mt-0.5"></i><span>{{ __('rankings::messages.how_formula', ['factor' => $factor]) }}</span></li>
        <li class="flex gap-2"><i class="bi bi-trophy text-primary mt-0.5"></i><span>{{ __('rankings::messages.how_places') }}</span></li>
        <li class="flex gap-2"><i class="bi bi-hourglass-split text-primary mt-0.5"></i><span>{{ __('rankings::messages.how_decay') }}</span></li>
        <li class="flex gap-2"><i class="bi bi-sort-down text-primary mt-0.5"></i><span>{{ __('rankings::messages.how_ties') }}</span></li>
    </ul>
</div>
