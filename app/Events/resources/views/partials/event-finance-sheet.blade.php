{{--
    Event P&L sheet — the organiser's, extracted from the event page so the
    console can own it and the public page can stop carrying it.

    Reads and writes the shared event Alpine root (partials/event-show-script):
    `financeOpen`, `fin`, `expenses`, `profit`, `addExpense()`, `removeExpense()`,
    `money()`. Include it inside an element that carries that x-data.
--}}
    {{-- ===== Finance sheet (owner only) ===== --}}
    @if($finance ?? false)
        <template x-teleport="body">
        <div x-show="financeOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="financeOpen=false" x-transition.opacity></div>
            <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl max-h-[90vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #047857, #059669b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-cash-stack text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('personal.event_show_event_finance') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('personal.event_show_total_revenue') }} · <span x-text="money(fin.revenue)"></span></p>
                        </div>
                        <button type="button" @click="financeOpen=false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    {{-- ===== Money collected =====

                         Every figure here is ARITHMETIC the organiser can
                         check: each thing they sell, the number who bought it,
                         the price each, and the product. They add up to the
                         total printed underneath, and that total is the one in
                         the band.

                         This replaced a single line reading "N entrants paid ×
                         BHD 10" against a total that was nothing of the sort —
                         a leftover from when an event had ONE price, and
                         nonsense the moment it has three (reported
                         2026-09-13). An event's prices are a list, so the sheet
                         shows a list. --}}
                    <div class="rounded-2xl border border-gray-100 p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-1">{{ __('personal.event_show_money_collected') }}</p>

                        <template x-for="src in (fin.sources || [])" :key="src.label + src.kind">
                            <div class="flex items-start gap-2.5 py-2">
                                <span class="w-1.5 h-1.5 rounded-full mt-2 flex-shrink-0"
                                      :class="src.kind === 'late' ? 'bg-amber-500' : (src.estimated ? 'bg-gray-300' : 'bg-green-500')"></span>

                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] font-bold text-foreground truncate"
                                          :class="src.estimated ? 'italic' : ''" x-text="src.label"></span>

                                    {{-- The multiplication, written out. No price
                                         each when the frozen lines disagree on one
                                         (a price edited mid-event) — then the
                                         subtotal stands alone rather than implying
                                         an average nobody was charged. --}}
                                    <span class="block text-[11px] text-muted-foreground tabular-nums mt-0.5">
                                        <span x-text="src.count"></span>
                                        {{ __('personal.event_show_money_paid_word') }}<template x-if="src.unit !== null && src.unit !== undefined"><span> × <span x-text="money(src.unit)"></span></span></template>
                                    </span>

                                    {{-- Who still owes, on the row for the thing
                                         they bought: "2 unpaid × BHD 10". --}}
                                    <span x-show="src.unpaid > 0" x-cloak
                                          class="block text-[11px] text-amber-600 tabular-nums">
                                        <span x-text="src.unpaid"></span>
                                        {{ __('personal.event_show_money_unpaid_word') }}<template x-if="src.unit !== null && src.unit !== undefined"><span> × <span x-text="money(src.unit)"></span></span></template>
                                        <span x-text="'= ' + money(src.expected - src.revenue)"></span>
                                    </span>
                                </span>

                                <span class="font-black text-foreground tabular-nums flex-shrink-0 text-end"
                                      x-text="money(src.revenue)"></span>
                            </div>
                        </template>

                        <p x-show="! (fin.sources || []).length" x-cloak
                           class="text-[11px] text-muted-foreground text-center py-2">{{ __('personal.event_show_money_nothing_yet') }}</p>

                        <div class="flex items-center justify-between text-sm pt-2 mt-1 border-t border-gray-100">
                            <span class="font-bold text-foreground">{{ __('personal.event_show_total_revenue') }}</span>
                            <span class="font-black text-green-600 tabular-nums" x-text="money(fin.revenue)"></span>
                        </div>

                        {{-- An estimate says so. Only an event with ONE price can
                             have them: where entries are priced through options a
                             missing fee line is counted as nothing, because
                             nobody recorded which option was bought. --}}
                        <p x-show="fin.estimated_entries > 0" x-cloak
                           class="text-[10.5px] text-muted-foreground mt-2 leading-snug"
                           x-text="@js(__('personal.event_show_money_estimated', ['count' => ':count', 'amount' => ':amount']))
                                    .replace(':count', fin.estimated_entries)
                                    .replace(':amount', money(fin.list_price))"></p>
                    </div>

                    {{-- The forecast. What the event takes if every entrant on
                         the list pays — the same arithmetic over all entries
                         instead of the paid ones, so the gap between the two IS
                         what is outstanding. Hidden when nothing is owed: a
                         forecast identical to the total is noise. --}}
                    <template x-if="fin.expected_outstanding > 0">
                        <div class="rounded-2xl border border-gray-100 p-3">
                            <div class="flex items-center justify-between text-sm py-1">
                                <span class="text-muted-foreground">{{ __('personal.event_show_money_outstanding') }}</span>
                                <span class="font-bold text-amber-600 tabular-nums" x-text="money(fin.expected_outstanding)"></span>
                            </div>
                            <div class="flex items-center justify-between text-sm py-1 pt-2 mt-1 border-t border-gray-100">
                                <span class="font-bold text-foreground">{{ __('personal.event_show_money_forecast') }}</span>
                                <span class="font-black text-foreground tabular-nums" x-text="money(fin.expected_revenue)"></span>
                            </div>
                            <p class="text-[10.5px] text-muted-foreground mt-1"
                               x-text="@js(__('personal.event_show_money_unpaid_entries', ['count' => ':count'])).replace(':count', fin.unpaid_entries)"></p>

                            {{-- Why the head count can exceed what is owed: an
                                 entrant with no fee recorded owes an amount
                                 nobody can name, so they are in the count and
                                 not in the forecast. Said out loud, because a
                                 figure that does not tie is what sends an
                                 organiser looking for a bug. --}}
                            <p x-show="fin.unpriced_entries > 0" x-cloak
                               class="text-[10.5px] text-muted-foreground leading-snug"
                               x-text="@js(__('personal.event_show_money_unpriced', ['count' => ':count'])).replace(':count', fin.unpriced_entries)"></p>
                        </div>
                    </template>

                    {{-- Expenses --}}
                    <div class="rounded-2xl border border-gray-100 p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('personal.event_show_expenses') }}</p>
                        <div class="space-y-1.5">
                            <template x-for="x in fin.expenses" :key="x.id">
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="flex-1 min-w-0 truncate text-foreground" x-text="x.label"></span>
                                    <span class="font-bold text-red-600" x-text="'− ' + money(x.amount)"></span>
                                    <button type="button" @click="removeExpense(x.id)" class="m-press w-7 h-7 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0"><i class="bi bi-x-lg text-[10px]"></i></button>
                                </div>
                            </template>
                            <p x-show="!fin.expenses.length" class="text-[11px] text-muted-foreground text-center py-1">{{ __('personal.event_show_no_expenses') }}</p>
                        </div>
                        {{-- Add expense --}}
                        <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-100">
                            <input x-model="newExpLabel" type="text" placeholder="{{ __('personal.event_show_ph_expense') }}"
                                   class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                            <div class="relative w-28 flex-shrink-0">
                                <span class="absolute start-2 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none" x-text="fin.currency"></span>
                                <input x-model="newExpAmount" type="number" min="0" step="0.001" inputmode="decimal" placeholder="0"
                                       class="w-full ps-12 pe-2 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                            </div>
                            <button type="button" @click="addExpense()" :disabled="busy" class="m-press w-9 h-9 rounded-xl bg-primary text-white grid place-items-center flex-shrink-0 disabled:opacity-50"><i class="bi bi-plus-lg"></i></button>
                        </div>
                        <div class="flex items-center justify-between text-sm pt-2 mt-2 border-t border-gray-100">
                            <span class="font-bold text-foreground">{{ __('personal.event_show_total_expenses') }}</span>
                            <span class="font-black text-red-600" x-text="'− ' + money(expensesTotal)"></span>
                        </div>
                    </div>

                    {{-- Profit, with the subtraction it came from written
                         underneath — the last figure in the sheet should not be
                         the one number the reader has to take on trust. --}}
                    <div class="rounded-2xl p-4 flex items-center justify-between gap-3" :class="profit >= 0 ? 'bg-green-50' : 'bg-red-50'">
                        <span class="min-w-0">
                            <span class="block text-sm font-black" :class="profit >= 0 ? 'text-green-700' : 'text-red-700'">{{ __('personal.event_show_profit') }}</span>
                            <span class="block text-[11px] tabular-nums mt-0.5" :class="profit >= 0 ? 'text-green-700/70' : 'text-red-700/70'"
                                  x-text="money(fin.revenue) + ' − ' + money(expensesTotal)"></span>
                        </span>
                        <span class="text-lg font-black tabular-nums flex-shrink-0" :class="profit >= 0 ? 'text-green-700' : 'text-red-700'" x-text="money(profit)"></span>
                    </div>
                </div>
            </div>
        </div>
        </template>
    @endif
