@props(['club', 'currency'])

{{-- Manual Income Modal --}}
<div x-show="showIncomeModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showIncomeModal = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-xl relative rounded-lg overflow-hidden" @click.stop>
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
                <form action="{{ route('admin.club.financials.income', $club->slug) }}" method="POST">
                    @csrf
                    <div class="space-y-4">

                        {{-- Category --}}
                        <div class="relative" x-data="{
                            open: false,
                            selected: '',
                            options: [
                                { value: 'subscription', label: 'Subscription',  icon: 'bi-person-badge',    color: 'text-blue-500' },
                                { value: 'event',        label: 'Event',          icon: 'bi-calendar-event',  color: 'text-purple-500' },
                                { value: 'product_sale', label: 'Product Sale',   icon: 'bi-bag-check',       color: 'text-green-500' },
                                { value: 'sponsorship',  label: 'Sponsorship',    icon: 'bi-trophy',          color: 'text-yellow-500' },
                                { value: 'donation',     label: 'Donation',       icon: 'bi-heart',           color: 'text-red-500' },
                                { value: 'other',        label: 'Other',          icon: 'bi-three-dots',      color: 'text-gray-400' },
                            ],
                            get current() { return this.options.find(o => o.value === this.selected) }
                        }" @click.outside="open = false">
                            <label class="form-label">Category</label>
                            <input type="hidden" name="category" :value="selected">
                            {{-- Trigger --}}
                            <button type="button"
                                    @click="open = !open"
                                    class="w-full flex items-center justify-between gap-2 form-control text-left">
                                <span class="flex items-center gap-2">
                                    <template x-if="current">
                                        <i class="bi text-base" :class="[current.icon, current.color]"></i>
                                    </template>
                                    <span :class="current ? 'text-foreground' : 'text-muted-foreground'"
                                          x-text="current ? current.label : 'Select category'"></span>
                                </span>
                                <i class="bi bi-chevron-down text-muted-foreground text-xs transition-transform duration-200"
                                   :class="{ 'rotate-180': open }"></i>
                            </button>
                            {{-- Dropdown panel --}}
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
                                            @click="selected = opt.value; open = false"
                                            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-muted/50 transition-colors"
                                            :class="{ 'bg-primary/5 font-medium': selected === opt.value }">
                                        <i class="bi text-base w-5 text-center" :class="[opt.icon, opt.color]"></i>
                                        <span x-text="opt.label"></span>
                                        <i x-show="selected === opt.value" class="bi bi-check2 ml-auto text-primary"></i>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Description --}}
                        <div>
                            <label class="form-label">Income Source <span class="text-destructive">*</span></label>
                            <input type="text" name="description" class="form-control" placeholder="e.g., Equipment sale, Special event" required>
                        </div>

                        {{-- Amount --}}
                        <div>
                            <label class="form-label">Amount <span class="text-destructive">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm font-medium">{{ $currency }}</span>
                                <input type="number" name="amount" class="form-control pl-14" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                            <small class="text-muted-foreground">VAT will be calculated automatically based on club settings</small>
                        </div>

                        {{-- Date --}}
                        <div>
                            <label class="form-label">Date <span class="text-destructive">*</span></label>
                            <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
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
                            <textarea name="reference_number" class="form-control" rows="3" placeholder="Additional details about this income..."></textarea>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button type="button" class="btn btn-outline-secondary flex-1" @click="showIncomeModal = false">Cancel</button>
                            <button type="submit" class="btn btn-success flex-1">
                                <i class="bi bi-check-lg mr-2"></i>Record Income
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
