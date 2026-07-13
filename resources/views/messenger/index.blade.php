@extends('layouts.app')

@section('title', __('messenger.messenger_index_title'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4" x-data="messenger()" x-init="init()">

    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('messenger.messenger_index_title') }}</h1>
            <p class="text-sm text-muted-foreground" x-text="connected ? '{{ __('messenger.messenger_index_status_connected') }}' : '{{ __('messenger.messenger_index_status_instant') }}'"></p>
        </div>
        <span class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
            <i class="bi bi-shield-lock"></i> {{ __('messenger.messenger_index_encrypted') }}
        </span>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden grid grid-cols-1 lg:grid-cols-3"
         style="height: calc(100vh - 11rem); min-height: 480px;">

        {{-- ── LEFT: conversations + new chat ── --}}
        <aside class="lg:col-span-1 border-r border-gray-100 flex flex-col min-h-0"
               :class="{ 'hidden lg:flex': activeId !== null }">
            <div class="p-3 border-b border-gray-100 relative">
                <div class="relative">
                    <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 w-4 text-gray-400"></i>
                    <input type="text" x-model="searchTerm" @input.debounce.300ms="searchUsers()"
                           placeholder="{{ __('messenger.messenger_index_search_placeholder') }}"
                           class="w-full ps-9 pe-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                </div>
                {{-- search results dropdown --}}
                <div x-show="searchResults.length > 0" x-cloak
                     class="absolute start-3 end-3 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-20 max-h-72 overflow-y-auto">
                    <template x-for="u in searchResults" :key="u.id">
                        <button type="button" @click="startWith(u)"
                                class="w-full flex items-center gap-3 p-2.5 hover:bg-muted/50 text-start">
                            <template x-if="u.avatar"><img :src="u.avatar" class="w-9 h-9 rounded-full object-cover shrink-0" alt=""></template>
                            <template x-if="!u.avatar"><span class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center text-sm font-bold shrink-0" x-text="u.initial"></span></template>
                            <span class="text-sm font-medium text-foreground truncate" x-text="u.name"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto" id="conv-list">
                @forelse($conversations as $c)
                    <button type="button"
                            class="conv-row w-full flex items-center gap-3 p-3 border-b border-gray-50 hover:bg-muted/50 transition-colors text-start"
                            data-conv-id="{{ $c->id }}"
                            data-name="{{ $c->partner['name'] }}"
                            data-avatar="{{ $c->partner['avatar'] }}"
                            data-initial="{{ $c->partner['initial'] }}"
                            @click="openConversation({{ $c->id }})">
                        @if($c->partner['avatar'])
                            <img src="{{ $c->partner['avatar'] }}" class="w-11 h-11 rounded-full object-cover shrink-0" alt="">
                        @else
                            <span class="w-11 h-11 rounded-full bg-primary text-white flex items-center justify-center font-bold shrink-0">{{ $c->partner['initial'] }}</span>
                        @endif
                        <span class="flex-1 min-w-0">
                            <span class="flex justify-between items-center">
                                <span class="font-semibold text-sm truncate">{{ $c->partner['name'] }}</span>
                                <span class="text-[11px] text-muted-foreground shrink-0 me-2 conv-time">{{ $c->last_at_human }}</span>
                            </span>
                            <span class="block text-xs text-muted-foreground truncate conv-preview">{{ $c->last_mine ? __('messenger.messenger_index_you_prefix') : '' }}{{ $c->last_body }}</span>
                        </span>
                        <span class="shrink-0 min-w-[18px] h-[18px] px-1 rounded-full bg-primary text-white text-[10px] font-bold flex items-center justify-center conv-unread {{ $c->unread_count > 0 ? '' : 'hidden' }}">{{ $c->unread_count }}</span>
                    </button>
                @empty
                @endforelse
                <div id="conv-empty" class="text-center py-16 px-4 {{ count($conversations) ? 'hidden' : '' }}">
                    <i class="bi bi-chat-heart text-4xl text-gray-300"></i>
                    <p class="text-sm text-muted-foreground mt-2">{{ __('messenger.messenger_index_no_conversations_1') }}<br>{{ __('messenger.messenger_index_no_conversations_2') }}</p>
                </div>
            </div>
        </aside>

        {{-- ── RIGHT: active thread ── --}}
        <section class="lg:col-span-2 flex flex-col min-h-0" :class="{ 'hidden lg:flex': activeId === null }">
            {{-- empty state --}}
            <div class="flex-1 flex flex-col items-center justify-center text-center p-8" x-show="activeId === null">
                <i class="bi bi-chat-square-text text-6xl text-gray-200"></i>
                <h5 class="mt-3 font-semibold text-gray-700">{{ __('messenger.messenger_index_your_messages') }}</h5>
                <p class="text-sm text-muted-foreground">{{ __('messenger.messenger_index_empty_hint') }}</p>
            </div>

            {{-- thread --}}
            <template x-if="activeId !== null">
                <div class="flex flex-col h-full min-h-0">
                    <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-100">
                        <button type="button" class="lg:hidden p-1 -ms-1" @click="closeThread()"><i class="bi bi-arrow-left text-xl"></i></button>
                        <template x-if="partner.avatar"><img :src="partner.avatar" class="w-10 h-10 rounded-full object-cover" alt=""></template>
                        <template x-if="!partner.avatar"><span class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold" x-text="partner.initial"></span></template>
                        <div class="min-w-0">
                            <p class="font-semibold text-sm truncate mb-0" x-text="partner.name"></p>
                            <p class="text-[11px] text-muted-foreground mb-0" x-text="connected ? '{{ __('messenger.messenger_index_active_now') }}' : ''"></p>
                        </div>
                        <div class="ms-auto relative" x-data="{ o: false }">
                            <button type="button" @click="o = !o" class="w-9 h-9 rounded-lg flex items-center justify-center text-muted-foreground hover:bg-muted" aria-label="{{ __('messenger.messenger_index_chat_options') }}"><i class="bi bi-three-dots-vertical"></i></button>
                            <div x-show="o" x-cloak @click.outside="o = false"
                                 x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                 class="absolute end-0 top-11 z-30 w-44 bg-white rounded-xl shadow-lg ring-1 ring-black/5 border border-border py-1">
                                <a x-show="partner.uuid" :href="partner.uuid ? ('/member/' + partner.uuid) : '#'" @click="o = false" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-foreground hover:bg-muted text-start no-underline"><i class="bi bi-person-circle"></i> {{ __('messenger.messenger_index_view_profile') }}</a>
                                <button type="button" @click="o = false; deleteChat(activeId)" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50 text-start"><i class="bi bi-trash"></i> {{ __('messenger.messenger_index_delete_chat') }}</button>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-1.5 bg-muted/20" id="thread-scroll">
                        <template x-if="loadingThread"><div class="text-center text-muted-foreground text-sm py-8"><i class="bi bi-arrow-repeat"></i> {{ __('shared.loading') }}</div></template>
                        <template x-for="m in messages" :key="m.id">
                            <div class="flex group" :class="m.mine ? 'justify-end' : 'justify-start'">
                                <div class="relative max-w-[72%]">
                                    <div class="rounded-2xl shadow-sm overflow-hidden"
                                         :class="(m.deleted
                                                    ? 'bg-muted text-muted-foreground border border-border ' + (m.mine ? 'rounded-br-md' : 'rounded-bl-md')
                                                    : (m.mine ? 'bg-primary text-white rounded-br-md' : 'bg-white text-foreground border border-border rounded-bl-md'))
                                                 + ((m.kind === 'image' || m.kind === 'video') && m.attachment && !m.deleted ? ' p-1' : ' px-3.5 py-2 text-sm')
                                                 + (m.pending ? ' opacity-70' : '')">
                                        <p x-show="m.deleted" class="mb-0 italic flex items-center gap-1.5"><i class="bi bi-slash-circle"></i> {{ __('messenger.messenger_index_message_deleted') }}</p>
                                        {{-- image attachment --}}
                                        <template x-if="!m.deleted && m.kind === 'image' && m.attachment">
                                            <a :href="m.attachment.url" target="_blank" rel="noopener" class="block">
                                                <img :src="m.attachment.url" :alt="m.attachment.name" loading="lazy" class="rounded-xl block" style="max-height:260px; max-width:100%;">
                                            </a>
                                        </template>
                                        {{-- video attachment --}}
                                        <template x-if="!m.deleted && m.kind === 'video' && m.attachment">
                                            <video :src="m.attachment.url" controls preload="metadata" playsinline class="rounded-xl block max-w-full" style="max-height:280px"></video>
                                        </template>
                                        {{-- audio attachment (mini player + download) --}}
                                        <template x-if="!m.deleted && m.kind === 'audio' && m.attachment">
                                            <div class="flex items-center gap-2">
                                                <audio :src="m.attachment.url" controls preload="metadata" class="h-10 max-w-full" style="width:300px"></audio>
                                                <a :href="m.attachment.url" :download="m.attachment.name" class="shrink-0 w-8 h-8 rounded-full bg-black/10 hover:bg-black/20 flex items-center justify-center" title="{{ __('messenger.messenger_index_download') }}"><i class="bi bi-download"></i></a>
                                            </div>
                                        </template>
                                        {{-- file attachment --}}
                                        <template x-if="!m.deleted && m.kind === 'file' && m.attachment">
                                            <a :href="m.attachment.url" :download="m.attachment.name" class="flex items-center gap-2.5">
                                                <span class="w-9 h-9 rounded-lg bg-black/10 flex items-center justify-center shrink-0"><i class="bi bi-file-earmark-arrow-down text-lg"></i></span>
                                                <span class="min-w-0">
                                                    <span class="block truncate font-medium" x-text="m.attachment.name"></span>
                                                    <span class="block text-[11px] opacity-70" x-text="window.ChatAttach.humanSize(m.attachment.size)"></span>
                                                </span>
                                            </a>
                                        </template>
                                        {{-- expired attachment --}}
                                        <p x-show="!m.deleted && m.kind && !m.attachment" class="mb-0 italic flex items-center gap-1.5"><i class="bi bi-clock-history"></i> {{ __('messenger.messenger_index_attachment_expired') }}</p>
                                        {{-- text + link preview --}}
                                        <template x-if="!m.deleted && !m.kind">
                                            <div>
                                                <p class="mb-0 whitespace-pre-wrap break-words" x-html="window.LinkPreview.linkifyHtml(m.body)"></p>
                                                <div x-data="linkCard()" x-init="load(m.body)" x-show="preview" x-cloak>
                                                    <template x-if="preview && preview.type === 'video_embed'">
                                                        <div class="mt-1.5 rounded-lg overflow-hidden bg-black/20" style="aspect-ratio:16/9; width:260px; max-width:100%">
                                                            <iframe :src="preview.embed" class="w-full h-full" style="border:0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                                                        </div>
                                                    </template>
                                                    <template x-if="preview && preview.type === 'link'">
                                                        <a :href="preview.url" target="_blank" rel="noopener noreferrer" class="mt-1.5 block rounded-lg overflow-hidden border border-black/10 bg-black/5 max-w-[280px]">
                                                            <template x-if="preview.image"><img :src="preview.image" class="w-full max-h-36 object-cover" loading="lazy" alt=""></template>
                                                            <div class="p-2">
                                                                <p class="text-[11px] opacity-70 truncate" x-show="preview.site" x-text="preview.site"></p>
                                                                <p class="text-[13px] font-semibold leading-snug line-clamp-2" x-show="preview.title" x-text="preview.title"></p>
                                                                <p class="text-[11px] opacity-80 line-clamp-2 mt-0.5" x-show="preview.description" x-text="preview.description"></p>
                                                            </div>
                                                        </a>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                        <span class="block text-[10px] opacity-70" :class="((m.kind === 'image' || m.kind === 'video') && m.attachment && !m.deleted) ? 'px-1.5 pt-0.5 pb-1' : 'mt-0.5'">
                                            <span x-show="m.edited">edited · </span><span x-text="m.time"></span>
                                            <i x-show="m.pending" class="bi bi-clock ms-0.5"></i>
                                        </span>
                                    </div>
                                    {{-- action trigger (text messages only) --}}
                                    <button type="button" x-show="!m.deleted && !m.kind" @click="toggleMenu(m.id)"
                                            class="absolute -top-1 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity w-6 h-6 rounded-full bg-white border border-border shadow-sm flex items-center justify-center text-muted-foreground hover:text-foreground"
                                            :class="m.mine ? '-start-7' : '-end-7'" aria-label="{{ __('messenger.messenger_index_message_actions') }}">
                                        <i class="bi bi-three-dots text-xs"></i>
                                    </button>
                                    {{-- context menu --}}
                                    <div x-show="menuId === m.id" x-cloak @click.outside="menuId = null"
                                         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                         class="absolute z-30 bottom-full mb-1 w-max min-w-[10rem] bg-white rounded-xl shadow-lg ring-1 ring-black/5 border border-border overflow-hidden py-1"
                                         :class="m.mine ? 'end-0' : 'start-0'">
                                        <button type="button" @click="copyMsg(m)" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-foreground hover:bg-muted/60 text-start"><i class="bi bi-clipboard"></i> {{ __('messenger.messenger_index_copy') }}</button>
                                        <template x-if="m.can_edit">
                                            <button type="button" @click="startEdit(m)" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-foreground hover:bg-muted/60 text-start"><i class="bi bi-pencil"></i> {{ __('shared.edit') }}</button>
                                        </template>
                                        <button type="button" @click="deleteForMe(m)" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-foreground hover:bg-muted/60 text-start"><i class="bi bi-eye-slash"></i> {{ __('messenger.messenger_index_delete_for_me') }}</button>
                                        <template x-if="m.mine && !m.deleted">
                                            <button type="button" @click="deleteMsg(m)" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50 text-start"><i class="bi bi-trash"></i> {{ __('messenger.messenger_index_delete_for_everyone') }}</button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Editing banner --}}
                    <div x-show="editing" x-cloak class="flex items-center gap-2 px-3 pt-2 -mb-1 text-xs text-primary">
                        <i class="bi bi-pencil-square"></i>
                        <span class="font-medium">{{ __('messenger.messenger_index_editing_message') }}</span>
                        <button type="button" @click="cancelEdit()" class="ms-auto text-muted-foreground hover:text-foreground"><i class="bi bi-x-lg"></i></button>
                    </div>

                    <form class="flex items-end gap-2 px-3 py-3 border-t border-gray-100" @submit.prevent="editing ? saveEdit() : send()">
                        <input type="file" x-ref="fileInput" class="hidden" @change="attachFile($event)">
                        <button type="button" @click="$refs.fileInput.click()" :disabled="editing"
                                class="w-10 h-10 shrink-0 rounded-full text-muted-foreground hover:bg-muted flex items-center justify-center transition-colors disabled:opacity-40" aria-label="{{ __('messenger.messenger_index_attach_file') }}">
                            <i class="bi bi-paperclip text-lg"></i>
                        </button>
                        <textarea x-ref="composer" x-model="draft" rows="1" placeholder="{{ __('messenger.messenger_index_composer_placeholder') }}" @keydown.enter.prevent="editing ? saveEdit() : send()" @keydown.escape="cancelEdit()"
                                  class="flex-1 resize-none px-3.5 py-2.5 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm" style="max-height:120px;"></textarea>
                        <button type="submit" class="w-10 h-10 shrink-0 rounded-full bg-primary text-white flex items-center justify-center hover:bg-primary/90 transition-colors disabled:opacity-50" :disabled="sending || !draft.trim()">
                            <i class="bi text-sm" :class="editing ? 'bi-check-lg' : 'bi-send-fill'"></i>
                        </button>
                    </form>
                </div>
            </template>
        </section>
    </div>
