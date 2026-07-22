{{--
  New-member alerts — an artistic, screen-centered alert for the club owner/staff.

  Sibling of <x-low-stock-alerts>: same persistence model, different subject. It is
  driven by the recipient's UNREAD `type='new_member'` UserNotifications, so it
  re-renders on every page load AND — mounted in layouts/app.blade.php OUTSIDE
  #shell-content — it survives SPA/shell navigation. A card only disappears when the
  owner acknowledges it ("Got it" / ✕ marks that notification read so it never
  returns) or taps "Review" (which acknowledges then deep-links to the members page
  to approve the pending payment). Never on its own, never on navigation. When
  several registrations land, they queue one-at-a-time.

  Each card shows the new member's PHOTO (resolved live from the User via the
  notification's subject_id, with a gendered fallback tile), NAME, the club, and the
  registration note. New alerts arrive over `realtime:notification` (filtered to the
  bi-person-plus-fill icon so it never cross-triggers with low-stock). Depends only
  on approved foundations: Alpine, Tailwind tokens, the CSRF meta tag, and the
  notifications.mark-read endpoint.
--}}
@auth
@php
    $__memberAlerts = \App\Models\UserNotification::query()
        ->where('user_id', auth()->id())
        ->where('type', 'new_member')
        ->where('is_read', false)
        ->latest()
        ->take(12)
        ->get()
        ->map(function ($n) {
            $member = $n->subject_id ? \App\Models\User::find($n->subject_id) : null;
            $club   = $n->tenant_id ? \App\Models\Tenant::find($n->tenant_id) : null;

            return [
                'id'     => $n->id,
                'name'   => $member->full_name ?? $member->name ?? trim(explode(' registered', (string) $n->body)[0]) ?: __('members.member'),
                'image'  => $member && $member->profile_picture
                                ? asset('storage/'.$member->profile_picture).'?v='.optional($member->updated_at)->timestamp
                                : null,
                'gender' => $member->gender ?? null,
                'club'   => $club->club_name ?? null,
                'body'   => $n->body,
                'url'    => $n->action_url,
            ];
        })->values();
@endphp

