{{--
  Low-stock alerts — an artistic, screen-centered alert for the club owner.

  Standalone & self-contained (own markup, Alpine state, request flow, styles).
  Driven by the owner's UNREAD `type='stock'` UserNotifications, so it re-renders
  on every page load AND — mounted in layouts/app.blade.php OUTSIDE #shell-content —
  it survives SPA/shell navigation. A card only disappears when the owner
  acknowledges it (marks that notification read so it never returns), never on its
  own and never on navigation. When several items are low, they queue one-at-a-time.

  Each card shows the product PHOTO, its NAME, and the REMAINING stock (resolved
  live from the ClubProduct via the notification's subject_id). New alerts arrive
  over `realtime:notification`. Depends only on approved foundations: Alpine,
  Tailwind tokens, the CSRF meta tag, and the notifications.mark-read endpoint.
--}}
@auth
@php
    $__stockAlerts = \App\Models\UserNotification::query()
        ->where('user_id', auth()->id())
        ->where('type', 'stock')
        ->where('is_read', false)
        ->latest()
        ->take(12)
        ->get()
        ->map(function ($n) {
            $product   = $n->subject_id ? \App\Models\ClubProduct::find($n->subject_id) : null;
            $remaining = $product && $product->quantity !== null ? (int) $product->quantity : null;
            if ($remaining === null && preg_match('/(\d+)/', (string) $n->body, $m)) {
                $remaining = (int) $m[1];
            }
            $name = $product->name ?? trim(explode('·', (string) $n->body)[0]) ?: __('admin.fin_item');

            return [
                'id'        => $n->id,
                'name'      => $name,
                'image'     => $product && $product->image_path ? asset('storage/'.$product->image_path) : null,
                'remaining' => $remaining,
                'url'       => $n->action_url,
                'muteUrl'   => $product && $product->tenant ? route('admin.club.shop.products.stock-mute', [$product->tenant, $product]) : null,
            ];
        })->values();
@endphp

<div x-data="lowStockAlerts(@js($__stockAlerts))" x-cloak>
    <template x-teleport="body">
        <div x-show="alerts.length" x-cloak
             class="fixed inset-0 z-[80] flex items-center justify-center p-5"
             role="dialog" aria-modal="true">

            {{-- Backdrop --}}
            <div x-show="alerts.length" x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

            {{-- The one currently-shown alert (queue of many) --}}
            <template x-for="a in [current]" :key="a ? a.id : 'none'">
                <div x-show="alerts.length"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-6 scale-90"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="ls-card relative w-full max-w-sm rounded-3xl bg-white shadow-2xl overflow-hidden">

                    {{-- warm alarm band + radial glow --}}
                    <div class="relative h-24 bg-gradient-to-br from-amber-400 via-amber-500 to-orange-500 overflow-hidden">
                        <div class="ls-glow absolute -top-10 -end-8 w-40 h-40 rounded-full bg-white/25 blur-2xl"></div>
                        <div class="absolute inset-x-0 top-0 flex items-center justify-between px-4 pt-3.5">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-white/95">
                                <i class="bi bi-exclamation-triangle-fill"></i> {{ __('market.low_stock_eyebrow') }}
                            </span>
                            <button type="button" @click="dismiss()"
                                    class="w-8 h-8 -me-1 rounded-full grid place-items-center text-white/85 hover:bg-white/20 transition-colors"
                                    aria-label="{{ __('market.low_stock_dismiss') }}">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    {{-- product photo — hero, overlapping the band --}}
                    <div class="flex justify-center -mt-12">
                        <div class="ls-photo relative w-28 h-28 rounded-3xl bg-muted ring-4 ring-white shadow-xl overflow-hidden grid place-items-center">
                            <template x-if="a && a.image">
                                <img :src="a.image" :alt="a.name" class="w-full h-full object-cover">
                            </template>
                            <template x-if="a && !a.image">
                                <i class="bi bi-bag text-3xl text-muted-foreground"></i>
                            </template>
                        </div>
                    </div>

                    {{-- name + remaining --}}
                    <div class="px-6 pt-3 pb-6 text-center">
                        <p class="text-xl font-black text-foreground leading-tight" x-text="a ? a.name : ''"></p>

                        <div class="mt-4 inline-flex flex-col items-center">
                            <span class="ls-count relative grid place-items-center w-24 h-24 rounded-full">
                                <span class="text-5xl font-black tabular-nums leading-none"
                                      :class="(a && a.remaining !== null && a.remaining <= 0) ? 'text-rose-600' : 'text-amber-500'"
                                      x-text="a && a.remaining !== null ? a.remaining : '—'"></span>
                            </span>
                            <span class="mt-1 text-[11px] font-bold uppercase tracking-widest text-muted-foreground"
                                  x-text="(a && a.remaining !== null && a.remaining <= 0) ? '{{ __('market.low_stock_out') }}' : '{{ __('market.low_stock_units_remaining') }}'"></span>
                        </div>

                        <p class="mt-4 text-[13px] text-muted-foreground leading-snug">{{ __('market.low_stock_snooze_hint') }}</p>

                        {{-- actions --}}
                        <div class="mt-5 space-y-2">
                            <template x-if="a && a.url">
                                <button type="button" @click="restock(a)"
                                   class="ls-cta flex items-center justify-center gap-2 w-full py-3 rounded-2xl bg-primary text-white font-bold text-sm shadow-lg hover:bg-primary/90 transition-colors">
                                    <i class="bi bi-box-seam"></i> {{ __('market.low_stock_restock_cta') }}
                                </button>
                            </template>
                            <button type="button" @click="dismiss()"
                                    class="w-full py-2.5 rounded-2xl text-muted-foreground font-semibold text-sm hover:bg-muted transition-colors">
                                {{ __('market.low_stock_got_it') }}
                            </button>
                            <template x-if="a && a.muteUrl">
                                <button type="button" @click="mute(a)"
                                        class="w-full py-2 rounded-2xl text-[12px] font-semibold text-muted-foreground/80 hover:text-foreground transition-colors flex items-center justify-center gap-1.5">
                                    <i class="bi bi-bell-slash"></i> {{ __('market.low_stock_mute') }}
                                </button>
                            </template>
                        </div>

                        {{-- queue indicator --}}
                        <p x-show="alerts.length > 1" x-cloak class="mt-3 text-[11px] font-semibold text-amber-600 flex items-center justify-center gap-1.5">
                            <i class="bi bi-stack"></i>
                            <span x-text="`+${alerts.length - 1} {{ __('market.low_stock_more_items') }}`"></span>
                        </p>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>

@once
<style>
    .ls-glow { animation: lsGlow 3.2s ease-in-out infinite; }
    .ls-photo { animation: lsFloat 4s ease-in-out infinite; }
    .ls-count::before, .ls-count::after {
        content: ''; position: absolute; inset: 0; border-radius: 9999px;
        border: 2px solid hsl(38 92% 55% / 0.5);
    }
    .ls-count::before { animation: lsPulse 2.2s ease-out infinite; }
    .ls-count::after  { animation: lsPulse 2.2s ease-out infinite 1.1s; }
    @keyframes lsGlow  { 0%,100% { opacity: .5; transform: scale(1); } 50% { opacity: .85; transform: scale(1.12); } }
    @keyframes lsFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
    @keyframes lsPulse { 0% { transform: scale(.7); opacity: .8; } 100% { transform: scale(1.35); opacity: 0; } }
    @media (prefers-reduced-motion: reduce) {
        .ls-glow, .ls-photo, .ls-count::before, .ls-count::after { animation: none; }
        .ls-count::after { display: none; }
    }
</style>
@push('scripts')
<script>
function lowStockAlerts(initial) {
    return {
        alerts: initial || [],
        markUrl: @js(route('notifications.mark-read')),

        get current() { return this.alerts[0] || null; },

        init() {
            // Lives outside #shell-content → init runs once per full load (SPA swaps don't
            // touch it). Receive new stock alerts live; the rich photo/stock fill in on the
            // next server render (this transient entry carries what the payload provides).
            window.addEventListener('realtime:notification', (e) => {
                const n = e.detail || {};
                if (n.action === 'remove') {
                    const ids = (n.ids || []).map(Number);
                    this.alerts = this.alerts.filter(a => !ids.includes(Number(a.id)));
                    return;
                }
                if (n.icon !== 'bi-exclamation-triangle-fill' || !n.id) return;         // stock-only icon
                if (this.alerts.some(a => Number(a.id) === Number(n.id))) return;
                const parts = String(n.body || '').split('·').map(s => s.trim());
                const num = (parts[1] || '').match(/(\d+)/);
                this.alerts.push({
                    id: n.id,
                    name: parts[0] || (n.subject || 'Low stock'),
                    image: null,
                    remaining: num ? parseInt(num[1], 10) : null,
                    url: n.action_url || '',
                });
            });
        },

        csrf() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; },

        async _ack(a) {
            if (!a) return;
            try {
                await fetch(this.markUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: JSON.stringify({ notification_id: a.id }),
                });
            } catch (e) { /* best-effort; the card is already gone for this session */ }
        },

        // Acknowledge the current card: mark it read so it never returns, then advance the queue.
        dismiss() { this._ack(this.alerts.shift()); },

        // Go restock — acknowledge first so the (persistent) alert can't block the shop page.
        restock(a) {
            const url = a && a.url;
            this.dismiss();
            if (url) window.location.href = url;
        },

        // Mute this item indefinitely — never alert for it again. Drops the card and,
        // if confirmed, stops the 24h re-nag until the owner unmutes it.
        async mute(a) {
            const ok = window.confirmAction
                ? await window.confirmAction({ title: @js(__('market.low_stock_mute_confirm_title')), message: @js(__('market.low_stock_mute_confirm_body')), type: 'warning', confirmText: @js(__('market.low_stock_mute')) })
                : true;
            if (!ok) return;
            const url = a.muteUrl;
            this.alerts = this.alerts.filter(x => x.id !== a.id);
            if (!url) return;
            try {
                await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: JSON.stringify({ notification_id: a.id }),
                });
                window.showToast && window.showToast('success', @js(__('market.low_stock_muted')));
            } catch (e) { /* best-effort */ }
        },
    };
}
</script>
@endpush
@endonce
@endauth
