{{-- Refund Modal --}}
<div x-show="showRefundModal" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showRefundModal = false"></div>
    <div class="relative flex min-h-full items-center justify-center p-4 z-10">
        <div class="modal-content border-0 shadow-lg w-full max-w-lg relative rounded-xl overflow-hidden" @click.stop>
            <div class="modal-header border-b border-gray-200 px-6 py-4">
                <h5 class="modal-title font-bold flex items-center gap-2">
                    <i class="bi bi-arrow-counterclockwise text-red-500"></i> Process Refund
                </h5>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showRefundModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="modal-body px-6 py-5 space-y-4">

                {{-- Refund Summary --}}
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-sm text-red-700 font-semibold mb-1">Refund Amount</p>
                    <p class="text-2xl font-bold text-red-600" x-text="'{{ $club->currency ?? 'BHD' }} ' + Number(refundTarget?.amount_paid || 0).toFixed(2)"></p>
                    <p class="text-xs text-red-600 mt-1" x-text="refundTarget?.description || ''"></p>
                </div>

                {{-- Refund Proof Upload --}}
                <div>
                    <p class="text-sm font-semibold mb-1">Proof of Refund</p>
                    <p class="text-xs text-muted-foreground mb-2">Optionally upload a receipt or screenshot as proof of the refund</p>
                    <x-takeone-cropper
                        id="refundProofCropper"
                        :width="900"
                        :height="600"
                        :canvasHeight="680"
                        shape="rectangle"
                        mode="form"
                        inputName="refund_proof_base64"
                        folder="payment-proofs"
                        :filename="'refund_proof_' . time()"
                        :previewWidth="320"
                        :previewHeight="200"
                        buttonText="Upload Refund Proof (Optional)"
                        buttonClass="w-full px-4 py-2 border border-dashed border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors flex items-center justify-center gap-2 bg-white text-gray-600"
                    />
                </div>

                {{-- Warning --}}
                <div class="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
                    <i class="bi bi-exclamation-triangle-fill shrink-0 mt-0.5"></i>
                    <span>This action will mark the subscription as refunded and record a refund transaction in your financials. This cannot be undone.</span>
                </div>

                {{-- Actions --}}
                <div class="flex gap-2 pt-2">
                    <button type="button"
                            class="flex-1 btn btn-outline-secondary"
                            @click="showRefundModal = false"
                            :disabled="refundingPayment">
                        Cancel
                    </button>
                    <button type="button"
                            class="flex-1 btn btn-danger flex items-center justify-center gap-2"
                            :disabled="refundingPayment"
                            @click="processRefund()">
                        <span x-show="!refundingPayment"><i class="bi bi-arrow-counterclockwise mr-1"></i>Confirm Refund</span>
                        <span x-show="refundingPayment"><span class="inline-block animate-spin mr-2">&#8635;</span>Processing...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
