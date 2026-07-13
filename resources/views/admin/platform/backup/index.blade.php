@extends('layouts.admin')

@section('admin-content')
<div>
    <x-admin-hero :eyebrow="__('platform.backup_index_eyebrow_system')" :title="__('platform.backup_index_title')" icon="bi-database"
                  :subtitle="__('platform.backup_index_subtitle')" />

    <!-- Warning Message -->
    <div class="flex items-start gap-3 mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800" role="alert">
        <i class="bi bi-exclamation-triangle-fill text-xl mt-0.5"></i>
        <div class="text-sm">
            <strong>{{ __('platform.backup_index_important_label') }}</strong> {{ __('platform.backup_index_warning_body') }}
        </div>
    </div>

    <!-- Operations Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <!-- Download Backup -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
            <div class="p-6 text-center">
                <div class="mb-3">
                    <i class="bi bi-download text-primary text-5xl"></i>
                </div>
                <h5 class="text-base font-bold text-gray-900 mb-1">{{ __('platform.backup_index_download_title') }}</h5>
                <p class="text-muted-foreground text-sm mb-4">
                    {{ __('platform.backup_index_download_desc') }}
                </p>
                <a href="{{ route('admin.platform.backup.download') }}" class="btn btn-primary w-full" onclick="event.preventDefault(); (async (href) => { const ok = await window.confirmAction({ title: '{{ __("platform.backup_index_download_title") }}', message: '{{ __("platform.backup_index_download_confirm_message") }}', type: 'info', confirmText: '{{ __("platform.backup_index_download_confirm_button") }}' }); if (ok) window.location.href = href; })(this.href); return false;">
                    <i class="bi bi-download me-2"></i>{{ __('platform.backup_index_download_button') }}
                </a>
                <small class="text-muted-foreground block mt-3">
                    <i class="bi bi-info-circle me-1"></i>{{ __('platform.backup_index_file_format_json') }}
                </small>
            </div>
        </div>

        <!-- Restore Database -->
        <div class="bg-white rounded-xl shadow-sm border border-red-200 h-full">
            <div class="p-6 text-center">
                <div class="mb-3">
                    <i class="bi bi-arrow-clockwise text-destructive text-5xl"></i>
                </div>
                <h5 class="text-base font-bold text-red-600 mb-1">{{ __('platform.backup_index_restore_title') }}</h5>
                <p class="text-muted-foreground text-sm mb-4">
                    {{ __('platform.backup_index_restore_desc') }} <strong class="text-destructive">{{ __('platform.backup_index_restore_desc_warning') }}</strong>
                </p>
                <button type="button" class="btn btn-danger w-full" data-bs-toggle="modal" data-bs-target="#restoreModal">
                    <i class="bi bi-arrow-clockwise me-2"></i>{{ __('platform.backup_index_restore_button') }}
                </button>
                <small class="text-destructive block mt-3">
                    <i class="bi bi-exclamation-triangle me-1"></i>{{ __('platform.backup_index_restore_caution') }}
                </small>
            </div>
        </div>

        <!-- Export Auth Users -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
            <div class="p-6 text-center">
                <div class="mb-3">
                    <i class="bi bi-people text-success text-5xl"></i>
                </div>
                <h5 class="text-base font-bold text-gray-900 mb-1">{{ __('platform.backup_index_export_title') }}</h5>
                <p class="text-muted-foreground text-sm mb-4">
                    {{ __('platform.backup_index_export_desc') }}
                </p>
                <a href="{{ route('admin.platform.backup.export-users') }}" class="btn btn-success w-full" data-no-shell download>
                    <i class="bi bi-download me-2"></i>{{ __('platform.backup_index_export_button') }}
                </a>
                <small class="text-muted-foreground block mt-3">
                    <i class="bi bi-info-circle me-1"></i>{{ __('platform.backup_index_export_note') }}
                </small>
            </div>
        </div>
    </div>

    <!-- Best Practices -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4">
        <div class="px-5 pt-5 pb-3 border-b border-gray-100">
            <h5 class="text-base font-bold text-gray-900 mb-0"><i class="bi bi-lightbulb me-2 text-primary"></i>{{ __('platform.backup_index_best_practices') }}</h5>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h6 class="text-primary mb-3">{{ __('platform.backup_index_backup_guidelines') }}</h6>
                    <ul class="list-none space-y-2">
                        <li>
                            <i class="bi bi-check-circle text-success me-2"></i>
                            {{ __('platform.backup_index_guideline_1') }}
                        </li>
                        <li>
                            <i class="bi bi-check-circle text-success me-2"></i>
                            {{ __('platform.backup_index_guideline_2') }}
                        </li>
                        <li>
                            <i class="bi bi-check-circle text-success me-2"></i>
                            {{ __('platform.backup_index_guideline_3') }}
                        </li>
                        <li>
                            <i class="bi bi-check-circle text-success me-2"></i>
                            {{ __('platform.backup_index_guideline_4') }}
                        </li>
                        <li>
                            <i class="bi bi-check-circle text-success me-2"></i>
                            {{ __('platform.backup_index_guideline_5') }}
                        </li>
                    </ul>
                </div>
                <div>
                    <h6 class="text-destructive mb-3">{{ __('platform.backup_index_restore_warnings') }}</h6>
                    <ul class="list-none space-y-2">
                        <li>
                            <i class="bi bi-exclamation-triangle text-destructive me-2"></i>
                            {{ __('platform.backup_index_warning_1') }}
                        </li>
                        <li>
                            <i class="bi bi-exclamation-triangle text-destructive me-2"></i>
                            {{ __('platform.backup_index_warning_2') }}
                        </li>
                        <li>
                            <i class="bi bi-exclamation-triangle text-destructive me-2"></i>
                            {{ __('platform.backup_index_warning_3') }}
                        </li>
                        <li>
                            <i class="bi bi-exclamation-triangle text-destructive me-2"></i>
                            {{ __('platform.backup_index_warning_4') }}
                        </li>
                        <li>
                            <i class="bi bi-exclamation-triangle text-destructive me-2"></i>
                            {{ __('platform.backup_index_warning_5') }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Restore Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-destructive text-white">
                <h5 class="modal-title" id="restoreModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>{{ __('platform.backup_index_restore_title') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('platform.backup_index_close') }}"></button>
            </div>
            <form action="{{ route('admin.platform.backup.restore') }}" method="POST" enctype="multipart/form-data" onsubmit="event.preventDefault(); confirmRestore(this); return false;">
                @csrf
                <div class="modal-body">
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 mb-3">
                        <strong>{{ __('platform.backup_index_warning_word') }}</strong> {{ __('platform.backup_index_restore_modal_warning') }}
                    </div>

                    <div class="mb-3">
                        <label for="backup_file" class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.backup_index_select_file_label') }}</label>
                        <input type="file" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" id="backup_file" name="backup_file" accept=".json" required>
                        <small class="text-muted-foreground">{{ __('platform.backup_index_file_accept_note') }}</small>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmRestore" required>
                        <label class="form-check-label" for="confirmRestore">
                            {{ __('platform.backup_index_confirm_checkbox') }}
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('shared.cancel') }}</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-arrow-clockwise me-2"></i>{{ __('platform.backup_index_restore_title') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
async function confirmRestore(form) {
    const ok = await window.confirmAction({ title: '{{ __("platform.backup_index_restore_title") }}', message: '{{ __("platform.backup_index_restore_final_warning") }}', type: 'danger', confirmText: '{{ __("platform.backup_index_restore_confirm_button") }}' });
    if (ok) form.submit();
}
</script>
@endpush
@endsection
