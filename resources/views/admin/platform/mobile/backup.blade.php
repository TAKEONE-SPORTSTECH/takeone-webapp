@extends('layouts.app')

@section('hide-navbar', true)
@section('title', 'Backup & Restore')

@section('content')
<div class="min-h-screen bg-background pb-20">

    {{-- ===== Header ===== --}}
    <header class="sticky top-0 z-40 bg-white border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('admin.platform.index') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">{{ __('platform.backup_restore') }}</p>
        </div>
    </header>

    <div class="px-4 pt-4 space-y-3 mobile-stagger">
        {{-- Warning --}}
        <div class="flex items-start gap-3 rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3">
            <i class="bi bi-exclamation-triangle-fill text-amber-600 text-lg mt-0.5"></i>
            <p class="text-[12px] text-amber-800"><span class="font-semibold">{{ __('platform.backup_important') }}</span> {{ __('platform.backup_important_text') }}</p>
        </div>

        {{-- Download --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 text-center">
            <span class="w-14 h-14 mx-auto rounded-2xl bg-accent text-primary flex items-center justify-center"><i class="bi bi-download text-2xl"></i></span>
            <h2 class="font-bold text-foreground mt-3">{{ __('platform.download_backup') }}</h2>
            <p class="text-[12px] text-muted-foreground mt-1">{{ __('platform.download_backup_desc') }}</p>
            <a href="{{ route('admin.platform.backup.download') }}"
               onclick="event.preventDefault(); (async (href) => { const ok = await window.confirmAction({ title: @js(__('platform.confirm_download_title')), message: @js(__('platform.confirm_download_message')), type: 'info', confirmText: @js(__('platform.confirm_download_btn')) }); if (ok) window.location.href = href; })(this.href); return false;"
               class="m-press mt-4 w-full inline-flex items-center justify-center gap-2 bg-primary text-white py-3 rounded-xl font-semibold">
                <i class="bi bi-download"></i> {{ __('platform.download_full_backup') }}
            </a>
        </div>

        {{-- Restore --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-red-200 text-center">
            <span class="w-14 h-14 mx-auto rounded-2xl bg-red-100 text-red-600 flex items-center justify-center"><i class="bi bi-arrow-clockwise text-2xl"></i></span>
            <h2 class="font-bold text-red-600 mt-3">{{ __('platform.restore_database') }}</h2>
            <p class="text-[12px] text-muted-foreground mt-1">{{ __('platform.restore_database_desc') }} <span class="font-semibold text-red-600">{{ __('platform.restore_overwrites') }}</span></p>
            <button type="button" data-bs-toggle="modal" data-bs-target="#restoreModal"
                    class="m-press mt-4 w-full inline-flex items-center justify-center gap-2 bg-destructive text-white py-3 rounded-xl font-semibold">
                <i class="bi bi-arrow-clockwise"></i> {{ __('platform.restore_from_backup') }}
            </button>
        </div>

        {{-- Export users --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 text-center">
            <span class="w-14 h-14 mx-auto rounded-2xl bg-green-100 text-green-600 flex items-center justify-center"><i class="bi bi-people text-2xl"></i></span>
            <h2 class="font-bold text-foreground mt-3">{{ __('platform.export_auth_users') }}</h2>
            <p class="text-[12px] text-muted-foreground mt-1">{{ __('platform.export_auth_users_desc') }}</p>
            <a href="{{ route('admin.platform.backup.export-users') }}"
               class="m-press mt-4 w-full inline-flex items-center justify-center gap-2 bg-green-600 text-white py-3 rounded-xl font-semibold">
                <i class="bi bi-download"></i> {{ __('platform.export_users') }}
            </a>
        </div>

        {{-- Best practices --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <h3 class="font-semibold text-foreground text-sm mb-2"><i class="bi bi-lightbulb text-primary mr-1"></i> {{ __('platform.best_practices') }}</h3>
            <ul class="space-y-1.5 text-[12px] text-muted-foreground">
                <li><i class="bi bi-check-circle text-green-600 mr-1.5"></i>{{ __('platform.bp_schedule') }}</li>
                <li><i class="bi bi-check-circle text-green-600 mr-1.5"></i>{{ __('platform.bp_store') }}</li>
                <li><i class="bi bi-check-circle text-green-600 mr-1.5"></i>{{ __('platform.bp_test') }}</li>
                <li><i class="bi bi-exclamation-triangle text-red-500 mr-1.5"></i>{{ __('platform.bp_backup_first') }}</li>
                <li><i class="bi bi-exclamation-triangle text-red-500 mr-1.5"></i>{{ __('platform.bp_overwrites') }}</li>
            </ul>
        </div>
    </div>
</div>

{{-- Restore Modal (Bootstrap bridge) --}}
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-destructive text-white">
                <h5 class="modal-title" id="restoreModalLabel"><i class="bi bi-exclamation-triangle mr-2"></i>{{ __('platform.restore_database') }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('shared.cancel') }}"></button>
            </div>
            <form action="{{ route('admin.platform.backup.restore') }}" method="POST" enctype="multipart/form-data" onsubmit="event.preventDefault(); confirmRestore(this); return false;">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>{{ __('platform.restore_warning') }}</strong> {{ __('platform.restore_warning_text') }}
                    </div>
                    <div class="mb-3">
                        <label for="backup_file" class="form-label">{{ __('platform.select_backup_file') }}</label>
                        <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".json" required>
                        <small class="text-muted-foreground">{{ __('platform.only_json_accepted') }}</small>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmRestore" required>
                        <label class="form-check-label" for="confirmRestore">{{ __('platform.restore_understand') }}</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('shared.cancel') }}</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-arrow-clockwise mr-2"></i>{{ __('platform.restore_database') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
async function confirmRestore(form) {
    const ok = await window.confirmAction({ title: @js(__('platform.confirm_restore_title')), message: @js(__('platform.confirm_restore_message')), type: 'danger', confirmText: @js(__('platform.confirm_restore_btn')) });
    if (ok) form.submit();
}
</script>
@endpush
@endsection
