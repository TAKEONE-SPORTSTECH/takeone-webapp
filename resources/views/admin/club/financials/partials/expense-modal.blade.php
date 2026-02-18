{{-- Record Expense/Transaction Modal --}}
<div x-show="showExpenseModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showExpenseModal = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-2xl relative rounded-lg overflow-hidden" @click.stop>
            <div class="modal-header border-b px-6 py-4">
                <div>
                    <h5 class="modal-title font-bold">Record Transaction</h5>
                    <p class="text-sm text-muted-foreground mb-0">Add a new expense, refund, or income transaction to the ledger</p>
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
                        <!-- Transaction Type -->
                        <div>
                            <label class="form-label">Transaction Type</label>
                            <select name="type_display" class="form-select" x-model="expenseType"
                                    @change="
                                        if (expenseType === 'income') {
                                            $el.closest('form').action = '{{ route('admin.club.financials.income', $club->slug) }}';
                                        } else {
                                            $el.closest('form').action = '{{ route('admin.club.financials.expense', $club->slug) }}';
                                        }
                                    ">
                                <option value="expense">Expense</option>
                                <option value="refund">Refund</option>
                                <option value="income">Product Sale</option>
                            </select>
                        </div>

                        <!-- Expense Category (only for expense type) -->
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

                        <!-- Description -->
                        <div>
                            <label class="form-label">Description <span class="text-destructive">*</span></label>
                            <input type="text" name="description" class="form-control" placeholder="e.g., Monthly gym rent" required>
                            <small class="text-muted-foreground">Brief description of the transaction</small>
                        </div>

                        <!-- Amount & Date -->
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

                        <!-- Payment Method -->
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

                        <!-- Notes -->
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
