{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.personal-mobile')

@section('title', __('event-taekwondo_tournament::messages.next_up_title'))

{{--
    Run day — "when am I on, and where?"

    Deliberately a COUNTDOWN, not a fixture list. The bouts-ahead count is the
    hero because it is the honest number: it falls as the mat is scored, so it
    stays true even when the day runs late. The time under it is explicitly an
    estimate, because a fixed clock time is a lie within the first hour.

    When the athlete is next, the whole card flips to an alert state — that is
    the one moment this screen exists for.

    Live: patches itself on `realtime:events` (bout results + draw changes),
    with the mobile-shell listener dedupe so handlers can't stack across AJAX
    navigations.
--}}
@section('personal-content')
<div x-data="nextUp(@js($mine), @js($squad), '{{ route('testcode.me.events.next-up', $e['key']) }}')"
     x-init="listen()" class="-mx-4 -mt-4 pb-8">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-5 pb-14 text-white relative overflow-hidden">
        {{-- Design Rule #6: back is the round 40px control holding a TAIL-LESS
             chevron and no words (2026-09-04); the destination is its
             aria-label / title. --}}
        <div class="flex items-center justify-between gap-2 relative z-50">
            {{-- Inside the sealed event app, back from a sub-screen means the
                     CONSOLE — the screen it was opened from. On the platform it
                     still means the event page. Same pill, honest label either
                     way (the audit: "'Event' means two different pages"). --}}
                <a href="{{ isset($shell) ? url('/e/'.$e['key'].'/admin/manage') : route('testcode.me.events.show', $e['key']) }}" data-shell-link data-route="testcode.me.events"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline"
           aria-label="{{ isset($shell) ? __('personal.event_manage_title') : __('personal.event_show_event') }}" title="{{ isset($shell) ? __('personal.event_manage_title') : __('personal.event_show_event') }}">
                <i class="bi bi-chevron-left"></i>
            </a>

            <button type="button" @click="refresh()" :disabled="busy"
                    class="m-press ev-ico w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0"
                    aria-label="{{ __('shared.refresh') }}">
                <i class="bi bi-arrow-clockwise text-lg" :class="busy && 'animate-spin'"></i>
            </button>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-hourglass-split"></i>{{ __('event-taekwondo_tournament::messages.next_up_title') }}
                </span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
        </div>
    </header>

    <div class="px-4 -mt-9 space-y-4 mobile-stagger">

        {{-- ===== The countdown ===== --}}
        <template x-if="mine">
            <div class="rounded-3xl overflow-hidden shadow-xl border border-white/50"
                 :class="mine.is_next ? 'bg-gradient-to-br from-red-500 to-red-700 text-white' : 'bg-white'">

                {{-- mat + bout code strip --}}
                <div class="flex items-center justify-between px-5 py-3 text-[11px] font-bold uppercase tracking-wider"
                     :class="mine.is_next ? 'bg-black/15 text-white/90' : 'bg-muted/70 text-muted-foreground'">
                    <span class="flex items-center gap-1.5">
                        <i class="bi bi-grid-3x3-gap-fill"></i>
                        <span x-text="mine.court || '—'"></span>
                    </span>
                    <span x-show="mine.code" class="font-mono tracking-widest" x-text="mine.code"></span>
                </div>

                <div class="px-5 py-6 text-center">
                    {{-- YOU ARE NEXT --}}
                    <template x-if="mine.is_next">
                        <div>
                            <div class="w-16 h-16 mx-auto rounded-3xl bg-white/20 border border-white/30 grid place-items-center m-float">
                                <i class="bi bi-megaphone-fill text-3xl"></i>
                            </div>
                            <p class="mt-4 text-xl font-black leading-tight">
                                {{ __('event-taekwondo_tournament::messages.next_up_you_are_next') }}
                            </p>
                        </div>
                    </template>

                    {{-- COUNTDOWN --}}
                    <template x-if="!mine.is_next">
                        <div>
                            <p class="text-[64px] leading-none font-black text-primary tabular-nums" x-text="mine.bouts_ahead"></p>
                            <p class="mt-1 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                {{ __('event-taekwondo_tournament::messages.next_up_ahead') }}
                            </p>

                            {{-- the queue on this mat, one pip per bout --}}
                            <div class="flex items-center justify-center gap-1.5 mt-4 flex-wrap">
                                <template x-for="i in Math.min(mine.bouts_ahead, 12)" :key="i">
                                    <span class="w-2 h-2 rounded-full bg-primary/25"></span>
                                </template>
                                <span class="w-3 h-3 rounded-full bg-primary ring-4 ring-primary/20"></span>
                            </div>

                            <p class="mt-5 text-sm font-bold text-foreground" x-show="mine.eta_minutes !== null">
                                ≈ <span x-text="mine.eta_minutes"></span> min
                            </p>
                            <p class="text-[11px] text-muted-foreground mt-0.5">
                                {{ __('event-taekwondo_tournament::messages.next_up_estimate') }}
                            </p>
                        </div>
                    </template>
                </div>

                {{-- opponent + corner --}}
                <div class="px-5 pb-5">
                    <div class="rounded-2xl px-4 py-3 flex items-center gap-3"
                         :class="mine.is_next ? 'bg-white/15 border border-white/25' : 'bg-muted/60'">
                        <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 text-white text-xs font-black"
                              :class="mine.corner === 'red' ? 'bg-red-500' : 'bg-blue-500'"
                              x-text="mine.corner === 'red' ? 'R' : 'B'"></span>
                        <div class="min-w-0 flex-1 text-start">
                            <p class="text-sm font-bold truncate"
                               :class="mine.is_next ? 'text-white' : 'text-foreground'"
                               x-text="mine.opponent || '{{ __('event-taekwondo_tournament::messages.next_up_opponent_tbc') }}'"></p>
                            <p class="text-[11px] truncate"
                               :class="mine.is_next ? 'text-white/75' : 'text-muted-foreground'"
                               x-text="[mine.round, mine.division].filter(Boolean).join(' · ')"></p>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- ===== Nothing to do ===== --}}
        <template x-if="!mine">
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 px-6 py-10 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-muted grid place-items-center">
                    <i class="bi bi-check2-circle text-2xl text-muted-foreground"></i>
                </div>
                <p class="mt-4 text-sm font-bold text-foreground">{{ __('event-taekwondo_tournament::messages.next_up_none') }}</p>
            </div>
        </template>

        {{-- ===== Coach: the squad, soonest first ===== --}}
        <template x-if="squad.length">
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <h2 class="px-5 pt-4 pb-3 text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                    <i class="bi bi-people-fill"></i>
                    {{ __('event-taekwondo_tournament::messages.next_up_squad') }}
                    <span class="ms-auto text-primary" x-text="squad.length"></span>
                </h2>
                <ul class="divide-y divide-gray-100">
                    <template x-for="a in squad" :key="a.user_id">
                        <li class="px-5 py-3 flex items-center gap-3">
                            <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 text-[11px] font-black tabular-nums"
                                  :class="a.next
                                      ? (a.next.is_next ? 'bg-red-500 text-white' : 'bg-accent text-primary')
                                      : 'bg-muted text-muted-foreground'"
                                  x-text="a.next ? a.next.bouts_ahead : '–'"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-foreground truncate" x-text="a.name"></p>
                                <p class="text-[11px] text-muted-foreground truncate"
                                   x-text="a.next
                                       ? [a.next.court, a.next.code, a.next.eta_minutes !== null ? '≈ ' + a.next.eta_minutes + ' min' : null].filter(Boolean).join(' · ')
                                       : '{{ __('event-taekwondo_tournament::messages.next_up_finished') }}'"></p>
                            </div>
                            <i class="bi bi-megaphone-fill text-red-500" x-show="a.next && a.next.is_next"></i>
                        </li>
                    </template>
                </ul>
            </section>
        </template>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function nextUp(mine, squad, url) {
        return {
            mine, squad, busy: false, url,

            async refresh() {
                if (this.busy) return;
                this.busy = true;
                try {
                    const res = await fetch(this.url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    const data = await res.json();
                    if (data.success) { this.mine = data.mine; this.squad = data.squad || []; }
                } catch (e) { /* offline at the venue — keep showing the last known state */ }
                finally { this.busy = false; }
            },

            /*
             * The mat moved: a bout was scored, or the draw was re-cut. Re-fetch
             * rather than patching, because every athlete's countdown shifts.
             *
             * The mobile shell re-runs inline scripts on each AJAX navigation, so
             * the previous handler is removed before re-adding — otherwise they
             * stack and each nav multiplies the requests.
             */
            listen() {
                if (window.__nextUpHandler) {
                    window.removeEventListener('realtime:events', window.__nextUpHandler);
                }
                window.__nextUpHandler = (ev) => {
                    const action = ev.detail?.action;
                    if (['outcome', 'draw', 'entrants', 'podium'].includes(action)) this.refresh();
                };
                window.addEventListener('realtime:events', window.__nextUpHandler);
            },
        };
    }
</script>
@endpush