<div x-data="newMemberAlerts(@js($__memberAlerts))" x-cloak>
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
                     class="nm-card relative w-full max-w-sm rounded-3xl bg-white shadow-2xl overflow-hidden">

                    {{-- welcoming band + radial glow + sheen sweep + confetti --}}
                    <div class="relative h-24 bg-gradient-to-br from-emerald-400 via-green-500 to-teal-500 overflow-hidden">
                        <div class="nm-glow absolute -top-10 -end-8 w-40 h-40 rounded-full bg-white/25 blur-2xl"></div>
                        <div class="nm-sheen absolute inset-0"></div>
                        {{-- confetti — a few lightweight falling flakes --}}
                        <span class="nm-confetti nm-c1"></span>
                        <span class="nm-confetti nm-c2"></span>
                        <span class="nm-confetti nm-c3"></span>
                        <span class="nm-confetti nm-c4"></span>
                        <span class="nm-confetti nm-c5"></span>
                        <span class="nm-confetti nm-c6"></span>
                        <div class="absolute inset-x-0 top-0 flex items-center justify-between px-4 pt-3.5">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-white/95">
                                <i class="bi bi-person-plus-fill nm-wiggle"></i> {{ __('New member') }}
                            </span>
                            <button type="button" @click="dismiss()"
                                    class="w-8 h-8 -me-1 rounded-full grid place-items-center text-white/85 hover:bg-white/20 transition-colors"
                                    aria-label="{{ __('Dismiss') }}">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    {{-- member photo — hero, overlapping the band, ringed by celebratory pulses --}}
                    <div class="flex justify-center -mt-12">
                        <div class="nm-photo-wrap relative">
                            <div class="nm-photo relative w-28 h-28 rounded-3xl ring-4 ring-white shadow-xl overflow-hidden grid place-items-center"
                                 :class="(a && a.image) ? 'bg-muted' : 'bg-primary'">
                                <template x-if="a && a.image">
                                    <img :src="a.image" :alt="a.name" class="w-full h-full object-cover"
                                         style="image-rendering:-webkit-optimize-contrast;image-rendering:crisp-edges;">
                                </template>
                                <template x-if="a && !a.image">
                                    <i class="bi bi-person-fill text-5xl text-white"></i>
                                </template>
                            </div>
                            {{-- expanding celebratory rings --}}
                            <span class="nm-ring"></span>
                            <span class="nm-ring nm-ring-2"></span>
                            {{-- celebratory badge — pops + twinkles in --}}
                            <span class="nm-badge absolute -bottom-1 -end-1 w-9 h-9 rounded-2xl bg-white grid place-items-center shadow-lg">
                                <i class="bi bi-stars text-green-500 text-lg"></i>
                            </span>
                        </div>
                    </div>

                    {{-- name + details --}}
                    <div class="px-6 pt-4 pb-6 text-center">
                        <p class="text-xl font-black text-foreground leading-tight" x-text="a ? a.name : ''"></p>
                        <p class="mt-1 text-[13px] font-semibold text-green-600" x-text="a && a.club ? a.club : ''"></p>

                        <p class="mt-3 text-[13px] text-muted-foreground leading-snug" x-text="a ? a.body : ''"></p>

                        {{-- actions --}}
                        <div class="mt-5 space-y-2">
                            <template x-if="a && a.url">
                                <button type="button" @click="review(a)"
                                   class="nm-cta flex items-center justify-center gap-2 w-full py-3 rounded-2xl bg-primary text-white font-bold text-sm shadow-lg hover:bg-primary/90 transition-colors">
                                    <i class="bi bi-check2-circle"></i> {{ __('Review registration') }}
                                </button>
                            </template>
                            <button type="button" @click="dismiss()"
                                    class="w-full py-2.5 rounded-2xl text-muted-foreground font-semibold text-sm hover:bg-muted transition-colors">
                                {{ __('Got it') }}
                            </button>
                        </div>

                        {{-- queue indicator --}}
                        <p x-show="alerts.length > 1" x-cloak class="mt-3 text-[11px] font-semibold text-green-600 flex items-center justify-center gap-1.5">
                            <i class="bi bi-stack"></i>
                            <span x-text="`+${alerts.length - 1} {{ __('more') }}`"></span>
                        </p>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>

