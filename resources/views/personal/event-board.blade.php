@extends('layouts.app')

@section('title', __('event-taekwondo_tournament::messages.board_title', ['event' => $e['title']]))

{{--
    Venue board — a hall screen, not an app page.

    ONE view rather than a mobile/desktop pair: a board is a single display
    surface that scales, not two diverging layouts. A phone propped on the
    officials' table and a 55" panel above the mats show the same thing at
    different sizes.

    Design brief it answers: readable across a sports hall. Dark, so it does not
    glare; huge type; the red/blue corners of Taekwondo carrying the meaning
    instead of decoration; on-deck bouts visibly quieter than the live one.

    Refreshes on the realtime channel AND polls slowly, because arena wifi drops
    and a board that silently freezes is worse than one a few seconds stale.
--}}
@section('content')
<div x-data="matBoard(@js($mats), '{{ route('me.events.board', $e['key']) }}{{ request('mat') ? '?mat='.urlencode(request('mat')) : '' }}')"
     x-init="start()"
     class="min-h-screen bg-gray-950 text-white -mx-4 sm:-mx-6 lg:-mx-8 -my-6 px-6 py-6">

    <header class="flex items-center justify-between mb-6">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-white/40">{{ $e['title'] }}</p>
            <h1 class="text-2xl font-black tracking-tight">{{ __('event-taekwondo_tournament::messages.board_heading') }}</h1>
        </div>
        <div class="flex items-center gap-2 text-white/40 text-xs font-medium">
            <span class="w-2 h-2 rounded-full" :class="stale ? 'bg-amber-400' : 'bg-emerald-400 animate-pulse'"></span>
            <span x-text="stale ? '{{ __('event-taekwondo_tournament::messages.board_stale') }}' : '{{ __('event-taekwondo_tournament::messages.board_live') }}'"></span>
        </div>
    </header>

    <template x-if="!mats.length">
        <div class="grid place-items-center py-32 text-white/30">
            <i class="bi bi-pause-circle text-5xl"></i>
            <p class="mt-4 text-lg font-medium">{{ __('event-taekwondo_tournament::messages.board_idle') }}</p>
        </div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-5">
        <template x-for="mat in mats" :key="mat.court">
            <section class="rounded-3xl bg-white/[0.04] border border-white/10 overflow-hidden">

                <div class="px-6 py-3 bg-white/[0.06] flex items-center justify-between">
                    <h2 class="text-lg font-black tracking-wide" x-text="mat.court"></h2>
                    <span class="font-mono text-sm text-white/40" x-show="mat.now" x-text="mat.now?.code"></span>
                </div>

                {{-- live bout --}}
                <template x-if="mat.now">
                    <div class="px-6 py-6">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-400 mb-3"
                           x-text="[mat.now.round, mat.now.division].filter(Boolean).join(' · ')"></p>

                        <div class="space-y-2">
                            <div class="flex items-center gap-3 rounded-2xl bg-red-500/15 border border-red-500/30 px-4 py-3">
                                <span class="w-8 h-8 rounded-lg bg-red-500 grid place-items-center text-xs font-black flex-shrink-0">R</span>
                                <span class="text-2xl font-black truncate" x-text="mat.now.red || '—'"></span>
                            </div>
                            <div class="flex items-center gap-3 rounded-2xl bg-blue-500/15 border border-blue-500/30 px-4 py-3">
                                <span class="w-8 h-8 rounded-lg bg-blue-500 grid place-items-center text-xs font-black flex-shrink-0">B</span>
                                <span class="text-2xl font-black truncate" x-text="mat.now.blue || '—'"></span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- queue --}}
                <div class="px-6 pb-5" x-show="mat.on_deck.length">
                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/30 mb-2">
                        {{ __('event-taekwondo_tournament::messages.board_on_deck') }}
                    </p>
                    <ul class="space-y-1.5">
                        <template x-for="(b, i) in mat.on_deck" :key="b.code || i">
                            <li class="flex items-center gap-3 text-sm" :class="i === 0 ? 'text-white/80' : 'text-white/40'">
                                <span class="font-mono text-xs w-12 flex-shrink-0" x-text="b.code || ''"></span>
                                <span class="truncate" x-text="(b.red || '—') + '  v  ' + (b.blue || '—')"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </section>
        </template>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function matBoard(mats, url) {
        return {
            mats, url, stale: false, timer: null,

            async refresh() {
                try {
                    const res = await fetch(this.url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    const data = await res.json();
                    if (data.success) { this.mats = data.mats || []; this.stale = false; }
                } catch (e) {
                    // Arena wifi dropped. Keep showing the last good board and
                    // flag it, rather than blanking the screen above the mats.
                    this.stale = true;
                }
            },

            start() {
                // Live channel first — a result lands within a second.
                if (window.__boardHandler) {
                    window.removeEventListener('realtime:events', window.__boardHandler);
                }
                window.__boardHandler = (ev) => {
                    if (['outcome', 'draw', 'entrants'].includes(ev.detail?.action)) this.refresh();
                };
                window.addEventListener('realtime:events', window.__boardHandler);

                // Slow poll as the safety net: a hall screen has nobody to press
                // reload, so it must recover from a dropped socket on its own.
                clearInterval(this.timer);
                this.timer = setInterval(() => this.refresh(), 30000);
            },
        };
    }
</script>
@endpush
