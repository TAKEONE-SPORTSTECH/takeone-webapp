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
                    <i class="bi bi-receipt text-primary"></i> Transaction Detail
                </h5>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showTransactionDetailModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="modal-body px-6 py-5 space-y-5" x-show="activeTransaction">

                {{-- Transaction Info --}}
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">Date</p>
                        <p class="font-semibold" x-text="activeTransaction?.transaction_date || '—'"></p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">Amount</p>
                        <p class="font-bold text-green-600" x-text="'{{ $club->currency ?? 'BHD' }} ' + Number(activeTransaction?.amount || 0).toFixed(2)"></p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">Member</p>
                        <p class="font-semibold" x-text="activeTransaction?.member_name || '—'"></p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">Payment Status</p>
                        <span class="badge"
                              :class="{
                                  'bg-amber-100 text-amber-700': activeTransaction?.payment_status === 'unpaid',
                                  'bg-blue-100 text-blue-700': activeTransaction?.payment_status === 'pending_approval',
                                  'bg-green-100 text-green-700': activeTransaction?.payment_status === 'paid'
                              }"
                              x-text="activeTransaction?.payment_status === 'pending_approval' ? 'Pending Approval' : (activeTransaction?.payment_status === 'paid' ? 'Paid' : 'Unpaid')">
                        </span>
                    </div>
                    <div class="col-span-2">
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">Description</p>
                        <p class="font-semibold" x-text="activeTransaction?.description || '—'"></p>
                    </div>
                </div>

                {{-- User-uploaded proof --}}
                <template x-if="activeTransaction?.proof_of_payment">
                    <div>
                        <p class="text-sm font-semibold mb-2">Member's Payment Proof</p>
                        <img :src="activeTransaction.proof_of_payment" alt="Payment proof"
                             class="w-full rounded-lg border border-gray-200 cursor-pointer"
                             @click="window.open(activeTransaction.proof_of_payment, '_blank')">
                        <p class="text-xs text-muted-foreground mt-1">Click image to view full size</p>
                    </div>
                </template>

                {{-- Approve Payment section (only for unpaid/pending) --}}
                <template x-if="activeTransaction?.payment_status !== 'paid' && activeTransaction?.subscription_id">
                    <div class="border-t pt-4 space-y-4">
                        <p class="text-sm font-semibold">Approve Payment</p>

                        <div>
                            <p class="text-xs text-muted-foreground mb-2">Optionally upload your own proof of receipt (admin copy)</p>
                            <x-takeone-cropper
                                id="adminProofCropper"
                                :width="900"
                                :height="600"
                                shape="rectangle"
                                mode="form"
                                inputName="admin_proof_base64"
                                folder="payment-proofs"
                                :filename="'admin_proof_' . time()"
                                :previewWidth="320"
                                :previewHeight="200"
                                buttonText="Upload Admin Proof (Optional)"
                                buttonClass="w-full px-4 py-2 border border-dashed border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors flex items-center justify-center gap-2 bg-white text-gray-600"
                            />
                        </div>

                        <button type="button"
                                class="w-full btn btn-success flex items-center justify-center gap-2"
                                :disabled="approvingPayment"
                                @click="approvePayment(activeTransaction.subscription_id)">
                            <span x-show="!approvingPayment"><i class="bi bi-check-circle mr-1"></i>Confirm & Approve Payment</span>
                            <span x-show="approvingPayment"><span class="inline-block animate-spin mr-2">&#8635;</span>Approving...</span>
                        </button>
                    </div>
                </template>

                <template x-if="activeTransaction?.payment_status === 'paid'">
                    <div class="border-t pt-4">
                        <div class="flex items-center gap-2 text-green-600">
                            <i class="bi bi-check-circle-fill"></i>
                            <span class="font-semibold text-sm">Payment has been approved</span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
