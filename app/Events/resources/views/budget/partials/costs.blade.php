{{-- Planned costs, grouped. Tap a row to edit it; medals appear under Awards
     as a counted line, not a typed one. --}}
<section class="space-y-3" aria-label="{{ __('events::events.budget_costs_title') }}">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5">
        <div class="flex items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-extrabold text-foreground">{{ __('events::events.budget_costs_title') }}</h2>
                <p class="text-[11px] text-muted-foreground mt-0.5"
                   x-text="money(b.plan.expenses) + ' ' + b.currency + (b.plan.entrants ? ' · ' + money(b.plan.expenses / b.plan.entrants) + ' ' + @js(__('events::events.budget_per_athlete')) : '')"></p>
            </div>
            <button type="button" @click="openItem(null)"
                    class="m-press inline-flex items-center gap-1.5 h-9 px-3.5 rounded-full border text-[12px] font-bold" style="border-color: #7c6bf559; color: #7c6bf5;">
                <i class="bi bi-plus-lg"></i>{{ __('events::events.budget_add_cost') }}
            </button>
        </div>

        <template x-if="shares().length">
            <div class="mt-3">
                <div class="flex h-3 rounded-full overflow-hidden gap-0.5">
                    <template x-for="s in shares()" :key="s.key"><div :style="{ width: s.pct + '%', background: s.colour }"></div></template>
                </div>
                <div class="grid grid-cols-2 gap-x-3 gap-y-1 mt-2.5">
                    <template x-for="s in shares()" :key="s.key">
                        <span class="flex items-center gap-1.5 text-[11.5px]"><span class="w-2.5 h-2.5 rounded" :style="{ background: s.colour }"></span><span x-text="s.label + ' · ' + money(s.value)"></span></span>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="! (b.items || []).length && ! b.plan.medals.cost">
            <div class="mt-4 rounded-xl bg-muted/50 p-4 text-center">
                <i class="bi bi-receipt text-2xl text-muted-foreground"></i>
                <p class="text-sm font-bold text-foreground mt-1">{{ __('events::events.budget_no_costs') }}</p>
                <p class="text-[12px] text-muted-foreground mt-0.5">{{ __('events::events.budget_no_costs_hint') }}</p>
                <button type="button" @click="openStart()" class="m-press mt-3 inline-flex items-center gap-1.5 h-9 px-4 rounded-full text-white text-[12px] font-bold" style="background: {{ $color }}">
                    <i class="bi bi-copy"></i>{{ __('events::events.budget_start_from') }}
                </button>
            </div>
        </template>
    </div>

    {{-- The advisor re-priced some lines on screen only — say so, and offer the way back. --}}
    <template x-if="previewing">
        <div class="rounded-2xl p-3 flex items-center gap-2.5 border" style="background:#f6f4ff;border-color:#c9c3f7">
            <i class="bi bi-stars" style="color:#5b4bd6"></i>
            <span class="flex-1 text-[12.5px] font-bold" style="color:#4c3dc4">{{ __('events::events.budget_previewing') }}</span>
            <button type="button" @click="clearPreview()" class="m-press h-8 px-3 rounded-lg border border-gray-200 bg-white text-[12px] font-bold text-gray-700">{{ __('events::events.budget_advisor_undo') }}</button>
        </div>
    </template>

    <template x-for="g in groups()" :key="g.key">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 bg-muted/40">
                <h3 class="text-[13px] font-extrabold text-foreground flex items-center gap-2">
                    <span x-text="g.label"></span>
                    <span x-show="g.key === 'people' && b.people && b.people.headcount" class="text-[10.5px] font-bold px-2 py-0.5 rounded-full" style="background:#efedfe;color:#5b4bd6"
                          x-text="@js(__('events::events.budget_headcount', ['n' => ':n', 'paid' => ':p', 'vol' => ':v'])).replace(':n', b.people.headcount).replace(':p', b.people.paid).replace(':v', b.people.volunteers)"></span>
                </h3>
                <span class="text-[13px] font-black" x-text="money(groupTotal(g))"></span>
            </div>
            <template x-if="g.key === 'awards' && b.plan.medals.cost > 0">
                <div class="flex items-center gap-3 px-4 py-3 border-t border-gray-100">
                    <span class="flex-1 min-w-0">
                        <span class="block text-[13px] font-bold text-foreground">{{ __('events::events.budget_medals_line') }}</span>
                        <span class="block text-[11px] text-muted-foreground mt-0.5" x-text="b.plan.medals.total + ' × ' + money(b.plan.medals.unit_cost)"></span>
                    </span>
                    <span class="text-[13px] font-black" x-text="money(b.plan.medals.cost)"></span>
                </div>
            </template>
            <template x-for="row in g.rows" :key="row.uuid">
                <button type="button" @click="openItem(row)" class="w-full text-start flex items-center gap-3 px-4 py-3 border-t border-gray-100 hover:bg-muted/40 transition-colors">
                    <span class="flex-1 min-w-0">
                        <span class="block text-[13px] font-bold text-foreground truncate" x-text="row.label"></span>
                        <span class="flex items-center gap-1.5 mt-1">
                            <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full"
                                  :class="{ 'bg-green-50 text-green-700': row.status === 'paid', 'bg-indigo-50 text-indigo-700': row.status === 'quoted', 'bg-amber-50 text-amber-800': row.status === 'planned' }"
                                  x-text="({ planned: @js(__('events::events.budget_status_item_planned')), quoted: @js(__('events::events.budget_status_item_quoted')), paid: @js(__('events::events.budget_status_item_paid')) })[row.status]"></span>
                            <span class="text-[11px] text-muted-foreground" x-show="row.basis === 'per_entrant' && row.quantity === null"
                                  x-text="money(row.amount) + ' ' + @js(__('events::events.budget_each_entrant'))"></span>
                            {{-- People: how many × what each costs ("8 × 30"), or "unpaid" for volunteers. --}}
                            <span class="text-[11px] text-muted-foreground inline-flex items-center gap-1" x-show="row.quantity !== null && row.quantity !== undefined">
                                <i class="bi" :class="row.category === 'people' ? 'bi-people' : 'bi-box-seam'"></i>
                                <span x-text="row.quantity + ' × ' + (Number(row.unit_cost) > 0 ? money(row.unit_cost) : (row.category === 'people' ? @js(__('events::events.budget_unpaid')) : '0'))"></span>
                            </span>
                            <span class="text-[11px] text-muted-foreground" x-show="row.basis === 'per_division'"
                                  x-text="'× ' + ((b.medal_options[tune.medals_mode] || {}).divisions_running || 0) + ' ' + @js(__('events::events.budget_div_divisions')).toLowerCase()"></span>
                        </span>
                    </span>
                    <span class="text-end">
                        <span class="block text-[11px] text-muted-foreground line-through" x-show="isPreviewed(row)" x-text="money(row.basis === 'per_entrant' ? row.amount * tune.entrants : row.amount)"></span>
                        <span class="block text-[13px] font-black" :style="isPreviewed(row) ? 'color:#5b4bd6' : ''" x-text="money(lineCost(row))"></span>
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs rtl:rotate-180"></i>
                </button>
            </template>
            {{-- The plan beside reality: officials actually appointed in the console. --}}
            <template x-if="g.key === 'people' && b.people && b.people.appointed.total">
                <div class="flex items-center gap-3 px-4 py-2.5 border-t border-gray-100 bg-muted/20">
                    <i class="bi bi-person-check text-muted-foreground"></i>
                    <span class="flex-1 text-[11.5px] text-muted-foreground"
                          x-text="@js(__('events::events.budget_appointed', ['n' => ':n', 'paid' => ':p', 'pay' => ':m'])).replace(':n', b.people.appointed.total).replace(':p', b.people.appointed.paid).replace(':m', money(b.people.appointed.pay) + ' ' + b.currency)"></span>
                </div>
            </template>
        </div>
    </template>

    <template x-if="b.plan.covered > 0">
        <div class="rounded-2xl p-3.5 flex items-center justify-between bg-green-50 border border-green-200">
            <span class="text-[13px] font-bold text-green-900">{{ __('events::events.budget_sponsor_covers') }}</span>
            <span class="text-[13px] font-black text-green-900" x-text="'−' + money(b.plan.covered)"></span>
        </div>
    </template>

    <div class="rounded-2xl px-4 py-3.5 flex items-center justify-between text-white" style="background:#111827">
        <span class="text-[13px] font-bold">{{ __('events::events.budget_total_planned') }}</span>
        <span class="text-lg font-black" x-text="money(b.plan.expenses) + ' ' + b.currency"></span>
    </div>
</section>
