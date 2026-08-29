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
                    {{-- Revenue --}}
                    <div class="rounded-2xl border border-gray-100 p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('personal.event_show_money_collected') }}</p>
                        <div class="flex items-center justify-between text-sm py-1">
                            <span class="text-muted-foreground"><span x-text="fin.paid_participants"></span> {{ __('personal.event_show_paid_entries') }} <span x-text="money(fin.participant_fee)"></span></span>
                            <span class="font-bold text-foreground" x-text="money(fin.participant_revenue)"></span>
                        </div>
                        <template x-if="fin.spectator_enabled">
                            <div class="flex items-center justify-between text-sm py-1">
                                <span class="text-muted-foreground"><span x-text="fin.paid_spectators"></span> {{ __('personal.event_show_tickets_x') }} <span x-text="money(fin.spectator_fee)"></span></span>
                                <span class="font-bold text-foreground" x-text="money(fin.spectator_revenue)"></span>
                            </div>
                        </template>
                        <div class="flex items-center justify-between text-sm pt-2 mt-1 border-t border-gray-100">
                            <span class="font-bold text-foreground">{{ __('personal.event_show_total_revenue') }}</span>
                            <span class="font-black text-green-600" x-text="money(fin.revenue)"></span>
                        </div>
                    </div>

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

                    {{-- Profit --}}
                    <div class="rounded-2xl p-4 flex items-center justify-between" :class="profit >= 0 ? 'bg-green-50' : 'bg-red-50'">
                        <span class="text-sm font-black" :class="profit >= 0 ? 'text-green-700' : 'text-red-700'">{{ __('personal.event_show_profit') }}</span>
                        <span class="text-lg font-black" :class="profit >= 0 ? 'text-green-700' : 'text-red-700'" x-text="money(profit)"></span>
                    </div>
                </div>
            </div>
        </div>
        </template>
    @endif
