{{-- Auto Recurring Expense Modal --}}
<div x-show="showAutoExpenseModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showAutoExpenseModal = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-4xl relative rounded-lg overflow-hidden" @click.stop>
            <div class="modal-header border-b px-6 py-4">
                <div>
                    <h5 class="modal-title font-bold flex items-center gap-2">
                        <i class="bi bi-calendar-check text-primary"></i>{{ __('admin.auto_expense_modal_title') }}
                    </h5>
                    <p class="text-sm text-muted-foreground mb-0">{{ __('admin.auto_expense_modal_subtitle') }}</p>
                </div>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showAutoExpenseModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    {{-- Add / Edit Form --}}
                    <div class="card" x-data="{
                        editing: null,
                        desc: '',
                        amount: '',
                        recurringDate: '',
                        category: '',
                        method: 'bank_transfer',
                        notes: '',
                        startEdit(re) {
                            this.editing = re;
                            this.desc = re.description;
                            this.amount = re.amount;
                            const today = new Date();
                            const d = String(re.day_of_month).padStart(2, '0');
                            const m = String(today.getMonth() + 1).padStart(2, '0');
                            this.recurringDate = today.getFullYear() + '-' + m + '-' + d;
                            this.category = re.category || '';
                            this.method = re.payment_method || 'bank_transfer';
                            this.notes = re.notes || '';
                        },
                        cancelEdit() {
                            this.editing = null;
                            this.desc = '';
                            this.amount = '';
                            this.recurringDate = '';
                            this.category = '';
                            this.method = 'bank_transfer';
                            this.notes = '';
                        }
                    }" @recurring-edit.window="startEdit($event.detail)">
                        <div class="card-header">
                            <div>
                                <h6 class="card-title mb-0 text-lg" x-text="editing ? '{{ __('admin.auto_expense_modal_edit_heading') }}' : '{{ __('admin.auto_expense_modal_add_heading') }}'"></h6>
                                <p class="text-sm text-muted-foreground mb-0" x-text="editing ? '{{ __('admin.auto_expense_modal_edit_sub') }}' : '{{ __('admin.auto_expense_modal_add_sub') }}'"></p>
                            </div>
                            <button type="button" x-show="editing" @click="cancelEdit()"
                                    class="text-xs text-muted-foreground hover:text-foreground flex items-center gap-1">
                                <i class="bi bi-x-lg"></i> {{ __('shared.cancel') }}
                            </button>
                        </div>
                        <div class="card-body">
                            <form
                                :action="editing
                                    ? '{{ url('admin/club/' . $club->slug . '/financials/recurring') }}/' + editing.id
                                    : '{{ route('admin.club.financials.recurring.store', $club->slug) }}'"
                                method="POST">
                                @csrf
                                <input type="hidden" name="_method" :value="editing ? 'PUT' : 'POST'">
                                <div class="space-y-4">
                                    <div>
                                        <label class="form-label">{{ __('admin.auto_expense_modal_expense_name') }} <span class="text-destructive">*</span></label>
                                        <input type="text" name="description" class="form-control"
                                               placeholder="{{ __('admin.auto_expense_modal_name_placeholder') }}"
                                               x-model="desc" required>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="form-label">{{ __('admin.auto_expense_modal_amount') }} ({{ $currency }}) <span class="text-destructive">*</span></label>
                                            <input type="number" name="amount" class="form-control"
                                                   step="0.01" min="0" placeholder="0.00"
                                                   x-model="amount" required>
                                        </div>
                                        <div>
                                            <label class="form-label">{{ __('admin.auto_expense_modal_day_of_month') }} <span class="text-destructive">*</span></label>
                                            <input type="date" name="recurring_date" class="form-control"
                                                   x-model="recurringDate" required>
                                            <small class="text-muted-foreground">{{ __('admin.auto_expense_modal_day_hint') }}</small>
                                        </div>
                                    </div>
                                    {{-- Category field hidden for now --}}
                                    <div>
                                        <label class="form-label">{{ __('admin.auto_expense_modal_payment_method') }}</label>
                                        <input type="hidden" name="payment_method" x-model="method">
                                        <div class="grid grid-cols-4 gap-2">
                                            <label class="payment-option" :class="{ 'active': method === 'cash' }" @click="method = 'cash'">
                                                <i class="bi bi-cash-stack text-lg"></i>
                                                <span>{{ __('admin.auto_expense_modal_cash') }}</span>
                                            </label>
                                            <label class="payment-option" :class="{ 'active': method === 'bank_transfer' }" @click="method = 'bank_transfer'">
                                                <i class="bi bi-bank text-lg"></i>
                                                <span>{{ __('admin.auto_expense_modal_bank') }}</span>
                                            </label>
                                            <label class="payment-option" :class="{ 'active': method === 'card' }" @click="method = 'card'">
                                                <i class="bi bi-credit-card text-lg"></i>
                                                <span>{{ __('admin.auto_expense_modal_card') }}</span>
                                            </label>
                                            <label class="payment-option" :class="{ 'active': method === 'other' }" @click="method = 'other'">
                                                <i class="bi bi-three-dots text-lg"></i>
                                                <span>{{ __('admin.auto_expense_modal_other') }}</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label">{{ __('admin.auto_expense_modal_notes') }}</label>
                                        <input type="text" name="notes" class="form-control"
                                               placeholder="{{ __('admin.auto_expense_modal_notes_placeholder') }}"
                                               x-model="notes">
                                    </div>
                                    <button type="submit" class="btn w-full" :class="editing ? 'btn-success' : 'btn-primary'">
                                        <i class="bi me-2" :class="editing ? 'bi-check-lg' : 'bi-plus-lg'"></i>
                                        <span x-text="editing ? '{{ __('admin.auto_expense_modal_save_changes') }}' : '{{ __('admin.auto_expense_modal_add_heading') }}'"></span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Active Recurring Expenses List --}}
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0 text-lg">{{ __('admin.auto_expense_modal_scheduled') }}</h6>
                            <p class="text-sm text-muted-foreground mb-0">{{ __('admin.auto_expense_modal_configured_count', ['count' => $recurringExpenses->count()]) }}</p>
                        </div>
                        <div class="card-body space-y-3 max-h-[420px] overflow-y-auto">
                            @forelse($recurringExpenses as $re)
                            <div class="border rounded-lg p-3 {{ $re->is_active ? '' : 'opacity-50' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold truncate">{{ $re->description }}</div>
                                        <div class="text-xs text-muted-foreground capitalize">
                                            {{ $re->category ?? __('admin.auto_expense_modal_uncategorized') }} &middot;
                                            {{ ucfirst(str_replace('_', ' ', $re->payment_method)) }}
                                        </div>
                                        <div class="text-xs text-muted-foreground mt-1 flex items-center gap-1">
                                            <i class="bi bi-calendar3"></i>
                                            {{ __('admin.auto_expense_modal_runs_on', ['day' => $re->day_of_month . (match(true) { $re->day_of_month === 1 => 'st', $re->day_of_month === 2 => 'nd', $re->day_of_month === 3 => 'rd', default => 'th' })]) }}
                                        </div>
                                        @if($re->last_run_at)
                                        <div class="text-xs text-muted-foreground flex items-center gap-1">
                                            <i class="bi bi-check-circle text-green-500"></i>
                                            {{ __('admin.auto_expense_modal_last_run', ['date' => $re->last_run_at->format('M d, Y')]) }}
                                        </div>
                                        @endif
                                    </div>
                                    <div class="text-end shrink-0">
                                        <div class="font-bold text-red-600">{{ $currency }} {{ number_format($re->amount, 2) }}</div>
                                        <span class="badge text-xs {{ $re->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $re->is_active ? __('admin.auto_expense_modal_active') : __('admin.auto_expense_modal_paused') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex gap-2 mt-2 pt-2 border-t">
                                    {{-- Edit button —— triggers form's startEdit() via x-ref or window event --}}
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary text-xs flex-1"
                                            @click="$dispatch('recurring-edit', {{ json_encode(['id' => $re->id, 'description' => $re->description, 'amount' => (float) $re->amount, 'day_of_month' => $re->day_of_month, 'category' => $re->category ?? '', 'payment_method' => $re->payment_method, 'notes' => $re->notes ?? '']) }})">
                                        <i class="bi bi-pencil me-1"></i>{{ __('shared.edit') }}
                                    </button>
                                    <form action="{{ route('admin.club.financials.recurring.toggle', [$club->slug, $re->id]) }}" method="POST" class="flex-1">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-secondary w-full text-xs">
                                            <i class="bi {{ $re->is_active ? 'bi-pause-fill' : 'bi-play-fill' }} me-1"></i>
                                            {{ $re->is_active ? __('admin.auto_expense_modal_pause') : __('admin.auto_expense_modal_resume') }}
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.club.financials.recurring.destroy', [$club->slug, $re->id]) }}" method="POST"
                                          onsubmit="event.preventDefault(); (async (f) => { const ok = await window.confirmAction({ title: '{{ __('admin.auto_expense_modal_remove_title') }}', message: '{{ __('admin.auto_expense_modal_remove_message') }}', type: 'danger', confirmText: '{{ __('admin.auto_expense_modal_remove_confirm') }}' }); if (ok) f.submit(); })(this); return false;">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger text-xs">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            @empty
                            <div class="text-center py-10 text-muted-foreground">
                                <i class="bi bi-calendar-check text-5xl mb-3 block opacity-40"></i>
                                <p class="font-medium">{{ __('admin.auto_expense_modal_empty_title') }}</p>
                                <p class="text-sm">{{ __('admin.auto_expense_modal_empty_sub') }}</p>
                            </div>
                            @endforelse
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
