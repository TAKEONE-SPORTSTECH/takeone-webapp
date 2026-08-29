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

        {{-- Destructive actions are grouped and labelled, so a wipe is never
             one tap away from a download. --}}
        <p class="text-[11px] font-bold uppercase tracking-wide text-red-600 flex items-center gap-1.5 pt-2">
            <i class="bi bi-exclamation-octagon-fill"></i>{{ __('platform.admin_platform_settings_danger_zone') }}
        </p>

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

        {{-- Reset to clean baseline — with the other restore operations, since
             it takes a database backup before it wipes. --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-red-200 text-center" x-data="platformResetBaseline()">
            <span class="w-14 h-14 mx-auto rounded-2xl bg-red-50 text-red-600 flex items-center justify-center">
                <i class="bi bi-arrow-counterclockwise text-2xl"></i>
            </span>
            <h2 class="font-bold text-red-600 mt-3">{{ __('platform.admin_platform_settings_reset_title') }}</h2>
            <p class="text-[12px] text-muted-foreground mt-1 leading-relaxed">{{ __('platform.admin_platform_settings_reset_description') }}</p>
            <button type="button" @click="open = true"
                    class="m-press mt-4 w-full inline-flex items-center justify-center gap-2 border border-red-300 text-red-600 py-3 rounded-xl font-semibold">
                <i class="bi bi-trash3"></i> {{ __('platform.admin_platform_settings_reset_button') }}
            </button>

            {{-- Confirmation sheet — requires typing RESET. Teleported to <body>
                 so the mobile shell's transform cannot clip a fixed overlay. --}}
            <template x-teleport="body">
                <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center"
                     x-transition.opacity @keydown.escape.window="open && cancel()">
                    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="cancel()"></div>

                    <div class="relative bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden max-h-[92vh] flex flex-col"
                         x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="translate-y-full sm:translate-y-0 sm:scale-95 opacity-0"
                         x-transition:enter-end="translate-y-0 sm:scale-100 opacity-100">

                        <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl sm:rounded-t-2xl text-white relative overflow-hidden"
                             style="background: linear-gradient(150deg, #b91c1c, #dc2626b0);">
                            <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                            <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                            <div class="relative flex items-start gap-3">
                                <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                                    <i class="bi bi-exclamation-octagon-fill text-xl"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-lg font-black leading-tight">{{ __('platform.admin_platform_settings_reset_modal_title') }}</h3>
                                    <p class="text-[12px] text-white/85 mt-0.5">{{ __('platform.admin_platform_settings_reset_modal_confirm_label') }}</p>
                                </div>
                                <button type="button" @click="cancel()" aria-label="{{ __('shared.close') }}"
                                        class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto px-5 py-4">
                            <p class="text-sm text-muted-foreground leading-relaxed">
                                {{ __('platform.admin_platform_settings_reset_modal_body') }}
                            </p>
                            <label class="block text-xs font-medium text-gray-700 mt-4 mb-1">
                                {{ __('platform.admin_platform_settings_reset_modal_prompt') }}
                            </label>
                            <input type="text" x-model="phrase" :disabled="working" autocomplete="off"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent uppercase tracking-widest font-mono">
                        </div>

                        <div class="flex-shrink-0 px-5 py-4 border-t border-gray-100 flex gap-2"
                             style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                            <button type="button" @click="cancel()" :disabled="working"
                                    class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-foreground">
                                {{ __('shared.cancel') }}
                            </button>
                            <button type="button" @click="submit()" :disabled="!canSubmit || working"
                                    class="flex-1 py-3 rounded-xl bg-red-600 text-white text-sm font-semibold disabled:opacity-50 inline-flex items-center justify-center gap-2">
                                <template x-if="working"><i class="bi bi-arrow-repeat animate-spin"></i></template>
                                <template x-if="!working"><i class="bi bi-trash3"></i></template>
                                <span x-text="working ? '{{ __('platform.admin_platform_settings_reset_working') }}' : '{{ __('platform.admin_platform_settings_reset_modal_confirm') }}'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
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
function platformResetBaseline() {
    return {
        open: false,
        phrase: '',
        working: false,
        get canSubmit() { return this.phrase.trim().toUpperCase() === 'RESET'; },

        cancel() {
            if (this.working) return;
            this.open = false;
            this.phrase = '';
        },

        async submit() {
            if (!this.canSubmit || this.working) return;
            this.working = true;
            try {
                const res = await fetch('{{ route('admin.platform.settings.reset-baseline') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ confirmation: 'RESET' }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    window.showToast('success', data.message || 'Platform reset.');
                    // Everything was wiped — leave the (now stale) settings page.
                    setTimeout(() => { window.location.href = data.redirect || '/admin'; }, 900);
                } else {
                    this.working = false;
                    window.showToast('error', data.message || 'Reset failed.');
                }
            } catch (e) {
                this.working = false;
                window.showToast('error', 'Network error while resetting.');
            }
        },
    };
}

async function confirmRestore(form) {
    const ok = await window.confirmAction({ title: @js(__('platform.confirm_restore_title')), message: @js(__('platform.confirm_restore_message')), type: 'danger', confirmText: @js(__('platform.confirm_restore_btn')) });
    if (ok) form.submit();
}
</script>
@endpush
@endsection
