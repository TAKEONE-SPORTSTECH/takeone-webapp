{{-- Delete Confirmation Modal --}}
<div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showDeleteModal = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-sm relative rounded-lg overflow-hidden" @click.stop>
            <div class="modal-header border-b border-destructive/30 px-6 py-4">
                <h5 class="modal-title text-destructive font-semibold">
                    <i class="bi bi-exclamation-triangle mr-2"></i>Delete Transaction
                </h5>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showDeleteModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form :action="'{{ url('admin/club/' . $club->slug . '/financials') }}/' + (deleteTransactionId || '')" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-body px-6 py-4">
                    <p class="mb-2">Are you sure you want to delete this transaction?</p>
                    <p class="font-semibold text-sm" x-text="deleteTransactionRef"></p>
                    <div class="alert alert-danger mt-3 text-sm">
                        <i class="bi bi-exclamation-triangle mr-1"></i>This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer border-t border-border px-6 py-4 flex justify-end gap-3">
                    <button type="button" class="btn btn-secondary" @click="showDeleteModal = false">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash mr-1"></i>Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
