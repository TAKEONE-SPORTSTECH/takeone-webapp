@extends('layouts.app')

@section('title', 'Messages')

{{-- Use the mobile chrome (own top bar below) instead of the desktop navbar,
     so the top bar matches the rest of the mobile experience. --}}
@section('hide-navbar', true)

@section('content')
<div x-data="mobileMessenger()" x-init="init()">
{{-- Mobile top bar — mirrors partials/mobile-header so there's no "switch" to
     the desktop navbar when landing on /messages on a phone. --}}
<header class="sticky top-0 z-40 bg-white border-b border-border">
    <div class="flex items-center gap-2 px-3 h-14">
        <a href="{{ route('me.home') }}" class="flex items-center justify-center w-10 h-10 rounded-xl flex-shrink-0" aria-label="Back">
            <i class="bi bi-arrow-left text-xl text-foreground"></i>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-[10px] text-muted-foreground font-medium leading-tight flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full" :class="connected ? 'bg-green-500' : 'bg-gray-300'"></span>
                <span x-text="connected ? 'Connected · realtime' : 'Delivers instantly'"></span>
            </p>
            <p class="text-base font-bold text-primary leading-tight truncate">Messages</p>
        </div>
        <button type="button" @click="openSearch()" class="w-9 h-9 rounded-xl bg-muted flex items-center justify-center text-muted-foreground flex-shrink-0" aria-label="New message">
            <i class="bi bi-pencil-square"></i>
        </button>
    </div>
</header>