@once
<style>
    .nm-glow  { animation: nmGlow 3.2s ease-in-out infinite; }
    .nm-photo { animation: nmFloat 4s ease-in-out infinite; }
    @keyframes nmGlow  { 0%,100% { opacity: .5; transform: scale(1); } 50% { opacity: .85; transform: scale(1.12); } }
    @keyframes nmFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }

    /* diagonal sheen sweeping across the band */
    .nm-sheen {
        background: linear-gradient(115deg, transparent 30%, rgba(255,255,255,.45) 50%, transparent 70%);
        transform: translateX(-120%);
        animation: nmSheen 2.8s ease-in-out infinite;
    }
    @keyframes nmSheen { 0% { transform: translateX(-120%); } 55%,100% { transform: translateX(120%); } }

    /* the person icon in the eyebrow gives a happy wiggle */
    .nm-wiggle { display: inline-block; transform-origin: bottom center; animation: nmWiggle 2.4s ease-in-out infinite; }
    @keyframes nmWiggle { 0%,88%,100% { transform: rotate(0); } 91% { transform: rotate(-14deg); } 95% { transform: rotate(12deg); } }

    /* confetti flakes falling through the band */
    .nm-confetti { position: absolute; top: -8px; width: 7px; height: 10px; border-radius: 2px; opacity: 0; animation: nmConfetti 2.6s linear infinite; }
    .nm-c1 { left: 14%; background: #fde047; animation-delay: .0s;  }
    .nm-c2 { left: 30%; background: #fff;    animation-delay: .5s; width: 6px; height: 6px; border-radius: 9999px; }
    .nm-c3 { left: 48%; background: #a7f3d0; animation-delay: .9s; }
    .nm-c4 { left: 63%; background: #fdba74; animation-delay: .3s; width: 6px; height: 6px; border-radius: 9999px; }
    .nm-c5 { left: 78%; background: #fff;    animation-delay: 1.2s; }
    .nm-c6 { left: 88%; background: #fde047; animation-delay: .7s; width: 6px; height: 6px; border-radius: 9999px; }
    @keyframes nmConfetti {
        0%   { transform: translateY(0) rotate(0);      opacity: 0; }
        12%  { opacity: 1; }
        100% { transform: translateY(108px) rotate(220deg); opacity: 0; }
    }

    /* expanding celebratory rings behind the avatar */
    .nm-photo-wrap .nm-ring {
        position: absolute; inset: 0; margin: auto; width: 7rem; height: 7rem; border-radius: 1.5rem;
        border: 2px solid rgba(16,185,129,.55); pointer-events: none;
        animation: nmRing 2.2s ease-out infinite;
    }
    .nm-photo-wrap .nm-ring-2 { animation-delay: 1.1s; }
    @keyframes nmRing { 0% { transform: scale(.85); opacity: .8; } 100% { transform: scale(1.5); opacity: 0; } }

    /* badge pops in then twinkles */
    .nm-badge { animation: nmBadgePop .6s cubic-bezier(.34,1.56,.64,1) both, nmTwinkle 2.6s ease-in-out .6s infinite; }
    @keyframes nmBadgePop { 0% { transform: scale(0) rotate(-40deg); } 100% { transform: scale(1) rotate(0); } }
    @keyframes nmTwinkle  { 0%,100% { transform: scale(1) rotate(0); } 50% { transform: scale(1.14) rotate(8deg); } }

    @media (prefers-reduced-motion: reduce) {
        .nm-glow, .nm-photo, .nm-sheen, .nm-wiggle, .nm-confetti, .nm-photo-wrap .nm-ring, .nm-badge {
            animation: none;
        }
        .nm-confetti, .nm-photo-wrap .nm-ring { display: none; }
        .nm-badge { transform: none; }
    }
</style>
@push('scripts')
<script>
function newMemberAlerts(initial) {
    return {
        alerts: initial || [],
        markUrl: @js(route('notifications.mark-read')),
        sounded: new Set(),

        get current() { return this.alerts[0] || null; },

        // Play the celebratory fanfare for a card, at most once per notification id.
        chime(a) {
            if (!a || this.sounded.has(a.id)) return;
            this.sounded.add(a.id);
            try { window.playNewMemberChime && window.playNewMemberChime(); } catch (e) {}
        },

        init() {
            // Lives outside #shell-content → init runs once per full load (SPA swaps
            // don't touch it). A persisted (server-rendered) card means the popup is
            // showing after a load/reload — sound it here (the page-load bell dedupe
            // may keep the global chime silent, so this guarantees it's never mute).
            if (this.current) this.chime(this.current);

            // Receive new-member alerts live; the rich photo fills in on the next
            // server render (this transient entry carries the payload text). We do NOT
            // chime here — the global live handler (rtRingBell) already plays the
            // fanfare for the bi-person-plus-fill icon, so this avoids a double sound.
            window.addEventListener('realtime:notification', (e) => {
                const n = e.detail || {};
                if (n.action === 'remove') {
                    const ids = (n.ids || []).map(Number);
                    this.alerts = this.alerts.filter(a => !ids.includes(Number(a.id)));
                    return;
                }
                if (n.icon !== 'bi-person-plus-fill' || !n.id) return;                   // new-member icon only
                if (this.alerts.some(a => Number(a.id) === Number(n.id))) return;
                this.sounded.add(Number(n.id));   // the global handler owns the sound for this id
                this.alerts.push({
                    id: n.id,
                    name: n.subject || @js(__('New member')),
                    image: n.avatar || null,
                    gender: null,
                    club: n.context || n.club_name || null,
                    body: n.body || '',
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

        // Go review — acknowledge first so the (persistent) alert can't block the members page.
        review(a) {
            const url = a && a.url;
            this.dismiss();
            if (url) window.location.href = url;
        },
    };
}
</script>
@endpush
@endonce
@endauth
