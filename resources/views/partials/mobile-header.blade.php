@php
    $hu = Auth::user();
    $mNotifs = \App\Models\UserNotification::where('user_id', $hu->id)
        ->with(['clubNotification.tenant', 'actor', 'tenant'])
        ->latest()->take(10)->get();
    $mUnread = \App\Models\UserNotification::where('user_id', $hu->id)->where('is_read', false)->count();
    $mItems = $mNotifs->map(function ($n) {
        $d = $n->display();
        return [
            'id'      => $n->id,
            'title'   => $d['title'],
            'body'    => $d['body'] ? \Illuminate\Support\Str::limit($d['body'], 70) : null,
            'context' => $d['context'] ?? 'TakeOne',
            'time'    => $n->created_at->diffForHumans(null, true, true),
            'url'     => $d['url'],
            'icon'    => $d['icon'],
            'avatar'  => $d['avatar'],
            'read'    => (bool) $n->is_read,
        ];
    })->values();
@endphp
{{-- Shared mobile header — identical in Personal and Club views so the top bar
     (and the switcher dropdown) never shifts when switching. Only the page title
     and the switcher's current-view checkmark differ. --}}
<header class="sticky top-0 z-40 bg-white border-b border-border">
    <div class="flex items-center gap-2 px-3 h-14">
        <button @click="drawer = true" class="flex items-center justify-center w-10 h-10 rounded-xl flex-shrink-0" aria-label="{{ __('header.menu') }}">
            @if($hu->profile_picture)
                <img src="{{ asset('storage/'.$hu->profile_picture) }}?v={{ optional($hu->updated_at)->timestamp }}" alt="" class="w-9 h-9 rounded-lg object-cover">
            @else
                <i class="bi bi-list text-xl text-foreground"></i>
            @endif
        </button>

        <div class="flex-1 min-w-0">
            @include('partials.mobile-switcher', ['current' => $switcherCurrent ?? 'personal'])
            <p id="shell-title" class="text-base font-bold text-primary leading-tight truncate">{{ $shellTitle ?? '' }}</p>
        </div>

        {{-- Notifications --}}
        <div class="relative" x-data="mobileNotifs()" @click.outside="open=false">
            <button type="button" @click="open=!open" class="relative w-9 h-9 rounded-xl bg-muted flex items-center justify-center text-muted-foreground flex-shrink-0" aria-label="{{ __('header.notifications') }}">
                <i class="bi bi-bell"></i>
                <span x-show="unread > 0" x-cloak class="notification-badge" x-text="unread > 99 ? '99+' : unread"></span>
            </button>

            <div x-show="open" x-cloak @click.outside="open=false"
                 x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 class="fixed top-14 left-3 right-3 ml-auto max-w-sm max-h-[calc(100dvh-9.5rem)] flex flex-col bg-white rounded-xl shadow-xl border border-border z-50 overflow-hidden">
                <div class="shrink-0 flex items-center justify-between px-4 py-3 border-b border-border">
                    <p class="text-sm font-semibold">{{ __('header.notifications') }}</p>
                    <div class="flex items-center gap-3">
                        <button type="button" x-show="unread > 0" @click="markAll()" class="text-xs text-primary hover:underline">{{ __('header.mark_all_read') }}</button>
                        <button type="button" x-show="items.length > 0" @click="clearAll()" class="text-xs text-destructive hover:underline">{{ __('header.clear_all') }}</button>
                    </div>
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto">
                    <template x-for="item in items" :key="item.id">
                        <button type="button" @click="openItem(item, $event)"
                                class="w-full text-left px-4 py-3 flex items-center gap-2.5 hover:bg-muted transition-colors border-b border-gray-50"
                                :class="item.read ? 'opacity-70' : ''">
                            <span class="shrink-0 w-9 h-9 rounded-full bg-accent text-primary flex items-center justify-center overflow-hidden">
                                <template x-if="item.avatar"><img :src="item.avatar" alt="" class="w-9 h-9 object-cover"></template>
                                <template x-if="!item.avatar"><i class="bi" :class="item.icon || 'bi-bell-fill'"></i></template>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-[13px] font-semibold leading-tight" :class="item.read ? 'text-foreground' : 'text-primary'" x-text="item.title"></span>
                                <span class="block text-[12px] text-gray-600 truncate" x-show="item.body" x-text="item.body"></span>
                                <span class="block text-[11px] text-muted-foreground" x-text="(item.context || 'TakeOne') + ' · ' + item.time"></span>
                            </span>
                        </button>
                    </template>
                    <div x-show="items.length === 0" class="px-4 py-10 text-center">
                        <i class="bi bi-bell-slash text-2xl text-gray-300 block mb-2"></i>
                        <p class="text-sm text-muted-foreground">{{ __('header.no_notifications') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <script>
            window.mobileNotifs = function () {
                return {
                    open: false,
                    unread: {{ (int) $mUnread }},
                    items: @js($mItems),
                    csrf: document.querySelector('meta[name=csrf-token]')?.content || '',

                    init() {
                        // Instant updates over MQTT — realtime.js dispatches this
                        // event from notifyUser()'s publish. Prepend + bump live.
                        window.addEventListener('realtime:notification', (e) => {
                            const n = e.detail || {};
                            // Removal event (e.g. the author deleted their post):
                            // drop the matching bell entries + fix the unread count.
                            if (n.action === 'remove') {
                                const ids = (n.ids || []).map(Number);
                                this.items = this.items.filter(i => {
                                    if (!ids.includes(Number(i.id))) return true;
                                    if (!i.read && this.unread > 0) this.unread--;
                                    return false;
                                });
                                return;
                            }
                            if (n.id && this.items.some(i => i.id === n.id)) return; // de-dupe
                            this.items.unshift({
                                id:      n.id,
                                title:   n.subject || @js(__('header.notification')),
                                body:    n.body || null,
                                context: n.context || n.club_name || null,
                                time:    n.created_at_human || @js(__('header.just_now')),
                                url:     n.action_url || null,
                                icon:    n.icon || 'bi-bell-fill',
                                avatar:  n.avatar || null,
                                read:    false,
                            });
                            if (this.items.length > 20) this.items.pop();
                            this.unread++;
                        });
                    },

                    mark(id) {
                        const body = new URLSearchParams();
                        if (id) body.set('notification_id', id);
                        // keepalive lets the request finish even though we navigate
                        // away on the same click — otherwise it's cancelled and the
                        // notification never gets marked read (badge stays the same).
                        fetch(@js(route('notifications.mark-read')), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                            credentials: 'same-origin', body, keepalive: true,
                        }).catch(() => {});
                    },
                    openItem(item, ev) {
                        if (!item.read) {
                            item.read = true;
                            if (this.unread > 0) this.unread--;
                            this.mark(item.id);
                        }
                        this.safeNavigate(item.url);
                    },
                    // Only navigate to http(s) URLs — never javascript:/data: etc.
                    safeNavigate(url) {
                        if (!url) return;
                        try {
                            const u = new URL(url, window.location.origin);
                            if (u.protocol === 'http:' || u.protocol === 'https:') {
                                window.location.href = u.href;
                            }
                        } catch (_) { /* ignore malformed URLs */ }
                    },
                    markAll() {
                        this.unread = 0;
                        this.items.forEach(i => i.read = true);
                        this.mark(null);
                    },
                    clearAll() {
                        this.items = [];
                        this.unread = 0;
                        fetch(@js(route('notifications.clear')), {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                            credentials: 'same-origin', keepalive: true,
                        }).catch(() => {});
                    },
                };
            };
        </script>
        @php
            $chatUnread = \Illuminate\Support\Facades\DB::table('messages as m')
                ->join('conversation_user as cu', 'cu.conversation_id', '=', 'm.conversation_id')
                ->where('cu.user_id', Auth::id())
                ->where('m.sender_id', '!=', Auth::id())
                ->whereRaw('m.created_at > COALESCE(cu.last_read_at, ?)', ['1970-01-01 00:00:00'])
                ->count();
        @endphp
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('mobile-chat:toggle'))" class="relative w-9 h-9 rounded-xl bg-muted flex items-center justify-center text-muted-foreground flex-shrink-0 chat-link" aria-label="{{ __('header.chat') }}">
            <i class="bi bi-chat-dots"></i>
            @if($chatUnread > 0)
                <span class="notification-badge chat-badge">{{ $chatUnread > 99 ? '99+' : $chatUnread }}</span>
            @endif
        </button>
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('qr-scan:open'))" class="w-9 h-9 rounded-xl bg-muted flex items-center justify-center text-muted-foreground flex-shrink-0" aria-label="{{ __('header.scan_qr') }}"><i class="bi bi-qr-code-scan"></i></button>
    </div>
</header>

{{-- ===== QR scanner overlay — camera viewfinder, scans QR codes via the
     native BarcodeDetector API; navigates to scanned URLs (e.g. club pages). ===== --}}
<div x-data="qrScanner()" x-cloak @qr-scan:open.window="open()" @keydown.escape.window="close()">
    <div x-show="active" class="fixed inset-0 z-[80] bg-black flex flex-col"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-between px-4 h-14 text-white flex-shrink-0">
            <span class="font-semibold">{{ __('header.scan_qr') }}</span>
            <button type="button" @click="close()" class="m-press w-10 h-10 -mr-2 rounded-full flex items-center justify-center hover:bg-white/10" aria-label="{{ __('shared.cancel') }}">
                <i class="bi bi-x-lg text-xl"></i>
            </button>
        </div>
        <div class="flex-1 relative overflow-hidden">
            <video x-ref="qrVideo" playsinline muted class="absolute inset-0 w-full h-full object-cover"></video>
            {{-- Focus frame with a darkened surround --}}
            <div class="absolute inset-0 grid place-items-center pointer-events-none">
                <div class="w-64 h-64 max-w-[70vw] max-h-[70vw] rounded-3xl border-2 border-white/90"
                     style="box-shadow: 0 0 0 100vmax rgba(0,0,0,.45);"></div>
            </div>
            <p class="absolute bottom-10 inset-x-0 text-center text-white/90 text-sm px-8">{{ __('header.scan_hint') }}</p>
        </div>
    </div>
</div>
<script>
    window.qrScanner = function () {
        return {
            active: false,
            stream: null,
            detector: null,
            raf: null,

            async open() {
                if (!('BarcodeDetector' in window)) {
                    window.showToast && window.showToast('info', @js(__('header.scan_unsupported')));
                    return;
                }
                this.active = true;
                await this.$nextTick();
                try {
                    this.detector = new BarcodeDetector({ formats: ['qr_code'] });
                    this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                    const v = this.$refs.qrVideo;
                    v.srcObject = this.stream;
                    await v.play();
                    this.scan();
                } catch (e) {
                    window.showToast && window.showToast('error', @js(__('header.scan_no_camera')));
                    this.close();
                }
            },

            async scan() {
                if (!this.active || !this.detector) return;
                try {
                    const codes = await this.detector.detect(this.$refs.qrVideo);
                    if (codes && codes.length && codes[0].rawValue) {
                        this.handle(codes[0].rawValue);
                        return;
                    }
                } catch (_) { /* transient detect error — keep scanning */ }
                this.raf = requestAnimationFrame(() => this.scan());
            },

            // Scanned a URL → navigate (same pattern as notifications: http(s) only).
            handle(value) {
                this.close();
                try {
                    const u = new URL(value, window.location.origin);
                    if (u.protocol === 'http:' || u.protocol === 'https:') {
                        window.location.href = u.href;
                        return;
                    }
                } catch (_) { /* not a URL */ }
                window.showToast && window.showToast('info', value);
            },

            close() {
                this.active = false;
                if (this.raf) { cancelAnimationFrame(this.raf); this.raf = null; }
                if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
                this.detector = null;
            },
        };
    };
</script>

@include('partials.mobile-chat')