<div class="px-3 pt-3 pb-24">

    {{-- Search to start a chat --}}
    <button type="button" @click="openSearch()"
            class="w-full flex items-center gap-2 px-4 py-2.5 mb-4 rounded-2xl bg-muted/70 text-muted-foreground text-sm m-press m-in">
        <i class="bi bi-search"></i> Search people to message…
    </button>

    {{-- Conversation list --}}
    <div id="m-conv-list" class="space-y-2 mobile-stagger">
        @foreach($conversations as $c)
            @php $p = $c->partner; @endphp
            <button type="button"
                    class="m-conv m-card m-press w-full flex items-center gap-3 p-3 text-left"
                    data-conv-id="{{ $c->id }}"
                    data-name="{{ $p['name'] }}"
                    data-avatar="{{ $p['avatar'] }}"
                    data-initial="{{ $p['initial'] }}"
                    @click="openThread({{ $c->id }})">
                <span class="relative shrink-0">
                    @if($p['avatar'])
                        <img src="{{ $p['avatar'] }}" class="w-12 h-12 rounded-full object-cover" alt="">
                    @else
                        <span class="w-12 h-12 rounded-full bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center text-lg font-bold">{{ $p['initial'] }}</span>
                    @endif
                    <span class="m-conv-dot absolute -top-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-primary ring-2 ring-white {{ $c->unread_count > 0 ? '' : 'hidden' }}"></span>
                </span>
                <span class="flex-1 min-w-0">
                    <span class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-[15px] text-foreground truncate">{{ $p['name'] }}</span>
                        <span class="text-[11px] text-muted-foreground shrink-0 m-conv-time">{{ $c->last_at_human }}</span>
                    </span>
                    <span class="block text-[13px] text-muted-foreground truncate mt-0.5 m-conv-preview {{ $c->unread_count > 0 ? 'font-semibold text-foreground' : '' }}">{{ $c->last_mine ? 'You: ' : '' }}{{ $c->last_body }}</span>
                </span>
            </button>
        @endforeach
    </div>

    <div id="m-conv-empty" class="text-center py-20 {{ count($conversations) ? 'hidden' : '' }}">
        <i class="bi bi-chat-heart text-5xl text-gray-200 m-float inline-block"></i>
        <p class="text-sm text-muted-foreground mt-3">No chats yet.<br>Tap search to message anyone.</p>
    </div>

    {{-- Compose FAB --}}
    <button type="button" @click="openSearch()"
            class="fixed right-4 bottom-5 z-40 w-14 h-14 rounded-full bg-primary text-white shadow-lg shadow-primary/30 flex items-center justify-center m-press"
            aria-label="New message">
        <i class="bi bi-pencil-square text-xl"></i>
    </button>

    {{-- ─────────── New-message search sheet ─────────── --}}
    <div x-show="searchOpen" x-cloak class="fixed inset-0 z-[60] bg-background flex flex-col"
         x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
        <div class="flex items-center gap-2 px-3 py-3 border-b border-border bg-white">
            <button type="button" class="w-9 h-9 rounded-lg flex items-center justify-center m-press" @click="closeSearch()"><i class="bi bi-arrow-left text-xl"></i></button>
            <div class="relative flex-1">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input x-ref="searchInput" type="text" x-model="searchTerm" @input.debounce.300ms="searchUsers()"
                       placeholder="Search by name…" autocomplete="off"
                       class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-3">
            <template x-if="searching"><div class="text-center text-muted-foreground text-sm py-8"><i class="bi bi-arrow-repeat"></i> Searching…</div></template>
            <div class="space-y-1">
                <template x-for="u in searchResults" :key="u.id">
                    <button type="button" @click="startWith(u)" class="w-full flex items-center gap-3 p-2.5 rounded-xl m-press hover:bg-muted/50 text-left">
                        <template x-if="u.avatar"><img :src="u.avatar" class="w-11 h-11 rounded-full object-cover shrink-0" alt=""></template>
                        <template x-if="!u.avatar"><span class="w-11 h-11 rounded-full bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center font-bold shrink-0" x-text="u.initial"></span></template>
                        <span class="text-[15px] font-medium text-foreground truncate" x-text="u.name"></span>
                    </button>
                </template>
            </div>
            <template x-if="!searching && searchTerm && searchResults.length === 0">
                <p class="text-center text-sm text-muted-foreground py-8">No one found.</p>
            </template>
        </div>
    </div>

    {{-- ─────────── Thread overlay (slides up) ─────────── --}}
    <div x-show="threadOpen" x-cloak class="fixed inset-0 z-[60] bg-background flex flex-col"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">

        {{-- Thread header --}}
        <div class="flex items-center gap-3 px-2.5 py-2.5 border-b border-border bg-white">
            <button type="button" class="w-9 h-9 rounded-lg flex items-center justify-center m-press" @click="closeThread()"><i class="bi bi-arrow-left text-xl"></i></button>
            <span class="shrink-0">
                <template x-if="partner.avatar"><img :src="partner.avatar" class="w-9 h-9 rounded-full object-cover" alt=""></template>
                <template x-if="!partner.avatar"><span class="w-9 h-9 rounded-full bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center text-sm font-bold" x-text="partner.initial"></span></template>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[15px] font-semibold text-foreground truncate mb-0" x-text="partner.name"></p>
                <p class="text-[11px] text-muted-foreground mb-0 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full" :class="connected ? 'bg-green-500' : 'bg-gray-300'"></span>
                    <span x-text="connected ? 'Active now' : 'Encrypted chat'"></span>
                </p>
            </div>
            <div class="relative" x-data="{ o: false }">
                <button type="button" @click="o = !o" class="w-9 h-9 rounded-lg flex items-center justify-center m-press text-muted-foreground" aria-label="Chat options"><i class="bi bi-three-dots-vertical text-lg"></i></button>
                <div x-show="o" x-cloak @click.outside="o = false"
                     x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     class="absolute right-0 top-11 z-30 w-44 bg-white rounded-xl shadow-lg ring-1 ring-black/5 border border-border py-1">
                    <button type="button" @click="o = false; deleteChat(activeId)" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-red-600 m-press text-left"><i class="bi bi-trash"></i> Delete chat</button>
                </div>
            </div>
        </div>

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto px-3 py-4 space-y-1.5 bg-muted/20" id="m-thread-scroll">
            <template x-if="loadingThread">
                <div class="space-y-2">
                    <div class="m-skeleton h-9 w-2/3 rounded-2xl"></div>
                    <div class="m-skeleton h-9 w-1/2 rounded-2xl ml-auto"></div>
                    <div class="m-skeleton h-9 w-3/5 rounded-2xl"></div>
                </div>
            </template>
            <template x-for="m in messages" :key="m.id">
                <div class="flex" :class="m.mine ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[80%] rounded-2xl shadow-sm select-none overflow-hidden"
                         @click="!m.deleted && !m.kind && openActions(m)"
                         :style="(m.kind === 'audio' && m.attachment && !m.deleted) ? 'width:min(78vw,360px)' : ''"
                         :class="(m.deleted
                                    ? 'bg-muted text-muted-foreground border border-border ' + (m.mine ? 'rounded-br-md' : 'rounded-bl-md')
                                    : (m.mine ? 'bg-primary text-white rounded-br-md' : 'bg-white text-foreground border border-border rounded-bl-md'))
                                 + ((m.kind === 'image' || m.kind === 'video') && m.attachment && !m.deleted ? ' p-1' : ' px-3.5 py-2 text-[15px]')
                                 + (m.id === lastAddedId ? ' m-in-pop' : '') + (m.pending ? ' opacity-70' : '')">
                        <p x-show="m.deleted" class="mb-0 italic flex items-center gap-1.5"><i class="bi bi-slash-circle"></i> This message was deleted</p>
                        <template x-if="!m.deleted && m.kind === 'image' && m.attachment">
                            <a :href="m.attachment.url" target="_blank" rel="noopener" class="block"><img :src="m.attachment.url" :alt="m.attachment.name" loading="lazy" class="rounded-xl block" style="max-height:300px; max-width:100%;"></a>
                        </template>
                        <template x-if="!m.deleted && m.kind === 'video' && m.attachment">
                            <video :src="m.attachment.url" controls preload="metadata" playsinline class="rounded-xl block max-w-full" style="max-height:300px"></video>
                        </template>
                        <template x-if="!m.deleted && m.kind === 'audio' && m.attachment">
                            <div x-data="audioPlayer()" class="flex items-center gap-3 w-full">
                                <audio x-ref="audio" :src="m.attachment.url" preload="metadata" class="hidden"></audio>
                                <button type="button" @click="toggle()"
                                        class="relative shrink-0 w-11 h-11 rounded-full flex items-center justify-center transition-transform active:scale-90"
                                        :class="m.mine ? 'bg-white text-primary' : 'bg-primary text-white'">
                                    <span x-show="playing" class="absolute inset-0 rounded-full animate-ping opacity-30" :class="m.mine ? 'bg-white' : 'bg-primary'"></span>
                                    <i class="bi relative text-lg" :class="playing ? 'bi-pause-fill' : 'bi-play-fill'"></i>
                                </button>
                                <div class="flex-1 min-w-0">
                                    <div x-ref="track" @pointerdown="startDrag($event)" @pointermove="onDrag($event)" @pointerup="endDrag($event)" @pointercancel="endDrag($event)"
                                         class="flex items-center gap-[2px] h-8 cursor-pointer touch-none select-none">
                                        <template x-for="(bar, i) in bars" :key="i">
                                            <span class="flex-1 rounded-full transition-all duration-150"
                                                  :style="`height:${barPlayed(i) ? bar : Math.max(16, bar*0.5)}%`"
                                                  :class="barPlayed(i) ? (m.mine ? 'bg-white' : 'bg-primary') : (m.mine ? 'bg-white/40' : 'bg-primary/25')"></span>
                                        </template>
                                    </div>
                                    <div class="flex items-center justify-between mt-0.5">
                                        <span class="text-[10px] tabular-nums opacity-80" x-text="timeLabel"></span>
                                        <a :href="m.attachment.url" :download="m.attachment.name" class="flex items-center gap-1 text-[10px] opacity-70" title="Download">
                                            <i class="bi bi-download"></i><span x-text="window.ChatAttach.humanSize(m.attachment.size)"></span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template x-if="!m.deleted && m.kind === 'file' && m.attachment">
                            <a :href="m.attachment.url" :download="m.attachment.name" class="flex items-center gap-2.5">
                                <span class="w-9 h-9 rounded-lg bg-black/10 flex items-center justify-center shrink-0"><i class="bi bi-file-earmark-arrow-down text-lg"></i></span>
                                <span class="min-w-0">
                                    <span class="block truncate font-medium" x-text="m.attachment.name"></span>
                                    <span class="block text-[11px] opacity-70" x-text="window.ChatAttach.humanSize(m.attachment.size)"></span>
                                </span>
                            </a>
                        </template>
                        <p x-show="!m.deleted && m.kind && !m.attachment" class="mb-0 italic flex items-center gap-1.5"><i class="bi bi-clock-history"></i> Attachment expired</p>
                        <template x-if="!m.deleted && !m.kind">
                            <div>
                                <p class="mb-0 whitespace-pre-wrap break-words" x-html="window.LinkPreview.linkifyHtml(m.body)"></p>
                                <div x-data="linkCard()" x-init="load(m.body)" x-show="preview" x-cloak>
                                    <template x-if="preview && preview.type === 'video_embed'">
                                        <div class="mt-1.5 rounded-lg overflow-hidden bg-black/20" style="aspect-ratio:16/9; width:min(72vw,300px); max-width:100%">
                                            <iframe :src="preview.embed" class="w-full h-full" style="border:0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                                        </div>
                                    </template>
                                    <template x-if="preview && preview.type === 'link'">
                                        <a :href="preview.url" target="_blank" rel="noopener noreferrer" class="mt-1.5 block rounded-lg overflow-hidden border border-black/10 bg-black/5" style="width:min(72vw,300px); max-width:100%">
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
                        <span class="block text-[10px] opacity-70 text-right" :class="((m.kind === 'image' || m.kind === 'video') && m.attachment && !m.deleted) ? 'px-1.5 pt-0.5 pb-1' : 'mt-0.5'">
                            <span x-show="m.edited">edited · </span><span x-text="m.time"></span>
                            <i x-show="m.pending" class="bi bi-clock ml-0.5"></i>
                        </span>
                    </div>
                </div>
            </template>
        </div>

        {{-- Editing banner --}}
        <div x-show="editing" x-cloak class="flex items-center gap-2 px-3 pt-2 bg-white text-xs text-primary">
            <i class="bi bi-pencil-square"></i>
            <span class="font-medium">Editing message</span>
            <button type="button" @click="cancelEdit()" class="ml-auto text-muted-foreground"><i class="bi bi-x-lg"></i></button>
        </div>

        {{-- Composer --}}
        <form class="flex items-end gap-2 px-2.5 py-2.5 border-t border-border bg-white" @submit.prevent="editing ? saveEdit() : send()"
              style="padding-bottom: calc(0.625rem + env(safe-area-inset-bottom));">
            <input type="file" x-ref="fileInput" class="hidden" @change="attachFile($event)">
            <button type="button" @click="$refs.fileInput.click()" :disabled="editing"
                    class="w-11 h-11 shrink-0 rounded-full text-muted-foreground flex items-center justify-center m-press disabled:opacity-40" aria-label="Attach file">
                <i class="bi bi-paperclip text-lg"></i>
            </button>
            <textarea x-ref="composer" x-model="draft" rows="1" placeholder="Aa" @keydown.enter.prevent="editing ? saveEdit() : send()"
                      class="flex-1 resize-none px-4 py-2.5 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-[15px]" style="max-height:120px;"></textarea>
            <button type="submit" class="w-11 h-11 shrink-0 rounded-full bg-primary text-white flex items-center justify-center m-press disabled:opacity-40" :disabled="sending || !draft.trim()">
                <i class="bi" :class="editing ? 'bi-check-lg' : 'bi-send-fill'"></i>
            </button>
        </form>
    </div>

    {{-- ─────────── Message action sheet ─────────── --}}
    <div x-show="actionMsg" x-cloak class="fixed inset-0 z-[70] flex flex-col justify-end" @click="actionMsg = null">
        <div class="absolute inset-0 bg-black/40"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
        <div class="relative bg-white rounded-t-3xl p-2 pb-[calc(1rem+env(safe-area-inset-bottom))]" @click.stop
             x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
            <div class="w-11 h-1.5 rounded-full bg-gray-200 mx-auto my-2.5"></div>
            <button type="button" @click="copyMsg(actionMsg)" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-2xl m-press text-left text-[15px] text-foreground"><i class="bi bi-clipboard text-lg"></i> Copy</button>
            <template x-if="actionMsg && actionMsg.can_edit">
                <button type="button" @click="startEdit(actionMsg)" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-2xl m-press text-left text-[15px] text-foreground"><i class="bi bi-pencil text-lg"></i> Edit</button>
            </template>
            <button type="button" @click="deleteForMe(actionMsg)" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-2xl m-press text-left text-[15px] text-foreground"><i class="bi bi-eye-slash text-lg"></i> Delete for me</button>
            <template x-if="actionMsg && actionMsg.mine && !actionMsg.deleted">
                <button type="button" @click="deleteMsg(actionMsg)" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-2xl m-press text-left text-[15px] text-red-600"><i class="bi bi-trash text-lg"></i> Delete for everyone</button>
            </template>
            <button type="button" @click="actionMsg = null" class="w-full mt-1 px-4 py-3.5 rounded-2xl bg-muted/60 m-press text-[15px] font-medium text-foreground">Cancel</button>
        </div>
    </div>
