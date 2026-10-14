{{-- Medals counted from the event's real divisions and its bronze rule. --}}
<section class="rounded-2xl p-4 sm:p-5 text-white relative overflow-hidden" style="background: linear-gradient(150deg, #b45309, #d97706b0);" aria-label="{{ __('events::events.budget_medals_title') }}">
    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
    <div class="relative">
        <p class="text-xs font-bold uppercase tracking-wide text-white/85">{{ __('events::events.budget_medals_title') }}</p>
        <p class="text-4xl font-black mt-1" x-text="live.medals.total"></p>
        <p class="text-[13px] text-white/90 mt-0.5"
           x-show="b.medal_options.entries.total < b.medal_options.full.total && ! b.medal_options.entries.estimated"
           x-text="b.settings.medal_unit_cost > 0
               ? @js(__('events::events.budget_medals_saving', ['full' => ':f', 'saved' => ':s'])).replace(':f', b.medal_options.full.total).replace(':s', money((b.medal_options.full.total - b.medal_options.entries.total) * b.settings.medal_unit_cost) + ' ' + b.currency)
               : @js(__('events::events.budget_medals_not', ['full' => ':f'])).replace(':f', b.medal_options.full.total)"></p>
        <p class="text-[12px] text-white/90 mt-0.5" x-show="live.medals.estimated">{{ __('events::events.budget_medals_estimate') }}</p>

        <div class="grid grid-cols-3 gap-2 mt-3">
            <div class="rounded-xl bg-white/20 p-2.5 text-center"><p class="text-xl font-black" x-text="live.medals.gold"></p><p class="text-[11px] font-bold">{{ __('events::events.budget_gold') }}</p><p class="text-[10.5px] text-white/85 mt-0.5" x-text="money(live.medals.gold * medalPrice('gold')) + ' ' + b.currency"></p></div>
            <div class="rounded-xl bg-white/20 p-2.5 text-center"><p class="text-xl font-black" x-text="live.medals.silver"></p><p class="text-[11px] font-bold">{{ __('events::events.budget_silver') }}</p><p class="text-[10.5px] text-white/85 mt-0.5" x-text="money(live.medals.silver * medalPrice('silver')) + ' ' + b.currency"></p></div>
            <div class="rounded-xl bg-white/20 p-2.5 text-center"><p class="text-xl font-black" x-text="live.medals.bronze"></p><p class="text-[11px] font-bold">{{ __('events::events.budget_bronze') }}</p><p class="text-[10.5px] text-white/85 mt-0.5" x-text="money(live.medals.bronze * medalPrice('bronze')) + ' ' + b.currency"></p></div>
        </div>
    </div>
</section>

<section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5" aria-label="{{ __('events::events.budget_medals_why') }}">
    <h2 class="text-sm font-extrabold text-foreground">{{ __('events::events.budget_medals_why') }}</h2>
    <p class="text-[12px] text-muted-foreground mt-1 leading-snug"
       x-text="b.medal_options.entries.two_bronzes ? @js(__('events::events.budget_medals_rule_two')) : @js(__('events::events.budget_medals_rule_one'))"></p>

    <div class="mt-2 divide-y divide-gray-100">
        <template x-for="row in [
            { k: '1', label: @js(__('events::events.budget_spread_1')) },
            { k: '2', label: @js(__('events::events.budget_spread_2')) },
            { k: '3', label: @js(__('events::events.budget_spread_3')) },
            { k: '4+', label: @js(__('events::events.budget_spread_4')) },
        ]" :key="row.k">
            <div class="flex items-center gap-3 py-2.5">
                <span class="w-8 h-8 rounded-lg bg-muted grid place-items-center text-xs font-black flex-shrink-0" x-text="row.k"></span>
                <span class="flex-1 text-[13px] text-foreground" x-text="row.label"></span>
                <span class="text-sm font-black" x-text="b.medal_options.entries.spread[row.k]"></span>
            </div>
        </template>
    </div>
    <p class="text-[11px] text-muted-foreground mt-1"
       x-text="@js(__('events::events.budget_divisions_count', ['n' => ':n'])).replace(':n', b.medal_options.full.divisions)"></p>

    {{-- Prices: one default, and a price per type where they differ (a gold
         usually costs more). A type left blank costs the default. --}}
    <div class="mt-3 pt-3 border-t border-gray-100">
        <div class="flex items-center gap-3">
            <label for="bud-medal-cost" class="flex-1 text-sm font-bold text-gray-700">{{ __('events::events.budget_medal_cost') }}</label>
            <div class="relative w-32">
                <span class="absolute start-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none" x-text="b.currency"></span>
                <input id="bud-medal-cost" type="number" min="0" step="0.01" inputmode="decimal" :value="b.settings.medal_unit_cost"
                       @change="saveSetting('medal_unit_cost', parseFloat($event.target.value || 0))"
                       class="w-full ps-12 pe-2 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
            </div>
        </div>
        <p class="text-[11px] text-muted-foreground mt-2">{{ __('events::events.budget_medal_prices_hint') }}</p>
        <div class="grid grid-cols-3 gap-2 mt-2">
            @foreach (['gold' => '#b45309', 'silver' => '#64748b', 'bronze' => '#9a3412'] as $type => $ink)
                <div>
                    <label for="bud-medal-{{ $type }}" class="block text-[11px] font-bold mb-1" style="color: {{ $ink }}">{{ __('events::events.budget_'.$type) }}</label>
                    <input id="bud-medal-{{ $type }}" type="number" min="0" step="0.01" inputmode="decimal"
                           :value="b.settings.medal_{{ $type }}_cost ?? ''" :placeholder="money(b.settings.medal_unit_cost)"
                           @change="saveSetting('medal_{{ $type }}_cost', $event.target.value === '' ? null : parseFloat($event.target.value))"
                           class="w-full px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>
            @endforeach
        </div>
    </div>
</section>
