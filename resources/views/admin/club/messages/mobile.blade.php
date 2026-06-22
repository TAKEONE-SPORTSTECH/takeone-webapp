@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_messages'))

@section('club-admin-content')
<div class="space-y-4" x-data="mobileMessages()" x-init="init()">

    {{-- Conversations --}}
    @if(!empty($conversations) && $conversations->isNotEmpty())
        <div class="m-card divide-y divide-gray-50 mobile-stagger" id="m-conv-list">
            @foreach($conversations as $c)
                @php $cu = $c->user; @endphp
                <button type="button"
                        class="w-full flex items-center gap-3 p-4 m-press text-left"
                        data-conv-id="{{ $c->user_id }}"
                        data-name="{{ $cu->full_name ?? 'Member' }}"
                        data-avatar="{{ $cu && $cu->profile_picture ? asset('storage/'.$cu->profile_picture) : '' }}"
                        @click="openThread({{ $c->user_id }})">
                    <span class="w-10 h-10 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if($cu && $cu->profile_picture)<img src="{{ asset('storage/'.$cu->profile_picture) }}" alt="" class="w-10 h-10 object-cover">@else<i class="bi bi-person text-muted-foreground"></i>@endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-foreground truncate">{{ $cu->full_name ?? 'Member' }}</p>
                        <p class="text-xs text-muted-foreground truncate conv-preview">{{ Str::limit($c->last_message, 40) }}</p>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-primary text-white conv-unread {{ ($c->unread_count ?? 0) > 0 ? '' : 'hidden' }}">{{ $c->unread_count }}</span>
                </button>
            @endforeach
        </div>
    @else
        <div class="m-card p-8 text-center" id="m-conv-empty">
            <i class="bi bi-chat-dots text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.no_conversations_yet') }}</p>
        </div>
    @endif

    {{-- Members directory — tap to start a chat --}}
    <div class="m-card p-4">
        <h3 class="font-semibold text-foreground mb-3">{{ __('admin.nav_members') }}</h3>
        @if($members->isEmpty())
            <p class="text-sm text-muted-foreground">{{ __('admin.no_members') }}</p>
        @else
            <div class="space-y-1 mobile-stagger">
                @foreach($members->take(50) as $m)
                    @php $u = $m->user; if(!$u) continue; @endphp
                    <button type="button"
                            class="w-full flex items-center gap-3 p-2 rounded-lg m-press text-left hover:bg-muted/40"
                            data-name="{{ $u->full_name }}"
                            data-avatar="{{ $u->profile_picture ? asset('storage/'.$u->profile_picture) : '' }}"
                            @click="openThread({{ $u->id }}, $el.dataset.name, $el.dataset.avatar)">
                        <span class="w-9 h-9 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                            @if($u->profile_picture)<img src="{{ asset('storage/'.$u->profile_picture) }}" alt="" class="w-9 h-9 object-cover">@else<i class="bi bi-person text-muted-foreground"></i>@endif
                        </span>
                        <div class="min-w-0 flex-1"><p class="text-sm font-medium text-foreground truncate">{{ $u->full_name }}</p><p class="text-xs text-muted-foreground truncate">{{ $u->email }}</p></div>
                        <i class="bi bi-chat-dots text-muted-foreground"></i>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── Thread overlay (slides up) ── --}}
    <div x-show="threadOpen" x-cloak
         class="fixed inset-0 z-50 bg-background flex flex-col"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">

        {{-- Header --}}
        <div class="flex items-center gap-3 px-3 py-3 border-b border-border bg-white shadow-sm">
            <button type="button" class="w-9 h-9 rounded-lg flex items-center justify-center m-press" @click="closeThread()">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <span class="w-9 h-9 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                <template x-if="activeUser.avatar"><img :src="activeUser.avatar" class="w-9 h-9 object-cover" alt=""></template>
                <template x-if="!activeUser.avatar"><span class="text-sm font-bold text-primary" x-text="activeUser.initial"></span></template>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-foreground truncate mb-0" x-text="activeUser.name"></p>
                <p class="text-[11px] text-muted-foreground mb-0" x-text="connected ? @js(__('admin.msg_online_realtime')) : @js(__('admin.msg_delivers_instantly'))"></p>
            </div>
        </div>

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto px-3 py-4 space-y-2 bg-muted/20" id="m-thread-scroll">
            <template x-if="loadingThread">
                <div class="text-center text-muted-foreground text-sm py-10"><i class="bi bi-arrow-repeat"></i> {{ __('shared.loading') }}</div>
            </template>
            <template x-for="m in messages" :key="m.id">
                <div class="flex m-in" :class="m.mine ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[80%] rounded-2xl px-3.5 py-2 text-sm shadow-sm"
                         :class="m.mine ? 'bg-primary text-white rounded-br-md' : 'bg-white text-foreground border border-border rounded-bl-md'">
                        <p class="mb-0 whitespace-pre-wrap break-words" x-text="m.body"></p>
                        <span class="block text-[10px] mt-1 opacity-70" x-text="m.time"></span>
                    </div>
                </div>
            </template>
        </div>

        {{-- Composer --}}
        <form class="flex items-end gap-2 px-3 py-2.5 border-t border-border bg-white" @submit.prevent="send()"
              style="padding-bottom: calc(0.625rem + env(safe-area-inset-bottom));">
            <textarea x-model="draft" rows="1" placeholder="{{ __('admin.msg_placeholder') }}"
                      class="flex-1 resize-none px-3 py-2.5 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                      style="max-height:120px;"></textarea>
            <button type="submit" class="w-10 h-10 shrink-0 rounded-full bg-primary text-white flex items-center justify-center m-press disabled:opacity-50"
                    :disabled="sending || !draft.trim()">
                <i class="bi bi-send"></i>
            </button>
        </form>
    </div>