</div>
</div>

@push('scripts')
<script>
function mobileMessenger() {
    return {
        threadOpen: false, searchOpen: false,
        activeId: null, partner: { name: '', avatar: null, initial: '' },
        messages: [], loadingThread: false, draft: '', sending: false, connected: false,
        lastAddedId: null,
        searchTerm: '', searchResults: [], searching: false,
        actionMsg: null, editing: false, editingId: null,

        urls: {
            search: '{{ route('messages.search-users') }}',
            startBase: '{{ url('messages/start') }}',
            base: '{{ url('messages') }}',
        },

        init() {
            window.addEventListener('realtime:status', (e) => this.connected = !!e.detail.connected);
            window.addEventListener('realtime:message', (e) => this.onIncoming(e.detail || {}));
            const pre = @json($openConversation ?? null);
            if (pre) this.openThread(pre);
        },

        openSearch() { this.searchOpen = true; this.searchResults = []; this.searchTerm = ''; this.$nextTick(() => this.$refs.searchInput?.focus()); },
        closeSearch() { this.searchOpen = false; },

        async searchUsers() {
            const q = this.searchTerm.trim();
            if (!q) { this.searchResults = []; return; }
            this.searching = true;
            try {
                const r = await fetch(`${this.urls.search}?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.searchResults = d.users || [];
            } catch (e) { this.searchResults = []; }
            finally { this.searching = false; }
        },

        async startWith(user) {
            try {
                const r = await fetch(`${this.urls.startBase}/${user.id}`, { method: 'POST', headers: this.headers() });
                const d = await r.json();
                if (d.success) {
                    this.ensureRow(d.conversation_id, user);
                    this.closeSearch();
                    this.openThread(d.conversation_id, user);
                }
            } catch (e) { window.showToast('error', 'Could not start chat.'); }
        },

        async openThread(id, user = null) {
            this.activeId = id;
            this.threadOpen = true;
            this.loadingThread = true;
            this.messages = [];
            this.lastAddedId = null;
            const row = document.querySelector(`.m-conv[data-conv-id="${id}"]`);
            this.partner = user
                ? { name: user.name, avatar: user.avatar, initial: user.initial || (user.name||'U').charAt(0).toUpperCase() }
                : { name: row?.dataset.name || 'User', avatar: row?.dataset.avatar || null, initial: row?.dataset.initial || 'U' };

            const dot = row?.querySelector('.m-conv-dot');
            const wasUnread = dot && !dot.classList.contains('hidden');
            this.clearUnread(id);
            if (wasUnread && window.updateChatBadge) window.updateChatBadge(-1);

            try {
                const r = await fetch(`${this.urls.base}/${id}/thread`, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                if (d.success) { this.partner = d.partner; this.messages = d.messages; this.scrollDown(); }
            } catch (e) { window.showToast('error', 'Could not load conversation.'); }
            finally { this.loadingThread = false; }
        },

        closeThread() { this.threadOpen = false; this.activeId = null; },

        async deleteChat(id) {
            const ok = await window.confirmAction({ title: 'Delete chat?', message: 'This chat and its history will be removed from your list. It reappears if a new message arrives.', type: 'danger', confirmText: 'Delete' });
            if (!ok) return;
            try {
                const r = await fetch(`${this.urls.base}/${id}`, { method: 'DELETE', headers: this.headers() });
                const d = await r.json();
                if (!d.success) { window.showToast('error', 'Could not delete chat.'); return; }
                document.querySelector(`.m-conv[data-conv-id="${id}"]`)?.remove();
                this.closeThread();
                window.showToast('success', 'Chat deleted');
            } catch (e) { window.showToast('error', 'Could not delete chat.'); }
        },

        async send() {
            const body = this.draft.trim();
            if (!body || this.sending) return;
            this.sending = true;
            try {
                const r = await fetch(`${this.urls.base}/${this.activeId}/send`, { method: 'POST', headers: this.headers(), body: JSON.stringify({ body }) });
                const d = await r.json();
                if (d.success) { this.lastAddedId = d.data.id; this.messages.push(d.data); this.draft = ''; this.scrollDown(); this.updateRow(this.activeId, body, true); }
            } catch (e) { window.showToast('error', 'Could not send.'); }
            finally { this.sending = false; }
        },

        onIncoming(detail) {
            if (!detail.conversation_id) return;
            const id = detail.conversation_id;

            // Edit / delete patch an open thread silently.
            if (detail.action === 'edit' || detail.action === 'delete') {
                if (this.threadOpen && this.activeId === id) this.applyRemoteChange(detail);
                if (detail.is_latest) this.updateRow(id, detail.action === 'delete' ? 'This message was deleted' : (detail.body || ''), false);
                return;
            }

            this.ensureRow(id, { id: detail.from_id, name: detail.from_name, avatar: detail.from_avatar, initial: (detail.from_name||'U').charAt(0).toUpperCase() });

            // Incoming ephemeral attachment (picture / file).
            if (detail.action === 'file') {
                if (this.threadOpen && this.activeId === id) {
                    this.lastAddedId = detail.id;
                    this.messages.push({ id: detail.id, mine: false, time: detail.time || '', kind: detail.kind, attachment: detail.attachment });
                    this.scrollDown(); this.markRead(id);
                } else { this.bumpUnread(id); if (window.updateChatBadge) window.updateChatBadge(1); }
                this.updateRow(id, detail.body || (detail.kind === 'image' ? '📷 Photo' : '📎 ' + (detail.attachment && detail.attachment.name || '')), false);
                if (window.playMessageSound) window.playMessageSound();
                return;
            }

            if (this.threadOpen && this.activeId === id) {
                this.lastAddedId = detail.id;
                this.messages.push({ id: detail.id, body: detail.body, mine: false, time: detail.time || '' });
                this.scrollDown();
                this.markRead(id);
            } else {
                this.bumpUnread(id);
                if (window.updateChatBadge) window.updateChatBadge(1);
            }
            this.updateRow(id, detail.body, false);
            if (window.playMessageSound) window.playMessageSound();
        },

        async attachFile(e) {
            const file = e.target.files && e.target.files[0];
            e.target.value = '';
            if (!file || !this.activeId) return;
            const convId = this.activeId;
            const t = file.type || '';
            const kind = t.startsWith('image/') ? 'image' : (t.startsWith('audio/') ? 'audio' : (t.startsWith('video/') ? 'video' : 'file'));
            const tempId = 'tmp-att-' + Date.now();
            const previewUrl = kind !== 'file' ? URL.createObjectURL(file) : null;
            this.lastAddedId = tempId;
            this.messages.push({ id: tempId, mine: true, time: '', kind, attachment: { url: previewUrl, name: file.name, size: file.size }, pending: true });
            this.scrollDown();
            const att = await window.ChatAttach.send(convId, file);
            const i = this.messages.findIndex((m) => m.id === tempId);
            if (!att) { if (i > -1) this.messages.splice(i, 1); if (previewUrl) URL.revokeObjectURL(previewUrl); return; }
            this.lastAddedId = att.id;
            if (i > -1) this.messages.splice(i, 1, att); else this.messages.push(att);
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            this.scrollDown();
            this.updateRow(convId, att.kind === 'image' ? '📷 Photo' : '📎 ' + (att.attachment && att.attachment.name || ''), true);
        },

        applyRemoteChange(detail) {
            const m = this.messages.find((x) => x.id === detail.id);
            if (!m) return;
            if (detail.action === 'delete') { m.deleted = true; m.body = null; m.can_edit = false; }
            else { m.body = detail.body; m.edited = true; }
        },

        /* ── message actions (copy / edit / delete) ── */
        openActions(m) { this.actionMsg = m; },

        copyMsg(m) {
            this.actionMsg = null;
            navigator.clipboard?.writeText(m.body || '').then(
                () => window.showToast('success', 'Message copied'),
                () => window.showToast('error', 'Could not copy'),
            );
        },

        startEdit(m) {
            this.actionMsg = null;
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
                if (!d.success) { window.showToast('error', d.message || 'Could not edit.'); return; }
                const m = this.messages.find((x) => x.id === this.editingId);
                if (m) { m.body = body; m.edited = true; }
                if (d.is_latest) this.updateRow(this.activeId, body, true);
                this.cancelEdit();
            } catch (e) { window.showToast('error', 'Could not edit.'); }
            finally { this.sending = false; }
        },

        async deleteMsg(m) {
            this.actionMsg = null;
            const ok = await window.confirmAction({ title: 'Delete for everyone?', message: 'This message will be deleted for everyone in the chat.', type: 'danger', confirmText: 'Delete' });
            if (!ok) return;
            try {
                const r = await fetch(`${this.urls.base}/${this.activeId}/messages/${m.id}`, { method: 'DELETE', headers: this.headers() });
                const d = await r.json();
                if (!d.success) { window.showToast('error', 'Could not delete.'); return; }
                m.deleted = true; m.body = null; m.can_edit = false;
                if (this.editingId === m.id) this.cancelEdit();
                if (d.is_latest) this.updateRow(this.activeId, 'This message was deleted', true);
            } catch (e) { window.showToast('error', 'Could not delete.'); }
        },

        async deleteForMe(m) {
            this.actionMsg = null;
            const ok = await window.confirmAction({ title: 'Delete for me?', message: 'This message will be removed from your view only.', type: 'danger', confirmText: 'Delete' });
            if (!ok) return;
            try {
                const r = await fetch(`${this.urls.base}/${this.activeId}/messages/${m.id}/hide`, { method: 'POST', headers: this.headers() });
                const d = await r.json();
                if (!d.success) { window.showToast('error', 'Could not delete.'); return; }
                const i = this.messages.findIndex((x) => x.id === m.id);
                if (i > -1) this.messages.splice(i, 1);
                if (this.editingId === m.id) this.cancelEdit();
            } catch (e) { window.showToast('error', 'Could not delete.'); }
        },

        ensureRow(id, user) {
            if (document.querySelector(`.m-conv[data-conv-id="${id}"]`)) return;
            document.getElementById('m-conv-empty')?.classList.add('hidden');
            const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
            const name = user.name || 'User';
            const initial = esc((name).charAt(0).toUpperCase());
            const list = document.getElementById('m-conv-list');
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'm-conv m-card m-press w-full flex items-center gap-3 p-3 text-left m-in';
            b.dataset.convId = id; b.dataset.name = name; b.dataset.avatar = user.avatar || ''; b.dataset.initial = initial;
            b.innerHTML =
                '<span class="relative shrink-0">' +
                (user.avatar ? `<img src="${esc(user.avatar)}" class="w-12 h-12 rounded-full object-cover" alt="">`
                             : `<span class="w-12 h-12 rounded-full bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center text-lg font-bold">${initial}</span>`) +
                '<span class="m-conv-dot absolute -top-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-primary ring-2 ring-white hidden"></span></span>' +
                '<span class="flex-1 min-w-0"><span class="flex items-center justify-between gap-2">' +
                `<span class="font-semibold text-[15px] text-foreground truncate">${esc(name)}</span><span class="text-[11px] text-muted-foreground shrink-0 m-conv-time">now</span></span>` +
                '<span class="block text-[13px] text-muted-foreground truncate mt-0.5 m-conv-preview"></span></span>';
            b.addEventListener('click', () => this.openThread(id));
            list.insertBefore(b, list.firstChild);
        },

        updateRow(id, text, mine) {
            const row = document.querySelector(`.m-conv[data-conv-id="${id}"]`);
            if (!row) return;
            const p = row.querySelector('.m-conv-preview'); if (p) { p.textContent = (mine ? 'You: ' : '') + text; }
            const t = row.querySelector('.m-conv-time'); if (t) t.textContent = 'now';
            document.getElementById('m-conv-list')?.prepend(row);
        },

        bumpUnread(id) {
            const row = document.querySelector(`.m-conv[data-conv-id="${id}"]`);
            row?.querySelector('.m-conv-dot')?.classList.remove('hidden');
            row?.querySelector('.m-conv-preview')?.classList.add('font-semibold', 'text-foreground');
        },
        clearUnread(id) {
            const row = document.querySelector(`.m-conv[data-conv-id="${id}"]`);
            row?.querySelector('.m-conv-dot')?.classList.add('hidden');
            row?.querySelector('.m-conv-preview')?.classList.remove('font-semibold', 'text-foreground');
        },

        markRead(id) { fetch(`${this.urls.base}/${id}/read`, { method: 'POST', headers: this.headers() }).catch(()=>{}); },
        scrollDown() { this.$nextTick(() => { const el = document.getElementById('m-thread-scroll'); if (el) el.scrollTop = el.scrollHeight; }); },
        headers() { return { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' }; },
    };
}
</script>
@endpush
@endsection
