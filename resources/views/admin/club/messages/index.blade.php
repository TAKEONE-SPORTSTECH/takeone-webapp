@extends('layouts.admin-club')

@section('club-admin-content')
<div x-data="messagesApp()" x-init="init()">
    <div class="flex flex-wrap gap-3 items-center justify-between mb-4">
        <div>
            <h2 class="tf-section-title">{{ __('admin.club_messages_index_title') }}</h2>
            <p class="text-sm text-gray-500 mt-1">{{ __('admin.club_messages_index_subtitle') }}</p>
        </div>
        <button class="btn btn-primary" @click="showNewMessageModal = true">
            <i class="bi bi-plus-lg me-2"></i>{{ __('admin.club_messages_index_new_message') }}
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Conversations List -->
        <div class="lg:col-span-1">
            <div class="card border-0 shadow-sm h-full">
                <div class="card-header bg-white border-0">
                    <input type="text" class="form-control" placeholder="{{ __('admin.club_messages_index_search_placeholder') }}"
                           x-model="search">
                </div>
                <div class="card-body p-0 max-h-[560px] overflow-y-auto" id="conversation-list">
                    @forelse($conversations as $conversation)
                        @php $cu = $conversation->user; @endphp
                        <div class="flex items-center gap-3 p-3 border-b border-border conversation-item cursor-pointer hover:bg-muted/50 transition-colors {{ $conversation->unread ? 'bg-muted/30' : '' }}"
                             data-conv-id="{{ $conversation->user_id }}"
                             data-name="{{ $cu->full_name ?? __('admin.club_messages_index_unknown') }}"
                             data-name-search="{{ Str::lower($cu->full_name ?? 'unknown') }}"
                             data-avatar="{{ $cu && $cu->profile_picture ? asset('storage/'.$cu->profile_picture) : '' }}"
                             @click="openConversation({{ $conversation->user_id }})"
                             :class="activeUserId === {{ $conversation->user_id }} ? 'bg-accent/40' : ''">
                            @if($cu && $cu->profile_picture)
                                <img src="{{ asset('storage/'.$cu->profile_picture) }}" alt="" class="rounded-full w-11 h-11 object-cover shrink-0">
                            @else
                                <div class="rounded-full bg-primary flex items-center justify-center w-11 h-11 shrink-0">
                                    <span class="text-white font-bold">{{ mb_strtoupper(mb_substr($cu->full_name ?? 'U', 0, 1, 'UTF-8'), 'UTF-8') }}</span>
                                </div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center">
                                    <p class="font-semibold mb-0 truncate">{{ $cu->full_name ?? __('admin.club_messages_index_unknown') }}</p>
                                    <small class="text-muted-foreground shrink-0 me-2 conv-time">{{ $conversation->last_message_at?->diffForHumans(null, true) }}</small>
                                </div>
                                <p class="text-muted-foreground text-sm mb-0 truncate conv-preview">{{ Str::limit($conversation->last_message, 38) }}</p>
                            </div>
                            <span class="badge bg-primary rounded-full conv-unread {{ $conversation->unread_count > 0 ? '' : 'hidden' }}">{{ $conversation->unread_count }}</span>
                        </div>
                    @empty
                    @endforelse

                    <div id="conversation-empty" class="text-center py-12 {{ count($conversations) ? 'hidden' : '' }}">
                        <i class="bi bi-chat-dots text-muted-foreground text-5xl"></i>
                        <p class="text-muted-foreground mt-2 mb-0">{{ __('admin.club_messages_index_no_conversations') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="lg:col-span-2">
            <div class="card border-0 shadow-sm h-full">
                <!-- Empty state -->
                <div class="card-body flex flex-col min-h-[560px]" x-show="activeUserId === null">
                    <div class="text-center my-auto">
                        <i class="bi bi-chat-square-text text-muted-foreground text-6xl"></i>
                        <h5 class="mt-3 mb-2">{{ __('admin.club_messages_index_select_conversation') }}</h5>
                        <p class="text-muted-foreground">{{ __('admin.club_messages_index_select_conversation_hint') }}</p>
                    </div>
                </div>

                <!-- Active thread -->
                <div class="flex flex-col min-h-[560px] max-h-[560px]" x-show="activeUserId !== null" x-cloak>
                    <!-- Thread header -->
                    <div class="flex items-center gap-3 px-4 py-3 border-b border-border">
                        <template x-if="activeUser.avatar">
                            <img :src="activeUser.avatar" class="rounded-full w-10 h-10 object-cover" alt="">
                        </template>
                        <template x-if="!activeUser.avatar">
                            <div class="rounded-full bg-primary flex items-center justify-center w-10 h-10">
                                <span class="text-white font-bold" x-text="activeUser.initial"></span>
                            </div>
                        </template>
                        <div class="min-w-0">
                            <p class="font-semibold mb-0 truncate" x-text="activeUser.name"></p>
                            <p class="text-xs text-muted-foreground mb-0" x-text="connected ? '{{ __('admin.club_messages_index_online_realtime') }}' : '{{ __('admin.club_messages_index_deliver_instantly') }}'"></p>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-2 bg-muted/20" id="thread-scroll">
                        <template x-if="loadingThread">
                            <div class="text-center text-muted-foreground text-sm py-8">
                                <i class="bi bi-arrow-repeat"></i> {{ __('admin.club_messages_index_loading') }}
                            </div>
                        </template>
                        <template x-for="m in messages" :key="m.id">
                            <div class="flex" :class="m.mine ? 'justify-end' : 'justify-start'">
                                <div class="max-w-[75%] rounded-2xl px-3.5 py-2 text-sm shadow-sm"
                                     :class="m.mine ? 'bg-primary text-white rounded-br-md' : 'bg-white text-foreground border border-border rounded-bl-md'">
                                    <p class="mb-0 whitespace-pre-wrap break-words" x-text="m.body"></p>
                                    <span class="block text-[10px] mt-1 opacity-70" :class="m.mine ? 'text-white' : 'text-muted-foreground'" x-text="m.time"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Composer -->
                    <form class="flex items-end gap-2 px-3 py-3 border-t border-border" @submit.prevent="send()">
                        <textarea x-model="draft" rows="1" placeholder="{{ __('admin.club_messages_index_type_message_placeholder') }}"
                                  @keydown.enter.prevent="send()"
                                  class="form-control flex-1 resize-none" style="max-height:120px;"></textarea>
                        <button type="submit" class="btn btn-primary shrink-0" :disabled="sending || !draft.trim()">
                            <i class="bi bi-send"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- New Message Modal -->
    <div x-show="showNewMessageModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showNewMessageModal = false"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-md relative" @click.stop>
                <div class="modal-header border-0 px-6 py-4">
                    <h5 class="modal-title font-bold">{{ __('admin.club_messages_index_new_message') }}</h5>
                    <button type="button" class="btn-close" @click="showNewMessageModal = false"></button>
                </div>
                <div class="modal-body px-6 pb-6">
                    <form @submit.prevent="sendNew()">
                        <div class="mb-4">
                            <label class="form-label">{{ __('admin.club_messages_index_to_label') }}</label>
                            <select x-model="newRecipient" class="form-select" required>
                                <option value="">{{ __('admin.club_messages_index_select_member') }}</option>
                                @foreach($members ?? [] as $member)
                                    @if($member->user)
                                        <option value="{{ $member->user_id }}">{{ $member->user->full_name ?? __('admin.club_messages_index_unknown') }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">{{ __('admin.club_messages_index_message_label') }}</label>
                            <textarea x-model="newBody" class="form-control" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-full" :disabled="sending">
                            <span x-text="sending ? '{{ __('admin.club_messages_index_sending') }}' : '{{ __('admin.club_messages_index_send_message') }}'"></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function messagesApp() {
    return {
        showNewMessageModal: false,
        search: '',
        activeUserId: null,
        activeUser: { name: '', avatar: null, initial: '' },
        messages: [],
        loadingThread: false,
        draft: '',
        sending: false,
        connected: false,
        newRecipient: '',
        newBody: '',

        threadBase: '{{ url('admin/club/'.$club->slug.'/messages/thread') }}',
        sendUrl: '{{ route('admin.club.messages.send', $club->slug) }}',

        init() {
            // Live status pill + inbound messages.
            window.addEventListener('realtime:status', (e) => { this.connected = !!e.detail.connected; });
            window.addEventListener('realtime:message', (e) => this.onIncoming(e.detail || {}));

            // Filter conversations as the admin types.
            this.$watch('search', (q) => {
                const term = (q || '').toLowerCase();
                document.querySelectorAll('#conversation-list .conversation-item').forEach(el => {
                    el.classList.toggle('hidden', term && !el.dataset.nameSearch.includes(term));
                });
            });
        },

        async openConversation(userId) {
            this.activeUserId = userId;
            this.loadingThread = true;
            this.messages = [];

            const row = document.querySelector(`.conversation-item[data-conv-id="${userId}"]`);
            this.activeUser = {
                name:    row?.dataset.name || '{{ __('admin.club_messages_index_member_fallback') }}',
                avatar:  row?.dataset.avatar || null,
                initial: (row?.dataset.name || 'M').charAt(0).toUpperCase(),
            };
            this.clearUnread(userId);

            try {
                const res = await fetch(`${this.threadBase}/${userId}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) {
                    this.activeUser = data.user;
                    this.messages = data.messages;
                    this.scrollDown();
                }
            } catch (e) {
                window.showToast('error', '{{ __('admin.club_messages_index_toast_load_failed') }}');
            } finally {
                this.loadingThread = false;
            }
        },

        async send() {
            const body = this.draft.trim();
            if (!body || this.sending) return;
            this.sending = true;
            try {
                const data = await this.post({ recipient_id: this.activeUserId, message: body });
                if (data.success) {
                    this.messages.push(data.data);
                    this.draft = '';
                    this.scrollDown();
                    this.updatePreview(this.activeUserId, body, false);
                }
            } finally {
                this.sending = false;
            }
        },

        async sendNew() {
            if (!this.newRecipient || !this.newBody.trim() || this.sending) return;
            this.sending = true;
            try {
                const recipient = parseInt(this.newRecipient);
                const data = await this.post({ recipient_id: recipient, message: this.newBody.trim() });
                if (data.success) {
                    this.showNewMessageModal = false;
                    this.newBody = '';
                    this.newRecipient = '';
                    window.showToast('success', '{{ __('admin.club_messages_index_toast_sent') }}');
                    this.ensureConversationRow(recipient);
                    this.openConversation(recipient);
                }
            } finally {
                this.sending = false;
            }
        },

        async post(payload) {
            try {
                const res = await fetch(this.sendUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                return await res.json();
            } catch (e) {
                window.showToast('error', '{{ __('admin.club_messages_index_toast_send_failed') }}');
                return { success: false };
            }
        },

        onIncoming(detail) {
            if (!detail.from_id) return;
            // Append to the open thread, otherwise badge the conversation row.
            if (this.activeUserId === detail.from_id) {
                this.messages.push({
                    id: detail.id, body: detail.body, mine: false,
                    time: '', created_at_human: detail.created_at_human,
                });
                this.scrollDown();
            } else {
                this.ensureConversationRow(detail.from_id, detail.from_name, detail.from_avatar);
                this.bumpUnread(detail.from_id);
            }
            this.updatePreview(detail.from_id, detail.body, true);
        },

        ensureConversationRow(userId, name, avatar) {
            if (document.querySelector(`.conversation-item[data-conv-id="${userId}"]`)) return;
            // Fall back to the member <select> label when no name is supplied.
            if (!name) {
                const opt = [...document.querySelectorAll('select option')].find(o => o.value == userId);
                name = opt ? opt.textContent.trim() : '{{ __('admin.club_messages_index_member_fallback') }}';
            }
            document.getElementById('conversation-empty')?.classList.add('hidden');
            const list = document.getElementById('conversation-list');
            const row = document.createElement('div');
            row.className = 'flex items-center gap-3 p-3 border-b border-border conversation-item cursor-pointer hover:bg-muted/50 transition-colors';
            row.dataset.convId = userId;
            row.dataset.name = name;
            row.dataset.nameSearch = name.toLowerCase();
            row.dataset.avatar = avatar || '';
            // Escape every interpolation — name/avatar are user-controlled (member
            // full_name, realtime payload) and flow into innerHTML.
            const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
            const initial = esc(name.charAt(0).toUpperCase());
            row.innerHTML =
                (avatar
                    ? `<img src="${esc(avatar)}" class="rounded-full w-11 h-11 object-cover shrink-0" alt="">`
                    : `<div class="rounded-full bg-primary flex items-center justify-center w-11 h-11 shrink-0"><span class="text-white font-bold">${initial}</span></div>`) +
                '<div class="flex-1 min-w-0"><div class="flex justify-between items-center">' +
                `<p class="font-semibold mb-0 truncate">${esc(name)}</p><small class="text-muted-foreground shrink-0 me-2 conv-time">{{ __('admin.club_messages_index_now') }}</small></div>` +
                '<p class="text-muted-foreground text-sm mb-0 truncate conv-preview"></p></div>' +
                '<span class="badge bg-primary rounded-full conv-unread hidden">0</span>';
            row.addEventListener('click', () => this.openConversation(userId));
            list.insertBefore(row, list.firstChild);
        },

        updatePreview(userId, text, incoming) {
            const row = document.querySelector(`.conversation-item[data-conv-id="${userId}"]`);
            if (!row) return;
            const preview = row.querySelector('.conv-preview');
            if (preview) preview.textContent = (incoming ? '' : '{{ __('admin.club_messages_index_you_prefix') }}') + text;
            const time = row.querySelector('.conv-time');
            if (time) time.textContent = '{{ __('admin.club_messages_index_now') }}';
            document.getElementById('conversation-list')?.prepend(row);
        },

        bumpUnread(userId) {
            const row = document.querySelector(`.conversation-item[data-conv-id="${userId}"]`);
            const badge = row?.querySelector('.conv-unread');
            if (!badge) return;
            badge.classList.remove('hidden');
            badge.textContent = (parseInt(badge.textContent) || 0) + 1;
        },

        clearUnread(userId) {
            const badge = document.querySelector(`.conversation-item[data-conv-id="${userId}"] .conv-unread`);
            if (badge) { badge.classList.add('hidden'); badge.textContent = '0'; }
        },

        scrollDown() {
            this.$nextTick(() => {
                const el = document.getElementById('thread-scroll');
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
    };
}
</script>
@endpush
@endsection
