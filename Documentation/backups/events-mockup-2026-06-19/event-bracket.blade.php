@extends('layouts.personal-mobile')

@section('title', 'Brackets')

{{--
    Tournament brackets — mobile. DUMMY content from PersonalMobileController@eventBracket.
    Per weight category: enrollment state (joined / open slots), the single-elim
    bracket rendered as stacked rounds with full match details (athletes, seeds,
    countries, scores, winners, court & time), and podium + prizes for finished
    categories. Reuses the shared mobile motion vocabulary and design tokens.
--}}
@php
    $color = $e['color'];
    $first = array_key_first($categories);
    // helpers
    $ini = fn ($n) => collect(explode(' ', $n))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp

@section('personal-content')
<div x-data="{ cat: '{{ $first }}' }" class="-mx-4 -mt-4 pb-6">

    {{-- ===== Header ===== --}}
    <header class="m-hero px-5 pt-5 pb-12 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $color }}, #1f2937);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <a href="{{ route('me.events.show', $e['id']) }}" data-shell-link data-route="me.events"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="Back">
                <i class="bi bi-arrow-left text-lg"></i>
            </a>
            <span class="px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur inline-flex items-center gap-1.5">
                <i class="bi bi-diagram-3-fill"></i> Brackets
            </span>
        </div>
        <div class="relative z-10 mt-4">
            <h1 class="text-xl font-black leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1 flex items-center gap-1.5"><i class="bi bi-diagram-3"></i> {{ count($categories) }} weight categories</p>
        </div>
    </header>

    {{-- ===== Category selector ===== --}}
    <div class="px-4 -mt-6 relative z-10">
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-2">
            <div class="flex gap-2 overflow-x-auto scrollbar-hide">
                @foreach($categories as $c)
                    <button type="button" @click="cat='{{ $c['key'] }}'"
                            class="m-press flex-shrink-0 px-3 py-2 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5"
                            :class="cat==='{{ $c['key'] }}' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                        {{ $c['name'] }}
                        @if($c['status'] === 'live')<span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span>
                        @elseif($c['status'] === 'completed')<i class="bi bi-check-circle-fill text-[10px]"></i>@endif
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== Category panels ===== --}}
    @foreach($categories as $c)
        <div x-show="cat==='{{ $c['key'] }}'" x-transition class="px-4 mt-4 space-y-4">

            {{-- status / enrolment summary --}}
            <div class="m-card rounded-2xl p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-black text-foreground">{{ $c['name'] }}</h2>
                        <p class="text-[11px] text-muted-foreground">{{ $c['class'] }}</p>
                    </div>
                    @php
                        $badge = match($c['status']) {
                            'live' => ['Live now', 'bg-red-50 text-red-600'],
                            'completed' => ['Completed', 'bg-green-50 text-green-600'],
                            default => ['Enrolling', 'bg-amber-50 text-amber-600'],
                        };
                    @endphp
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $badge[1] }}">{{ $badge[0] }}</span>
                </div>

                {{-- joined / open slots --}}
                <div class="flex items-center justify-between text-[11px] mt-3 mb-1.5">
                    <span class="font-semibold text-foreground">{{ $c['joined'] }} joined</span>
                    <span class="text-muted-foreground">{{ $c['open'] }} {{ $c['open'] === 1 ? 'slot' : 'slots' }} open</span>
                </div>
                <div class="h-2 rounded-full bg-muted overflow-hidden flex">
                    <div class="m-bar-fill h-full" style="width: {{ round($c['joined'] / $c['cap'] * 100) }}%; background: {{ $color }};"></div>
                </div>
                <p class="text-[11px] text-muted-foreground mt-2 flex items-center gap-1.5"><i class="bi bi-info-circle"></i> {{ $c['note'] }}</p>
            </div>

            {{-- ===== Completed → podium & prizes ===== --}}
            @if($c['status'] === 'completed' && !empty($c['podium']))
                <div class="m-card rounded-2xl p-4">
                    <h3 class="text-sm font-bold text-foreground flex items-center gap-2 mb-3"><i class="bi bi-award-fill text-amber-500"></i> Podium &amp; prizes</h3>
                    <div class="space-y-2">
                        @foreach($c['podium'] as $p)
                            @php $medal = [1 => ['#f59e0b','bi-trophy-fill'], 2 => ['#9ca3af','bi-award-fill'], 3 => ['#b45309','bi-award']][$p['place']]; @endphp
                            <div class="flex items-center gap-3 rounded-xl p-2.5" style="background: {{ $medal[0] }}12;">
                                <div class="w-9 h-9 rounded-full grid place-items-center text-white flex-shrink-0" style="background: {{ $medal[0] }};"><i class="bi {{ $medal[1] }}"></i></div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-foreground truncate">{{ $p['name'] }} <span class="text-[10px] font-semibold text-muted-foreground">{{ $p['country'] }}</span></p>
                                    <p class="text-[11px] text-muted-foreground">{{ $p['place'] === 1 ? 'Champion' : ($p['place'] === 2 ? 'Runner-up' : '3rd place') }}</p>
                                </div>
                                <span class="text-[11px] font-black flex-shrink-0" style="color: {{ $medal[0] }};">{{ $p['prize'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Bracket (rounds) ===== --}}
            @if(!empty($c['rounds']))
                @foreach($c['rounds'] as $round)
                    <div class="m-card rounded-2xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-diagram-2 text-primary"></i> {{ $round['name'] }}</h3>
                            <span class="text-[11px] text-muted-foreground">{{ count($round['matches']) }} {{ count($round['matches']) === 1 ? 'bout' : 'bouts' }}</span>
                        </div>
                        <div class="space-y-3">
                            @foreach($round['matches'] as $m)
                                <div class="rounded-xl border border-gray-100 overflow-hidden">
                                    @foreach(['a', 'b'] as $side)
                                        @php $ath = $m[$side]; $win = $m['winner'] === $side; $lose = $m['winner'] && $m['winner'] !== $side; @endphp
                                        <div class="flex items-center gap-2.5 px-3 py-2.5 {{ $side === 'a' ? 'border-b border-gray-50' : '' }} {{ $win ? '' : '' }}"
                                             style="{{ $win ? 'background: '.$color.'0d;' : '' }}">
                                            <div class="w-8 h-8 rounded-full grid place-items-center text-white text-[10px] font-bold flex-shrink-0 {{ $lose ? 'opacity-50' : '' }}"
                                                 style="background: hsl({{ (crc32($ath['name']) % 360) }} 55% 58%);">{{ $ini($ath['name']) }}</div>
                                            <div class="min-w-0 flex-1 {{ $lose ? 'opacity-60' : '' }}">
                                                <p class="text-sm font-bold text-foreground truncate flex items-center gap-1.5">
                                                    {{ $ath['name'] }}
                                                    @if($win)<i class="bi bi-check-circle-fill text-[11px]" style="color: {{ $color }};"></i>@endif
                                                </p>
                                                <p class="text-[10px] text-muted-foreground">
                                                    @if($ath['country']){{ $ath['country'] }}@endif
                                                    @if($ath['seed']) · #{{ $ath['seed'] }} seed @endif
                                                </p>
                                            </div>
                                            <span class="text-base font-black flex-shrink-0 {{ $win ? '' : 'text-muted-foreground' }}" style="{{ $win ? 'color: '.$color : '' }}">{{ $ath['score'] }}</span>
                                        </div>
                                    @endforeach
                                    {{-- match meta --}}
                                    <div class="flex items-center justify-between px-3 py-1.5 bg-muted/40 text-[10px] text-muted-foreground">
                                        <span><i class="bi bi-geo-alt"></i> {{ $m['court'] }} · {{ $m['time'] }}</span>
                                        @if($m['status'] === 'done')
                                            <span class="font-bold text-green-600"><i class="bi bi-check2"></i> Final</span>
                                        @elseif($m['status'] === 'live')
                                            <span class="font-bold text-red-600 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span> LIVE</span>
                                        @else
                                            <span class="font-semibold">Upcoming</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif

            {{-- ===== Enrolling → roster + open slots ===== --}}
            @if($c['status'] === 'enrolling')
                <div class="m-card rounded-2xl p-4" x-data="{ joined: false }">
                    <h3 class="text-sm font-bold text-foreground flex items-center gap-2 mb-1"><i class="bi bi-people text-primary"></i> Registered athletes</h3>
                    <p class="text-[11px] text-muted-foreground mb-3"><i class="bi bi-clock-history"></i> Bracket &amp; seeding generated after the weigh-in.</p>
                    <div class="space-y-2">
                        @foreach($c['roster'] as $i => $r)
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0" style="background: hsl({{ ($i*67)%360 }} 55% 58%);">{{ $ini($r['name']) }}</div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-foreground truncate">{{ $r['name'] }}</p>
                                    <p class="text-[10px] text-muted-foreground">{{ $r['country'] }}</p>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-600"><i class="bi bi-check2"></i> In</span>
                            </div>
                        @endforeach

                        {{-- open slots (yet to join) --}}
                        @for($s = 0; $s < $c['open']; $s++)
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full grid place-items-center text-gray-300 border-2 border-dashed border-gray-200 flex-shrink-0"><i class="bi bi-person-plus"></i></div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-muted-foreground">Open slot</p>
                                    <p class="text-[10px] text-gray-400">Awaiting entry</p>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-muted text-muted-foreground">Open</span>
                            </div>
                        @endfor
                    </div>

                    <button type="button" x-show="!joined" @click="joined=true; window.showToast('success','Enrolled in {{ $c['name'] }} · {{ $e['participant_fee'] }} — make weight on Jul 22')"
                            class="m-press mt-4 w-full py-3 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2" style="background: {{ $color }};">
                        <i class="bi bi-plus-circle"></i> Enter this category · {{ $e['participant_fee'] }}
                    </button>
                    <div x-show="joined" x-cloak class="mt-4 rounded-2xl bg-green-50 text-green-700 py-3 text-center text-sm font-bold"><i class="bi bi-check2-circle"></i> You're entered — see you at weigh-in</div>
                </div>
            @endif

        </div>
    @endforeach

</div>
@endsection
