{{-- Mobile chat: header-icon dropdown + Facebook-style floating chat heads.
     Self-contained Alpine component. Its overlay uses fixed positioning with a
     high z-index (above the z-40 header/bottom-nav) — the component sits as a
     sibling of the header inside the shell root, which is not a stacking
     context, so the fixed layers escape to the viewport. Reuses the Messenger
     JSON endpoints and realtime:message MQTT events — no reloads, instant. --}}
<div x-data="mobileChatHeads()">

    {{-- ── Dropdown: backdrop ── --}}
    <div x-show="open" x-cloak @click="open = false"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="bg-black/10"
         style="position:fixed; inset:0; z-index:120;"></div>

    {{-- ── Dropdown: panel (anchored under the header chat icon) ── --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
         class="bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 border border-border/60 overflow-hidden"
         style="position:fixed; top:3.75rem; right:0.5rem; z-index:121; width:min(360px, calc(100vw - 1rem)); max-width:calc(100vw - 1rem); transform-origin:top right;">

        <div class="flex items-center justify-between px-4 py-3 border-b border-border">
            <div>
                <h6 class="text-sm font-bold text-foreground mb-0">Messages</h6>
                <p class="text-[10px] text-muted-foreground mb-0 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full" :class="connected ? 'bg-green-500' : 'bg-gray-300'"></span>
                    <span x-text="connected ? 'Connected · realtime' : 'Delivers instantly'"></span>
                </p>
            </div>
            <a href="{{ route('messages.index') }}" class="text-xs text-primary font-medium hover:underline">Open inbox</a>
        </div>

        <div class="max-h-[60vh] overflow-y-auto">
            {{-- Loading --}}
            <template x-if="loading">
                <div class="p-3 space-y-2">
                    <div class="m-skeleton h-12 rounded-xl"></div>
                    <div class="m-skeleton h-12 rounded-xl"></div>
                    <div class="m-skeleton h-12 rounded-xl"></div>
                </div>
            </template>

            {{-- Empty --}}
            <template x-if="!loading && chats.length === 0">
                <div class="px-4 py-10 text-center">
                    <i class="bi bi-chat-heart text-4xl text-gray-200 m-float inline-block"></i>
                    <p class="text-sm text-muted-foreground mt-3 mb-0">No chats yet.</p>
                </div>
            </template>

            {{-- List --}}
            <div class="mobile-stagger">
                <template x-for="c in chats" :key="c.id">
                    <button type="button" @click="openHead(c)"
                            class="w-full flex items-center gap-3 px-3 py-2.5 text-left m-press border-b border-border/60 last:border-0"
                            :class="c.unread_count > 0 ? 'bg-accent/40' : 'bg-white'">
                        <span class="relative shrink-0">
                            <template x-if="c.partner.avatar"><img :src="c.partner.avatar" class="w-11 h-11 rounded-full object-cover" alt=""></template>
                            <template x-if="!c.partner.avatar"><span class="w-11 h-11 rounded-full bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center font-bold" x-text="c.partner.initial"></span></template>
                            <span x-show="c.unread_count > 0" class="absolute -top-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-primary ring-2 ring-white"></span>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-[14px] font-semibold truncate" :class="c.unread_count > 0 ? 'text-primary' : 'text-foreground'" x-text="c.partner.name"></span>
                                <span class="text-[10px] text-muted-foreground shrink-0" x-text="c.last_at_human"></span>
                            </span>
                            <span class="block text-[12px] truncate mt-0.5" dir="auto" :class="c.unread_count > 0 ? 'font-semibold text-foreground' : 'text-muted-foreground'">
                                <span x-show="c.last_mine">You: </span><span x-text="c.last_body || 'New message'"></span>
                            </span>
                        </span>
                        <span x-show="c.unread_count > 0" class="shrink-0 min-w-[18px] h-[18px] px-1 rounded-full bg-primary text-white text-[10px] font-bold flex items-center justify-center"
                              x-text="c.unread_count > 99 ? '99+' : c.unread_count"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- ── Floating chat heads (collapsed bubbles) ── --}}
    <div x-show="heads.length > 0 && !anyExpanded()" x-cloak
         class="flex flex-col-reverse gap-3"
         style="position:fixed; right:0.75rem; z-index:122; bottom: calc(5.5rem + env(safe-area-inset-bottom));">
        <template x-for="h in heads" :key="'head-' + h.id">
            <div class="relative m-in-pop">
                <button type="button" @click="expand(h)"
                        class="w-14 h-14 rounded-full shadow-lg shadow-primary/30 ring-2 ring-white m-press overflow-hidden flex items-center justify-center bg-gradient-to-br from-primary to-purple-400 text-white">
                    <template x-if="h.partner.avatar"><img :src="h.partner.avatar" class="w-full h-full object-cover" alt=""></template>
                    <template x-if="!h.partner.avatar"><span class="text-lg font-bold" x-text="h.partner.initial"></span></template>
                </button>
                <span x-show="h.unread > 0" class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 rounded-full bg-destructive text-white text-[10px] font-bold flex items-center justify-center ring-2 ring-white"
                      x-text="h.unread > 99 ? '99+' : h.unread"></span>
                <button type="button" @click.stop="closeHead(h)"
                        class="absolute -top-1 -left-1 w-5 h-5 rounded-full bg-white text-muted-foreground border border-border shadow flex items-center justify-center text-[10px]">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </template>
    </div>

    {{-- ── Expanded chat window (one at a time) ── --}}
    <template x-for="h in heads" :key="'win-' + h.id">
        <div x-show="h.expanded" x-cloak
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-full" x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-full"
             class="flex flex-col bg-background overflow-hidden"
             style="position:fixed; inset:0; z-index:123;">

            {{-- Page header --}}
            <div class="flex items-center gap-2 px-2 border-b border-border bg-white" style="padding-top: calc(0.625rem + env(safe-area-inset-top)); padding-bottom: 0.625rem;">
                <button type="button" @click="h.expanded = false" class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center text-foreground m-press" aria-label="Back"><i class="bi bi-arrow-left text-xl"></i></button>
                <span class="shrink-0">
                    <template x-if="h.partner.avatar"><img :src="h.partner.avatar" class="w-9 h-9 rounded-full object-cover" alt=""></template>
                    <template x-if="!h.partner.avatar"><span class="w-9 h-9 rounded-full bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center text-sm font-bold" x-text="h.partner.initial"></span></template>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[15px] font-semibold text-foreground truncate mb-0" x-text="h.partner.name"></p>
                    <p class="text-[11px] text-muted-foreground mb-0 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full" :class="connected ? 'bg-green-500' : 'bg-gray-300'"></span>
                        <span x-text="connected ? 'Active now' : 'Encrypted chat'"></span>
                    </p>
                </div>
                {{-- Options menu --}}
                <div class="relative shrink-0" x-data="{ menu:false }" @click.outside="menu=false">
                    <button type="button" @click="menu=!menu" class="w-10 h-10 rounded-full flex items-center justify-center text-muted-foreground m-press" aria-label="Options"><i class="bi bi-three-dots-vertical text-lg"></i></button>
                    <div x-show="menu" x-cloak
                         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                         class="absolute right-0 top-11 z-20 w-52 bg-white rounded-xl shadow-lg border border-gray-100 py-1">
                        <a :href="'{{ url('u') }}/' + (h.partner.slug || '')" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-foreground hover:bg-muted transition-colors">
                            <i class="bi bi-person"></i> View profile
                        </a>
                        <button type="button" @click="menu=false; clearHistory(h)" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-foreground hover:bg-muted transition-colors">
                            <i class="bi bi-eraser"></i> Clear chat history
                        </button>
                        <button type="button" @click="menu=false; deleteChat(h)" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-foreground hover:bg-muted transition-colors">
                            <i class="bi bi-trash"></i> Delete chat
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button" x-show="!h.partner.blocked" @click="menu=false; blockPartner(h)" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                            <i class="bi bi-slash-circle"></i> Block <span x-text="(h.partner.name||'').split(' ')[0]"></span>
                        </button>
                        <button type="button" x-show="h.partner.blocked" @click="menu=false; unblockPartner(h)" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-foreground hover:bg-muted transition-colors">
                            <i class="bi bi-arrow-counterclockwise"></i> Unblock <span x-text="(h.partner.name||'').split(' ')[0]"></span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 bg-muted/20" :id="'chathead-scroll-' + h.id">
                <template x-if="h.loadingThread">
                    <div class="space-y-2">
                        <div class="m-skeleton h-9 w-2/3 rounded-2xl"></div>
                        <div class="m-skeleton h-9 w-1/2 rounded-2xl ml-auto"></div>
                        <div class="m-skeleton h-9 w-3/5 rounded-2xl"></div>
                    </div>
                </template>
                <template x-if="!h.loadingThread && h.messages.length === 0">
                    <p class="text-center text-[12px] text-muted-foreground py-8">Say hello 👋</p>
                </template>
                <template x-for="m in h.messages" :key="m.id">
                    <div class="flex gap-1.5 items-end" :class="m.mine ? 'justify-end' : 'justify-start'">
                        {{-- Small avatar of the person texting (incoming only) --}}
                        <template x-if="!m.mine">
                            <span class="w-6 h-6 rounded-full overflow-hidden shrink-0 self-end mb-0.5">
                                <template x-if="h.partner.avatar"><img :src="h.partner.avatar" class="w-6 h-6 rounded-full object-cover" alt=""></template>
                                <template x-if="!h.partner.avatar"><span class="w-6 h-6 rounded-full bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center text-[10px] font-bold" x-text="h.partner.initial"></span></template>
                            </span>
                        </template>
                        <div class="max-w-[80%] rounded-2xl shadow-sm select-none overflow-hidden"
                             @click="!m.deleted && !m.pending && !m.kind && openActions(h, m)"
                             :style="(m.kind === 'audio' && m.attachment && !m.deleted) ? 'width:min(78vw,360px)' : ''"
                             :class="(m.deleted
                                        ? 'bg-muted text-muted-foreground border border-border ' + (m.mine ? 'rounded-br-md' : 'rounded-bl-md')
                                        : (m.mine ? 'bg-primary text-white rounded-br-md' : 'bg-white text-foreground border border-border rounded-bl-md'))
                                     + ((m.kind === 'image' || m.kind === 'video') && m.attachment && !m.deleted ? ' p-1' : ' px-3.5 py-2 text-[15px]')
                                     + (m.id === h.lastAddedId ? ' m-in-pop' : '') + (m.pending ? ' opacity-60' : '')">
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
                                    <p class="mb-0 whitespace-pre-wrap break-words" dir="auto" x-html="window.LinkPreview.linkifyHtml(m.body)"></p>
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
                        {{-- My own avatar (outgoing) --}}
                        <template x-if="m.mine">
                            <span class="w-6 h-6 rounded-full overflow-hidden shrink-0 self-end mb-0.5">
                                @if(Auth::user()->profile_picture)
                                    <img src="{{ asset('storage/'.Auth::user()->profile_picture) }}?v={{ optional(Auth::user()->updated_at)->timestamp }}" class="w-6 h-6 rounded-full object-cover" alt="">
                                @else
                                    <span class="w-6 h-6 rounded-full bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center text-[10px] font-bold">{{ strtoupper(mb_substr(Auth::user()->full_name ?? 'U', 0, 1)) }}</span>
                                @endif
                            </span>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Editing banner --}}
            <div x-show="h.editing" x-cloak class="flex items-center gap-2 px-3 pt-2 bg-white text-xs text-primary">
                <i class="bi bi-pencil-square"></i>
                <span class="font-medium">Editing message</span>
                <button type="button" @click="cancelEdit(h)" class="ml-auto text-muted-foreground"><i class="bi bi-x-lg"></i></button>
            </div>

            {{-- Composer --}}
            <form class="flex items-end gap-2 px-2.5 pt-3 border-t border-border bg-white" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));" @submit.prevent="h.editing ? saveEdit(h) : send(h)">
                <label class="w-11 h-11 shrink-0 rounded-full text-muted-foreground flex items-center justify-center m-press cursor-pointer" :class="h.editing ? 'opacity-40 pointer-events-none' : ''" aria-label="Attach file">
                    <i class="bi bi-paperclip text-lg"></i>
                    <input type="file" class="hidden" @change="attachFile($event, h)">
                </label>
                <textarea x-model="h.draft" rows="1" placeholder="Aa" dir="auto" @keydown.enter.prevent="h.editing ? saveEdit(h) : send(h)"
                          class="flex-1 resize-none px-4 py-2.5 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-[15px]" style="max-height:120px;"></textarea>
                <button type="submit" class="w-11 h-11 shrink-0 rounded-full bg-primary text-white flex items-center justify-center m-press disabled:opacity-40" :disabled="h.sending || !(h.draft || '').trim()">
                    <i class="bi" :class="h.editing ? 'bi-check-lg' : 'bi-send-fill'"></i>
                </button>
            </form>
        </div>
    </template>

    {{-- ─────────── Message action sheet ─────────── --}}
    <div x-show="actionMsg" x-cloak class="flex flex-col justify-end" style="position:fixed; inset:0; z-index:130;" @click="actionMsg = null">
        <div class="absolute inset-0 bg-black/40"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
        <div class="relative bg-white rounded-t-3xl p-2 pb-[calc(1rem+env(safe-area-inset-bottom))]" @click.stop
             x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
            <div class="w-11 h-1.5 rounded-full bg-gray-200 mx-auto my-2.5"></div>
            <button type="button" @click="copyMsg(actionMsg)" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-2xl m-press text-left text-[15px] text-foreground"><i class="bi bi-clipboard text-lg"></i> Copy</button>
            <template x-if="actionMsg && actionMsg.can_edit">
                <button type="button" @click="startEdit(actionHead, actionMsg)" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-2xl m-press text-left text-[15px] text-foreground"><i class="bi bi-pencil text-lg"></i> Edit</button>
            </template>
            <button type="button" @click="deleteForMe(actionHead, actionMsg)" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-2xl m-press text-left text-[15px] text-foreground"><i class="bi bi-eye-slash text-lg"></i> Delete for me</button>
            <template x-if="actionMsg && actionMsg.mine && !actionMsg.deleted">
                <button type="button" @click="deleteMsg(actionHead, actionMsg)" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-2xl m-press text-left text-[15px] text-red-600"><i class="bi bi-trash text-lg"></i> Delete for everyone</button>
            </template>
            <button type="button" @click="actionMsg = null" class="w-full mt-1 px-4 py-3.5 rounded-2xl bg-muted/60 m-press text-[15px] font-medium text-foreground">Cancel</button>
        </div>
    </div>

    {{-- ─────────── In-chat confirmation (renders above the chat overlay) ─────────── --}}
    <div x-show="chatConfirm.open" x-cloak class="flex items-center justify-center px-6" style="position:fixed; inset:0; z-index:140;" @click="resolveConfirm(false)">
        <div class="absolute inset-0 bg-black/50"
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>
        <div class="relative w-full max-w-sm bg-white rounded-2xl p-5 shadow-2xl" @click.stop
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <p class="text-base font-semibold text-foreground" x-text="chatConfirm.title"></p>
            <p class="text-sm text-muted-foreground mt-1.5" x-text="chatConfirm.message"></p>
            <div class="flex gap-2 mt-5">
                <button type="button" @click="resolveConfirm(false)" class="m-press flex-1 py-2.5 rounded-lg bg-muted text-foreground text-sm font-semibold">Cancel</button>
                <button type="button" @click="resolveConfirm(true)" class="m-press flex-1 py-2.5 rounded-lg bg-destructive text-white text-sm font-semibold" x-text="chatConfirm.confirmText"></button>
            </div>
        </div>
    </div>

