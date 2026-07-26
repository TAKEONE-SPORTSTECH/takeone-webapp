@extends('layouts.admin')

@section('admin-content')
<div>
    <x-admin-hero eyebrow="{{ __('platform.admin_platform_settings_eyebrow') }}" title="{{ __('platform.admin_platform_settings_title') }}" icon="bi-gear"
                  subtitle="{{ __('platform.admin_platform_settings_subtitle') }}" />

    <form method="POST" action="{{ route('admin.platform.settings.update') }}" class="max-w-2xl">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-4">{{ __('platform.admin_platform_settings_notifications') }}</h3>

            <div x-data="{ on: {{ $toastsEnabled ? 'true' : 'false' }} }" class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-semibold text-gray-900">{{ __('platform.admin_platform_settings_toast_notifications') }}</p>
                    <p class="text-sm text-muted-foreground mt-0.5">
                        {{ __('platform.admin_platform_settings_toast_description') }}
                    </p>
                </div>

                {{-- Hidden field carries the value; the switch flips it. --}}
                <input type="hidden" name="toasts_enabled" :value="on ? 1 : 0">
                <button type="button" role="switch" :aria-checked="on" @click="on = !on"
                        class="relative inline-flex h-7 w-12 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary/40"
                        :class="on ? 'bg-primary' : 'bg-gray-300'">
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                          :class="on ? 'translate-x-6' : 'translate-x-1'"></span>
                </button>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-2"></i>{{ __('platform.admin_platform_settings_save_changes') }}
            </button>
        </div>
    </form>

    <div class="mt-8">
        <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-4">{{ __('platform.admin_platform_settings_integrations') }}</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

        @php
            $rtStateColors = [
                'online'   => ['dot' => 'bg-green-500', 'text' => 'text-green-700', 'bg' => 'bg-green-50', 'ring' => 'ring-green-200'],
                'offline'  => ['dot' => 'bg-red-500',   'text' => 'text-red-700',   'bg' => 'bg-red-50',   'ring' => 'ring-red-200'],
                'disabled' => ['dot' => 'bg-gray-400',  'text' => 'text-gray-600',  'bg' => 'bg-gray-50',  'ring' => 'ring-gray-200'],
            ];
            $rtSc = $rtStateColors[$realtimeStatus['state']] ?? $rtStateColors['disabled'];
        @endphp

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col" x-data="platformRealtimeIntegration()">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-5">
                <div class="flex items-center gap-3">
                    <span class="w-11 h-11 rounded-xl bg-accent text-primary flex items-center justify-center shrink-0">
                        <i class="bi bi-broadcast-pin text-xl"></i>
                    </span>
                    <div>
                        <p class="font-semibold text-gray-900">{{ __('platform.admin_platform_settings_realtime_title') }}</p>
                        <p class="text-sm text-muted-foreground mt-0.5">{{ __('platform.admin_platform_settings_realtime_description') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full ring-1 {{ $rtSc['bg'] }} {{ $rtSc['ring'] }}" id="rt-status-pill">
                    <span class="w-2 h-2 rounded-full" :class="statusDot"></span>
                    <span class="text-xs font-medium {{ $rtSc['text'] }}" id="rt-status-label">{{ $realtimeStatus['label'] }}</span>
                </div>
            </div>

            <div class="space-y-5">
                {{-- Master switch --}}
                <div class="border-t border-gray-100 pt-5">
                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <span class="block text-sm font-semibold text-gray-900">{{ __('platform.admin_platform_settings_realtime_enable') }}</span>
                            <span class="block text-xs text-muted-foreground mt-0.5">{{ __('platform.admin_platform_settings_realtime_enable_hint') }}</span>
                        </div>
                        <button type="button" @click="form.enabled = !form.enabled"
                                :class="form.enabled ? 'bg-primary' : 'bg-gray-300'"
                                class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors">
                            <span :class="form.enabled ? 'translate-x-5' : 'translate-x-0.5'"
                                  class="inline-block h-5 w-5 mt-0.5 rounded-full bg-white shadow transform transition-transform"></span>
                        </button>
                    </label>
                </div>

                {{-- Broker connection --}}
                <div class="border-t border-gray-100 pt-5 space-y-4">
                    <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                        <i class="bi bi-hdd-network text-primary"></i> {{ __('platform.admin_platform_settings_realtime_broker_connection') }}
                    </h4>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.admin_platform_settings_realtime_host') }} <span class="text-xs text-muted-foreground">{{ __('platform.admin_platform_settings_realtime_host_hint') }}</span></label>
                            <input type="text" x-model="form.broker_host" placeholder="127.0.0.1"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.admin_platform_settings_realtime_port') }}</label>
                            <input type="number" x-model="form.broker_port" placeholder="1883"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.admin_platform_settings_realtime_ws_url') }}</label>
                        <input type="text" x-model="form.broker_ws_url" placeholder="wss://takeone.bh/mqtt"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="text-xs text-muted-foreground mt-1">{{ __('platform.admin_platform_settings_realtime_ws_url_hint') }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.admin_platform_settings_realtime_username') }}</label>
                            <input type="text" x-model="form.broker_username" placeholder="takeone-server" autocomplete="off"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.admin_platform_settings_realtime_password') }}</label>
                            <input type="password" x-model="form.broker_password" placeholder="•••••••• (unchanged)" autocomplete="new-password"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>
                </div>

                {{-- Token / security --}}
                <div class="border-t border-gray-100 pt-5 space-y-4">
                    <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                        <i class="bi bi-shield-lock text-primary"></i> {{ __('platform.admin_platform_settings_realtime_jwt_title') }}
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.admin_platform_settings_realtime_jwt_ttl') }} <span class="text-xs text-muted-foreground">{{ __('platform.admin_platform_settings_realtime_jwt_ttl_hint') }}</span></label>
                            <input type="number" x-model="form.jwt_ttl" placeholder="3600"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('platform.admin_platform_settings_realtime_jwt_secret') }}
                                <span class="text-xs" :class="secretSet ? 'text-green-600' : 'text-red-500'" x-text="secretSet ? '· {{ __('platform.admin_platform_settings_realtime_jwt_secret_set') }}' : '· {{ __('platform.admin_platform_settings_realtime_jwt_secret_using_app_key') }}'"></span>
                            </label>
                            <input type="password" x-model="form.jwt_secret" placeholder="•••••••• (unchanged)" autocomplete="new-password"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <p class="text-xs text-muted-foreground mt-1">{{ __('platform.admin_platform_settings_realtime_jwt_secret_hint') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="border-t border-gray-100 pt-5 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <button type="button" @click="test" :disabled="testing"
                            class="border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors disabled:opacity-50 w-full sm:w-auto">
                        <i class="bi bi-broadcast mr-1"></i>
                        <span x-text="testing ? '{{ __('platform.admin_platform_settings_realtime_testing') }}' : '{{ __('platform.admin_platform_settings_realtime_test') }}'"></span>
                    </button>
                    <button type="button" @click="save" :disabled="saving"
                            class="bg-primary text-white px-5 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium disabled:opacity-50 w-full sm:w-auto">
                        <i class="bi bi-check-lg mr-1"></i>
                        <span x-text="saving ? '{{ __('platform.admin_platform_settings_realtime_saving') }}' : '{{ __('platform.admin_platform_settings_realtime_save') }}'"></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col" x-data="platformWhatsAppIntegration()">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-5">
                <div class="flex items-center gap-3">
                    <span class="w-11 h-11 rounded-xl bg-accent text-primary flex items-center justify-center shrink-0">
                        <i class="bi bi-whatsapp text-xl"></i>
                    </span>
                    <div>
                        <p class="font-semibold text-gray-900">{{ __('platform.admin_platform_settings_whatsapp_title') }}</p>
                        <p class="text-sm text-muted-foreground mt-0.5">{{ __('platform.admin_platform_settings_whatsapp_description') }}</p>
                    </div>
                </div>
                <a href="https://wa.takeone.bh" target="_blank" rel="noopener"
                   class="border border-primary text-primary bg-transparent px-3 py-1.5 rounded-md text-xs font-medium hover:bg-primary hover:text-white transition-colors inline-flex items-center gap-1.5 flex-shrink-0">
                    <i class="bi bi-box-arrow-up-right"></i>
                    {{ __('platform.admin_platform_settings_whatsapp_open_dashboard') }}
                </a>
            </div>

            <div class="space-y-5">
                {{-- Master switch --}}
                <div class="border-t border-gray-100 pt-5">
                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <span class="block text-sm font-semibold text-gray-900">{{ __('platform.admin_platform_settings_whatsapp_enable') }}</span>
                            <span class="block text-xs text-muted-foreground mt-0.5">{{ __('platform.admin_platform_settings_whatsapp_enable_hint') }}</span>
                        </div>
                        <button type="button" @click="form.enabled = !form.enabled"
                                :class="form.enabled ? 'bg-primary' : 'bg-gray-300'"
                                class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors">
                            <span :class="form.enabled ? 'translate-x-5' : 'translate-x-0.5'"
                                  class="inline-block h-5 w-5 mt-0.5 rounded-full bg-white shadow transform transition-transform"></span>
                        </button>
                    </label>
                </div>

                {{-- Gateway connection --}}
                <div class="border-t border-gray-100 pt-5 space-y-4">
                    <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                        <i class="bi bi-hdd-network text-primary"></i> {{ __('platform.admin_platform_settings_realtime_broker_connection') }}
                    </h4>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.admin_platform_settings_whatsapp_base_url') }}</label>
                        <input type="text" x-model="form.base_url" placeholder="https://your-openwa-host:2785"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.admin_platform_settings_whatsapp_session_name') }}</label>
                        <input type="text" x-model="form.session_name" placeholder="my-bot"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="text-xs text-muted-foreground mt-1">{{ __('platform.admin_platform_settings_whatsapp_session_name_hint') }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('platform.admin_platform_settings_whatsapp_api_key') }}
                            <span class="text-xs" :class="apiKeySet ? 'text-green-600' : 'text-red-500'" x-text="apiKeySet ? '· {{ __('platform.admin_platform_settings_whatsapp_api_key_set') }}' : '· {{ __('platform.admin_platform_settings_whatsapp_api_key_not_set') }}'"></span>
                        </label>
                        <input type="password" x-model="form.api_key" placeholder="•••••••• (unchanged)" autocomplete="new-password"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="text-xs text-muted-foreground mt-1">{{ __('platform.admin_platform_settings_whatsapp_api_key_hint') }}</p>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="border-t border-gray-100 pt-5 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <button type="button" @click="test" :disabled="testing"
                            class="border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors disabled:opacity-50 w-full sm:w-auto">
                        <i class="bi bi-broadcast mr-1"></i>
                        <span x-text="testing ? '{{ __('platform.admin_platform_settings_whatsapp_testing') }}' : '{{ __('platform.admin_platform_settings_whatsapp_test') }}'"></span>
                    </button>
                    <button type="button" @click="save" :disabled="saving"
                            class="bg-primary text-white px-5 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium disabled:opacity-50 w-full sm:w-auto">
                        <i class="bi bi-check-lg mr-1"></i>
                        <span x-text="saving ? '{{ __('platform.admin_platform_settings_whatsapp_saving') }}' : '{{ __('platform.admin_platform_settings_whatsapp_save') }}'"></span>
                    </button>
                </div>

                {{-- Send test message --}}
                <div class="border-t border-gray-100 pt-5 space-y-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.admin_platform_settings_whatsapp_send_test_label') }}</label>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <input type="text" x-model="testPhone" placeholder="{{ __('platform.admin_platform_settings_whatsapp_send_test_placeholder') }}"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <button type="button" @click="sendTest" :disabled="sendingTest || !testPhone"
                                class="border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors disabled:opacity-50 w-full sm:w-auto flex-shrink-0">
                            <i class="bi bi-send mr-1"></i>
                            <span x-text="sendingTest ? '{{ __('platform.admin_platform_settings_whatsapp_sending_test') }}' : '{{ __('platform.admin_platform_settings_whatsapp_send_test') }}'"></span>
                        </button>
                    </div>
                    <p class="text-xs text-muted-foreground">{{ __('platform.admin_platform_settings_whatsapp_send_test_hint') }}</p>
                </div>
            </div>
        </div>

        </div>
    </div>

    {{-- ── Danger Zone — reset the platform to its clean baseline ──────────── --}}
    <div class="mt-8" x-data="platformResetBaseline()">
        <h3 class="text-sm font-semibold text-red-600 uppercase tracking-wide mb-4 flex items-center gap-2">
            <i class="bi bi-exclamation-octagon-fill"></i>{{ __('platform.admin_platform_settings_danger_zone') }}
        </h3>

        <div class="bg-white rounded-xl shadow-sm border border-red-200 overflow-hidden max-w-2xl">
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

function platformRealtimeIntegration() {
    return {
        saving: false,
        testing: false,
        secretSet: @json($realtimeSettings['jwt_secret_set']),
        statusDot: '{{ $realtimeStatus['state'] === 'online' ? 'bg-green-500' : ($realtimeStatus['state'] === 'offline' ? 'bg-red-500' : 'bg-gray-400') }}',
        form: {
            enabled:         @json($realtimeSettings['enabled']),
            broker_host:     @json($realtimeSettings['broker_host']),
            broker_port:     @json($realtimeSettings['broker_port']),
            broker_username: @json($realtimeSettings['broker_username']),
            broker_password: '',
            broker_ws_url:   @json($realtimeSettings['broker_ws_url']),
            jwt_ttl:         @json($realtimeSettings['jwt_ttl']),
            jwt_secret:      '',
        },

        async save() {
            this.saving = true;
            try {
                const res = await fetch('{{ route('admin.plugins.realtime.update') }}', {
                    method: 'PUT',
                    headers: this._headers(),
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (data.success) {
                    window.showToast('success', data.message);
                    this._applyStatus(data.status);
                    if (this.form.jwt_secret) this.secretSet = true;
                    this.form.broker_password = '';
                    this.form.jwt_secret = '';
                } else {
                    window.showToast('error', data.message || 'Could not save settings.');
                }
            } catch (e) {
                window.showToast('error', 'Network error while saving.');
            } finally {
                this.saving = false;
            }
        },

        async test() {
            this.testing = true;
            try {
                const res = await fetch('{{ route('admin.plugins.realtime.test') }}', {
                    method: 'POST',
                    headers: this._headers(),
                });
                const data = await res.json();
                window.showToast(data.success ? 'success' : 'error', data.message);
            } catch (e) {
                window.showToast('error', 'Network error during test.');
            } finally {
                this.testing = false;
            }
        },

        _applyStatus(status) {
            if (!status) return;
            const map = { online: 'bg-green-500', offline: 'bg-red-500', disabled: 'bg-gray-400' };
            this.statusDot = map[status.state] || 'bg-gray-400';
            const label = document.getElementById('rt-status-label');
            if (label) label.textContent = status.label;
        },

        _headers() {
            return {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            };
        },
    };
}

function platformWhatsAppIntegration() {
    return {
        saving: false,
        testing: false,
        sendingTest: false,
        testPhone: '',
        apiKeySet: @json($whatsappSettings['api_key_set']),
        form: {
            enabled:      @json($whatsappSettings['enabled']),
            base_url:     @json($whatsappSettings['base_url']),
            session_name: @json($whatsappSettings['session_name']),
            api_key:      '',
        },

        async save() {
            this.saving = true;
            try {
                const res = await fetch('{{ route('admin.platform.settings.whatsapp.update') }}', {
                    method: 'PUT',
                    headers: this._headers(),
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (data.success) {
                    window.showToast('success', data.message);
                    if (this.form.api_key) this.apiKeySet = true;
                    this.form.api_key = '';
                } else {
                    window.showToast('error', data.message || 'Could not save settings.');
                }
            } catch (e) {
                window.showToast('error', 'Network error while saving.');
            } finally {
                this.saving = false;
            }
        },

        async test() {
            this.testing = true;
            try {
                const res = await fetch('{{ route('admin.platform.settings.whatsapp.test') }}', {
                    method: 'POST',
                    headers: this._headers(),
                });
                const data = await res.json();
                window.showToast(data.success ? 'success' : 'error', data.message);
            } catch (e) {
                window.showToast('error', 'Network error during test.');
            } finally {
                this.testing = false;
            }
        },

        async sendTest() {
            if (!this.testPhone) return;
            this.sendingTest = true;
            try {
                const res = await fetch('{{ route('admin.platform.settings.whatsapp.send-test') }}', {
                    method: 'POST',
                    headers: this._headers(),
                    body: JSON.stringify({ phone: this.testPhone }),
                });
                const data = await res.json();
                window.showToast(data.success ? 'success' : 'error', data.message);
            } catch (e) {
                window.showToast('error', 'Network error while sending.');
            } finally {
                this.sendingTest = false;
            }
        },

        _headers() {
            return {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            };
        },
    };
}
</script>
@endpush
@endsection
