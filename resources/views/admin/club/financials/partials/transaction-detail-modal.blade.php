{{-- Transaction Detail & Approve Payment Modal --}}
<div x-show="showTransactionDetailModal" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showTransactionDetailModal = false"></div>
    <div class="relative flex min-h-full items-center justify-center p-4 z-10">
        <div class="modal-content border-0 shadow-lg w-full max-w-lg relative rounded-xl overflow-hidden" @click.stop>
            <div class="modal-header border-b border-gray-200 px-6 py-4">
                <h5 class="modal-title font-bold flex items-center gap-2">
                    <i class="bi bi-receipt text-primary"></i> {{ __('admin.transaction_detail_modal_title') }}
                </h5>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showTransactionDetailModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="modal-body px-6 py-5 space-y-5" x-show="activeTransaction">

                {{-- Transaction Info --}}
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">{{ __('admin.transaction_detail_modal_date') }}</p>
                        <p class="font-semibold" x-text="activeTransaction?.transaction_date || '—'"></p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">{{ __('admin.transaction_detail_modal_amount') }}</p>
                        <p class="font-bold text-green-600" x-text="'{{ $club->currency ?? 'BHD' }} ' + Number(activeTransaction?.amount || 0).toFixed(2)"></p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">{{ __('admin.transaction_detail_modal_member') }}</p>
                        <p class="font-semibold" x-text="activeTransaction?.member_name || '—'"></p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">{{ __('admin.transaction_detail_modal_payment_status') }}</p>
                        <span class="badge"
                              :class="{
                                  'bg-amber-100 text-amber-700': activeTransaction?.payment_status === 'unpaid',
                                  'bg-blue-100 text-blue-700': activeTransaction?.payment_status === 'pending_approval',
                                  'bg-green-100 text-green-700': activeTransaction?.payment_status === 'paid',
                                  'bg-red-100 text-red-700': activeTransaction?.payment_status === 'refunded'
                              }"
                              x-text="activeTransaction?.payment_status === 'pending_approval' ? '{{ __('admin.transaction_detail_modal_status_pending_approval') }}' : (activeTransaction?.payment_status === 'paid' ? '{{ __('admin.transaction_detail_modal_status_paid') }}' : (activeTransaction?.payment_status === 'refunded' ? '{{ __('admin.transaction_detail_modal_status_refunded') }}' : '{{ __('admin.transaction_detail_modal_status_unpaid') }}'))">
                        </span>
                    </div>
                    <div class="col-span-2">
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">{{ __('admin.transaction_detail_modal_description') }}</p>
                        <p class="font-semibold" x-text="activeTransaction?.description || '—'"></p>
                    </div>
                </div>

                {{-- User-uploaded proof --}}
                <template x-if="activeTransaction?.proof_of_payment">
                    <div>
                        <p class="text-sm font-semibold mb-2">{{ __('admin.transaction_detail_modal_member_payment_proof') }}</p>
                        <img :src="activeTransaction.proof_of_payment" alt="{{ __('admin.transaction_detail_modal_payment_proof_alt') }}"
                             class="w-full rounded-lg border border-gray-200 cursor-pointer"
                             @click="window.open(activeTransaction.proof_of_payment, '_blank')">
                        <p class="text-xs text-muted-foreground mt-1">{{ __('admin.transaction_detail_modal_click_to_view_full') }}</p>
                    </div>
                </template>

                {{-- Approve Payment section (only for unpaid/pending) --}}
                <div x-show="activeTransaction?.payment_status !== 'paid' && activeTransaction?.subscription_id" class="border-t pt-4 space-y-4">
                        <p class="text-sm font-semibold">{{ __('admin.transaction_detail_modal_approve_payment') }}</p>

                        <div>
                            <p class="text-xs text-muted-foreground mb-2">{{ __('admin.transaction_detail_modal_optional_receipt_hint') }}</p>
                            <x-takeone-cropper
                                id="adminProofCropper"
                                :width="900"
                                :height="600"
                                :canvasHeight="680"
                                shape="rectangle"
                                mode="form"
                                inputName="admin_proof_base64"
                                folder="payment-proofs"
                                :filename="'admin_proof_' . time()"
                                :previewWidth="320"
                                :previewHeight="200"
                                buttonText="{{ __('admin.transaction_detail_modal_upload_admin_proof') }}"
                                buttonClass="w-full px-4 py-2 border border-dashed border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors flex items-center justify-center gap-2 bg-white text-gray-600"
                            />
                        </div>

                        <button type="button"
                                class="w-full btn btn-success flex items-center justify-center gap-2"
                                :disabled="approvingPayment"
                                @click="approvePayment(activeTransaction.subscription_id)">
                            <span x-show="!approvingPayment"><i class="bi bi-check-circle me-1"></i>{{ __('admin.transaction_detail_modal_confirm_approve') }}</span>
                            <span x-show="approvingPayment"><span class="inline-block animate-spin me-2">&#8635;</span>{{ __('admin.transaction_detail_modal_approving') }}</span>
                        </button>
                </div>

                <template x-if="activeTransaction?.payment_status === 'paid'">
                    <div class="border-t pt-4 space-y-3">
                        <div class="flex items-center gap-2 text-green-600">
                            <i class="bi bi-check-circle-fill"></i>
                            <span class="font-semibold text-sm">{{ __('admin.transaction_detail_modal_payment_approved') }}</span>
                        </div>
                        <button type="button"
                                class="w-full btn btn-outline-danger flex items-center justify-center gap-2"
                                @click="showTransactionDetailModal = false; openRefundModal(activeTransaction)">
                            <i class="bi bi-arrow-counterclockwise"></i> {{ __('admin.transaction_detail_modal_refund_payment') }}
                        </button>
                    </div>
                </template>

                <template x-if="activeTransaction?.payment_status === 'refunded'">
                    <div class="border-t pt-4 space-y-3">
                        <div class="flex items-center gap-2 text-red-600">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            <span class="font-semibold text-sm">{{ __('admin.transaction_detail_modal_payment_refunded') }}</span>
                        </div>
                        <template x-if="activeTransaction?.refund_proof">
                            <div>
                                <p class="text-sm font-semibold mb-2">{{ __('admin.transaction_detail_modal_refund_proof') }}</p>
                                <img :src="activeTransaction.refund_proof" alt="{{ __('admin.transaction_detail_modal_refund_proof_alt') }}"
                                     class="w-full rounded-lg border border-gray-200 cursor-pointer"
                                     @click="window.open(activeTransaction.refund_proof, '_blank')">
                                <p class="text-xs text-muted-foreground mt-1">{{ __('admin.transaction_detail_modal_click_to_view_full') }}</p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
