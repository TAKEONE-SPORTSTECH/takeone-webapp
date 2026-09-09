@extends('layouts.admin')

@section('title', 'AI Providers')

@php
    $modalityOptions = collect($modalities)->map(fn ($m) => ['value' => $m, 'label' => [
        'text' => 'Text / chat', 'tts' => 'Voice — text to speech', 'stt' => 'Voice — speech to text', 'image' => 'Image generation',
    ][$m] ?? $m])->all();
    $driverOptions = collect($drivers)->map(fn ($d) => ['value' => $d, 'label' => ucfirst($d)])->all();
    $modalityLabels = ['text' => 'Text / chat', 'tts' => 'Voice — text to speech', 'stt' => 'Voice — speech to text', 'image' => 'Image generation'];
@endphp

@section('admin-content')
<div class="space-y-6" x-data="aiSettings(@js($providers), @js($translationPrimary), @js($translationChain))">

    <x-admin-hero title="AI Providers" eyebrow="Settings" icon="bi-robot"
        subtitle="Connect any AI service — local or cloud — for text, voice, and image. Keys are stored encrypted and never leave the server.">
        <x-slot:actions>
            <button type="button" @click="openCreate()"
                class="bg-white text-primary px-4 py-2 rounded-lg font-medium hover:bg-white/90 transition-colors inline-flex items-center gap-2">
                <i class="bi bi-plus-lg"></i> Add provider
            </button>
        </x-slot:actions>
    </x-admin-hero>

    {{-- ===== Which model writes the translations =====

         Separate from the "default" flag on a provider, and deliberately so:
         the default is what the Copilot talks to, and an operator may want
         Claude answering the coach while a local model does the translating —
         or the reverse the month the bill arrives. One click, effective on the
         next translation, reversible with the same click.

         The list under it is the FALLBACK ORDER. It is shown because the
         honest thing about a chain is that the reader should be able to see
         what happens when the first one is down. --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-start gap-3 mb-4">
            <span class="w-11 h-11 rounded-2xl bg-accent text-primary grid place-items-center flex-shrink-0">
                <i class="bi bi-translate text-lg"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-bold text-foreground">Which model translates events</h3>
                <p class="text-xs text-muted-foreground mt-0.5">
                    An organiser writes an event once; this model rewrites it into every language a reader asks for.
                    Switch at any time — it takes effect on the next translation, and nothing already written changes.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
            {{-- Automatic: follow whichever provider is marked default. --}}
            <button type="button" @click="setTranslator(null)" :disabled="switching"
                    class="text-start rounded-xl border-2 p-3 transition-colors disabled:opacity-60"
                    :class="translationPrimary === null ? 'border-primary bg-primary/5' : 'border-gray-200 hover:border-gray-300'">
                <div class="flex items-center gap-2">
                    <i class="bi bi-magic text-primary"></i>
                    <span class="text-sm font-bold text-foreground">Automatic</span>
                    <span x-show="translationPrimary === null" class="ms-auto text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary text-white">IN USE</span>
                </div>
                <p class="text-[11px] text-muted-foreground mt-1">Follow the default provider below.</p>
            </button>

            <template x-for="p in providersFor('text')" :key="'tr-' + p.id">
                <button type="button" @click="setTranslator(p.id)" :disabled="switching || !p.enabled"
                        class="text-start rounded-xl border-2 p-3 transition-colors disabled:opacity-40"
                        :class="translationPrimary === p.id ? 'border-primary bg-primary/5' : 'border-gray-200 hover:border-gray-300'">
                    <div class="flex items-center gap-2">
                        <i class="bi text-primary" :class="p.driver === 'ollama' ? 'bi-hdd-network' : 'bi-cloud'"></i>
                        <span class="text-sm font-bold text-foreground truncate" x-text="p.name"></span>
                        <span x-show="translationPrimary === p.id" class="ms-auto text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary text-white flex-shrink-0">IN USE</span>
                    </div>
                    <p class="text-[11px] text-muted-foreground mt-1 truncate">
                        <span x-text="p.model || p.driver"></span>
                        <span x-show="p.driver === 'ollama'"> · runs on your own hardware</span>
                    </p>
                </button>
            </template>
        </div>

        {{-- The order, so "what happens when it is down" has a visible answer. --}}
        <div class="mt-4 pt-3 border-t border-gray-100">
            <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-2">Tried in this order</p>
            <div class="flex flex-wrap items-center gap-1.5">
                <template x-for="(link, i) in translationChain" :key="'chain-' + i">
                    <span class="inline-flex items-center gap-1.5">
                        <span x-show="i > 0" class="text-muted-foreground/40 text-xs"><i class="bi bi-chevron-right"></i></span>
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold"
                              :class="i === 0 ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'">
                            <span x-text="link.label"></span>
                        </span>
                    </span>
                </template>
            </div>
            <p class="text-[11px] text-muted-foreground mt-2">
                If the first cannot answer — a key expires, a service is down, a server is rebooting — the next one writes it instead.
                The reader still gets their language.
            </p>
        </div>
    </div>

    @foreach($modalities as $modality)
        <div>
            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-3">{{ $modalityLabels[$modality] ?? $modality }}</h3>

            <template x-if="providersFor('{{ $modality }}').length === 0">
                <div class="bg-white rounded-xl border border-dashed border-gray-200 p-6 text-center text-muted-foreground text-sm">
                    No {{ strtolower($modalityLabels[$modality] ?? $modality) }} provider yet.
                </div>
            </template>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <template x-for="p in providersFor('{{ $modality }}')" :key="p.id">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-semibold text-foreground truncate" x-text="p.name"></h4>
                                    <span x-show="p.is_default" class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-primary/10 text-primary">DEFAULT</span>
                                </div>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    <span class="uppercase" x-text="p.driver"></span>
                                    <span x-show="p.model"> · <span x-text="p.model"></span></span>
                                </p>
                            </div>
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 mt-1.5" :class="p.enabled ? 'bg-green-500' : 'bg-gray-300'" :title="p.enabled ? 'Enabled' : 'Disabled'"></span>
                        </div>
                        <p class="text-xs text-muted-foreground truncate" x-show="p.base_url" x-text="p.base_url"></p>
                        <p class="text-xs text-muted-foreground" x-show="!p.base_url && p.has_key">API key configured</p>
                        <div class="flex items-center gap-2 mt-3">
                            <button type="button" @click="test(p)" x-show="p.modality === 'text'"
                                class="text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 text-foreground hover:bg-muted transition-colors">
                                <i class="bi bi-plug"></i> Test
                            </button>
                            <button type="button" @click="openEdit(p)"
                                class="text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 text-foreground hover:bg-muted transition-colors">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button type="button" @click="remove(p)"
                                class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-colors ml-auto">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    @endforeach

    {{-- Add / edit modal --}}
    <div x-show="showForm" x-cloak class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center">
        <div class="fixed inset-0 bg-black/40" @click="showForm = false"></div>
        <div class="relative bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-3xl shadow-2xl max-h-[92vh] flex flex-col">
            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl sm:rounded-t-2xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi bi-cpu text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h4 class="text-lg font-black leading-tight" x-text="editing ? 'Edit provider' : 'Add provider'"></h4>
                        <p class="text-[12px] text-white/85 mt-0.5" x-show="form.name" x-text="form.name"></p>
                    </div>
                    <button type="button" @click="showForm = false" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div class="px-5 py-4 overflow-y-auto space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" x-model="form.name" placeholder="e.g. Local Ollama, OpenAI GPT-4o"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Modality</label>
                        <x-select-menu model="form.modality" :options="$modalityOptions" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Driver</label>
                        <x-select-menu model="form.driver" :options="$driverOptions" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Base URL <span class="text-muted-foreground font-normal">(local/self-hosted or custom endpoint)</span></label>
                    <input type="url" x-model="form.base_url" placeholder="http://127.0.0.1:11434  ·  https://api.openai.com/v1"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">API key</label>
                    <input type="password" x-model="form.api_key" autocomplete="new-password"
                        :placeholder="editing ? '•••••••• (leave blank to keep current)' : 'Paste the API key (leave blank for keyless local servers)'"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <p class="text-xs text-muted-foreground mt-1">Stored encrypted. Never shown again.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Model</label>
                    <input type="text" x-model="form.model" placeholder="qwen3-coder:30b  ·  gpt-4o-mini  ·  claude-sonnet-5"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Temperature</label>
                        <input type="number" step="0.1" min="0" max="2" x-model="form.temperature" placeholder="0.2"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Max tokens</label>
                        <input type="number" min="1" x-model="form.max_tokens" placeholder="4096"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Timeout (s)</label>
                        <input type="number" min="1" x-model="form.timeout" placeholder="120"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div class="flex items-center gap-6 pt-1">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" x-model="form.is_default" class="rounded border-gray-300 text-primary focus:ring-primary"> Default for its modality
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" x-model="form.enabled" class="rounded border-gray-300 text-primary focus:ring-primary"> Enabled
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-100 flex-shrink-0"
                 style="padding-bottom: max(1rem, calc(0.75rem + env(safe-area-inset-bottom)));">
                <button type="button" @click="showForm = false" class="px-4 py-2 rounded-lg border border-gray-200 text-muted-foreground hover:bg-muted transition-colors">Cancel</button>
                <button type="button" @click="save()" :disabled="saving"
                    class="px-4 py-2 rounded-lg bg-primary text-white font-medium hover:bg-primary/90 transition-colors disabled:opacity-60">
                    <span x-show="!saving">Save</span><span x-show="saving">Saving…</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function aiSettings(providers, translationPrimary, translationChain) {
    return {
        providers: providers || [],
        showForm: false,
        editing: null,
        saving: false,
        form: {},

        // Which model writes the translations, and the fallback order behind it.
        translationPrimary: translationPrimary ?? null,
        translationChain: translationChain || [],
        switching: false,

        /* Switch the translator. Optimistic on the card so the click feels
           immediate, rolled back if the server refuses. */
        async setTranslator(id) {
            if (this.switching || this.translationPrimary === id) return;

            const previous = this.translationPrimary;
            this.translationPrimary = id;
            this.switching = true;

            try {
                const res = await fetch(@js(route('admin.ai.translation-primary')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    credentials: 'same-origin',
                    body: JSON.stringify({ provider_id: id }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || !d.success) throw new Error(d.message || 'Could not switch.');

                this.translationPrimary = d.primary ?? null;
                this.translationChain = d.chain || [];
                window.showToast('success', d.message);
            } catch (e) {
                this.translationPrimary = previous;
                window.showToast('error', e.message);
            } finally { this.switching = false; }
        },

        init() { this.form = this.blank(); },
        blank() {
            return { id: null, name: '', modality: 'text', driver: 'ollama', base_url: '', api_key: '', model: '', temperature: '', max_tokens: '', timeout: '', is_default: false, enabled: true };
        },
        csrf() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; },
        providersFor(m) { return this.providers.filter(p => p.modality === m); },

        openCreate() { this.editing = null; this.form = this.blank(); this.showForm = true; },
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

        async save() {
            if (this.saving) return;
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
                    window.showToast && window.showToast('success', data.message || 'Saved');
                    this.showForm = false;
                    window.__adminShellRefresh && window.__adminShellRefresh();
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
                    window.showToast && window.showToast('success', data.message || 'Removed');
                    window.__adminShellRefresh && window.__adminShellRefresh();
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
