{{-- Export CSV Modal --}}
<div x-show="showExportModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showExportModal = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-md relative rounded-lg overflow-hidden" @click.stop>
            <div class="modal-header border-b px-6 py-4">
                <h5 class="modal-title font-bold flex items-center gap-2">
                    <i class="bi bi-file-earmark-spreadsheet text-primary"></i>{{ __('admin.partials_export_modal_title') }}
                </h5>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showExportModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body px-6 py-4">
                <p class="text-sm text-muted-foreground mb-4">{{ __('admin.partials_export_modal_description') }}</p>

                <div class="border rounded-lg p-4 mb-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">{{ __('admin.partials_export_modal_total_transactions') }}</span>
                        <span class="font-medium">{{ $transactions->count() }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">{{ __('admin.partials_export_modal_income_entries') }}</span>
                        <span class="font-medium text-green-600">{{ $transactions->where('type', 'income')->count() }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">{{ __('admin.partials_export_modal_expense_entries') }}</span>
                        <span class="font-medium text-red-600">{{ $transactions->where('type', 'expense')->count() }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">{{ __('admin.partials_export_modal_refund_entries') }}</span>
                        <span class="font-medium text-orange-600">{{ $transactions->where('type', 'refund')->count() }}</span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">{{ __('admin.partials_export_modal_file_name') }}</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="exportFileName" value="transactions-{{ now()->format('Y-m-d') }}">
                        <span class="input-group-text">.csv</span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="button" class="btn btn-outline-secondary flex-1" @click="showExportModal = false">{{ __('shared.cancel') }}</button>
                    <button type="button" class="btn btn-primary flex-1" onclick="exportCSV()">
                        <i class="bi bi-download me-2"></i>{{ __('admin.partials_export_modal_download_csv') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
