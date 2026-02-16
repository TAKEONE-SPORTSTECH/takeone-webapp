{{-- Auto Recurring Expense Modal --}}
<div x-show="showAutoExpenseModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showAutoExpenseModal = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-4xl relative rounded-lg overflow-hidden" @click.stop>
            <div class="modal-header border-b px-6 py-4">
                <div>
                    <h5 class="modal-title font-bold">Auto Recurring Expenses</h5>
                    <p class="text-sm text-muted-foreground mb-0">Set up monthly recurring expenses that will be automatically added to your transaction ledger</p>
                </div>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showAutoExpenseModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Add Form -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0 text-lg">Add Auto Expense</h6>
                            <p class="text-sm text-muted-foreground mb-0">These expenses will be automatically added at the end of each month</p>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.club.financials.expense', $club->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="category" value="">
                                <div class="space-y-4">
                                    <div>
                                        <label class="form-label">Expense Name <span class="text-destructive">*</span></label>
                                        <input type="text" name="description" class="form-control" placeholder="e.g., Rent, Electricity, Internet" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Amount ({{ $currency }}) <span class="text-destructive">*</span></label>
                                        <input type="number" name="amount" class="form-control" step="0.01" min="0" placeholder="10.00" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Category</label>
                                        <select name="category" class="form-select">
                                            <option value="utilities">Utilities</option>
                                            <option value="rent">Rent</option>
                                            <option value="salaries">Salaries</option>
                                            <option value="maintenance">Maintenance</option>
                                            <option value="insurance">Insurance</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label">Day of Month</label>
                                        <select name="day_of_month" class="form-select">
                                            <option value="1">1st (End of Month)</option>
                                            <option value="5">5th</option>
                                            <option value="10">10th</option>
                                            <option value="15">15th</option>
                                            <option value="20">20th</option>
                                            <option value="25">25th</option>
                                            <option value="30">30th (Last day)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label">Description (Optional)</label>
                                        <input type="text" name="reference_number" class="form-control" placeholder="Additional notes...">
                                    </div>
                                    <input type="hidden" name="transaction_date" value="{{ date('Y-m-d') }}">
                                    <input type="hidden" name="payment_method" value="bank_transfer">
                                    <button type="submit" class="btn btn-primary w-full">
                                        <i class="bi bi-plus-lg mr-2"></i>Add Auto Expense
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Active Auto Expenses List -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0 text-lg">Active Auto Expenses</h6>
                            <p class="text-sm text-muted-foreground mb-0">Manage your recurring monthly expenses</p>
                        </div>
                        <div class="card-body">
                            @php
                                $recurringExpenses = $transactions->where('type', 'expense')
                                    ->groupBy('description')
                                    ->map(fn($items) => [
                                        'description' => $items->first()->description,
                                        'category' => $items->first()->category ?? 'Other',
                                        'amount' => $items->avg('amount'),
                                        'count' => $items->count(),
                                        'last_date' => $items->max('transaction_date'),
                                    ])
                                    ->sortByDesc('count')
                                    ->take(10);
                            @endphp
                            @if($recurringExpenses->count() > 0)
                            <div class="space-y-2">
                                @foreach($recurringExpenses as $expense)
                                <div class="border rounded-lg p-3 space-y-2 hover:bg-muted/50 transition-colors">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="font-semibold">{{ $expense['description'] }}</div>
                                            <div class="text-sm text-muted-foreground capitalize">{{ $expense['category'] }}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-red-600">{{ $currency }} {{ number_format($expense['amount'], 2) }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <i class="bi bi-calendar3"></i>
                                        {{ $expense['count'] }} occurrence(s)
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @else
                            <div class="text-center py-8 text-muted-foreground">
                                <i class="bi bi-calendar-check text-5xl mb-2 block opacity-50"></i>
                                <p>No recurring expenses found yet</p>
                                <p class="text-sm">Add your first recurring expense to get started</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
