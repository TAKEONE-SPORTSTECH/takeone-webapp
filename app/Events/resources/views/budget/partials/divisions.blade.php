{{-- Divisions & weight classes — what merging (or splitting) them does to the
     budget. Lists every division under its heading with its athletes, flags the
     small ones, suggests merges with what each saves, and lets the organiser pick
     their own. A SIMULATION: divisions are merged in the draw, never here. --}}
<section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5" aria-label="{{ __('events::events.budget_div_title') }}" x-init="loadDivisions()">
    <div class="flex items-center justify-between gap-2">
        <h2 class="text-sm font-extrabold text-foreground flex items-center gap-2"><i class="bi bi-diagram-3 bracket-icon text-primary"></i>{{ __('events::events.budget_div_title') }}</h2>
        <button type="button" x-show="div.merges.length" @click="clearMerges()" class="m-press h-8 px-3 rounded-lg border border-gray-200 bg-white text-[12px] font-bold text-gray-700">{{ __('events::events.budget_advisor_undo') }}</button>
    </div>

    <template x-if="! div.list">
        <p class="text-sm text-muted-foreground py-4 text-center"><i class="bi bi-arrow-repeat animate-spin"></i></p>
    </template>

    <template x-if="div.list">
        <div>
            <div class="flex flex-wrap gap-1.5 mt-2">
                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-muted text-foreground" x-text="@js(__('events::events.budget_div_running', ['n' => ':n'])).replace(':n', div.list.counts.running)"></span>
                <span x-show="div.list.counts.alone" class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-red-50 text-red-700" x-text="@js(__('events::events.budget_div_alone', ['n' => ':n'])).replace(':n', div.list.counts.alone)"></span>
                <span x-show="div.list.counts.small" class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-amber-50 text-amber-800" x-text="@js(__('events::events.budget_div_small', ['n' => ':n'])).replace(':n', div.list.counts.small)"></span>
                <span x-show="div.list.counts.empty" class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-muted text-muted-foreground" x-text="@js(__('events::events.budget_div_empty', ['n' => ':n'])).replace(':n', div.list.counts.empty)"></span>
            </div>

            {{-- The impact of what is ticked / paired --}}
            <template x-if="div.impact && div.merges.length">
                <div class="mt-3 rounded-xl border-2 p-3" style="border-color:#c9c3f7;background:#f6f4ff">
                    <p class="text-[11px] font-extrabold uppercase tracking-wide" style="color:#5b4bd6" x-text="@js(__('events::events.budget_div_if_merged', ['n' => ':n'])).replace(':n', div.merges.length)"></p>
                    <div class="grid grid-cols-2 gap-x-3 gap-y-1 mt-2 text-[12.5px]">
                        <span class="text-muted-foreground">{{ __('events::events.budget_div_divisions') }}</span>
                        <span class="font-bold text-end" x-text="div.impact.before.divisions_running + ' → ' + div.impact.after.divisions_running"></span>
                        <span class="text-muted-foreground">{{ __('events::events.budget_medals_word') }}</span>
                        <span class="font-bold text-end" x-text="div.impact.before.medals.total + ' → ' + div.impact.after.medals.total"></span>
                        <span class="text-muted-foreground">{{ __('events::events.budget_div_medal_cost') }}</span>
                        <span class="font-bold text-end" x-text="money(div.impact.before.medal_cost) + ' → ' + money(div.impact.after.medal_cost)"></span>
                        <span class="text-muted-foreground" x-show="div.impact.before.per_division_costs">{{ __('events::events.budget_div_per_division_costs') }}</span>
                        <span class="font-bold text-end" x-show="div.impact.before.per_division_costs" x-text="money(div.impact.before.per_division_costs) + ' → ' + money(div.impact.after.per_division_costs)"></span>
                        <span class="text-muted-foreground">{{ __('events::events.budget_net_result') }}</span>
                        <span class="font-black text-end" :class="div.impact.after.net < 0 ? 'text-red-700' : 'text-green-700'" x-text="signed(div.impact.before.net) + ' → ' + signed(div.impact.after.net) + ' ' + b.currency"></span>
                    </div>
                    <p class="text-[11px] text-muted-foreground mt-2">{{ __('events::events.budget_div_simulation') }}</p>
                </div>
            </template>

            {{-- Suggested merges: neighbours in the same group, one of them small --}}
            <template x-if="div.list.suggestions.length">
                <div class="mt-3">
                    <p class="text-[12px] font-bold text-foreground">{{ __('events::events.budget_div_suggestions') }}</p>
                    <div class="mt-1.5 space-y-1.5">
                        <template x-for="sg in div.list.suggestions" :key="sg.ids.join('-')">
                            <button type="button" @click="toggleMerge(sg.ids)" role="checkbox" :aria-checked="isMerged(sg.ids)"
                                    class="w-full text-start rounded-xl border p-2.5 flex items-start gap-2.5 transition-colors"
                                    :class="isMerged(sg.ids) ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'">
                                <span class="w-5 h-5 mt-0.5 rounded-md border-2 grid place-items-center flex-shrink-0"
                                      :class="isMerged(sg.ids) ? 'border-primary bg-primary text-white' : 'border-gray-300'"><i class="bi bi-check text-xs" x-show="isMerged(sg.ids)"></i></span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[12.5px] font-bold text-foreground leading-snug" x-text="sg.names.join(' + ')"></span>
                                    <span class="block text-[11px] text-muted-foreground mt-0.5"
                                          x-text="sg.athletes.join(' + ') + ' = ' + sg.merged_athletes + ' ' + @js(__('events::events.budget_div_athletes')) + ' · −' + sg.medals_saved + ' ' + @js(__('events::events.budget_medals_word'))"></span>
                                </span>
                                <span class="text-[12.5px] font-black text-green-700 flex-shrink-0" x-text="'+' + money(sg.saves)"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Every division, by group — tap two to merge them yourself --}}
            <button type="button" @click="div.open = ! div.open" class="mt-3 w-full flex items-center justify-between text-[12px] font-bold text-foreground py-1.5">
                <span>{{ __('events::events.budget_div_all') }}</span>
                <i class="bi bi-chevron-down transition-transform" :class="div.open && 'rotate-180'"></i>
            </button>
            <div x-show="div.open" x-transition class="space-y-3 mt-1">
                <p class="text-[11px] text-muted-foreground">{{ __('events::events.budget_div_pick_hint') }}</p>
                <template x-for="(g, gi) in div.list.groups" :key="gi">
                    <div>
                        <p class="text-[11px] font-extrabold uppercase tracking-wide text-muted-foreground" x-text="g.heading || @js(__('events::events.budget_div_ungrouped'))"></p>
                        <div class="mt-1 rounded-xl border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                            <template x-for="d in g.divisions" :key="d.id">
                                <button type="button" @click="pickDivision(d.id)" class="w-full text-start flex items-center gap-2.5 px-3 py-2 transition-colors"
                                        :class="div.picked.includes(d.id) ? 'bg-primary/5' : (mergedWith(d.id) ? 'bg-green-50' : 'bg-white')">
                                    <span style="min-width:1.75rem" class="h-7 px-1.5 rounded-lg grid place-items-center text-[12px] font-black flex-shrink-0"
                                          :class="d.athletes === 1 ? 'bg-red-50 text-red-700' : (d.athletes === 2 ? 'bg-amber-50 text-amber-800' : (d.athletes === 0 ? 'bg-muted text-muted-foreground' : 'bg-muted text-foreground'))"
                                          x-text="d.athletes"></span>
                                    <span class="flex-1 min-w-0 text-[12.5px] text-foreground truncate" x-text="d.name"></span>
                                    <span class="text-[10.5px] text-muted-foreground flex-shrink-0" x-text="(d.medals.gold + d.medals.silver + d.medals.bronze) + ' ' + @js(__('events::events.budget_medals_word'))"></span>
                                    <i class="bi text-[13px] flex-shrink-0" :class="div.picked.includes(d.id) ? 'bi-check-circle-fill text-primary' : (mergedWith(d.id) ? 'bi-link-45deg text-green-700' : 'bi-circle text-gray-300')"></i>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
                <button type="button" x-show="div.picked.length >= 2" @click="mergePicked()"
                        class="m-press w-full h-10 rounded-xl text-white text-[12.5px] font-extrabold" style="background:#7c6bf5"
                        x-text="@js(__('events::events.budget_div_merge_these', ['n' => ':n'])).replace(':n', div.picked.length)"></button>
            </div>
        </div>
    </template>
</section>