</div>

@once
@push('scripts')
<script>
function mobileChatHeads() {
    return {
        open: false,
        loading: false,
        chats: [],
        heads: [],
        connected: false,
        _tmp: 0,
        actionMsg: null, actionHead: null,
        chatConfirm: { open: false, title: '', message: '', confirmText: 'Confirm', _resolve: null },

        urls: {
            conversations: '{{ route('messages.conversations') }}',
            base: '{{ url('messages') }}',
            wall: '{{ url('u') }}',
        },

        init() {
            window.addEventListener('mobile-chat:toggle', () => this.toggleDropdown());
            window.addEventListener('realtime:status', (e) => this.connected = !!(e.detail && e.detail.connected));
            window.addEventListener('realtime:message', (e) => this.onIncoming(e.detail || {}));
            // Preload the conversation list so the first open is instant.
            this.load();
            // Lets the global realtime handler skip its toast/badge/sound for a
            // conversation the user is actively reading in an expanded chat head.
            window.__chatHeadActive = (cid) => !document.hidden && this.heads.some(h => h.id === cid && h.expanded);
        },

        anyExpanded() { return this.heads.some(h => h.expanded); },

        // In-chat confirmation (renders inside the chat overlay so it can never
        // be hidden behind it). Returns a Promise<boolean>.
        askConfirm(opts) {
            return new Promise((resolve) => {
                this.chatConfirm = {
                    open: true,
                    title: opts.title || 'Are you sure?',
                    message: opts.message || '',
                    confirmText: opts.confirmText || 'Confirm',
                    _resolve: resolve,
                };
            });
        },
        resolveConfirm(val) {
            const r = this.chatConfirm._resolve;
            this.chatConfirm.open = false;
            this.chatConfirm._resolve = null;
            if (r) r(val);
        },

        toggleDropdown() {
            this.open = !this.open;
            if (this.open) this.load();
        },

        load() {
            // Skeleton only when we have nothing to show yet; otherwise refresh
            // silently in the background so reopening is instant (no flash).
            if (this.chats.length === 0) this.loading = true;
            fetch(this.urls.conversations, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then((r) => r.json())
                .then((d) => { this.chats = (d && d.conversations) || []; })
                .catch(() => {})
                .finally(() => { this.loading = false; });
        },

        openHead(c) {
            this.open = false;
            let h = this.heads.find((x) => x.id === c.id);
            if (!h) {
                this.heads.push({ id: c.id, partner: c.partner, messages: [], draft: '', loaded: false, loadingThread: false, sending: false, expanded: false, unread: c.unread_count || 0, lastAddedId: null, editing: false, editingId: null });
                if (this.heads.length > 4) this.heads.shift();
                // Re-read from the array so we mutate the reactive proxy, not the
                // raw object we pushed — otherwise async thread loads never render.
                h = this.heads.find((x) => x.id === c.id);
            }
            this.expand(h);
        },

        expand(h) {
            this.heads.forEach((x) => { x.expanded = false; });
            h.expanded = true;
            if (h.unread > 0) {
                if (window.updateChatBadge) window.updateChatBadge(-h.unread);
                h.unread = 0;
            }
            if (!h.loaded) this.loadThread(h);
            else { this.scrollDown(h); this.markRead(h.id); }
        },

        closeHead(h) { this.heads = this.heads.filter((x) => x.id !== h.id); },

        async deleteChat(h) {
            const ok = await this.askConfirm({ title: 'Delete chat?', message: 'This chat and its history will be removed from your list. It reappears if a new message arrives.', type: 'danger', confirmText: 'Delete' });
            if (!ok) return;
            try {
                const r = await fetch(`${this.urls.base}/${h.id}`, { method: 'DELETE', headers: this.headers() });
                const d = await r.json();
                if (!d.success) { window.showToast && window.showToast('error', 'Could not delete chat.'); return; }
                this.chats = this.chats.filter((c) => c.id !== h.id);
                this.closeHead(h);
                window.showToast && window.showToast('success', 'Chat deleted');
            } catch (e) { window.showToast && window.showToast('error', 'Could not delete chat.'); }
        },

        async clearHistory(h) {
            const ok = await this.askConfirm({ title: 'Clear chat history?', message: 'All earlier messages will be permanently removed from your chat. This cannot be undone.', type: 'danger', confirmText: 'Clear' });
            if (!ok) return;
            try {
                const r = await fetch(`${this.urls.base}/${h.id}`, { method: 'DELETE', headers: this.headers() });
                const d = await r.json();
                if (!d.success) { window.showToast && window.showToast('error', 'Could not clear history.'); return; }
                h.messages.splice(0, h.messages.length);   // empty the screen instantly (reactive)
                h.loaded = true;                           // don't refetch the cleared thread
                const c = this.chats.find((x) => x.id === h.id);
                if (c) { c.last_body = null; c.last_at_human = null; }
                window.showToast && window.showToast('success', 'Chat history cleared');
            } catch (e) { window.showToast && window.showToast('error', 'Could not clear history.'); }
        },

        async blockPartner(h) {
            const name = (h.partner.name || '').split(' ')[0] || 'this person';
            if (!h.partner.slug) { window.showToast && window.showToast('error', 'Cannot block this user.'); return; }
            const ok = await this.askConfirm({ title: 'Block ' + name + '?', message: 'They will not be able to see your wall or interact with you, and you will not see their posts. You can unblock them from their profile.', type: 'danger', confirmText: 'Block' });
            if (!ok) return;
            try {
                const r = await fetch(`${this.urls.wall}/${h.partner.slug}/block`, { method: 'POST', headers: this.headers() });
                const d = await r.json();
                if (d.success === false) { window.showToast && window.showToast('error', d.message || 'Could not block.'); return; }
                h.partner.blocked = true;
                window.showToast && window.showToast('success', name + ' blocked');
            } catch (e) { window.showToast && window.showToast('error', 'Could not block.'); }
        },

        async unblockPartner(h) {
            const name = (h.partner.name || '').split(' ')[0] || 'this person';
            if (!h.partner.slug) return;
            try {
                const r = await fetch(`${this.urls.wall}/${h.partner.slug}/block`, { method: 'DELETE', headers: this.headers() });
                const d = await r.json();
                if (d.success === false) { window.showToast && window.showToast('error', d.message || 'Could not unblock.'); return; }
                h.partner.blocked = false;
                window.showToast && window.showToast('success', name + ' unblocked');
            } catch (e) { window.showToast && window.showToast('error', 'Could not unblock.'); }
        },

        async loadThread(h) {
            h.loadingThread = true;
            try {
                const r = await fetch(`${this.urls.base}/${h.id}/thread`, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                if (d.success) { h.partner = d.partner; h.messages = d.messages; h.loaded = true; this.scrollDown(h); }
            } catch (e) { window.showToast && window.showToast('error', 'Could not load conversation.'); }
            finally { h.loadingThread = false; }
        },

        async send(h) {
            const body = (h.draft || '').trim();
            if (!body || h.sending) return;
            h.sending = true;
            // Optimistic append for an instant feel; reconcile on response.
            const tempId = 'tmp-' + (++this._tmp);
            const now = new Date();
            const time = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            h.lastAddedId = tempId;
            h.messages.push({ id: tempId, body, mine: true, time, pending: true });
            h.draft = '';
            this.scrollDown(h);
            try {
                const r = await fetch(`${this.urls.base}/${h.id}/send`, { method: 'POST', headers: this.headers(), body: JSON.stringify({ body }) });
                const d = await r.json();
                if (!d.success) throw new Error();
                const msg = h.messages.find((m) => m.id === tempId);
                if (msg) { Object.assign(msg, d.data); msg.pending = false; }
                h.lastAddedId = d.data.id;
                this.touchChat({ id: h.id, partner: h.partner, last_body: body, last_mine: true });
            } catch (e) {
                const idx = h.messages.findIndex((m) => m.id === tempId);
                if (idx > -1) h.messages.splice(idx, 1);
                window.showToast && window.showToast('error', 'Could not send.');
            } finally { h.sending = false; }
        },

        // Upsert a conversation into the dropdown list and move it to the top —
        // keeps the top-bar chat live without a refetch.
        touchChat(conv) {
            let c = this.chats.find((x) => x.id === conv.id);
            if (c) {
                c.last_body = conv.last_body;
                c.last_mine = conv.last_mine;
                c.last_at_human = 'now';
                if (conv.unreadDelta) c.unread_count = Math.max(0, (c.unread_count || 0) + conv.unreadDelta);
                this.chats = [c, ...this.chats.filter((x) => x.id !== conv.id)];
            } else {
                this.chats = [{
                    id: conv.id, partner: conv.partner,
                    last_body: conv.last_body, last_mine: conv.last_mine,
                    last_at_human: 'now', unread_count: Math.max(0, conv.unreadDelta || 0),
                }, ...this.chats];
            }
        },

        onIncoming(detail) {
            if (!detail.conversation_id) return;
            const h = this.heads.find((x) => x.id === detail.conversation_id);
            const active = h && h.expanded && !document.hidden;

            // Edit / delete patch the message in place, silently.
            if (detail.action === 'edit' || detail.action === 'delete') {
                if (h) {
                    const m = h.messages.find((x) => x.id === detail.id);
                    if (m) {
                        if (detail.action === 'delete') { m.deleted = true; m.body = null; m.can_edit = false; }
                        else { m.body = detail.body; m.edited = true; }
                    }
                }
                const c = this.chats.find((x) => x.id === detail.conversation_id);
                if (c && detail.is_latest) c.last_body = detail.action === 'delete' ? 'This message was deleted' : detail.body;
                return;
            }

            // New message or attachment.
            if (h) {
                h.lastAddedId = detail.id;
                if (detail.action === 'file') {
                    h.messages.push({ id: detail.id, mine: false, time: detail.time || '', kind: detail.kind, attachment: detail.attachment });
                } else {
                    h.messages.push({ id: detail.id, body: detail.body, mine: false, time: detail.time || '' });
                }
                if (active) { this.scrollDown(h); this.markRead(h.id); }
                else { h.unread = (h.unread || 0) + 1; }
            }

            // Always refresh the dropdown list preview (even when no head is open).
            const label = detail.action === 'file'
                ? (detail.kind === 'image' ? '📷 Photo' : '📎 ' + ((detail.attachment && detail.attachment.name) || 'file'))
                : detail.body;
            this.touchChat({
                id: detail.conversation_id,
                partner: { id: detail.from_id, name: detail.from_name, avatar: detail.from_avatar, initial: (detail.from_name || 'U').charAt(0).toUpperCase() },
                last_body: label, last_mine: false,
                unreadDelta: active ? 0 : 1,
            });
        },

        async attachFile(e, h) {
            const file = e.target.files && e.target.files[0];
            e.target.value = '';
            if (!file || !h) return;
            const t = file.type || '';
            const kind = t.startsWith('image/') ? 'image' : (t.startsWith('audio/') ? 'audio' : (t.startsWith('video/') ? 'video' : 'file'));
            const tempId = 'tmp-att-' + Date.now();
            const previewUrl = kind !== 'file' ? URL.createObjectURL(file) : null;
            h.lastAddedId = tempId;
            h.messages.push({ id: tempId, mine: true, time: '', kind, attachment: { url: previewUrl, name: file.name, size: file.size }, pending: true });
            this.scrollDown(h);
            const att = await window.ChatAttach.send(h.id, file);
            const i = h.messages.findIndex((m) => m.id === tempId);
            if (!att) { if (i > -1) h.messages.splice(i, 1); if (previewUrl) URL.revokeObjectURL(previewUrl); return; }
            h.lastAddedId = att.id;
            if (i > -1) h.messages.splice(i, 1, att); else h.messages.push(att);
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            this.scrollDown(h);
            this.touchChat({ id: h.id, partner: h.partner, last_body: att.kind === 'image' ? '📷 Photo' : '📎 ' + ((att.attachment && att.attachment.name) || 'file'), last_mine: true });
        },

        /* ── message actions (copy / edit / delete) ── */
        openActions(h, m) { this.actionHead = h; this.actionMsg = m; },

        copyMsg(m) {
            this.actionMsg = null;
            navigator.clipboard?.writeText(m.body || '').then(
                () => window.showToast && window.showToast('success', 'Message copied'),
                () => window.showToast && window.showToast('error', 'Could not copy'),
            );
        },

        startEdit(h, m) {
            this.actionMsg = null;
            if (!h) return;
            h.editing = true; h.editingId = m.id; h.draft = m.body || '';
        },
        cancelEdit(h) { h.editing = false; h.editingId = null; h.draft = ''; },

        async saveEdit(h) {
            const body = (h.draft || '').trim();
            if (!body || h.sending) return;
            h.sending = true;
            try {
                const r = await fetch(`${this.urls.base}/${h.id}/messages/${h.editingId}`, { method: 'PATCH', headers: this.headers(), body: JSON.stringify({ body }) });
                const d = await r.json();
                if (!d.success) { window.showToast && window.showToast('error', d.message || 'Could not edit.'); return; }
                const m = h.messages.find((x) => x.id === h.editingId);
                if (m) { m.body = body; m.edited = true; }
                this.cancelEdit(h);
            } catch (e) { window.showToast && window.showToast('error', 'Could not edit.'); }
            finally { h.sending = false; }
        },

        async deleteMsg(h, m) {
            this.actionMsg = null;
            const ok = await this.askConfirm({ title: 'Delete for everyone?', message: 'This message will be deleted for everyone in the chat.', type: 'danger', confirmText: 'Delete' });
            if (!ok) return;
            try {
                const r = await fetch(`${this.urls.base}/${h.id}/messages/${m.id}`, { method: 'DELETE', headers: this.headers() });
                const d = await r.json();
                if (!d.success) { window.showToast && window.showToast('error', 'Could not delete.'); return; }
                m.deleted = true; m.body = null; m.can_edit = false;
                if (h.editingId === m.id) this.cancelEdit(h);
            } catch (e) { window.showToast && window.showToast('error', 'Could not delete.'); }
        },

        async deleteForMe(h, m) {
            this.actionMsg = null;
            if (!h) return;
            const ok = await this.askConfirm({ title: 'Delete for me?', message: 'This message will be removed from your view only.', type: 'danger', confirmText: 'Delete' });
            if (!ok) return;
            try {
                const r = await fetch(`${this.urls.base}/${h.id}/messages/${m.id}/hide`, { method: 'POST', headers: this.headers() });
                const d = await r.json();
                if (!d.success) { window.showToast && window.showToast('error', 'Could not delete.'); return; }
                const i = h.messages.findIndex((x) => x.id === m.id);
                if (i > -1) h.messages.splice(i, 1);
                if (h.editingId === m.id) this.cancelEdit(h);
            } catch (e) { window.showToast && window.showToast('error', 'Could not delete.'); }
        },

        markRead(id) { fetch(`${this.urls.base}/${id}/read`, { method: 'POST', headers: this.headers() }).catch(() => {}); },
        scrollDown(h) { this.$nextTick(() => { const el = document.getElementById('chathead-scroll-' + h.id); if (el) el.scrollTop = el.scrollHeight; }); },
        headers() { return { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' }; },
    };
}
</script>
@endpush
@endonce
