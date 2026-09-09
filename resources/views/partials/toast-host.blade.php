{{--
    The toast host — the ONE toast renderer on this platform.

    Extracted from layouts/app.blade.php on 2026-09-08 because a second
    document now needs it: the branded event surface (entry/layout.blade.php)
    is a standalone page that never loads the app shell, and the event screens
    moving onto it call window.showToast 61 times and window.confirmAction 15
    times between them. Re-implementing either there would be exactly the
    duplication CLAUDE.md's *Shared Stays Shared* rule forbids — and the copy
    that drifts is the one nobody notices, because a toast that stops
    appearing looks like an action that silently did nothing.

    So: one copy, included by every layout that needs it. layouts/app.blade.php
    behaves exactly as before — this is its own markup and its own script,
    moved, not rewritten.

    Provides:
      · window.showToast(type, message) — tolerant of every signature in use
      · the Alpine container the toasts are drawn in
      · the platform-wide toasts_enabled switch, errors and warnings always through
--}}
    <!-- Toast Container (Alpine.js) -->
    @php $toastsEnabled = \App\Models\PlatformSetting::getBool('toasts_enabled', true); @endphp
    <div x-data="toastManager()" class="fixed top-20 right-4 z-[200] space-y-2 pointer-events-none">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.visible"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="translate-x-full opacity-0"
                 x-transition:enter-end="translate-x-0 opacity-100"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="translate-x-0 opacity-100"
                 x-transition:leave-end="translate-x-full opacity-0"
                 :class="{
                     'bg-success text-white': toast.type === 'success',
                     'bg-destructive text-white': toast.type === 'error',
                     'bg-info text-white': toast.type === 'info',
                     'bg-warning text-warning-foreground': toast.type === 'warning'
                 }"
                 class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg w-[min(300px,calc(100vw-2rem))] border-0">
                <i :class="{
                    'bi bi-check-circle': toast.type === 'success',
                    'bi bi-exclamation-triangle': toast.type === 'error' || toast.type === 'warning',
                    'bi bi-info-circle': toast.type === 'info'
                }"></i>
                <span class="flex-1 text-sm" x-text="toast.message"></span>
                <button @click="removeToast(toast.id)" class="hover:opacity-70">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </template>
    </div>

    <script>
        // Toast Manager Alpine Component
        function toastManager() {
            return {
                toasts: [],
                // Stay silent on mobile while a super-admin is impersonating —
                // toasts (incl. the "viewing as …" flash) are distracting there.
                suppressed: @json(($isMobile ?? false) && session()->has('impersonate.original_id')),
                init() {
                    if (this.suppressed) return;
                    // Surface ALL server-side flash + validation messages as toasts —
                    // never as inline page banners (banners removed from individual views).
                    @if(session('success'))
                        this.addToast('success', @json(session('success')));
                    @endif
                    @if(session('error'))
                        this.addToast('error', @json(session('error')));
                    @endif
                    @if(session('info'))
                        this.addToast('info', @json(session('info')));
                    @endif
                    @if(session('warning'))
                        this.addToast('warning', @json(session('warning')));
                    @endif
                    @if(session('status'))
                        this.addToast('info', @json(session('status')));
                    @endif
                    @if(session('message') && is_string(session('message')))
                        this.addToast('info', @json(session('message')));
                    @endif

                    // Single rendering path: every programmatic toast (window.showToast
                    // and the legacy Toast.* API) routes through this one container.
                    window.addEventListener('show-toast', (e) => {
                        const d = e.detail || {};
                        this.addToast(d.type || 'info', d.message ?? '', d.duration ?? 3000);
                    });
                },
                addToast(type, message, duration = 3000) {
                    {{-- Turning toasts off silences CHATTER, never failures. The
                         platform-wide toggle used to drop every toast, including
                         the only channel a form has for telling someone why a
                         save was refused — so a rejected create looked exactly
                         like a button that does nothing. Errors and warnings
                         always get through. --}}
                    @if(! $toastsEnabled)
                        if (type !== 'error' && type !== 'warning') return;
                    @endif
                    const id = Date.now();
                    this.toasts.push({ id, type, message, visible: true });
                    if (duration > 0) {
                        setTimeout(() => this.removeToast(id), duration);
                    }
                },
                removeToast(id) {
                    const index = this.toasts.findIndex(t => t.id === id);
                    if (index > -1) {
                        this.toasts[index].visible = false;
                        setTimeout(() => {
                            this.toasts = this.toasts.filter(t => t.id !== id);
                        }, 200);
                    }
                }
            }
        }

        // Global toast — the ONLY toast renderer (routes to toastManager above).
        // Tolerates every signature used around the app:
        //   showToast('success', 'Message')
        //   showToast('success', 'Title', 'Message')
        //   showToast('Message', 'error')           // reversed
        window.showToast = function (a, b, c, duration = 3000) {
            const TYPES = ['success', 'error', 'warning', 'info'];
            let type, message;
            if (TYPES.includes(a)) {
                type = a;
                message = (c !== undefined && c !== null && c !== '') ? (b + ' — ' + c) : (b ?? '');
            } else if (TYPES.includes(b)) {
                type = b; message = a;
            } else {
                type = 'info'; message = a ?? '';
            }
            window.dispatchEvent(new CustomEvent('show-toast', { detail: { type, message, duration } }));
        };
    </script>
