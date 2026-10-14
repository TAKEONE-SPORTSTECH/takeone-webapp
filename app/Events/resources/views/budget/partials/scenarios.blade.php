{{-- Saved what-ifs. "Now" is each one recomputed against today's costs. --}}
<section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5" aria-label="{{ __('events::events.budget_scenarios') }}">
    <div class="flex items-center justify-between gap-2">
        <h2 class="text-sm font-extrabold text-foreground">{{ __('events::events.budget_scenarios') }}</h2>
        <span class="text-[11px] text-muted-foreground" x-show="b.approved_at">{{ __('events::events.budget_has_approved') }}</span>
    </div>

    <template x-if="! b.scenarios.length">
        <p class="text-[12px] text-muted-foreground mt-2">{{ __('events::events.budget_no_scenarios') }}</p>
    </template>

    <div class="mt-3 space-y-2">
        <template x-for="s in b.scenarios" :key="s.uuid">
            <div class="rounded-xl border p-3" :class="s.approved ? 'border-green-300 bg-green-50/60' : 'border-gray-200'">
                <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-extrabold text-foreground truncate">
                            <span x-text="s.name"></span>
                            <span x-show="s.approved" class="ms-1 text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-green-600 text-white align-middle">{{ __('events::events.budget_approved_chip') }}</span>
                        </p>
                        <p class="text-[11px] text-muted-foreground mt-0.5"
                           x-text="money(s.fee) + ' ' + b.currency + ' × ' + s.entrants + (s.sponsor > 0 ? ' · ' + @js(__('events::events.budget_sponsor_short')) + ' ' + money(s.sponsor) : '') + ' · ' + (s.medals_mode === 'full' ? @js(__('events::events.budget_medals_full')) : @js(__('events::events.budget_medals_entries')))"></p>
                    </div>
                    <span class="text-sm font-black" :class="s.now < 0 ? 'text-red-700' : 'text-green-700'" x-text="signed(s.now)"></span>
                </div>
                <div class="flex flex-wrap gap-1.5 mt-2.5">
                    <button type="button" @click="loadScenario(s)" class="m-press h-8 px-3 rounded-lg border border-gray-200 bg-white text-[12px] font-bold text-gray-700">{{ __('events::events.budget_try_it') }}</button>
                    <button type="button" @click="openApprove(s)" x-show="! s.approved" class="m-press h-8 px-3 rounded-lg text-white text-[12px] font-bold bg-green-700">{{ __('events::events.budget_approve') }}</button>
                    <button type="button" @click="removeScenario(s)" class="m-press h-8 px-3 rounded-lg border border-red-200 bg-white text-[12px] font-bold text-red-600" aria-label="{{ __('events::events.budget_remove') }}"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        </template>
    </div>
</section>
