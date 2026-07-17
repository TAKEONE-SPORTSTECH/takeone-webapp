{{-- Test → Live switch review: pick which test-tagged rows are actually real --}}
<div x-show="showModeReviewModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showModeReviewModal = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-2xl max-w-[calc(100vw-2rem)] relative rounded-xl overflow-hidden bg-white" @click.stop>
            <div class="border-b border-gray-100 px-6 py-4 flex items-start justify-between gap-4">
                <div>
                    <h5 class="font-semibold text-gray-900 flex items-center gap-2">
                        <i class="bi bi-cone-striped text-amber-500"></i>
                        {{ __('admin.club_financials_index_review_title') }}
                    </h5>
                    <p class="text-sm text-muted-foreground mt-1">{{ __('admin.club_financials_index_review_subtitle') }}</p>
                </div>
                <button type="button" class="text-muted-foreground hover:text-foreground flex-shrink-0" @click="showModeReviewModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="px-6 py-4 max-h-[60vh] overflow-y-auto space-y-5">
                <div class="flex justify-end gap-2 text-xs">
                    <button type="button" class="text-primary hover:underline"
                        @click="['transactions','subscriptions','orders'].forEach(k => reviewData[k].forEach(r => r.keep = true))">
                        {{ __('admin.club_financials_index_review_select_all') }}
                    </button>
                    <span class="text-gray-300">|</span>
                    <button type="button" class="text-primary hover:underline"
                        @click="['transactions','subscriptions','orders'].forEach(k => reviewData[k].forEach(r => r.keep = false))">
                        {{ __('admin.club_financials_index_review_select_none') }}
                    </button>
                </div>

                {{-- Transactions --}}
                <div x-show="reviewData.transactions.length > 0">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">{{ __('admin.club_financials_index_ledger') }}</p>
                    <div class="space-y-1.5">
                        <template x-for="row in reviewData.transactions" :key="'t'+row.id">
                            <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg border border-gray-100 hover:bg-gray-50/70 cursor-pointer">
                                <span class="flex items-center gap-2.5 min-w-0">
                                    <input type="checkbox" x-model="row.keep" class="rounded border-gray-300 text-primary focus:ring-primary/40">
                                    <span class="truncate">
                                        <span class="text-sm text-gray-800 font-medium" x-text="row.description || row.category || '—'"></span>
                                        <span class="text-xs text-gray-400 block" x-text="row.date"></span>
                                    </span>
                                </span>
                                <span class="text-sm font-semibold tabular-nums flex-shrink-0"
                                    :class="row.type === 'income' ? 'text-emerald-600' : 'text-red-500'"
                                    x-text="(row.type === 'income' ? '+' : '−') + '{{ $currency }} ' + Number(row.amount).toFixed(2)"></span>
                            </label>
                        </template>
                    </div>
                </div>

                {{-- Subscriptions --}}
                <div x-show="reviewData.subscriptions.length > 0">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">{{ __('admin.club_financials_index_review_subscriptions') }}</p>
                    <div class="space-y-1.5">
                        <template x-for="row in reviewData.subscriptions" :key="'s'+row.id">
                            <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg border border-gray-100 hover:bg-gray-50/70 cursor-pointer">
                                <span class="flex items-center gap-2.5 min-w-0">
                                    <input type="checkbox" x-model="row.keep" class="rounded border-gray-300 text-primary focus:ring-primary/40">
                                    <span class="truncate">
                                        <span class="text-sm text-gray-800 font-medium" x-text="row.name"></span>
                                        <span class="text-xs text-gray-400 block" x-text="(row.package || '—') + ' · ' + row.date"></span>
                                    </span>
                                </span>
                                <span class="text-sm font-semibold tabular-nums text-amber-600 flex-shrink-0"
                                    x-text="'{{ $currency }} ' + Number(row.amount).toFixed(2)"></span>
                            </label>
                        </template>
                    </div>
                </div>

                {{-- Orders --}}
                <div x-show="reviewData.orders.length > 0">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">{{ __('admin.club_financials_index_review_orders') }}</p>
                    <div class="space-y-1.5">
                        <template x-for="row in reviewData.orders" :key="'o'+row.id">
                            <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg border border-gray-100 hover:bg-gray-50/70 cursor-pointer">
                                <span class="flex items-center gap-2.5 min-w-0">
                                    <input type="checkbox" x-model="row.keep" class="rounded border-gray-300 text-primary focus:ring-primary/40">
                                    <span class="truncate">
                                        <span class="text-sm text-gray-800 font-medium" x-text="row.reference"></span>
                                        <span class="text-xs text-gray-400 block" x-text="row.date"></span>
                                    </span>
                                </span>
                                <span class="text-sm font-semibold tabular-nums text-gray-700 flex-shrink-0"
                                    x-text="row.currency + ' ' + Number(row.total).toFixed(2)"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                <p class="text-xs text-muted-foreground">{{ __('admin.club_financials_index_review_hint') }}</p>
                <div class="flex gap-2 w-full sm:w-auto">
                    <button type="button" class="btn btn-secondary flex-1 sm:flex-none" @click="showModeReviewModal = false">{{ __('shared.cancel') }}</button>
                    <button type="button" class="btn btn-danger flex-1 sm:flex-none" :disabled="modeSwitching" @click="confirmModeReview()">
                        <i class="bi bi-broadcast me-1"></i>{{ __('admin.club_financials_index_review_confirm') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