</div>

@push('scripts')
<script>
function mobileMessages() {
    return {
        threadOpen: false,
        activeUserId: null,
        activeUser: { name: '', avatar: null, initial: '' },
        messages: [],
        loadingThread: false,
        draft: '',
        sending: false,
        connected: false,

        threadBase: '{{ url('admin/club/'.$club->slug.'/messages/thread') }}',
        sendUrl: '{{ route('admin.club.messages.send', $club->slug) }}',

        init() {
            window.addEventListener('realtime:status', (e) => { this.connected = !!e.detail.connected; });
            window.addEventListener('realtime:message', (e) => this.onIncoming(e.detail || {}));
        },

        async openThread(userId, name, avatar) {
            this.activeUserId = userId;
            this.threadOpen = true;
            this.loadingThread = true;
            this.messages = [];
            const row = document.querySelector(`#m-conv-list [data-conv-id="${userId}"]`);
            this.activeUser = {
                name:    name || row?.dataset.name || 'Member',
                avatar:  avatar || row?.dataset.avatar || null,
                initial: (name || row?.dataset.name || 'M').charAt(0).toUpperCase(),
            };
            this.clearUnread(userId);
            try {
                const res = await fetch(`${this.threadBase}/${userId}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.activeUser = data.user; this.messages = data.messages; this.scrollDown(); }
            } catch (e) {
                window.showToast('error', @js(__('admin.msg_load_failed')));
            } finally {
                this.loadingThread = false;
            }
        },

        closeThread() { this.threadOpen = false; this.activeUserId = null; },

        async send() {
            const body = this.draft.trim();
            if (!body || this.sending) return;
            this.sending = true;
            try {
                const res = await fetch(this.sendUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Content-Type': 'application/json', 'Accept': 'application/json',
                    },
                    body: JSON.stringify({ recipient_id: this.activeUserId, message: body }),
                });
                const data = await res.json();
                if (data.success) { this.messages.push(data.data); this.draft = ''; this.scrollDown(); }
            } catch (e) {
                window.showToast('error', @js(__('admin.msg_send_failed')));
            } finally {
                this.sending = false;
            }
        },

        onIncoming(detail) {
            if (!detail.from_id) return;
            if (this.threadOpen && this.activeUserId === detail.from_id) {
                this.messages.push({ id: detail.id, body: detail.body, mine: false, time: '' });
                this.scrollDown();
            } else {
                this.bumpUnread(detail.from_id);
            }
        },

        bumpUnread(userId) {
            const badge = document.querySelector(`#m-conv-list [data-conv-id="${userId}"] .conv-unread`);
            if (!badge) return;
            badge.classList.remove('hidden');
            badge.textContent = (parseInt(badge.textContent) || 0) + 1;
        },

        clearUnread(userId) {
            const badge = document.querySelector(`#m-conv-list [data-conv-id="${userId}"] .conv-unread`);
            if (badge) { badge.classList.add('hidden'); badge.textContent = '0'; }
        },

        scrollDown() {
            this.$nextTick(() => {
                const el = document.getElementById('m-thread-scroll');
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
    };
}
</script>
@endpush
@endsection