</div>

@push('scripts')
<script>
function messenger() {
    return {
        activeId: null,
        partner: { name: '', avatar: null, initial: '' },
        messages: [], loadingThread: false, draft: '', sending: false, connected: false,
        searchTerm: '', searchResults: [],
        menuId: null, editing: false, editingId: null,

        urls: {
            search: '{{ route('messages.search-users') }}',
            startBase: '{{ url('messages/start') }}',
            base: '{{ url('messages') }}',
        },

        init() {
            window.addEventListener('realtime:status', (e) => this.connected = !!e.detail.connected);
            window.addEventListener('realtime:message', (e) => this.onIncoming(e.detail || {}));
            const pre = @json($openConversation ?? null);
            if (pre) this.openConversation(pre);
        },

        async searchUsers() {
            const q = this.searchTerm.trim();
            if (!q) { this.searchResults = []; return; }
            try {
                const r = await fetch(`${this.urls.search}?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.searchResults = d.users || [];
            } catch (e) { this.searchResults = []; }
        },

        async startWith(user) {
            this.searchResults = []; this.searchTerm = '';
            try {
                const r = await fetch(`${this.urls.startBase}/${user.id}`, { method: 'POST', headers: this.headers() });
                const d = await r.json();
                if (d.success) {
                    this.ensureRow(d.conversation_id, user);
                    this.openConversation(d.conversation_id);
                }
            } catch (e) { window.showToast('error', '{{ __("messenger.messenger_index_err_start_chat") }}'); }
        },

        async openConversation(id) {
            this.activeId = id;
            this.loadingThread = true;
            this.messages = [];
            const row = document.querySelector(`.conv-row[data-conv-id="${id}"]`);
            this.partner = {
                name: row?.dataset.name || '{{ __("messenger.messenger_index_user_fallback") }}',
                avatar: row?.dataset.avatar || null,
                initial: row?.dataset.initial || (row?.dataset.name || 'U').charAt(0).toUpperCase(),
            };
            // Clearing this conversation's unread also reduces the header total.
            const badge = row?.querySelector('.conv-unread');
            const had = (badge && !badge.classList.contains('hidden')) ? (parseInt(badge.textContent) || 0) : 0;
            this.clearUnread(id);
            if (had) this.bumpHeaderBadge(-had);
            try {
                const r = await fetch(`${this.urls.base}/${id}/thread`, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                if (d.success) { this.partner = d.partner; this.messages = d.messages; this.scrollDown(); }
            } catch (e) { window.showToast('error', '{{ __("messenger.messenger_index_err_load_conversation") }}'); }
            finally { this.loadingThread = false; }
        },

        closeThread() { this.activeId = null; },

        async send() {
            const body = this.draft.trim();
            if (!body || this.sending) return;
            this.sending = true;
            try {
                const r = await fetch(`${this.urls.base}/${this.activeId}/send`, { method: 'POST', headers: this.headers(), body: JSON.stringify({ body }) });
                const d = await r.json();
                if (d.success) { this.messages.push(d.data); this.draft = ''; this.scrollDown(); this.updateRow(this.activeId, body, true); }
            } catch (e) { window.showToast('error', '{{ __("messenger.messenger_index_err_send") }}'); }
            finally { this.sending = false; }
        },

        onIncoming(detail) {
            if (!detail.conversation_id) return;
            const id = detail.conversation_id;

            // Edit / delete of a message I can see — patch in place, stay silent.
            if (detail.action === 'edit' || detail.action === 'delete') {
                if (this.activeId === id) this.applyRemoteChange(detail);
                if (detail.is_latest) {
                    this.updateRow(id, detail.action === 'delete' ? '{{ __("messenger.messenger_index_message_deleted") }}' : (detail.body || ''), false);
                }
                return;
            }

            this.ensureRow(id, { id: detail.from_id, name: detail.from_name, avatar: detail.from_avatar, initial: (detail.from_name||'U').charAt(0).toUpperCase() });

            // Incoming ephemeral attachment (picture / file).
            if (detail.action === 'file') {
                if (this.activeId === id) {
                    this.messages.push({ id: detail.id, mine: false, time: detail.time || '', kind: detail.kind, attachment: detail.attachment });
                    this.scrollDown(); this.markRead(id);
                } else { this.bumpUnread(id); this.bumpHeaderBadge(1); }
                this.updateRow(id, detail.body || (detail.kind === 'image' ? '{{ __("messenger.messenger_index_photo") }}' : '📎 ' + (detail.attachment && detail.attachment.name || '')), false);
                if (window.playMessageSound) window.playMessageSound();
                return;
            }

            if (this.activeId === id) {
                this.messages.push({ id: detail.id, body: detail.body, mine: false, time: detail.time || '' });
                this.scrollDown();
                this.markRead(id);
            } else {
                this.bumpUnread(id);
                this.bumpHeaderBadge(1);
            }
            this.updateRow(id, detail.body, false);
            if (window.playMessageSound) window.playMessageSound();
        },

        applyRemoteChange(detail) {
            const m = this.messages.find((x) => x.id === detail.id);
            if (!m) return;
            if (detail.action === 'delete') { m.deleted = true; m.body = null; m.can_edit = false; }
            else { m.body = detail.body; m.edited = true; }
        },

        async attachFile(e) {
            const file = e.target.files && e.target.files[0];
            e.target.value = '';
            if (!file || !this.activeId) return;
            const convId = this.activeId;
            // Optimistic bubble: show it instantly (local preview) while uploading.
            const t = file.type || '';
            const kind = t.startsWith('image/') ? 'image' : (t.startsWith('audio/') ? 'audio' : (t.startsWith('video/') ? 'video' : 'file'));
            const tempId = 'tmp-att-' + Date.now();
            const previewUrl = kind !== 'file' ? URL.createObjectURL(file) : null;
            this.messages.push({ id: tempId, mine: true, time: '', kind, attachment: { url: previewUrl, name: file.name, size: file.size }, pending: true });
            this.scrollDown();
            const att = await window.ChatAttach.send(convId, file);
            const i = this.messages.findIndex((m) => m.id === tempId);
            if (!att) { if (i > -1) this.messages.splice(i, 1); if (previewUrl) URL.revokeObjectURL(previewUrl); return; }
            if (i > -1) this.messages.splice(i, 1, att); else this.messages.push(att);
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            this.scrollDown();
            this.updateRow(convId, att.kind === 'image' ? '{{ __("messenger.messenger_index_photo") }}' : '📎 ' + (att.attachment && att.attachment.name || ''), true);
        },

        async deleteChat(id) {
            const ok = await window.confirmAction({ title: '{{ __("messenger.messenger_index_delete_chat_title") }}', message: '{{ __("messenger.messenger_index_delete_chat_message") }}', type: 'danger', confirmText: '{{ __("shared.delete") }}' });
            if (!ok) return;
            try {
                const r = await fetch(`${this.urls.base}/${id}`, { method: 'DELETE', headers: this.headers() });
                const d = await r.json();
                if (!d.success) { window.showToast('error', '{{ __("messenger.messenger_index_err_delete_chat") }}'); return; }
                document.querySelector(`.conv-row[data-conv-id="${id}"]`)?.remove();
                if (this.activeId === id) this.activeId = null;
                window.showToast('success', '{{ __("messenger.messenger_index_chat_deleted") }}');
            } catch (e) { window.showToast('error', '{{ __("messenger.messenger_index_err_delete_chat") }}'); }
        },

        /* ── message actions (edit / delete / copy) ── */
        toggleMenu(id) { this.menuId = this.menuId === id ? null : id; },

        copyMsg(m) {
            this.menuId = null;
            navigator.clipboard?.writeText(m.body || '').then(
                () => window.showToast('success', '{{ __("messenger.messenger_index_message_copied") }}'),
                () => window.showToast('error', '{{ __("messenger.messenger_index_err_copy") }}'),
            );
        },

        startEdit(m) {
            this.menuId = null;
            this.editing = true; this.editingId = m.id; this.draft = m.body || '';
            this.$nextTick(() => this.$refs.composer?.focus());
        },
        cancelEdit() { this.editing = false; this.editingId = null; this.draft = ''; },

        async saveEdit() {
            const body = this.draft.trim();
            if (!body || this.sending) return;
            this.sending = true;
            try {
                const r = await fetch(`${this.urls.base}/${this.activeId}/messages/${this.editingId}`, { method: 'PATCH', headers: this.headers(), body: JSON.stringify({ body }) });
                const d = await r.json();
                if (!d.success) { window.showToast('error', d.message || '{{ __("messenger.messenger_index_err_edit") }}'); return; }
                const m = this.messages.find((x) => x.id === this.editingId);
                if (m) { m.body = body; m.edited = true; }
                if (d.is_latest) this.updateRow(this.activeId, body, true);
                this.cancelEdit();
            } catch (e) { window.showToast('error', '{{ __("messenger.messenger_index_err_edit") }}'); }
            finally { this.sending = false; }
        },

        async deleteMsg(m) {
            this.menuId = null;
            const ok = await window.confirmAction({ title: '{{ __("messenger.messenger_index_delete_everyone_title") }}', message: '{{ __("messenger.messenger_index_delete_everyone_message") }}', type: 'danger', confirmText: '{{ __("shared.delete") }}' });
            if (!ok) return;
            try {
                const r = await fetch(`${this.urls.base}/${this.activeId}/messages/${m.id}`, { method: 'DELETE', headers: this.headers() });
                const d = await r.json();
                if (!d.success) { window.showToast('error', '{{ __("messenger.messenger_index_err_delete") }}'); return; }
                m.deleted = true; m.body = null; m.can_edit = false;
                if (this.editingId === m.id) this.cancelEdit();
                if (d.is_latest) this.updateRow(this.activeId, '{{ __("messenger.messenger_index_message_deleted") }}', true);
            } catch (e) { window.showToast('error', '{{ __("messenger.messenger_index_err_delete") }}'); }
        },

        async deleteForMe(m) {
            this.menuId = null;
            const ok = await window.confirmAction({ title: '{{ __("messenger.messenger_index_delete_me_title") }}', message: '{{ __("messenger.messenger_index_delete_me_message") }}', type: 'danger', confirmText: '{{ __("shared.delete") }}' });
            if (!ok) return;
            try {
                const r = await fetch(`${this.urls.base}/${this.activeId}/messages/${m.id}/hide`, { method: 'POST', headers: this.headers() });
                const d = await r.json();
                if (!d.success) { window.showToast('error', '{{ __("messenger.messenger_index_err_delete") }}'); return; }
                const i = this.messages.findIndex((x) => x.id === m.id);
                if (i > -1) this.messages.splice(i, 1);
                if (this.editingId === m.id) this.cancelEdit();
            } catch (e) { window.showToast('error', '{{ __("messenger.messenger_index_err_delete") }}'); }
        },

        ensureRow(id, user) {
            if (document.querySelector(`.conv-row[data-conv-id="${id}"]`)) return;
            document.getElementById('conv-empty')?.classList.add('hidden');
            const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
            const name = user.name || '{{ __("messenger.messenger_index_user_fallback") }}';
            const initial = esc((name).charAt(0).toUpperCase());
            const list = document.getElementById('conv-list');
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'conv-row w-full flex items-center gap-3 p-3 border-b border-gray-50 hover:bg-muted/50 transition-colors text-start';
            b.dataset.convId = id; b.dataset.name = name; b.dataset.avatar = user.avatar || ''; b.dataset.initial = initial;
            b.innerHTML =
                (user.avatar ? `<img src="${esc(user.avatar)}" class="w-11 h-11 rounded-full object-cover shrink-0" alt="">`
                             : `<span class="w-11 h-11 rounded-full bg-primary text-white flex items-center justify-center font-bold shrink-0">${initial}</span>`) +
                '<span class="flex-1 min-w-0"><span class="flex justify-between items-center">' +
                `<span class="font-semibold text-sm truncate">${esc(name)}</span><span class="text-[11px] text-muted-foreground shrink-0 me-2 conv-time">{{ __("messenger.messenger_index_now") }}</span></span>` +
                '<span class="block text-xs text-muted-foreground truncate conv-preview"></span></span>' +
                '<span class="shrink-0 min-w-[18px] h-[18px] px-1 rounded-full bg-primary text-white text-[10px] font-bold flex items-center justify-center conv-unread hidden">0</span>';
            b.addEventListener('click', () => this.openConversation(id));
            list.insertBefore(b, list.firstChild);
        },

        updateRow(id, text, mine) {
            const row = document.querySelector(`.conv-row[data-conv-id="${id}"]`);
            if (!row) return;
            const p = row.querySelector('.conv-preview'); if (p) p.textContent = (mine ? '{{ __("messenger.messenger_index_you_prefix") }}' : '') + text;
            const t = row.querySelector('.conv-time'); if (t) t.textContent = '{{ __("messenger.messenger_index_now") }}';
            document.getElementById('conv-list')?.prepend(row);
        },

        bumpUnread(id) {
            const badge = document.querySelector(`.conv-row[data-conv-id="${id}"] .conv-unread`);
            if (!badge) return;
            badge.classList.remove('hidden');
            badge.textContent = (parseInt(badge.textContent) || 0) + 1;
        },
        clearUnread(id) {
            const badge = document.querySelector(`.conv-row[data-conv-id="${id}"] .conv-unread`);
            if (badge) { badge.classList.add('hidden'); badge.textContent = '0'; }
        },
        bumpHeaderBadge(delta) { if (window.updateChatBadge) window.updateChatBadge(delta); },

        markRead(id) { fetch(`${this.urls.base}/${id}/read`, { method: 'POST', headers: this.headers() }).catch(()=>{}); },
        scrollDown() { this.$nextTick(() => { const el = document.getElementById('thread-scroll'); if (el) el.scrollTop = el.scrollHeight; }); },
        headers() { return { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' }; },
    };
}
</script>
@endpush
@endsection
