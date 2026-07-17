@props(['club'])

{{-- Record Expense/Transaction Modal --}}
<div x-show="showExpenseModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showExpenseModal = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-xl relative rounded-lg overflow-hidden" @click.stop>
            <div class="modal-header border-b px-6 py-4">
                <div>
                    <h5 class="modal-title font-bold">{{ __('shared.components_expense_modal_title') }}</h5>
                    <p class="text-sm text-muted-foreground mb-0">{{ __('shared.components_expense_modal_subtitle') }}</p>
                </div>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showExpenseModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body px-6 py-4">
                <form x-data="{ isRecurring: false }"
                      :action="isRecurring ? '{{ route('admin.club.financials.recurring.store', $club->slug) }}' : '{{ route('admin.club.financials.expense', $club->slug) }}'"
                      method="POST" id="expenseForm">
                    @csrf
                    <input type="hidden" name="_expense_type" x-bind:value="expenseType">

                    <div class="space-y-4">

                        {{-- Transaction Type --}}
                        <div class="relative"
                             x-data="{
                                open: false,
                                options: [
                                    { value: 'expense', label: 'Expense', icon: 'bi-dash-circle',           color: 'text-red-500' },
                                    { value: 'refund',  label: 'Refund',  icon: 'bi-arrow-counterclockwise', color: 'text-orange-500' },
                                ],
                                get current() { return this.options.find(o => o.value === expenseType) }
                             }" @click.outside="open = false">
                            <label class="form-label">{{ __('shared.components_expense_modal_transaction_type') }}</label>
                            <input type="hidden" name="type_display" :value="expenseType">
                            <button type="button"
                                    @click="open = !open"
                                    class="w-full flex items-center justify-between gap-2 form-control text-start">
                                <span class="flex items-center gap-2">
                                    <i class="bi text-base" :class="[current.icon, current.color]"></i>
                                    <span x-text="current.label"></span>
                                </span>
                                <i class="bi bi-chevron-down text-muted-foreground text-xs transition-transform duration-200"
                                   :class="{ 'rotate-180': open }"></i>
                            </button>
                            <div x-show="open" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 -translate-y-1"
                                 class="absolute z-50 mt-1 w-full bg-white border border-border rounded-lg shadow-lg overflow-hidden">
                                <template x-for="opt in options" :key="opt.value">
                                    <button type="button"
                                            @click="expenseType = opt.value; open = false"
                                            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-muted/50 transition-colors"
                                            :class="{ 'bg-primary/5 font-medium': expenseType === opt.value }">
                                        <i class="bi text-base w-5 text-center" :class="[opt.icon, opt.color]"></i>
                                        <span x-text="opt.label"></span>
                                        <i x-show="expenseType === opt.value" class="bi bi-check2 ms-auto text-primary"></i>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Expense Category --}}
                        <div x-show="expenseType === 'expense'" x-transition>
                            <label class="form-label">{{ __('shared.components_expense_modal_expense_category') }}</label>
                            <x-select-menu name="category" :value="''" :placeholder="__('shared.components_expense_modal_select_category')"
                                           :options="[
                                               ['value' => 'rent', 'label' => __('shared.components_expense_modal_cat_rent')],
                                               ['value' => 'utilities', 'label' => __('shared.components_expense_modal_cat_utilities')],
                                               ['value' => 'equipment', 'label' => __('shared.components_expense_modal_cat_equipment')],
                                               ['value' => 'salaries', 'label' => __('shared.components_expense_modal_cat_salaries')],
                                               ['value' => 'maintenance', 'label' => __('shared.components_expense_modal_cat_maintenance')],
                                               ['value' => 'marketing', 'label' => __('shared.components_expense_modal_cat_marketing')],
                                               ['value' => 'insurance', 'label' => __('shared.components_expense_modal_cat_insurance')],
                                               ['value' => 'other', 'label' => __('shared.components_expense_modal_cat_other')],
                                           ]" />
                            <small class="text-muted-foreground">{{ __('shared.components_expense_modal_category_help') }}</small>
                        </div>

                        {{-- Description --}}
                        <div>
                            <label class="form-label">{{ __('shared.components_expense_modal_description') }} <span class="text-destructive">*</span></label>
                            <input type="text" name="description" class="form-control" placeholder="{{ __('shared.components_expense_modal_description_placeholder') }}" required>
                            <small class="text-muted-foreground">{{ __('shared.components_expense_modal_description_help') }}</small>
                        </div>

                        {{-- Recurring toggle --}}
                        <div x-show="expenseType === 'expense'" x-transition class="flex items-center justify-between rounded-lg border border-border px-3 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <span class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center flex-shrink-0"><i class="bi bi-arrow-repeat text-primary"></i></span>
                                <div>
                                    <p class="text-sm font-medium text-foreground">{{ __('shared.components_expense_modal_recurring') }}</p>
                                    <p class="text-xs text-muted-foreground">{{ __('shared.components_expense_modal_recurring_help') }}</p>
                                </div>
                            </div>
                            <button type="button" role="switch" :aria-checked="isRecurring.toString()" @click="isRecurring = !isRecurring"
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors"
                                    :class="isRecurring ? 'bg-primary' : 'bg-gray-200'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform" :class="isRecurring ? 'translate-x-6' : 'translate-x-1'"></span>
                            </button>
                        </div>

                        {{-- Amount & Date --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">{{ __('shared.components_expense_modal_amount') }} <span class="text-destructive">*</span></label>
                                <input type="number" name="amount" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                                <small class="text-muted-foreground">{{ __('shared.components_expense_modal_amount_help') }}</small>
                            </div>
                            <div>
                                <label class="form-label" x-text="isRecurring ? '{{ __('shared.components_expense_modal_recurring_day') }}' : '{{ __('shared.components_expense_modal_date') }}'"></label>
                                <input type="date" :name="isRecurring ? 'recurring_date' : 'transaction_date'" class="form-control"
                                       value="{{ date('Y-m-d') }}" :max="isRecurring ? null : '{{ date('Y-m-d') }}'" required>
                                <small x-show="isRecurring" x-cloak class="text-muted-foreground">{{ __('shared.components_expense_modal_recurring_day_help') }}</small>
                            </div>
                        </div>

                        {{-- Payment Method --}}
                        <div x-data="{ method: 'cash' }">
                            <label class="form-label">{{ __('shared.components_expense_modal_payment_method') }}</label>
                            <input type="hidden" name="payment_method" :value="method">
                            <div class="grid grid-cols-4 gap-2">
                                <label class="payment-option" :class="{ 'active': method === 'cash' }" @click="method = 'cash'">
                                    <i class="bi bi-cash-stack text-lg"></i>
                                    <span>{{ __('shared.components_expense_modal_pay_cash') }}</span>
                                </label>
                                <label class="payment-option" :class="{ 'active': method === 'bank_transfer' }" @click="method = 'bank_transfer'">
                                    <i class="bi bi-bank text-lg"></i>
                                    <span>{{ __('shared.components_expense_modal_pay_bank') }}</span>
                                </label>
                                <label class="payment-option" :class="{ 'active': method === 'card' }" @click="method = 'card'">
                                    <i class="bi bi-credit-card text-lg"></i>
                                    <span>{{ __('shared.components_expense_modal_pay_card') }}</span>
                                </label>
                                <label class="payment-option" :class="{ 'active': method === 'other' }" @click="method = 'other'">
                                    <i class="bi bi-three-dots text-lg"></i>
                                    <span>{{ __('shared.components_expense_modal_pay_other') }}</span>
                                </label>
                            </div>
                        </div>

                        {{-- Notes --}}
                        <div>
                            <label class="form-label">{{ __('shared.components_expense_modal_notes') }}</label>
                            <textarea :name="isRecurring ? 'notes' : 'reference_number'" class="form-control" rows="2" placeholder="{{ __('shared.components_expense_modal_notes_placeholder') }}"></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-4">
                            <button type="button" class="btn btn-outline-secondary" @click="showExpenseModal = false">{{ __('shared.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi me-2" :class="isRecurring ? 'bi-arrow-repeat' : 'bi-check-lg'"></i>
                                <span x-text="isRecurring ? '{{ __('shared.components_expense_modal_record_recurring') }}' : '{{ __('shared.components_expense_modal_record_transaction') }}'"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
