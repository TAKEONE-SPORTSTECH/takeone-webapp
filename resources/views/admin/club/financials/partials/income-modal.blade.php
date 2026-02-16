{{-- Manual Income Modal --}}
<div x-show="showIncomeModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showIncomeModal = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-md relative rounded-lg overflow-hidden" @click.stop>
            <div class="modal-header border-b px-6 py-4">
                <h5 class="modal-title font-bold flex items-center gap-2">
                    <i class="bi bi-currency-dollar text-green-600"></i>Record Manual Income
                </h5>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showIncomeModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body px-6 py-4">
                <p class="text-sm text-muted-foreground mb-4">Add a manual income entry to your financial records</p>
                <form action="{{ route('admin.club.financials.income', $club->id) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label">Amount <span class="text-destructive">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm font-medium">{{ $currency }}</span>
                            <input type="number" name="amount" class="form-control pl-14" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <small class="text-muted-foreground">VAT will be calculated automatically based on club settings</small>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Date <span class="text-destructive">*</span></label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Income Source <span class="text-destructive">*</span></label>
                        <input type="text" name="description" class="form-control" placeholder="e.g., Equipment sale, Special event" required>
                    </div>
                    <div class="mb-4" x-data="{ method: 'cash' }">
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
                    <div class="mb-4">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control" placeholder="e.g., Product Sale, Event">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="reference_number" class="form-control" rows="3" placeholder="Additional details about this income..."></textarea>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" class="btn btn-outline-secondary flex-1" @click="showIncomeModal = false">Cancel</button>
                        <button type="submit" class="btn btn-success flex-1">
                            <i class="bi bi-check-lg mr-2"></i>Record Income
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
