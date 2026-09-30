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

    {{-- Hall screens: which side the red corner stands on, per sport.
         Saves on tap (no page reload); every screen of that sport follows it
         on its next reload. App\Sports\Combat\CornerSide. --}}
    <div class="mt-8 max-w-2xl">
        <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-4">{{ __('platform.corner_side_title') }}</h3>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6"
             x-data="{
                rows: @js($cornerSides),
                saving: null,
                async pick(row, side) {
                    if (this.saving) return;
                    this.saving = row.sport;
                    try {
                        const res = await fetch(@js(route('admin.platform.settings.corner-side')), {
                            method: 'PUT',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ sport: row.sport, side: side }),
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            Object.assign(row, data.corner);
                            window.showToast('success', data.message);
                        } else {
                            window.showToast('error', data.message || 'Could not save.');
                        }
                    } catch (e) {
                        window.showToast('error', 'Network error while saving.');
                    } finally {
                        this.saving = null;
                    }
                },
             }">
            <p class="font-semibold text-gray-900">{{ __('platform.corner_side_heading') }}</p>
            <p class="text-sm text-muted-foreground mt-0.5">{{ __('platform.corner_side_desc') }}</p>

            <div class="mt-5 space-y-4">
                <template x-for="row in rows" :key="row.sport">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-between border-t border-gray-100 pt-4 first:border-t-0 first:pt-0">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900" x-text="row.label"></p>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                <span x-text="row.default === 'right'
                                    ? @js(__('platform.corner_side_default_is', ['side' => __('platform.corner_side_side_right')]))
                                    : @js(__('platform.corner_side_default_is', ['side' => __('platform.corner_side_side_left')]))"></span>
                                <template x-if="row.override">
                                    <span class="ms-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 font-medium">
                                        <i class="bi bi-arrow-left-right"></i>{{ __('platform.corner_side_overridden') }}
                                    </span>
                                </template>
                            </p>
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0" role="radiogroup" :aria-label="row.label">
                            {{-- The two choices drawn as the corners themselves: a red and a blue tile, in the order they would stand. --}}
                            <template x-for="side in ['left', 'right']" :key="side">
                                <button type="button" role="radio" :aria-checked="row.side === side"
                                        @click="pick(row, side)" :disabled="saving === row.sport"
                                        class="flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium transition-colors disabled:opacity-60"
                                        :class="row.side === side ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-gray-700 hover:bg-muted/60'">
                                    <span class="inline-flex rounded overflow-hidden" aria-hidden="true">
                                        <span class="w-3 h-4" :class="side === 'left' ? 'bg-red-600' : 'bg-blue-600'"></span>
                                        <span class="w-3 h-4" :class="side === 'left' ? 'bg-blue-600' : 'bg-red-600'"></span>
                                    </span>
                                    <span x-text="side === 'left' ? @js(__('platform.corner_side_left')) : @js(__('platform.corner_side_right'))"></span>
                                </button>
                            </template>
                            <button type="button" x-show="row.override" x-cloak @click="pick(row, 'default')" :disabled="saving === row.sport"
                                    class="px-3 py-2 rounded-lg text-sm font-medium text-muted-foreground hover:bg-muted/60 transition-colors"
                                    title="{{ __('platform.corner_side_default') }}">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

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
</div>

@push('scripts')
<script>
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
