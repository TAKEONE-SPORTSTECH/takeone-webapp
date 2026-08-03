@extends('layouts.app')

@section('title', __('event-taekwondo_tournament::messages.next_up_title'))

{{--
    Run day, desktop — the coach's console.

    Same data as the mobile athlete screen, weighted differently: on a phone the
    athlete's own countdown is everything, while at a desk it is a coach
    watching a squad spread across mats. So the squad table leads and the
    personal countdown sits alongside it.
--}}
@section('content')
<div x-data="nextUpDesktop(@js($mine), @js($squad), '{{ route('me.events.next-up', $e['key']) }}')"
     x-init="listen()" class="px-4 sm:px-6 lg:px-8 py-6">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <a href="{{ route('me.events.show', $e['key']) }}"
               class="text-xs font-medium text-muted-foreground hover:text-foreground transition-colors">
                <i class="bi bi-chevron-left rtl:rotate-180"></i> {{ $e['title'] }}
            </a>
            <h1 class="text-3xl font-bold text-gray-900 mt-1">{{ __('event-taekwondo_tournament::messages.next_up_title') }}</h1>
        </div>
        <button type="button" @click="refresh()" :disabled="busy"
                class="border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors">
            <i class="bi bi-arrow-clockwise me-1" :class="busy && 'animate-spin'"></i> {{ __('shared.refresh') }}
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ===== My countdown ===== --}}
        <div class="lg:col-span-1">
            <template x-if="mine">
                <div class="rounded-2xl overflow-hidden shadow-sm border"
                     :class="mine.is_next ? 'bg-red-600 text-white border-red-700' : 'bg-white border-gray-100'">
                    <div class="flex items-center justify-between px-5 py-3 text-[11px] font-bold uppercase tracking-wider"
                         :class="mine.is_next ? 'bg-black/15 text-white/90' : 'bg-muted/70 text-muted-foreground'">
                        <span x-text="mine.court || '—'"></span>
                        <span class="font-mono tracking-widest" x-show="mine.code" x-text="mine.code"></span>
                    </div>

                    <div class="px-6 py-8 text-center">
                        <template x-if="mine.is_next">
                            <div>
                                <i class="bi bi-megaphone-fill text-4xl"></i>
                                <p class="mt-3 text-lg font-bold">{{ __('event-taekwondo_tournament::messages.next_up_you_are_next') }}</p>
                            </div>
                        </template>
                        <template x-if="!mine.is_next">
                            <div>
                                <p class="text-6xl font-black text-primary tabular-nums" x-text="mine.bouts_ahead"></p>
                                <p class="mt-1 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                    {{ __('event-taekwondo_tournament::messages.next_up_ahead') }}
                                </p>
                                <p class="mt-4 text-sm font-bold text-foreground" x-show="mine.eta_minutes !== null">
                                    ≈ <span x-text="mine.eta_minutes"></span> min
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ __('event-taekwondo_tournament::messages.next_up_estimate') }}
                                </p>
                            </div>
                        </template>
                    </div>

                    <div class="px-5 pb-5">
                        <div class="rounded-xl px-4 py-3 flex items-center gap-3"
                             :class="mine.is_next ? 'bg-white/15' : 'bg-muted/60'">
                            <span class="w-8 h-8 rounded-lg grid place-items-center text-white text-xs font-black flex-shrink-0"
                                  :class="mine.corner === 'red' ? 'bg-red-500' : 'bg-blue-500'"
                                  x-text="mine.corner === 'red' ? 'R' : 'B'"></span>
                            <div class="min-w-0">
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

            <template x-if="!mine">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                    <i class="bi bi-check2-circle text-3xl text-muted-foreground"></i>
                    <p class="mt-3 text-sm font-medium text-foreground">{{ __('event-taekwondo_tournament::messages.next_up_none') }}</p>
                </div>
            </template>
        </div>

        {{-- ===== Squad ===== --}}
        <div class="lg:col-span-2">
            <template x-if="squad.length">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-sm font-medium text-muted-foreground">
                            <i class="bi bi-people-fill me-1"></i> {{ __('event-taekwondo_tournament::messages.next_up_squad') }}
                        </h2>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-600" x-text="squad.length"></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="a in squad" :key="a.user_id">
                                    <tr :class="a.next && a.next.is_next ? 'bg-red-50' : ''">
                                        <td class="px-6 py-3">
                                            <p class="font-medium text-foreground" x-text="a.name"></p>
                                            <p class="text-xs text-muted-foreground" x-text="a.division"></p>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                                            x-text="a.next ? a.next.court : ''"></td>
                                        <td class="px-4 py-3 whitespace-nowrap font-mono text-xs text-muted-foreground"
                                            x-text="a.next ? (a.next.code || '') : ''"></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-end">
                                            <template x-if="a.next && a.next.is_next">
                                                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-red-600 text-white">
                                                    <i class="bi bi-megaphone-fill"></i>
                                                </span>
                                            </template>
                                            <template x-if="a.next && !a.next.is_next">
                                                <span class="text-xs font-medium text-foreground">
                                                    <span class="tabular-nums" x-text="a.next.bouts_ahead"></span>
                                                    {{ __('event-taekwondo_tournament::messages.next_up_ahead') }}
                                                </span>
                                            </template>
                                            <template x-if="!a.next">
                                                <span class="text-xs text-muted-foreground">{{ __('event-taekwondo_tournament::messages.next_up_finished') }}</span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function nextUpDesktop(mine, squad, url) {
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
                } catch (e) { /* keep the last known state */ }
                finally { this.busy = false; }
            },

            listen() {
                if (window.__nextUpDeskHandler) {
                    window.removeEventListener('realtime:events', window.__nextUpDeskHandler);
                }
                window.__nextUpDeskHandler = (ev) => {
                    if (['outcome', 'draw', 'entrants', 'podium'].includes(ev.detail?.action)) this.refresh();
                };
                window.addEventListener('realtime:events', window.__nextUpDeskHandler);
            },
        };
    }
</script>
@endpush
