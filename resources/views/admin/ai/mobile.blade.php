@extends('layouts.app')

@section('hide-navbar', true)
@section('title', 'AI Providers')

@php
    $modalityMeta = [
        'text'  => ['label' => 'Text / chat',            'icon' => 'bi-chat-dots-fill', 'tint' => 'bg-indigo-100 text-indigo-600'],
        'tts'   => ['label' => 'Voice · text to speech', 'icon' => 'bi-volume-up-fill', 'tint' => 'bg-emerald-100 text-emerald-600'],
        'stt'   => ['label' => 'Voice · speech to text', 'icon' => 'bi-mic-fill',        'tint' => 'bg-amber-100 text-amber-600'],
        'image' => ['label' => 'Image generation',       'icon' => 'bi-image-fill',      'tint' => 'bg-rose-100 text-rose-600'],
    ];
    $modalityOptions = collect($modalities)->map(fn ($m) => ['value' => $m, 'label' => $modalityMeta[$m]['label'] ?? $m, 'icon' => $modalityMeta[$m]['icon'] ?? 'bi-cpu'])->all();
    $driverOptions   = collect($drivers)->map(fn ($d) => ['value' => $d, 'label' => ucfirst($d)])->all();
@endphp

@section('content')
<div class="min-h-screen bg-background pb-24" x-data="aiSettingsM(@js($providers))">

    {{-- ===== Header ===== --}}
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('admin.platform.index') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="Back">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">AI Providers</p>
            <button type="button" @click="openCreate()"
                    class="m-press w-10 h-10 rounded-xl flex items-center justify-center text-primary" aria-label="Add provider">
                <i class="bi bi-plus-circle-fill text-xl"></i>
            </button>
        </div>
    </header>

    {{-- ===== Hero ===== --}}
    <header class="m-hero mx-4 mt-4 rounded-3xl px-5 py-5 text-white relative overflow-hidden">
        <div class="absolute -end-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
        <div class="relative z-10 flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                <i class="bi bi-robot text-2xl m-float"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">Settings</p>
                <h1 class="text-xl font-black leading-tight">AI Providers</h1>
            </div>
            <div class="ms-auto text-end flex-shrink-0">
                <p class="text-2xl font-black leading-none" x-text="providers.length"></p>
                <p class="text-[10px] uppercase tracking-wide text-white/70">connected</p>
            </div>
        </div>
        <p class="relative z-10 text-[12px] text-white/80 leading-snug mt-3">
            Connect any AI service — local or cloud — for text, voice &amp; image. Keys are stored encrypted and never leave the server.
        </p>
    </header>

    {{-- ===== Provider groups ===== --}}
    <div class="px-4 pt-5 space-y-6 mobile-stagger">
        @foreach($modalities as $modality)
            @php $meta = $modalityMeta[$modality] ?? ['label' => $modality, 'icon' => 'bi-cpu', 'tint' => 'bg-muted text-foreground']; @endphp
            <section>
                <div class="flex items-center gap-2.5 mb-2.5">
                    <span class="w-8 h-8 rounded-xl grid place-items-center flex-shrink-0 {{ $meta['tint'] }}"><i class="bi {{ $meta['icon'] }} text-sm"></i></span>
                    <h3 class="text-sm font-bold text-foreground">{{ $meta['label'] }}</h3>
                    <span class="ms-auto text-xs font-bold text-muted-foreground tabular-nums" x-text="providersFor('{{ $modality }}').length"></span>
                </div>

                <template x-if="providersFor('{{ $modality }}').length === 0">
                    <button type="button" @click="openCreate('{{ $modality }}')"
                            class="w-full m-card border border-dashed border-gray-200 rounded-2xl p-5 text-center text-muted-foreground text-sm flex flex-col items-center gap-1.5">
                        <i class="bi bi-plus-circle text-xl text-gray-300"></i>
                        <span>Add a {{ strtolower($meta['label']) }} provider</span>
                    </button>
                </template>

                <div class="space-y-3">
                    <template x-for="p in providersFor('{{ $modality }}')" :key="p.id">
                        <div class="m-card rounded-2xl p-4">
                            <div class="flex items-start gap-3">
                                <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 {{ $meta['tint'] }}"><i class="bi {{ $meta['icon'] }}"></i></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="font-bold text-foreground truncate" x-text="p.name"></h4>
                                        <span x-show="p.is_default" class="text-[9px] font-black px-2 py-0.5 rounded-full bg-primary/10 text-primary uppercase tracking-wide">Default</span>
                                    </div>
                                    <p class="text-[11px] text-muted-foreground mt-0.5">
                                        <span class="uppercase font-semibold" x-text="p.driver"></span>
                                        <template x-if="p.model"><span> · <span x-text="p.model"></span></span></template>
                                    </p>
                                    <p class="text-[11px] text-muted-foreground truncate mt-0.5" x-show="p.base_url" x-text="p.base_url"></p>
                                    <p class="text-[11px] text-muted-foreground mt-0.5" x-show="!p.base_url && p.has_key">API key configured</p>
                                </div>
                                <span class="flex items-center gap-1.5 flex-shrink-0 text-[10px] font-bold px-2 py-1 rounded-full"
                                      :class="p.enabled ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-400'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="p.enabled ? 'bg-green-500' : 'bg-gray-300'"></span>
                                    <span x-text="p.enabled ? 'On' : 'Off'"></span>
                                </span>
                            </div>

                            <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-100">
                                <button type="button" @click="test(p)" x-show="p.modality === 'text'"
                                        class="m-press flex-1 text-xs font-semibold px-3 py-2 rounded-xl border border-gray-200 text-foreground flex items-center justify-center gap-1.5">
                                    <i class="bi bi-plug"></i> Test
                                </button>
                                <button type="button" @click="openEdit(p)"
                                        class="m-press flex-1 text-xs font-semibold px-3 py-2 rounded-xl border border-gray-200 text-foreground flex items-center justify-center gap-1.5">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button type="button" @click="remove(p)"
                                        class="m-press w-10 flex-shrink-0 text-xs px-3 py-2 rounded-xl border border-red-200 text-red-600 flex items-center justify-center">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </section>
        @endforeach
    </div>

    {{-- ===== Add / Edit bottom-sheet (teleported so it escapes the mobile-shell transform) ===== --}}
    <template x-teleport="body">
        <div x-show="showForm" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="showForm = false">
            <div x-show="showForm" x-transition.opacity class="fixed inset-0 bg-gray-900/50" @click="showForm = false"></div>
            <div class="fixed inset-x-0 bottom-0 flex justify-center">
                <div x-show="showForm"
                     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                     class="relative bg-background w-full sm:max-w-lg rounded-t-3xl shadow-2xl flex flex-col"
                     style="max-height: 92vh; max-height: 92dvh;" @click.stop>

                    {{-- header --}}
                    <div class="pt-2.5 pb-1 flex justify-center flex-shrink-0"><span class="w-10 h-1 rounded-full bg-gray-300"></span></div>
                    <div class="flex items-center justify-between px-5 pt-1 pb-3 flex-shrink-0">
                        <h4 class="font-black text-lg text-foreground" x-text="editing ? 'Edit provider' : 'Add provider'"></h4>
                        <button type="button" @click="showForm = false" class="w-9 h-9 -me-1.5 rounded-full grid place-items-center text-muted-foreground hover:bg-muted transition-colors">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    {{-- scrollable body --}}
                    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-5 pb-5 space-y-5">

                        {{-- Name --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Name</label>
                            <input type="text" x-model="form.name" placeholder="e.g. Local Ollama, OpenAI GPT-4o"
                                   class="w-full px-3.5 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                        </div>

                        {{-- Modality — selection cards (no native select, no clipping) --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Modality</label>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($modalityOptions as $opt)
                                    <button type="button" @click="form.modality = '{{ $opt['value'] }}'"
                                            class="m-press flex items-center gap-2 px-3 py-2.5 rounded-xl border text-start transition-colors"
                                            :class="form.modality === '{{ $opt['value'] }}' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 bg-white text-foreground'">
                                        <i class="bi {{ $opt['icon'] }} text-sm flex-shrink-0"></i>
                                        <span class="text-[12px] font-semibold leading-tight">{{ $opt['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Driver — wrap chips --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Driver</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach($driverOptions as $opt)
                                    <button type="button" @click="form.driver = '{{ $opt['value'] }}'"
                                            class="m-press px-3.5 py-2 rounded-full border text-xs font-bold transition-colors"
                                            :class="form.driver === '{{ $opt['value'] }}' ? 'border-primary bg-primary text-white' : 'border-gray-200 bg-white text-muted-foreground'">
                                        {{ $opt['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Base URL --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Base URL <span class="normal-case font-normal text-muted-foreground/70">(local or custom endpoint)</span></label>
                            <input type="url" x-model="form.base_url" inputmode="url" placeholder="http://127.0.0.1:11434 · https://api.openai.com/v1"
                                   class="w-full px-3.5 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                        </div>

                        {{-- API key --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">API key</label>
                            <input type="password" x-model="form.api_key" autocomplete="new-password"
                                   :placeholder="editing ? '•••••••• (leave blank to keep current)' : 'Paste the key (blank for keyless local servers)'"
                                   class="w-full px-3.5 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                            <p class="text-[11px] text-muted-foreground mt-1.5 flex items-center gap-1"><i class="bi bi-shield-lock"></i> Stored encrypted. Never shown again.</p>
                        </div>

                        {{-- Model --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Model</label>
                            <input type="text" x-model="form.model" placeholder="qwen3-coder:30b · gpt-4o-mini · claude-sonnet-5"
                                   class="w-full px-3.5 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                        </div>

                        {{-- Tuning --}}
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wide text-muted-foreground mb-1">Temp</label>
                                <input type="number" step="0.1" min="0" max="2" inputmode="decimal" x-model="form.temperature" placeholder="0.2"
                                       class="w-full px-2.5 py-2.5 bg-white border border-gray-200 rounded-xl text-sm text-center focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wide text-muted-foreground mb-1">Max tok</label>
                                <input type="number" min="1" inputmode="numeric" x-model="form.max_tokens" placeholder="4096"
                                       class="w-full px-2.5 py-2.5 bg-white border border-gray-200 rounded-xl text-sm text-center focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wide text-muted-foreground mb-1">Timeout</label>
                                <input type="number" min="1" inputmode="numeric" x-model="form.timeout" placeholder="120"
                                       class="w-full px-2.5 py-2.5 bg-white border border-gray-200 rounded-xl text-sm text-center focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                            </div>
                        </div>

                        {{-- Toggles --}}
                        <div class="space-y-2">
                            <button type="button" @click="form.is_default = !form.is_default"
                                    class="w-full flex items-center justify-between gap-3 px-3.5 py-3 rounded-xl bg-white border border-gray-200">
                                <span class="min-w-0 text-start">
                                    <span class="block text-sm font-semibold text-foreground">Default for its modality</span>
                                    <span class="block text-[11px] text-muted-foreground">Used automatically when none is specified</span>
                                </span>
                                <span class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors" :class="form.is_default ? 'bg-primary' : 'bg-gray-200'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform" :class="form.is_default ? 'translate-x-6' : 'translate-x-1'"></span>
                                </span>
                            </button>
                            <button type="button" @click="form.enabled = !form.enabled"
                                    class="w-full flex items-center justify-between gap-3 px-3.5 py-3 rounded-xl bg-white border border-gray-200">
                                <span class="min-w-0 text-start">
                                    <span class="block text-sm font-semibold text-foreground">Enabled</span>
                                    <span class="block text-[11px] text-muted-foreground">Available for the app to use</span>
                                </span>
                                <span class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors" :class="form.enabled ? 'bg-primary' : 'bg-gray-200'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform" :class="form.enabled ? 'translate-x-6' : 'translate-x-1'"></span>
                                </span>
                            </button>
                        </div>
                    </div>

                    {{-- sticky footer --}}
                    <div class="flex items-center gap-2 px-5 pt-3 border-t border-gray-100 flex-shrink-0"
                         style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                        <button type="button" @click="showForm = false" class="px-4 py-3 rounded-xl border border-gray-200 text-muted-foreground font-semibold text-sm">Cancel</button>
                        <button type="button" @click="save()" :disabled="saving"
                                class="m-press flex-1 px-4 py-3 rounded-xl bg-primary text-white font-bold text-sm disabled:opacity-60 flex items-center justify-center gap-2">
                            <span x-show="!saving" x-text="editing ? 'Save changes' : 'Add provider'"></span>
                            <span x-show="saving" x-cloak>Saving…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<script>
function aiSettingsM(providers) {
    return {
        providers: providers || [],
        showForm: false,
        editing: null,
        saving: false,
        form: {},

        init() { this.form = this.blank(); },
        blank(modality = 'text') {
            return { id: null, name: '', modality, driver: 'ollama', base_url: '', api_key: '', model: '', temperature: '', max_tokens: '', timeout: '', is_default: false, enabled: true };
        },
        csrf() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; },
        providersFor(m) { return this.providers.filter(p => p.modality === m); },

        openCreate(modality = 'text') { this.editing = null; this.form = this.blank(modality); this.showForm = true; },
        openEdit(p) {
            this.editing = p;
            this.form = {
                id: p.id, name: p.name, modality: p.modality, driver: p.driver,
                base_url: p.base_url || '', api_key: '', model: p.model || '',
                temperature: p.options?.temperature ?? '', max_tokens: p.options?.max_tokens ?? '', timeout: p.options?.timeout ?? '',
                is_default: !!p.is_default, enabled: !!p.enabled,
            };
            this.showForm = true;
        },

        {{-- Patch the list in place (No-Reload rule) from the provider the server returns. --}}
        upsert(prov) {
            if (!prov) return;
            if (prov.is_default) {
                this.providers.forEach(p => { if (p.modality === prov.modality && p.id !== prov.id) p.is_default = false; });
            }
            const i = this.providers.findIndex(p => p.id === prov.id);
            if (i > -1) this.providers.splice(i, 1, prov); else this.providers.push(prov);
        },

        async save() {
            if (this.saving) return;
            if (!this.form.name.trim()) { window.showToast && window.showToast('warning', 'Give the provider a name.'); return; }
            this.saving = true;
            const editing = !!this.form.id;
            const url = editing ? `/admin/ai/providers/${this.form.id}` : '/admin/ai/providers';
            const payload = { ...this.form };
            if (editing) payload._method = 'PUT';
            ['temperature', 'max_tokens', 'timeout'].forEach(k => { if (payload[k] === '') delete payload[k]; });
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.upsert(data.provider);
                    window.showToast && window.showToast('success', data.message || 'Saved');
                    this.showForm = false;
                } else {
                    window.showToast && window.showToast('error', data.message || 'Could not save the provider.');
                }
            } catch (e) {
                window.showToast && window.showToast('error', 'Could not save the provider.');
            } finally { this.saving = false; }
        },

        async remove(p) {
            const ok = window.confirmAction
                ? await window.confirmAction({ title: 'Remove provider', message: `Remove "${p.name}"?`, type: 'danger', confirmText: 'Remove' })
                : true;
            if (!ok) return;
            try {
                const res = await fetch(`/admin/ai/providers/${p.id}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: JSON.stringify({ _method: 'DELETE' }),
                });
                const data = await res.json();
                if (data.success) {
                    const i = this.providers.findIndex(x => x.id === p.id);
                    if (i > -1) this.providers.splice(i, 1);
                    window.showToast && window.showToast('success', data.message || 'Removed');
                }
            } catch (e) { window.showToast && window.showToast('error', 'Could not remove.'); }
        },

        async test(p) {
            window.showToast && window.showToast('info', `Testing ${p.name}…`);
            try {
                const res = await fetch(`/admin/ai/providers/${p.id}/test`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: '{}',
                });
                const data = await res.json();
                window.showToast && window.showToast(data.success ? 'success' : 'error', data.message);
            } catch (e) { window.showToast && window.showToast('error', 'Test failed.'); }
        },
    };
}
</script>
@endpush
