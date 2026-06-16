@extends('layouts.app')

@section('title', $club->club_name . ' · Chat')
@section('hide-navbar', true)

@section('content')
<div class="fixed inset-0 flex flex-col bg-background" x-data="clubRoom()" x-init="init()">

    {{-- Header --}}
    <div class="flex items-center gap-2 px-2 border-b border-border bg-white" style="padding-top: calc(0.5rem + env(safe-area-inset-top)); padding-bottom: 0.5rem;">
        <a href="{{ route('me.community') }}" class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center text-foreground m-press" aria-label="Back"><i class="bi bi-arrow-left text-xl"></i></a>
        <span class="shrink-0">
            @if($club->logo)<img src="{{ asset('storage/'.$club->logo) }}" class="w-9 h-9 rounded-xl object-cover" alt="">
            @else<span class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center text-sm font-bold">{{ strtoupper(substr($club->club_name,0,1)) }}</span>@endif
        </span>
        <button type="button" @click="openMembers()" class="min-w-0 flex-1 text-left m-press">
            <p class="text-[15px] font-semibold text-foreground truncate mb-0">{{ $club->club_name }}</p>
            <p class="text-[11px] text-muted-foreground mb-0 flex items-center gap-1">
                <i class="bi bi-people-fill text-[10px]"></i> <span x-text="memberCount ? memberCount + ' members' : 'Club room'"></span>
            </p>
        </button>
        <button type="button" @click="toggleMute()" class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center text-muted-foreground m-press" :aria-label="muted ? 'Unmute' : 'Mute'">
            <i class="bi" :class="muted ? 'bi-bell-slash' : 'bi-bell'"></i>
        </button>
        <div class="relative" x-data="{ o:false }">
            <button type="button" @click="o=!o" class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center text-muted-foreground m-press" aria-label="Options"><i class="bi bi-three-dots-vertical text-lg"></i></button>
            <div x-show="o" x-cloak @click.outside="o=false" class="absolute right-0 top-11 z-30 w-44 bg-white rounded-xl shadow-lg ring-1 ring-black/5 border border-border py-1">
                <button type="button" @click="o=false; openMembers()" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-foreground m-press text-left"><i class="bi bi-people"></i> Members</button>
                <button type="button" @click="o=false; toggleMute()" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-foreground m-press text-left"><i class="bi" :class="muted ? 'bi-bell' : 'bi-bell-slash'"></i> <span x-text="muted ? 'Unmute' : 'Mute'"></span></button>
                <button type="button" @click="o=false; leaveRoom()" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-red-600 m-press text-left"><i class="bi bi-box-arrow-right"></i> Exit chat</button>
            </div>
        </div>
    </div>

    {{-- Messages --}}
    <div class="flex-1 overflow-y-auto px-3 py-3 bg-muted/20" id="room-scroll">
        <template x-if="loading">
            <div class="space-y-2"><div class="m-skeleton h-9 w-2/3 rounded-2xl"></div><div class="m-skeleton h-9 w-1/2 rounded-2xl ml-auto"></div></div>
        </template>
        <template x-if="!loading && messages.length === 0">
            <p class="text-center text-[12px] text-muted-foreground py-8">Be the first to say something 👋</p>
        </template>
        <template x-for="(m, i) in messages" :key="m.id">
            <div class="flex gap-2 items-end" :class="(m.mine ? 'justify-end' : 'justify-start') + (firstOfRun(i) ? ' mt-2.5' : ' mt-0.5')">
                {{-- avatar for others: only on the first message of a run; a spacer keeps the rest aligned --}}
                <template x-if="!m.mine && firstOfRun(i)">
                    <span class="shrink-0">
                        <template x-if="m.sender && m.sender.avatar"><img :src="m.sender.avatar" class="w-7 h-7 rounded-full object-cover" alt=""></template>
                        <template x-if="!m.sender || !m.sender.avatar"><span class="w-7 h-7 rounded-full bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center text-[11px] font-bold" x-text="m.sender ? m.sender.initial : '?'"></span></template>
                    </span>
                </template>
                <template x-if="!m.mine && !firstOfRun(i)"><span class="w-7 shrink-0"></span></template>
                <div class="max-w-[80%] shadow-sm select-none overflow-hidden"
                     @click="!m.deleted && !m.pending && !m.kind && openActions(m)"
                     :class="(m.deleted
                                ? 'bg-muted text-muted-foreground border border-border'
                                : (m.mine ? 'bg-primary text-white' : 'bg-white text-foreground border border-border'))
                             + (m.mine ? ' rounded-2xl rounded-br-md' : ' rounded-2xl rounded-bl-md')
                             + ((m.kind === 'image' || m.kind === 'video') && m.attachment && !m.deleted ? ' p-1' : ' px-3 py-1.5 text-[15px] leading-snug')
                             + (m.pending ? ' opacity-60' : '')">
                    {{-- sender name (others): once per run --}}
                    <template x-if="!m.mine && !m.deleted && firstOfRun(i)">
                        <p class="text-[11px] font-bold mb-0.5" :style="`color:${nameColor(m.sender ? m.sender.id : 0)}`" x-text="m.sender ? m.sender.name : ''"></p>
                    </template>
                    <p x-show="m.deleted" class="mb-0 italic flex items-center gap-1.5"><i class="bi bi-slash-circle"></i> This message was deleted</p>
                    {{-- attachments --}}
                    <template x-if="!m.deleted && m.kind === 'image' && m.attachment">
                        <a :href="m.attachment.url" target="_blank" rel="noopener" class="block"><img :src="m.attachment.url" loading="lazy" class="rounded-xl block" style="max-height:300px;max-width:100%"></a>
                    </template>
                    <template x-if="!m.deleted && m.kind === 'video' && m.attachment">
                        <video :src="m.attachment.url" controls preload="metadata" playsinline class="rounded-xl block max-w-full" style="max-height:300px"></video>
                    </template>
                    <template x-if="!m.deleted && m.kind === 'audio' && m.attachment">
                        <div x-data="audioPlayer()" class="flex items-center gap-3" style="width:min(70vw,320px)">
                            <audio x-ref="audio" :src="m.attachment.url" preload="metadata" class="hidden"></audio>
                            <button type="button" @click="toggle()" class="relative shrink-0 w-10 h-10 rounded-full flex items-center justify-center active:scale-90 transition-transform" :class="m.mine ? 'bg-white text-primary' : 'bg-primary text-white'">
                                <i class="bi text-lg" :class="playing ? 'bi-pause-fill' : 'bi-play-fill'"></i>
                            </button>
                            <div class="flex-1 min-w-0">
                                <div x-ref="track" @pointerdown="startDrag($event)" @pointermove="onDrag($event)" @pointerup="endDrag($event)" class="flex items-center gap-[2px] h-7 cursor-pointer touch-none">
                                    <template x-for="(bar,i) in bars" :key="i"><span class="flex-1 rounded-full transition-all duration-150" :style="`height:${barPlayed(i)?bar:Math.max(16,bar*0.5)}%`" :class="barPlayed(i)?(m.mine?'bg-white':'bg-primary'):(m.mine?'bg-white/40':'bg-primary/25')"></span></template>
                                </div>
                                <span class="block text-[10px] opacity-80 mt-0.5" x-text="timeLabel"></span>
                            </div>
                        </div>
                    </template>
                    <template x-if="!m.deleted && m.kind === 'file' && m.attachment">
                        <a :href="m.attachment.url" :download="m.attachment.name" class="flex items-center gap-2.5">
                            <span class="w-9 h-9 rounded-lg bg-black/10 flex items-center justify-center shrink-0"><i class="bi bi-file-earmark-arrow-down text-lg"></i></span>
                            <span class="min-w-0"><span class="block truncate font-medium" x-text="m.attachment.name"></span><span class="block text-[11px] opacity-70" x-text="window.ChatAttach.humanSize(m.attachment.size)"></span></span>
                        </a>
                    </template>
                    <p x-show="!m.deleted && m.kind && !m.attachment" class="mb-0 italic flex items-center gap-1.5"><i class="bi bi-clock-history"></i> Attachment expired</p>
                    {{-- text + links + mentions --}}
                    <template x-if="!m.deleted && !m.kind">
                        <div>
                            <p class="mb-0 whitespace-pre-wrap break-words" x-html="window.renderRoomBody(m.body)"></p>
                            <div x-data="linkCard()" x-init="load(m.body)" x-show="preview" x-cloak>
                                <template x-if="preview && preview.type === 'video_embed'">
                                    <div class="mt-1.5 rounded-lg overflow-hidden bg-black/20" style="aspect-ratio:16/9;width:min(66vw,280px);max-width:100%"><iframe :src="preview.embed" class="w-full h-full" style="border:0" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>
                                </template>
                                <template x-if="preview && preview.type === 'link'">
                                    <a :href="preview.url" target="_blank" rel="noopener noreferrer" class="mt-1.5 block rounded-lg overflow-hidden border border-black/10 bg-black/5" style="width:min(66vw,280px);max-width:100%">
                                        <template x-if="preview.image"><img :src="preview.image" class="w-full max-h-32 object-cover" loading="lazy" alt=""></template>
                                        <div class="p-2"><p class="text-[11px] opacity-70 truncate" x-show="preview.title" x-text="preview.title"></p></div>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </template>
                    <span class="block text-[10px] opacity-70 text-right" :class="((m.kind==='image'||m.kind==='video') && m.attachment && !m.deleted) ? 'px-1.5 pb-1' : ''">
                        <span x-show="m.edited">edited · </span><span x-text="m.time"></span>
                        <i x-show="m.pending" class="bi bi-clock ml-0.5"></i>
                    </span>
                </div>
            </div>
        </template>
    </div>

    {{-- Editing banner --}}
    <div x-show="editing" x-cloak class="flex items-center gap-2 px-3 pt-2 bg-white text-xs text-primary">
        <i class="bi bi-pencil-square"></i><span class="font-medium">Editing message</span>
        <button type="button" @click="cancelEdit()" class="ml-auto text-muted-foreground"><i class="bi bi-x-lg"></i></button>
    </div>

    {{-- Composer --}}
    <form class="flex items-end gap-2 px-2.5 pt-3 border-t border-border bg-white" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));" @submit.prevent="editing ? saveEdit() : send()">
        <textarea x-ref="composer" x-model="draft" rows="1" placeholder="Message…" @keydown.enter.prevent="editing ? saveEdit() : send()"
                  class="flex-1 resize-none px-4 py-2.5 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-[15px]" style="max-height:120px;"></textarea>
        <button type="submit" class="w-11 h-11 shrink-0 rounded-full bg-primary text-white flex items-center justify-center m-press disabled:opacity-40" :disabled="sending || !draft.trim()">
            <i class="bi" :class="editing ? 'bi-check-lg' : 'bi-send-fill'"></i>
        </button>
    </form>

    {{-- Members sheet --}}
    <div x-show="membersOpen" x-cloak class="fixed inset-0 z-[70] flex flex-col justify-end" @click="membersOpen=false">
        <div class="absolute inset-0 bg-black/40" x-transition.opacity></div>
        <div class="relative bg-white rounded-t-3xl max-h-[80vh] flex flex-col pb-[calc(0.5rem+env(safe-area-inset-bottom))]" @click.stop
             x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
            <div class="w-11 h-1.5 rounded-full bg-gray-200 mx-auto my-2.5"></div>
            <p class="px-4 pb-2 text-sm font-bold text-foreground">Members <span class="text-muted-foreground font-normal" x-text="'· ' + members.length"></span></p>
            <div class="overflow-y-auto px-2">
                <template x-for="u in members" :key="u.id">
                    <div class="flex items-center gap-3 px-2 py-2.5 rounded-xl">
                        <template x-if="u.avatar"><img :src="u.avatar" class="w-10 h-10 rounded-full object-cover" alt=""></template>
                        <template x-if="!u.avatar"><span class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center font-bold" x-text="u.initial"></span></template>
                        <div class="flex-1 min-w-0">
                            <p class="text-[15px] font-medium text-foreground truncate mb-0" x-text="u.name + (u.me ? ' (you)' : '')"></p>
                            <p class="text-[11px] mb-0" x-show="u.blocked" style="color:#dc2626">Blocked</p>
                            <p class="text-[11px] text-amber-600 mb-0" x-show="u.banned && !u.blocked">Kicked (temporary)</p>
                        </div>
                        <template x-if="!u.me">
                            <div class="flex items-center gap-1">
                                <button type="button" @click="dm(u)" class="w-9 h-9 rounded-full bg-muted flex items-center justify-center text-primary m-press" title="Message"><i class="bi bi-chat-dots"></i></button>
                                <template x-if="isModerator">
                                    <div class="relative" x-data="{ mo:false }">
                                        <button type="button" @click="mo=!mo" class="w-9 h-9 rounded-full bg-muted flex items-center justify-center text-muted-foreground m-press"><i class="bi bi-shield"></i></button>
                                        <div x-show="mo" x-cloak @click.outside="mo=false" class="absolute right-0 bottom-11 z-30 w-40 bg-white rounded-xl shadow-lg ring-1 ring-black/5 border border-border py-1">
                                            <button type="button" @click="mo=false; kick(u)" class="w-full text-left px-3 py-2 text-sm text-amber-700 hover:bg-amber-50">Kick (1 hour)</button>
                                            <button type="button" x-show="!u.blocked" @click="mo=false; block(u)" class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50">Block permanently</button>
                                            <button type="button" x-show="u.blocked || u.banned" @click="mo=false; unblock(u)" class="w-full text-left px-3 py-2 text-sm text-green-700 hover:bg-green-50">Remove block</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Message action sheet (own/text) --}}
    <div x-show="actionMsg" x-cloak class="fixed inset-0 z-[70] flex flex-col justify-end" @click="actionMsg=null">
        <div class="absolute inset-0 bg-black/40"></div>
        <div class="relative bg-white rounded-t-3xl p-2 pb-[calc(1rem+env(safe-area-inset-bottom))]" @click.stop
             x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
            <div class="w-11 h-1.5 rounded-full bg-gray-200 mx-auto my-2.5"></div>
            <button type="button" @click="copyMsg(actionMsg)" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-2xl m-press text-left text-[15px]"><i class="bi bi-clipboard text-lg"></i> Copy</button>
            <template x-if="actionMsg && actionMsg.can_edit"><button type="button" @click="startEdit(actionMsg)" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-2xl m-press text-left text-[15px]"><i class="bi bi-pencil text-lg"></i> Edit</button></template>
            <template x-if="actionMsg && (actionMsg.mine || isModerator) && !actionMsg.deleted"><button type="button" @click="deleteMsg(actionMsg)" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-2xl m-press text-left text-[15px] text-red-600"><i class="bi bi-trash text-lg"></i> Delete</button></template>
            <button type="button" @click="actionMsg=null" class="w-full mt-1 px-4 py-3.5 rounded-2xl bg-muted/60 m-press text-[15px] font-medium">Cancel</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function clubRoom() {
    return {
        roomId: {{ $roomId }},
        clubId: {{ $club->id }},
        isModerator: {{ $isModerator ? 'true' : 'false' }},
        messages: [], loading: true, draft: '', sending: false, connected: false,
        muted: false, members: [], memberCount: 0, membersOpen: false,
        actionMsg: null, editing: false, editingId: null, lastId: null, _tmp: 0,

        urls: {
            thread: '{{ route('club-chat.thread', $club) }}',
            members: '{{ route('club-chat.members', $club) }}',
            mute: '{{ route('club-chat.mute', $club) }}',
            leave: '{{ route('club-chat.leave', $club) }}',
            base: '{{ url('club-chat/'.$club->id) }}',
            msgBase: '{{ url('messages') }}',
            startBase: '{{ url('messages/start') }}',
        },

        init() {
            window.addEventListener('realtime:status', (e) => this.connected = !!(e.detail && e.detail.connected));
            window.addEventListener('realtime:message', (e) => this.onIncoming(e.detail || {}));
            this.loadThread();
        },

        loadThread() {
            fetch(this.urls.thread, { headers: { 'Accept': 'application/json' } })
                .then((r) => r.json())
                .then((d) => { if (d.success) { this.messages = d.messages; this.isModerator = d.isModerator; this.scrollDown(); } })
                .catch(() => {})
                .finally(() => { this.loading = false; });
        },

        async send() {
            const body = this.draft.trim();
            if (!body || this.sending) return;
            this.sending = true;
            const tempId = 'tmp-' + (++this._tmp);
            const now = new Date();
            this.lastId = tempId;
            this.messages.push({ id: tempId, body, mine: true, time: now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }), pending: true });
            this.draft = ''; this.scrollDown();
            try {
                const r = await fetch(`${this.urls.msgBase}/${this.roomId}/send`, { method: 'POST', headers: this.headers(), body: JSON.stringify({ body }) });
                const d = await r.json();
                if (!d.success) throw new Error(d.message || 'fail');
                const m = this.messages.find((x) => x.id === tempId);
                if (m) { Object.assign(m, d.data); m.pending = false; }
            } catch (e) {
                const i = this.messages.findIndex((x) => x.id === tempId);
                if (i > -1) this.messages.splice(i, 1);
                window.showToast && window.showToast('error', 'Could not send.');
            } finally { this.sending = false; }
        },

        async attachFile(e) {
            const file = e.target.files && e.target.files[0]; e.target.value = '';
            if (!file) return;
            const t = file.type || '';
            const kind = t.startsWith('image/') ? 'image' : (t.startsWith('audio/') ? 'audio' : (t.startsWith('video/') ? 'video' : 'file'));
            const tempId = 'tmp-' + (++this._tmp);
            const previewUrl = kind !== 'file' ? URL.createObjectURL(file) : null;
            this.lastId = tempId;
            this.messages.push({ id: tempId, mine: true, time: '', kind, attachment: { url: previewUrl, name: file.name, size: file.size }, pending: true });
            this.scrollDown();
            const att = await window.ChatAttach.send(this.roomId, file);
            const i = this.messages.findIndex((x) => x.id === tempId);
            if (!att) { if (i > -1) this.messages.splice(i, 1); if (previewUrl) URL.revokeObjectURL(previewUrl); return; }
            if (i > -1) this.messages.splice(i, 1, att); else this.messages.push(att);
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            this.scrollDown();
        },

        onIncoming(d) {
            if (!d.club_room || d.conversation_id !== this.roomId) return;
            if (d.action === 'edit' || d.action === 'delete') {
                const m = this.messages.find((x) => x.id === d.id);
                if (m) { if (d.action === 'delete') { m.deleted = true; m.body = null; } else { m.body = d.body; m.edited = true; } }
                return;
            }
            const msg = { id: d.id, mine: false, time: d.time || '', sender: { id: d.from_id, name: d.from_name, avatar: d.from_avatar, initial: (d.from_name || 'U').charAt(0).toUpperCase() } };
            if (d.action === 'file') { msg.kind = d.kind; msg.attachment = d.attachment; } else { msg.body = d.body; }
            this.lastId = d.id;
            this.messages.push(msg);
            this.scrollDown();
            if (!this.muted && window.playMessageSound) window.playMessageSound();
        },

        /* members + DM + moderation */
        openMembers() { this.membersOpen = true; this.fetchMembers(); },
        fetchMembers() {
            fetch(this.urls.members, { headers: { 'Accept': 'application/json' } }).then((r) => r.json())
                .then((d) => { if (d.success) { this.members = d.members; this.memberCount = d.members.length; this.isModerator = d.isModerator; } });
        },
        async dm(u) {
            try {
                const r = await fetch(`${this.urls.startBase}/${u.id}`, { method: 'POST', headers: this.headers() });
                const d = await r.json();
                if (d.success) window.location.href = `${this.urls.msgBase}/${d.conversation_id}`;
            } catch (e) { window.showToast && window.showToast('error', 'Could not open chat.'); }
        },
        async kick(u) {
            if (!(await window.confirmAction({ title: 'Kick member?', message: `${u.name} won't be able to post for 1 hour.`, type: 'danger', confirmText: 'Kick' }))) return;
            await this.mod(`${this.urls.base}/kick/${u.id}`, { minutes: 60 }); u.banned = true; window.showToast && window.showToast('success', 'Member kicked for 1 hour');
        },
        async block(u) {
            if (!(await window.confirmAction({ title: 'Block member?', message: `${u.name} will be permanently blocked from this chat.`, type: 'danger', confirmText: 'Block' }))) return;
            await this.mod(`${this.urls.base}/block/${u.id}`); u.blocked = true; window.showToast && window.showToast('success', 'Member blocked');
        },
        async unblock(u) {
            await this.mod(`${this.urls.base}/unblock/${u.id}`); u.blocked = false; u.banned = false; window.showToast && window.showToast('success', 'Block removed');
        },
        async mod(url, body) {
            const r = await fetch(url, { method: 'POST', headers: this.headers(), body: body ? JSON.stringify(body) : undefined });
            const d = await r.json();
            if (!d.success) window.showToast && window.showToast('error', 'Action failed');
        },

        /* mute + leave */
        async toggleMute() {
            this.muted = !this.muted;
            await fetch(this.urls.mute, { method: 'POST', headers: this.headers(), body: JSON.stringify({ muted: this.muted }) }).catch(() => {});
            window.showToast && window.showToast('info', this.muted ? 'Muted' : 'Unmuted');
        },
        async leaveRoom() {
            if (!(await window.confirmAction({ title: 'Exit chat?', message: 'You will leave this club chat. You can rejoin from Club Chat anytime.', type: 'danger', confirmText: 'Exit' }))) return;
            await fetch(this.urls.leave, { method: 'POST', headers: this.headers() }).catch(() => {});
            window.location.href = '{{ route('me.community') }}';
        },

        /* message actions */
        openActions(m) { this.actionMsg = m; },
        copyMsg(m) { this.actionMsg = null; navigator.clipboard?.writeText(m.body || ''); window.showToast && window.showToast('success', 'Copied'); },
        startEdit(m) { this.actionMsg = null; this.editing = true; this.editingId = m.id; this.draft = m.body || ''; this.$nextTick(() => this.$refs.composer?.focus()); },
        cancelEdit() { this.editing = false; this.editingId = null; this.draft = ''; },
        async saveEdit() {
            const body = this.draft.trim(); if (!body || this.sending) return; this.sending = true;
            try {
                const r = await fetch(`${this.urls.msgBase}/${this.roomId}/messages/${this.editingId}`, { method: 'PATCH', headers: this.headers(), body: JSON.stringify({ body }) });
                const d = await r.json();
                if (!d.success) { window.showToast && window.showToast('error', d.message || 'Could not edit.'); return; }
                const m = this.messages.find((x) => x.id === this.editingId); if (m) { m.body = body; m.edited = true; }
                this.cancelEdit();
            } catch (e) { window.showToast && window.showToast('error', 'Could not edit.'); } finally { this.sending = false; }
        },
        async deleteMsg(m) {
            this.actionMsg = null;
            if (!(await window.confirmAction({ title: 'Delete message?', message: 'This message will be removed for everyone.', type: 'danger', confirmText: 'Delete' }))) return;
            try {
                const r = await fetch(`${this.urls.msgBase}/${this.roomId}/messages/${m.id}`, { method: 'DELETE', headers: this.headers() });
                const d = await r.json();
                if (!d.success) { window.showToast && window.showToast('error', 'Could not delete.'); return; }
                m.deleted = true; m.body = null;
            } catch (e) { window.showToast && window.showToast('error', 'Could not delete.'); }
        },

        // Group consecutive messages from the same sender (compact group layout).
        sameSender(i) {
            if (i <= 0) return false;
            const a = this.messages[i - 1], b = this.messages[i];
            if (!a || !b || a.mine !== b.mine) return false;
            return a.mine ? true : (a.sender && b.sender && a.sender.id === b.sender.id);
        },
        firstOfRun(i) { return !this.sameSender(i); },

        nameColor(id) { const h = (id * 47) % 360; return `hsl(${h} 55% 45%)`; },
        scrollDown() { this.$nextTick(() => { const el = document.getElementById('room-scroll'); if (el) el.scrollTop = el.scrollHeight; }); },
        headers() { return { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' }; },
    };
}
</script>
@endpush
@endsection
