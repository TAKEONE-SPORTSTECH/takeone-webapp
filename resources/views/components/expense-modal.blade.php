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
                    <h5 class="modal-title font-bold">Record Manual Expense</h5>
                    <p class="text-sm text-muted-foreground mb-0">Add a new expense or refund transaction to the ledger</p>
                </div>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showExpenseModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body px-6 py-4">
                <form action="{{ route('admin.club.financials.expense', $club->slug) }}" method="POST" id="expenseForm">
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
                            <label class="form-label">Transaction Type</label>
                            <input type="hidden" name="type_display" :value="expenseType">
                            <button type="button"
                                    @click="open = !open"
                                    class="w-full flex items-center justify-between gap-2 form-control text-left">
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
                                        <i x-show="expenseType === opt.value" class="bi bi-check2 ml-auto text-primary"></i>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Expense Category --}}
                        <div x-show="expenseType === 'expense'" x-transition>
                            <label class="form-label">Expense Category</label>
                            <select name="category" class="form-select">
                                <option value="">Select category</option>
                                <option value="rent">Rent</option>
                                <option value="utilities">Utilities</option>
                                <option value="equipment">Equipment</option>
                                <option value="salaries">Salaries</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="marketing">Marketing</option>
                                <option value="insurance">Insurance</option>
                                <option value="other">Other</option>
                            </select>
                            <small class="text-muted-foreground">Choose the category for proper expense tracking</small>
                        </div>

                        {{-- Description --}}
                        <div>
                            <label class="form-label">Description <span class="text-destructive">*</span></label>
                            <input type="text" name="description" class="form-control" placeholder="e.g., Monthly gym rent" required>
                            <small class="text-muted-foreground">Brief description of the transaction</small>
                        </div>

                        {{-- Amount & Date --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Amount <span class="text-destructive">*</span></label>
                                <input type="number" name="amount" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                                <small class="text-muted-foreground">Amount before VAT</small>
                            </div>
                            <div>
                                <label class="form-label">Date <span class="text-destructive">*</span></label>
                                <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>

                        {{-- Payment Method --}}
                        <div x-data="{ method: 'cash' }">
                            <label class="form-label">Payment Method</label>
                            <input type="hidden" name="payment_method" :value="method">
                            <div class="grid grid-cols-4 gap-2">
                                <label class="payment-option" :class="{ 'active': method === 'cash' }" @click="method = 'cash'">
                                    <i class="bi bi-cash-stack text-lg"></i>
                                    <span>Cash</span>
                                </label>
                                <label class="payment-option" :class="{ 'active': method === 'bank_transfer' }" @click="method = 'bank_transfer'">
                                    <i class="bi bi-bank text-lg"></i>
                                    <span>Bank</span>
                                </label>
                                <label class="payment-option" :class="{ 'active': method === 'card' }" @click="method = 'card'">
                                    <i class="bi bi-credit-card text-lg"></i>
                                    <span>Card</span>
                                </label>
                                <label class="payment-option" :class="{ 'active': method === 'other' }" @click="method = 'other'">
                                    <i class="bi bi-three-dots text-lg"></i>
                                    <span>Other</span>
                                </label>
                            </div>
                        </div>

                        {{-- Notes --}}
                        <div>
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="reference_number" class="form-control" rows="2" placeholder="Additional details..."></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-4">
                            <button type="button" class="btn btn-outline-secondary" @click="showExpenseModal = false">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg mr-2"></i>Record Transaction
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
