@extends($layout)

@section('admin-mobile-title', 'Realtime / MQTT')

@section('admin-content')
@php
    $stateColors = [
        'online'   => ['dot' => 'bg-green-500',  'text' => 'text-green-700',  'bg' => 'bg-green-50',  'ring' => 'ring-green-200'],
        'offline'  => ['dot' => 'bg-red-500',    'text' => 'text-red-700',    'bg' => 'bg-red-50',    'ring' => 'ring-red-200'],
        'disabled' => ['dot' => 'bg-gray-400',   'text' => 'text-gray-600',   'bg' => 'bg-gray-50',   'ring' => 'ring-gray-200'],
    ];
    $sc = $stateColors[$status['state']] ?? $stateColors['disabled'];
@endphp

<div class="max-w-3xl mx-auto" x-data="realtimePlugin()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div class="flex items-center gap-3">
            <span class="w-11 h-11 rounded-xl bg-accent text-primary flex items-center justify-center shrink-0">
                <i class="bi bi-broadcast-pin text-xl"></i>
            </span>
            <div>
                <h1 class="text-xl font-bold text-gray-900">Realtime / MQTT</h1>
                <p class="text-sm text-muted-foreground">Instant notifications &amp; messaging over a self-hosted broker</p>
            </div>
        </div>
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-full ring-1 {{ $sc['bg'] }} {{ $sc['ring'] }}"
             id="rt-status-pill">
            <span class="w-2 h-2 rounded-full {{ $sc['dot'] }}" :class="statusDot"></span>
            <span class="text-xs font-medium {{ $sc['text'] }}" id="rt-status-label">{{ $status['label'] }}</span>
        </div>
    </div>

    <form @submit.prevent="save" class="space-y-6">
        @csrf

        {{-- Master switch --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <label class="flex items-center justify-between cursor-pointer">
                <div>
                    <span class="block text-sm font-semibold text-gray-900">Enable realtime delivery</span>
                    <span class="block text-xs text-muted-foreground mt-0.5">When off, the app still works — notifications &amp; messages simply require a page refresh.</span>
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
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                <i class="bi bi-hdd-network text-primary"></i> Broker connection
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Host <span class="text-xs text-muted-foreground">(PHP publisher, TCP)</span></label>
                    <input type="text" x-model="form.broker_host" placeholder="127.0.0.1"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">TCP port</label>
                    <input type="number" x-model="form.broker_port" placeholder="1883"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Browser WebSocket URL</label>
                <input type="text" x-model="form.broker_ws_url" placeholder="wss://takeone.bh/mqtt"
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <p class="text-xs text-muted-foreground mt-1">What browsers connect to. Use <code class="bg-muted px-1 rounded">ws://</code> for local dev, <code class="bg-muted px-1 rounded">wss://</code> in production (terminate TLS at your proxy).</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Backend username</label>
                    <input type="text" x-model="form.broker_username" placeholder="takeone-server" autocomplete="off"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Backend password</label>
                    <input type="password" x-model="form.broker_password" placeholder="•••••••• (unchanged)" autocomplete="new-password"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
            </div>
        </div>

        {{-- Token / security --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                <i class="bi bi-shield-lock text-primary"></i> Browser token (JWT)
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Token lifetime <span class="text-xs text-muted-foreground">(seconds)</span></label>
                    <input type="number" x-model="form.jwt_ttl" placeholder="3600"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Signing secret
                        <span class="text-xs" :class="secretSet ? 'text-green-600' : 'text-red-500'" x-text="secretSet ? '· set' : '· using APP_KEY'"></span>
                    </label>
                    <input type="password" x-model="form.jwt_secret" placeholder="•••••••• (unchanged)" autocomplete="new-password"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <p class="text-xs text-muted-foreground mt-1">Must match the EMQX broker's JWT secret.</p>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <button type="button" @click="test" :disabled="testing"
                    class="border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors disabled:opacity-50 w-full sm:w-auto">
                <i class="bi bi-broadcast mr-1"></i>
                <span x-text="testing ? 'Testing…' : 'Test connection'"></span>
            </button>
            <button type="submit" :disabled="saving"
                    class="bg-primary text-white px-5 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium disabled:opacity-50 w-full sm:w-auto">
                <i class="bi bi-check-lg mr-1"></i>
                <span x-text="saving ? 'Saving…' : 'Save settings'"></span>
            </button>
        </div>
    </form>

    {{-- Broker setup hint --}}
    <div class="mt-6 bg-accent/40 border border-accent rounded-xl p-4 flex gap-3">
        <i class="bi bi-info-circle text-primary mt-0.5"></i>
        <div class="text-xs text-foreground/80 leading-relaxed">
            <strong class="text-foreground">Self-hosted broker:</strong> a ready-to-run EMQX stack ships with this plugin under
            <code class="bg-white px-1 rounded border border-border">docker/realtime/</code>.
            Run <code class="bg-white px-1 rounded border border-border">docker compose up -d</code> there to start the broker
            (WS <code class="bg-white px-1 rounded border border-border">8083</code>, dashboard <code class="bg-white px-1 rounded border border-border">18083</code>),
            then point the fields above at it and hit <em>Test connection</em>.
        </div>
    </div>
</div>

@push('scripts')
<script>
function realtimePlugin() {
    return {
        saving: false,
        testing: false,
        secretSet: @json($settings['jwt_secret_set']),
        statusDot: '{{ $status['state'] === 'online' ? 'bg-green-500' : ($status['state'] === 'offline' ? 'bg-red-500' : 'bg-gray-400') }}',
        form: {
            enabled:         @json($settings['enabled']),
            broker_host:     @json($settings['broker_host']),
            broker_port:     @json($settings['broker_port']),
            broker_username: @json($settings['broker_username']),
            broker_password: '',
            broker_ws_url:   @json($settings['broker_ws_url']),
            jwt_ttl:         @json($settings['jwt_ttl']),
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
</script>
@endpush
@endsection
