{{-- The three numbers behind the suggested fee. Saved as soon as one changes. --}}
<section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5" aria-label="{{ __('events::events.budget_targets') }}">
    <h2 class="text-sm font-extrabold text-foreground">{{ __('events::events.budget_targets') }}</h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3">
        <div>
            <label for="bud-target" class="block text-xs font-medium text-gray-600 mb-1">{{ __('events::events.budget_target_profit') }}</label>
            <div class="relative">
                <span class="absolute start-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none" x-text="b.currency"></span>
                <input id="bud-target" type="number" min="0" step="10" inputmode="decimal" :value="b.settings.target_profit"
                       @change="saveSetting('target_profit', parseFloat($event.target.value || 0))"
                       class="w-full ps-12 pe-2 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
            </div>
        </div>
        <div>
            <label for="bud-reserve" class="block text-xs font-medium text-gray-600 mb-1">{{ __('events::events.budget_reserve') }}</label>
            <div class="relative">
                <input id="bud-reserve" type="number" min="0" max="100" step="1" inputmode="decimal" :value="b.settings.reserve_percent"
                       @change="saveSetting('reserve_percent', parseFloat($event.target.value || 0))"
                       class="w-full px-3 pe-8 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                <span class="absolute end-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none">%</span>
            </div>
        </div>
        <div>
            <p class="block text-xs font-medium text-gray-600 mb-1">{{ __('events::events.budget_round_to') }}</p>
            <div class="flex gap-1.5" role="radiogroup" aria-label="{{ __('events::events.budget_round_to') }}">
                @foreach ([0.5, 1, 5] as $step)
                    <button type="button" role="radio" :aria-checked="b.settings.round_to == {{ $step }}" @click="saveSetting('round_to', {{ $step }})"
                            class="m-press flex-1 h-10 rounded-xl border text-sm font-bold transition-colors"
                            :class="b.settings.round_to == {{ $step }} ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 bg-white text-gray-700'">{{ $step }}</button>
                @endforeach
            </div>
        </div>
    </div>
</section>
