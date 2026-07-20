{{--
  Copilot ("Coach") — standalone, page-aware AI assistant widget.

  Thin slice: a floating button that opens a chat drawer where a super-admin
  creates a club conversationally (Ollama-backed, draft-then-confirm). Drop
  <x-copilot /> once into a layout (OUTSIDE the SPA-swapped <main>) — it is fully
  self-contained: own markup, own Alpine state, own request flow. No page glue.

  Depends only on approved shared foundations: Alpine, Tailwind tokens,
  window.showToast, and the CSRF meta tag.
--}}
@php($copilotContext = $context ?? 'create_club')
@php($copilotI18n = [
    'greeting' => __('copilot.greeting'),
    'changed' => __('copilot.changed'),
    'error_generic' => __('copilot.error_generic'),
    'error_create' => __('copilot.error_create'),
    'toast_created' => __('copilot.toast_created'),
    'created' => __('copilot.created'),
    'voice_unsupported' => __('copilot.voice_unsupported'),
    'mic_denied' => __('copilot.mic_denied'),
    'voice_empty' => __('copilot.voice_empty'),
])

<div x-data="copilotWidget(@js($copilotI18n))" x-cloak
     data-copilot-context="{{ $copilotContext }}"
     data-message-url="{{ route('admin.copilot.message') }}"
     data-apply-url="{{ route('admin.copilot.apply') }}"
     data-stt-url="{{ route('admin.copilot.stt') }}"
     data-tts-url="{{ route('admin.copilot.tts') }}">

    {{-- Floating action button --}}
    <button type="button" @click="toggle()"
            x-show="!isOpen"
            class="fixed bottom-6 end-6 z-[60] w-14 h-14 rounded-full bg-primary text-white shadow-lg
                   flex items-center justify-center hover:bg-primary/90 transition-all hover:scale-105"
            title="{{ __('copilot.fab_title') }}">
        <i class="bi bi-stars text-2xl"></i>
    </button>

    {{-- Backdrop (mobile) --}}
    <div x-show="isOpen" x-transition.opacity @click="close()"
         class="fixed inset-0 z-[60] bg-black/30 sm:hidden"></div>

    {{-- Drawer --}}
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-x-full opacity-0"
         x-transition:enter-end="translate-x-0 opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-x-0 opacity-100"
         x-transition:leave-end="translate-x-full opacity-0"
         class="fixed inset-y-0 end-0 z-[61] w-full sm:w-[400px] bg-white shadow-2xl flex flex-col">

        {{-- Header --}}
        <div class="flex-shrink-0 flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-primary text-white">
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                    <i class="bi bi-stars"></i>
                </span>
                <div>
                    <p class="font-semibold leading-tight">{{ __('copilot.header_title') }}</p>
                    <p class="text-[11px] text-white/70 leading-tight">{{ __('copilot.header_subtitle') }}</p>
                </div>
            </div>
            <button type="button" @click="close()" class="w-8 h-8 rounded-lg hover:bg-white/15 flex items-center justify-center">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        {{-- Messages --}}
        <div x-ref="scroll" class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-background">
            <template x-for="(m, i) in messages" :key="i">
                <div>
                    {{-- User bubble --}}
                    <template x-if="m.role === 'user'">
                        <div class="flex justify-end">
                            <div class="max-w-[85%] px-3 py-2 rounded-2xl rounded-br-sm bg-primary text-white text-sm" x-text="m.text"></div>
                        </div>
                    </template>

                    {{-- Assistant bubble --}}
                    <template x-if="m.role === 'assistant'">
                        <div class="flex flex-col items-start gap-2">
                            <div class="max-w-[85%] px-3 py-2 rounded-2xl rounded-bl-sm bg-white border border-gray-100 text-sm text-foreground whitespace-pre-line" x-text="m.text"></div>

                            {{-- Draft proposal card --}}
                            <template x-if="m.proposal && !m.created">
                                <div class="w-full bg-white rounded-xl border border-primary/30 shadow-sm p-3">
                                    <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">{{ __('copilot.draft_label') }}</p>
                                    <dl class="space-y-1 text-sm">
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-muted-foreground">{{ __('copilot.f_name') }}</dt>
                                            <dd class="font-medium text-foreground text-end" x-text="m.proposal.club_name"></dd>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-muted-foreground">{{ __('copilot.f_handle') }}</dt>
                                            <dd class="font-mono text-xs text-foreground text-end" x-text="m.proposal.slug"></dd>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-muted-foreground">{{ __('copilot.f_country') }}</dt>
                                            <dd class="font-medium text-foreground text-end" x-text="m.proposal.country"></dd>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-muted-foreground">{{ __('copilot.f_currency') }}</dt>
                                            <dd class="font-medium text-foreground text-end" x-text="m.proposal.currency"></dd>
                                        </div>
                                        <template x-if="m.proposal.slogan">
                                            <div class="flex justify-between gap-3">
                                                <dt class="text-muted-foreground">{{ __('copilot.f_slogan') }}</dt>
                                                <dd class="text-foreground text-end" x-text="m.proposal.slogan"></dd>
                                            </div>
                                        </template>
                                        <template x-if="m.proposal.email">
                                            <div class="flex justify-between gap-3">
                                                <dt class="text-muted-foreground">{{ __('copilot.f_email') }}</dt>
                                                <dd class="text-foreground text-end break-all" x-text="m.proposal.email"></dd>
                                            </div>
                                        </template>
                                        <template x-if="m.proposal.description">
                                            <div class="pt-1">
                                                <dt class="text-muted-foreground mb-0.5">{{ __('copilot.f_description') }}</dt>
                                                <dd class="text-foreground" x-text="m.proposal.description"></dd>
                                            </div>
                                        </template>
                                        <template x-if="m.proposal.has_requirements">
                                            <div class="flex justify-between gap-3">
                                                <dt class="text-muted-foreground">{{ __('copilot.f_requirements') }}</dt>
                                                <dd class="text-green-600 text-end"><i class="bi bi-check-circle-fill"></i> {{ __('copilot.generated') }}</dd>
                                            </div>
                                        </template>
                                        <template x-if="m.proposal.has_terms">
                                            <div class="flex justify-between gap-3">
                                                <dt class="text-muted-foreground">{{ __('copilot.f_terms') }}</dt>
                                                <dd class="text-green-600 text-end"><i class="bi bi-check-circle-fill"></i> {{ __('copilot.generated') }}</dd>
                                            </div>
                                        </template>
                                    </dl>
                                    <div class="flex gap-2 mt-3">
                                        <button type="button" @click="createClub(m)" :disabled="m.creating"
                                                class="flex-1 bg-primary text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors disabled:opacity-60 flex items-center justify-center gap-2">
                                            <span x-show="!m.creating"><i class="bi bi-check-lg"></i> {{ __('copilot.create') }}</span>
                                            <span x-show="m.creating" class="flex items-center gap-2">
                                                <i class="bi bi-arrow-repeat animate-spin"></i> {{ __('copilot.creating') }}
                                            </span>
                                        </button>
                                        <button type="button" @click="dismissProposal(m)"
                                                class="px-3 py-2 rounded-lg text-sm font-medium border border-gray-200 text-muted-foreground hover:bg-muted transition-colors">
                                            {{ __('copilot.cancel') }}
                                        </button>
                                    </div>
                                </div>
                            </template>

                            {{-- Created confirmation --}}
                            <template x-if="m.created">
                                <div class="w-full bg-green-50 rounded-xl border border-green-200 p-3">
                                    <p class="text-sm font-medium text-green-700 flex items-center gap-2">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span x-text="i18n.created.replace(':name', m.created.club_name)"></span>
                                    </p>
                                    <a :href="m.created.dashboard_url"
                                       class="inline-flex items-center gap-1 mt-2 text-sm font-medium text-primary hover:underline">
                                        {{ __('copilot.open_club') }} <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Thinking indicator --}}
            <div x-show="loading" class="flex items-center gap-2 text-muted-foreground text-sm">
                <i class="bi bi-stars"></i>
                <span class="flex gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary/60 animate-bounce" style="animation-delay:0ms"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-primary/60 animate-bounce" style="animation-delay:120ms"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-primary/60 animate-bounce" style="animation-delay:240ms"></span>
                </span>
            </div>
        </div>

        {{-- Composer --}}
        <div class="flex-shrink-0 border-t border-gray-100 p-3"
             style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            {{-- Transcribing hint --}}
            <div x-show="transcribing" x-cloak class="flex items-center gap-2 text-xs text-muted-foreground mb-2">
                <i class="bi bi-arrow-repeat animate-spin"></i> <span>{{ __('copilot.transcribing') }}</span>
            </div>
            <div class="flex items-end gap-2">
                {{-- Speaker toggle: auto-speak Coach's replies --}}
                <button type="button" @click="toggleSpeak()"
                        :class="speak ? 'bg-accent text-primary border-primary/30' : 'bg-white text-muted-foreground border-gray-200'"
                        class="w-10 h-10 flex-shrink-0 rounded-lg border flex items-center justify-center hover:bg-accent transition-colors"
                        :title="speak ? '{{ __('copilot.voice_on') }}' : '{{ __('copilot.voice_off') }}'">
                    <i class="bi" :class="speak ? 'bi-volume-up-fill' : 'bi-volume-mute'"></i>
                </button>
                {{-- Mic: hold-free tap to record, tap again to stop & transcribe --}}
                <button type="button" @click="toggleMic()" :disabled="loading || transcribing"
                        :class="recording ? 'bg-red-500 text-white border-red-500 animate-pulse' : 'bg-white text-muted-foreground border-gray-200'"
                        class="w-10 h-10 flex-shrink-0 rounded-lg border flex items-center justify-center hover:bg-accent transition-colors disabled:opacity-50"
                        :title="recording ? '{{ __('copilot.stop_recording') }}' : '{{ __('copilot.start_recording') }}'">
                    <i class="bi" :class="recording ? 'bi-stop-fill' : 'bi-mic-fill'"></i>
                </button>
                <textarea x-ref="input" x-model="input" @keydown.enter.prevent="send()" :disabled="loading"
                          rows="1" placeholder="{{ __('copilot.placeholder') }}"
                          class="flex-1 resize-none px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent max-h-28"></textarea>
                <button type="button" @click="send()" :disabled="loading || !input.trim()"
                        class="w-10 h-10 flex-shrink-0 rounded-lg bg-primary text-white flex items-center justify-center hover:bg-primary/90 transition-colors disabled:opacity-50">
                    <i class="bi bi-send"></i>
                </button>
            </div>
        </div>
    </div>
</div>

@once
<script>
window.copilotWidget = function (i18n) {
    return {
        isOpen: false,
        greeted: false,
        loading: false,
        input: '',
        messages: [],
        context: 'create_club',
        messageUrl: '',
        applyUrl: '',
        sttUrl: '',
        ttsUrl: '',
        i18n: i18n || {},
        // Voice conversation
        speak: false,          // auto-speak Coach's replies
        recording: false,
        transcribing: false,
        _recorder: null,
        _chunks: [],
        _audio: null,

        t(key) {
            return this.i18n[key] || key;
        },

        init() {
            this.context = this.$root.dataset.copilotContext || 'create_club';
            this.messageUrl = this.$root.dataset.messageUrl;
            this.applyUrl = this.$root.dataset.applyUrl;
            this.sttUrl = this.$root.dataset.sttUrl;
            this.ttsUrl = this.$root.dataset.ttsUrl;
            // Let any page control open Coach (e.g. the club-modal header button).
            window.openCopilot = () => this.open();
            window.addEventListener('copilot:open', this._openHandler = () => this.open());
        },

        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },

        toggle() { this.isOpen ? this.close() : this.open(); },

        open() {
            this.isOpen = true;
            if (!this.greeted) {
                this.messages.push({ role: 'assistant', text: this.t('greeting') });
                this.greeted = true;
            }
            this.$nextTick(() => this.$refs.input?.focus());
            this.scrollDown();
        },

        close() { this.isOpen = false; },

        scrollDown() {
            this.$nextTick(() => {
                const el = this.$refs.scroll;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        history() {
            return this.messages
                .filter(m => (m.role === 'user' || m.role === 'assistant') && m.text)
                .map(m => ({ role: m.role, content: m.text }));
        },

        async send() {
            const text = this.input.trim();
            if (!text || this.loading) return;

            this.messages.push({ role: 'user', text });
            this.input = '';
            this.loading = true;
            this.scrollDown();

            try {
                const res = await fetch(this.messageUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                    },
                    body: JSON.stringify({ context: this.context, messages: this.history() }),
                });
                const data = await res.json();
                this.messages.push({
                    role: 'assistant',
                    text: data.reply || '…',
                    proposal: data.proposal || null,
                    token: data.token || null,
                });
                if (this.speak && data.reply) this.speakText(data.reply);
            } catch (e) {
                this.messages.push({ role: 'assistant', text: this.t('error_generic') });
            } finally {
                this.loading = false;
                this.scrollDown();
                // The textarea is :disabled while loading, which blurs it — restore focus.
                this.$nextTick(() => this.$refs.input?.focus());
            }
        },

        // ── Voice conversation (Gemini STT + TTS) ──
        async toggleMic() {
            if (this.recording) { this.stopRecording(); return; }
            if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
                window.showToast && window.showToast('error', this.t('voice_unsupported'));
                return;
            }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this._chunks = [];
                const mr = new MediaRecorder(stream);
                this._recorder = mr;
                mr.ondataavailable = e => { if (e.data && e.data.size) this._chunks.push(e.data); };
                mr.onstop = () => {
                    stream.getTracks().forEach(t => t.stop());
                    this.sendAudio(new Blob(this._chunks, { type: mr.mimeType || 'audio/webm' }));
                };
                mr.start();
                this.recording = true;
            } catch (e) {
                window.showToast && window.showToast('error', this.t('mic_denied'));
            }
        },
        stopRecording() {
            this.recording = false;
            try { if (this._recorder && this._recorder.state !== 'inactive') this._recorder.stop(); } catch (e) {}
        },
        async sendAudio(blob) {
            if (!blob || !blob.size) return;
            this.transcribing = true;
            this.scrollDown();
            try {
                const fd = new FormData();
                fd.append('audio', blob, 'clip.webm');
                const res = await fetch(this.sttUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' }, body: fd });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || this.t('error_generic'));
                const text = (d.text || '').trim();
                if (text) { this.input = text; this.send(); }
                else window.showToast && window.showToast('info', this.t('voice_empty'));
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally {
                this.transcribing = false;
            }
        },
        async speakText(text) {
            try {
                if (this._audio) { this._audio.pause(); this._audio = null; }
                const res = await fetch(this.ttsUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf() }, body: JSON.stringify({ text: String(text).slice(0, 5000) }) });
                if (!res.ok) return; // voice is best-effort — never block the chat
                const url = URL.createObjectURL(await res.blob());
                this._audio = new Audio(url);
                this._audio.onended = () => URL.revokeObjectURL(url);
                this._audio.play().catch(() => {});
            } catch (e) {}
        },
        toggleSpeak() {
            this.speak = !this.speak;
            if (!this.speak && this._audio) { this._audio.pause(); this._audio = null; }
        },

        async createClub(m) {
            if (m.creating || m.created || !m.token) return;
            m.creating = true;
            try {
                const res = await fetch(this.applyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                    },
                    body: JSON.stringify({ token: m.token }),
                });
                const data = await res.json();
                if (data.success) {
                    m.created = data.club;
                    m.token = null;
                    window.showToast && window.showToast('success', this.t('toast_created').replace(':name', data.club.club_name));
                    // If the manual create modal was open, close it, then open the new club.
                    window.dispatchEvent(new CustomEvent('close-club-modal'));
                    setTimeout(() => { window.location.href = data.club.dashboard_url; }, 900);
                } else {
                    window.showToast && window.showToast('error', data.message || this.t('error_create'));
                }
            } catch (e) {
                window.showToast && window.showToast('error', this.t('error_create'));
            } finally {
                m.creating = false;
                this.scrollDown();
            }
        },

        dismissProposal(m) {
            m.proposal = null;
            m.token = null;
            this.messages.push({ role: 'assistant', text: this.t('changed') });
            this.scrollDown();
            this.$nextTick(() => this.$refs.input?.focus());
        },
    };
};
</script>
@endonce
