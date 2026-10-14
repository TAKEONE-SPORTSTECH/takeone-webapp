{{-- Try anything: fee, turnout, medal rule, sponsor. Nothing is saved until the
     organiser keeps it as the plan or saves it as a scenario. --}}
<section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5 space-y-5" aria-label="{{ __('events::events.budget_tune') }}">
    <div class="flex items-center justify-between gap-2">
        <h2 class="text-sm font-extrabold text-foreground">{{ __('events::events.budget_tune') }}</h2>
        <span class="text-[11px] font-bold px-2.5 py-1 rounded-full" style="background:#efedfe;color:#5b4bd6">{{ __('events::events.budget_try_anything') }}</span>
    </div>

    {{-- The two numbers that move most. Each is a centred stepper: − · a box
         you can type in · +, with the slider underneath for big moves. --}}
    <style>
        .bud-num::-webkit-outer-spin-button, .bud-num::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .bud-num { -moz-appearance: textfield; appearance: textfield; }
    </style>

    {{-- Fee --}}
    <div>
        <label for="bud-fee-input" class="block text-sm font-bold text-gray-700 text-center">{{ __('events::events.budget_fee') }}</label>
        <div class="flex items-center justify-center gap-2 mt-2">
            <button type="button" @click="nudge('fee', -0.5, 0, 100000)" class="m-press w-11 h-11 rounded-xl border border-gray-200 bg-white text-lg font-bold text-gray-700" aria-label="{{ __('events::events.budget_less') }}">−</button>
            <div class="relative">
                <input id="bud-fee-input" type="number" min="0" max="100000" step="0.001" inputmode="decimal" x-model.number="tune.fee"
                       @change="tune.fee = Math.min(100000, Math.max(0, Math.round((parseFloat($event.target.value) || 0) * 1000) / 1000))"
                       class="bud-num text-center text-lg font-black h-11 ps-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none" style="width:8.5rem; padding-inline-end:2.75rem">
                <span class="absolute end-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none" x-text="b.currency"></span>
            </div>
            <button type="button" @click="nudge('fee', 0.5, 0, 100000)" class="m-press w-11 h-11 rounded-xl border border-gray-200 bg-white text-lg font-bold text-gray-700" aria-label="{{ __('events::events.budget_more') }}">+</button>
        </div>
        <input id="bud-fee" type="range" min="0" :max="feeMax" step="0.5" x-model.number="tune.fee" class="w-full mt-3" style="accent-color: {{ $color }}" aria-label="{{ __('events::events.budget_fee') }}">
        <p class="text-[11px] text-muted-foreground mt-1.5 text-center"
           x-text="@js(__('events::events.budget_each_bhd', ['n' => ':n'])).replace(':n', money(live.n))"></p>
    </div>

    {{-- Turnout --}}
    <div>
        <label for="bud-n-input" class="block text-sm font-bold text-gray-700 text-center">{{ __('events::events.budget_entrants') }}</label>
        <div class="flex items-center justify-center gap-2 mt-2">
            <button type="button" @click="nudge('entrants', -5, 0, 100000)" class="m-press w-11 h-11 rounded-xl border border-gray-200 bg-white text-lg font-bold text-gray-700" aria-label="{{ __('events::events.budget_less') }}">−</button>
            <input id="bud-n-input" type="number" min="0" max="100000" step="1" inputmode="numeric" x-model.number="tune.entrants"
                   @change="tune.entrants = Math.min(100000, Math.max(0, Math.round(parseFloat($event.target.value) || 0)))"
                   class="bud-num text-center text-lg font-black h-11 px-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none" style="width:8.5rem">
            <button type="button" @click="nudge('entrants', 5, 0, 100000)" class="m-press w-11 h-11 rounded-xl border border-gray-200 bg-white text-lg font-bold text-gray-700" aria-label="{{ __('events::events.budget_more') }}">+</button>
        </div>
        <input id="bud-n" type="range" min="0" :max="entrantsMax" step="1" x-model.number="tune.entrants" class="w-full mt-3" style="accent-color: {{ $color }}" aria-label="{{ __('events::events.budget_entrants') }}">
        <p class="text-[11px] text-muted-foreground mt-1.5 text-center"
           x-text="@js(__('events::events.budget_turnout_so_far', ['entered' => ':e', 'paid' => ':p'])).replace(':e', b.turnout.entered).replace(':p', b.turnout.paid)"></p>
    </div>

    {{-- Medal rule — two selection cards --}}
    <div>
        <p class="text-sm font-bold text-gray-700">{{ __('events::events.budget_medals_rule') }}</p>
        <div class="grid grid-cols-2 gap-2 mt-2" role="radiogroup" aria-label="{{ __('events::events.budget_medals_rule') }}">
            @foreach (['entries', 'full'] as $mode)
                <button type="button" role="radio" :aria-checked="tune.medals_mode === '{{ $mode }}'" @click="tune.medals_mode = '{{ $mode }}'"
                        class="m-press text-start rounded-xl border p-3 transition-colors"
                        :class="tune.medals_mode === '{{ $mode }}' ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'">
                    <span class="block text-sm font-bold text-foreground">{{ __('events::events.budget_medals_'.$mode) }}</span>
                    <span class="block text-[11px] text-muted-foreground mt-0.5" x-text="b.medal_options['{{ $mode }}'].total + ' ' + @js(__('events::events.budget_medals_word'))"></span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Sponsor --}}
    <div>
        <label for="bud-sponsor" class="block text-sm font-bold text-gray-700">{{ __('events::events.budget_sponsor') }}</label>
        <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('events::events.budget_sponsor_hint') }}</p>
        <div class="relative mt-2 w-40">
            <span class="absolute start-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none" x-text="b.currency"></span>
            <input id="bud-sponsor" type="number" min="0" step="1" inputmode="decimal" x-model.number="tune.sponsor"
                   class="w-full ps-12 pe-2 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
        </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-2 pt-1">
        <button type="button" @click="openScenario()" :disabled="busy"
                class="m-press w-full sm:flex-1 h-11 rounded-xl text-white text-sm font-extrabold flex items-center justify-center gap-2" style="background: {{ $color }}">
            <i class="bi bi-bookmark-plus"></i>{{ __('events::events.budget_save_scenario') }}
        </button>
        <button type="button" @click="usePlan()" :disabled="busy"
                class="m-press w-full sm:flex-1 h-11 rounded-xl border border-gray-200 bg-white text-sm font-bold text-gray-700 flex items-center justify-center gap-2">
            <i class="bi bi-pin-angle"></i>{{ __('events::events.budget_keep_as_plan') }}
        </button>
    </div>
</section>
