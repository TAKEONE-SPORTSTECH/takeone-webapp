{{-- Edit Transaction Modal --}}
<div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showEditModal = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-2xl relative rounded-lg overflow-hidden" @click.stop>
            <div class="modal-header border-b px-6 py-4">
                <div>
                    <h5 class="modal-title font-bold">
                        <i class="bi bi-pencil text-primary me-2"></i>{{ __('admin.partials_edit_modal_title') }}
                    </h5>
                    <p class="text-sm text-muted-foreground mb-0">{{ __('admin.partials_edit_modal_subtitle') }}</p>
                </div>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showEditModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body px-6 py-4">
                <form :action="'{{ url('admin/club/' . $club->slug . '/financials') }}/' + (editTransaction ? editTransaction.id : '')" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="space-y-4">
                        <!-- Transaction Type -->
                        <div>
                            <label class="form-label">{{ __('admin.partials_edit_modal_transaction_type') }}</label>
                            <x-select-menu model="(editTransaction||{}).type" name="type" :options="[
                                ['value' => 'expense', 'label' => __('admin.partials_edit_modal_type_expense')],
                                ['value' => 'refund',  'label' => __('admin.partials_edit_modal_type_refund')],
                                ['value' => 'income',  'label' => __('admin.partials_edit_modal_type_income')],
                            ]" />
                        </div>

                        <!-- Category -->
                        <div x-show="editTransaction && editTransaction.type === 'expense'" x-transition>
                            <label class="form-label">{{ __('admin.partials_edit_modal_expense_category') }}</label>
                            <x-select-menu model="(editTransaction||{}).category" name="category"
                                placeholder="{{ __('admin.partials_edit_modal_select_category') }}" :options="[
                                ['value' => 'rent',        'label' => __('admin.partials_edit_modal_category_rent')],
                                ['value' => 'utilities',   'label' => __('admin.partials_edit_modal_category_utilities')],
                                ['value' => 'equipment',   'label' => __('admin.partials_edit_modal_category_equipment')],
                                ['value' => 'salaries',    'label' => __('admin.partials_edit_modal_category_salaries')],
                                ['value' => 'maintenance', 'label' => __('admin.partials_edit_modal_category_maintenance')],
                                ['value' => 'marketing',   'label' => __('admin.partials_edit_modal_category_marketing')],
                                ['value' => 'insurance',   'label' => __('admin.partials_edit_modal_category_insurance')],
                                ['value' => 'other',       'label' => __('admin.partials_edit_modal_category_other')],
                            ]" />
                            <small class="text-muted-foreground">{{ __('admin.partials_edit_modal_category_help') }}</small>
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="form-label">{{ __('admin.partials_edit_modal_description') }} <span class="text-destructive">*</span></label>
                            <input type="text" name="description" class="form-control" :value="editTransaction ? editTransaction.description : ''" placeholder="{{ __('admin.partials_edit_modal_description_placeholder') }}" required>
                            <small class="text-muted-foreground">{{ __('admin.partials_edit_modal_description_help') }}</small>
                        </div>

                        <!-- Amount & Date -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">{{ __('admin.partials_edit_modal_amount') }} <span class="text-destructive">*</span></label>
                                <input type="number" name="amount" class="form-control" step="0.01" min="0" :value="editTransaction ? editTransaction.amount : ''" placeholder="0.00" required>
                                <small class="text-muted-foreground">{{ __('admin.partials_edit_modal_amount_help') }}</small>
                            </div>
                            <div>
                                <label class="form-label">{{ __('admin.partials_edit_modal_date') }} <span class="text-destructive">*</span></label>
                                <input type="date" name="transaction_date" class="form-control" :value="editTransaction ? editTransaction.transaction_date : ''" max="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div x-data="{ method: 'cash' }" x-init="$watch('editTransaction', val => { if(val) method = val.payment_method || 'cash' }); if(editTransaction) method = editTransaction.payment_method || 'cash'">
                            <label class="form-label">{{ __('admin.partials_edit_modal_payment_method') }}</label>
                            <input type="hidden" name="payment_method" :value="method">
                            <div class="grid grid-cols-4 gap-2">
                                <label class="payment-option" :class="{ 'active': method === 'cash' }" @click="method = 'cash'">
                                    <i class="bi bi-cash-stack text-lg"></i>
                                    <span>{{ __('admin.partials_edit_modal_payment_cash') }}</span>
                                </label>
                                <label class="payment-option" :class="{ 'active': method === 'bank_transfer' }" @click="method = 'bank_transfer'">
                                    <i class="bi bi-bank text-lg"></i>
                                    <span>{{ __('admin.partials_edit_modal_payment_bank') }}</span>
                                </label>
                                <label class="payment-option" :class="{ 'active': method === 'card' }" @click="method = 'card'">
                                    <i class="bi bi-credit-card text-lg"></i>
                                    <span>{{ __('admin.partials_edit_modal_payment_card') }}</span>
                                </label>
                                <label class="payment-option" :class="{ 'active': method === 'other' }" @click="method = 'other'">
                                    <i class="bi bi-three-dots text-lg"></i>
                                    <span>{{ __('admin.partials_edit_modal_payment_other') }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="form-label">{{ __('admin.partials_edit_modal_notes') }}</label>
                            <textarea name="reference_number" class="form-control" rows="2" :value="editTransaction ? editTransaction.reference_number : ''" placeholder="{{ __('admin.partials_edit_modal_notes_placeholder') }}"></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-4">
                            <button type="button" class="btn btn-outline-secondary" @click="showEditModal = false">{{ __('shared.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>{{ __('admin.partials_edit_modal_update_button') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
