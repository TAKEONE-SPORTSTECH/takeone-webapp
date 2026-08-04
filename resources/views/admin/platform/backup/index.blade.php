@extends('layouts.admin')

@section('admin-content')
{{--
    Backup & Restore.

    Organised by CONSEQUENCE, not by feature: everything that only READS data
    sits together at the top; everything that overwrites or deletes it is
    grouped below, behind its own heading and red framing. The two groups never
    share a row, so a destructive button is never one column away from a safe
    one — which is how the wrong button gets pressed.
--}}
<div class="space-y-6" x-data="platformResetBaseline()">

    <x-admin-hero :eyebrow="__('platform.backup_index_eyebrow_system')" :title="__('platform.backup_index_title')" icon="bi-database"
                  :subtitle="__('platform.backup_index_subtitle')" />

    <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800" role="alert">
        <i class="bi bi-exclamation-triangle-fill text-lg mt-0.5 shrink-0"></i>
        <p class="text-sm leading-relaxed">
            <strong>{{ __('platform.backup_index_important_label') }}</strong> {{ __('platform.backup_index_warning_body') }}
        </p>
    </div>

    {{-- ═══ Safe — these only read data ══════════════════════════════════════ --}}
    <section>
        <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-4 flex items-center gap-2">
            <i class="bi bi-box-arrow-down"></i>{{ __('platform.backup_index_group_export') }}
        </h3>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 rounded-xl bg-accent text-primary flex items-center justify-center shrink-0">
                        <i class="bi bi-download text-xl"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">{{ __('platform.backup_index_download_title') }}</p>
                        <p class="text-sm text-muted-foreground mt-1 leading-relaxed">{{ __('platform.backup_index_download_desc') }}</p>
                    </div>
                </div>
                <div class="mt-5 pt-4 flex items-center justify-between gap-3 border-t border-gray-100">
                    <span class="text-xs text-muted-foreground"><i class="bi bi-filetype-json"></i> {{ __('platform.backup_index_file_format_json') }}</span>
                    <a href="{{ route('admin.platform.backup.download') }}"
                       class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary/90 transition-colors"
                       onclick="event.preventDefault(); (async (href) => { const ok = await window.confirmAction({ title: '{{ __("platform.backup_index_download_title") }}', message: '{{ __("platform.backup_index_download_confirm_message") }}', type: 'info', confirmText: '{{ __("platform.backup_index_download_confirm_button") }}' }); if (ok) window.location.href = href; })(this.href); return false;">
                        <i class="bi bi-download"></i>{{ __('platform.backup_index_download_button') }}
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 rounded-xl bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                        <i class="bi bi-people text-xl"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">{{ __('platform.backup_index_export_title') }}</p>
                        <p class="text-sm text-muted-foreground mt-1 leading-relaxed">{{ __('platform.backup_index_export_desc') }}</p>
                    </div>
                </div>
                <div class="mt-5 pt-4 flex items-center justify-between gap-3 border-t border-gray-100">
                    <span class="text-xs text-muted-foreground"><i class="bi bi-info-circle"></i> {{ __('platform.backup_index_export_note') }}</span>
                    <a href="{{ route('admin.platform.backup.export-users') }}" data-no-shell download
                       class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-green-600 text-green-700 text-sm font-medium hover:bg-green-600 hover:text-white transition-colors">
                        <i class="bi bi-download"></i>{{ __('platform.backup_index_export_button') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══ Destructive — these overwrite or delete ══════════════════════════ --}}
    <section>
        <h3 class="text-sm font-semibold text-red-600 uppercase tracking-wide mb-4 flex items-center gap-2">
            <i class="bi bi-exclamation-octagon-fill"></i>{{ __('platform.admin_platform_settings_danger_zone') }}
        </h3>

        <div class="rounded-xl border border-red-200 bg-red-50/40 p-4 space-y-4">
            <div class="bg-white rounded-xl border border-red-200 overflow-hidden">
                <div class="h-1 bg-gradient-to-r from-red-500 via-red-400 to-orange-400"></div>
                <div class="p-6 flex flex-col sm:flex-row sm:items-start justify-between gap-5">
                    <div class="flex items-start gap-3">
                        <span class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                            <i class="bi bi-arrow-clockwise text-xl"></i>
                        </span>
                        <div>
                            <p class="font-semibold text-gray-900">{{ __('platform.backup_index_restore_title') }}</p>
                            <p class="text-sm text-muted-foreground mt-1 leading-relaxed">
                                {{ __('platform.backup_index_restore_desc') }}
                                <strong class="text-destructive">{{ __('platform.backup_index_restore_desc_warning') }}</strong>
                            </p>
                            <p class="text-xs text-destructive mt-2">
                                <i class="bi bi-exclamation-triangle"></i> {{ __('platform.backup_index_restore_caution') }}
                            </p>
                        </div>
                    </div>
                    <button type="button" data-bs-toggle="modal" data-bs-target="#restoreModal"
                            class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-red-300 text-red-600 font-medium text-sm hover:bg-red-600 hover:text-white hover:border-red-600 transition-colors">
                        <i class="bi bi-arrow-clockwise"></i>{{ __('platform.backup_index_restore_button') }}
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-red-200 overflow-hidden">
                <div class="h-1 bg-gradient-to-r from-red-500 via-red-400 to-orange-400"></div>
                <div class="p-6 flex flex-col sm:flex-row sm:items-start justify-between gap-5">
                    <div class="flex items-start gap-3">
                        <span class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                            <i class="bi bi-arrow-counterclockwise text-xl"></i>
                        </span>
                        <div>
                            <p class="font-semibold text-gray-900">{{ __('platform.admin_platform_settings_reset_title') }}</p>
                            <p class="text-sm text-muted-foreground mt-1 leading-relaxed">
                                {{ __('platform.admin_platform_settings_reset_description') }}
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="open = true"
                            class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-red-300 text-red-600 font-medium text-sm hover:bg-red-600 hover:text-white hover:border-red-600 transition-colors">
                        <i class="bi bi-trash3"></i>{{ __('platform.admin_platform_settings_reset_button') }}
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══ Guidance ═════════════════════════════════════════════════════════ --}}
    <section class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide flex items-center gap-2">
                <i class="bi bi-lightbulb text-primary"></i>{{ __('platform.backup_index_best_practices') }}
            </h3>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
            <div>
                <p class="text-xs font-semibold text-primary uppercase tracking-wide mb-3">{{ __('platform.backup_index_backup_guidelines') }}</p>
                <ul class="space-y-2 text-sm text-foreground">
                    @foreach (['guideline_1', 'guideline_2', 'guideline_3', 'guideline_4', 'guideline_5'] as $key)
                        <li class="flex items-start gap-2">
                            <i class="bi bi-check-circle-fill text-green-600 mt-0.5 shrink-0"></i>
                            <span>{{ __('platform.backup_index_'.$key) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold text-destructive uppercase tracking-wide mb-3">{{ __('platform.backup_index_restore_warnings') }}</p>
                <ul class="space-y-2 text-sm text-foreground">
                    @foreach (['warning_1', 'warning_2', 'warning_3', 'warning_4', 'warning_5'] as $key)
                        <li class="flex items-start gap-2">
                            <i class="bi bi-exclamation-triangle-fill text-destructive mt-0.5 shrink-0"></i>
                            <span>{{ __('platform.backup_index_'.$key) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

        {{-- Confirmation modal — requires typing RESET to enable the action. --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4"
             x-transition.opacity @keydown.escape.window="open && cancel()">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="cancel()"></div>

            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden"
                 x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="h-1.5 bg-gradient-to-r from-red-500 via-red-400 to-orange-400"></div>
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                            <i class="bi bi-exclamation-triangle-fill text-xl"></i>
                        </span>
                        <h4 class="text-lg font-bold text-gray-900">{{ __('platform.admin_platform_settings_reset_modal_title') }}</h4>
                    </div>

                    <p class="text-sm text-muted-foreground leading-relaxed mb-5">
                        {{ __('platform.admin_platform_settings_reset_modal_warning') }}
                    </p>

                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.admin_platform_settings_reset_modal_confirm_label') }}</label>
                    <input type="text" x-model="phrase" x-ref="phrase" :disabled="working"
                           autocomplete="off" spellcheck="false" placeholder="RESET"
                           @keydown.enter="canSubmit && submit()"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent font-mono tracking-widest uppercase">

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="cancel()" :disabled="working"
                                class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors disabled:opacity-50">
                            {{ __('platform.admin_platform_settings_reset_modal_cancel') }}
                        </button>
                        <button type="button" @click="submit()" :disabled="!canSubmit || working"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                            <template x-if="working"><i class="bi bi-arrow-repeat animate-spin"></i></template>
                            <template x-if="!working"><i class="bi bi-trash3"></i></template>
                            <span x-text="working ? '{{ __('platform.admin_platform_settings_reset_working') }}' : '{{ __('platform.admin_platform_settings_reset_modal_confirm') }}'"></span>
                        </button>
                    </div>
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

                    <label for="confirmRestore" class="flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" id="confirmRestore" required
                               class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary shrink-0">
                        <span class="text-sm text-foreground">{{ __('platform.backup_index_confirm_checkbox') }}</span>
                    </label>
                </div>
                <div class="modal-footer">
                    <button type="button" class="px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-foreground hover:bg-muted transition-colors" data-bs-dismiss="modal">{{ __('shared.cancel') }}</button>
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-destructive text-white text-sm font-medium hover:bg-red-700 transition-colors">
                        <i class="bi bi-arrow-clockwise"></i>{{ __('platform.backup_index_restore_title') }}
                    </button>
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
    const ok = await window.confirmAction({ title: '{{ __("platform.backup_index_restore_title") }}', message: '{{ __("platform.backup_index_restore_final_warning") }}', type: 'danger', confirmText: '{{ __("platform.backup_index_restore_confirm_button") }}' });
    if (ok) form.submit();
}
</script>
@endpush
@endsection
