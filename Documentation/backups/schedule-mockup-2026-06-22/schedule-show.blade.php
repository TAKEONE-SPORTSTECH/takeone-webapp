@extends('layouts.personal-mobile')

@section('title', $s['title'])

{{--
    Training session detail — mobile. DUMMY content from PersonalMobileController@scheduleShow.
    Gradient cover, quick facts, focus chips, and the full workout breakdown
    (warm-up · main lifts with sets×reps · cool-down) with check-off + a
    "start / complete session" action. Reuses the shared mobile motion vocabulary.
--}}
@php
    $mainCount = count($s['workout']['main']);
@endphp

@section('personal-content')
<div x-data="{
        done: {{ ($s['status'] ?? 'upcoming') === 'done' ? 'true' : 'false' }},
        checked: {},
        get doneCount() { return Object.values(this.checked).filter(Boolean).length; },
        toggle(i) { this.checked[i] = !this.checked[i]; },
        complete() {
            this.done = true;
            for (let i = 0; i < {{ $mainCount }}; i++) this.checked[i] = true;
            window.showToast('success', 'Session complete — nice work! 💪');
        }
     }"
     class="-mx-4 -mt-4 pb-4">

    {{-- ===== Cover ===== --}}
    <header class="m-hero px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $s['color'] }}, {{ $s['color'] }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-10">
            <a href="{{ route('me.schedule') }}" data-shell-link data-route="me.schedule"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="Back">
                <i class="bi bi-arrow-left text-lg"></i>
            </a>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                <i class="bi {{ $s['icon'] }}"></i> {{ $s['discipline'] }}
            </span>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-full grid place-items-center text-white text-[10px] font-bold border-2 border-white/40" style="background: {{ $member['color'] }};">{{ $member['initials'] }}</span>
                <span class="text-sm text-white/85 font-medium">{{ $member['name'] }} · {{ $member['relation'] }}</span>
            </div>
            <h1 class="text-2xl font-black mt-2 leading-tight">{{ $s['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-clock"></i>{{ $s['start'] }} – {{ $s['end'] }} · {{ $s['duration'] }}
            </p>
        </div>
    </header>

    {{-- ===== Quick facts (overlaps cover) ===== --}}
    <div class="px-4 -mt-10 relative z-10">
        <div class="bg-white rounded-3xl shadow-lg border border-gray-100 p-4">
            <div class="grid grid-cols-3 gap-2 text-center">
                <div>
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-speedometer2"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5">{{ $s['intensity'] }}</p>
                    <p class="text-[10px] text-muted-foreground">Intensity</p>
                </div>
                <div class="border-x border-gray-100">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-person-badge"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5 truncate px-1">{{ $s['coach'] }}</p>
                    <p class="text-[10px] text-muted-foreground">Coach</p>
                </div>
                <div>
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-geo-alt"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5 truncate px-1">{{ $s['location'] }}</p>
                    <p class="text-[10px] text-muted-foreground">Where</p>
                </div>
            </div>

            {{-- focus chips --}}
            <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-100">
                <span class="text-[11px] font-semibold text-muted-foreground mr-1 self-center">Focus:</span>
                @foreach($s['focus'] as $f)
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-medium" style="background: {{ $s['color'] }}1a; color: {{ $s['color'] }};">{{ $f }}</span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== Coach note ===== --}}
    @if(!empty($s['notes']))
        <div class="px-4 mt-4">
            <div class="rounded-2xl p-4 flex items-start gap-3" style="background: {{ $s['color'] }}0d; border: 1px solid {{ $s['color'] }}26;">
                <i class="bi bi-chat-quote-fill text-lg" style="color: {{ $s['color'] }};"></i>
                <div>
                    <p class="text-xs font-bold text-foreground">Coach’s note</p>
                    <p class="text-sm text-muted-foreground mt-0.5">{{ $s['notes'] }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Warm-up ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-fire text-amber-500"></i> Warm-up</h2>
            <ul class="mt-3 space-y-2">
                @foreach($s['workout']['warmup'] as $w)
                    <li class="flex items-start gap-2.5 text-sm text-muted-foreground">
                        <i class="bi bi-dot text-lg leading-none" style="color: {{ $s['color'] }};"></i><span>{{ $w }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- ===== Main workout (check-off) ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-clipboard-check text-primary"></i> Workout</h2>
                <span class="text-[11px] font-semibold text-muted-foreground"><span x-text="doneCount">0</span>/{{ $mainCount }} done</span>
            </div>
            <div class="mt-3 space-y-2.5">
                @foreach($s['workout']['main'] as $i => $ex)
                    <button type="button" @click="toggle({{ $i }})"
                            class="m-press w-full text-start flex items-center gap-3 rounded-xl p-3 border transition-colors"
                            :class="checked[{{ $i }}] ? 'bg-green-50 border-green-200' : 'bg-white border-gray-100'">
                        <span class="w-7 h-7 rounded-lg grid place-items-center flex-shrink-0 border-2 transition-colors"
                              :class="checked[{{ $i }}] ? 'bg-green-500 border-green-500 text-white' : 'border-gray-200 text-transparent'">
                            <i class="bi bi-check-lg text-sm"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-foreground" :class="checked[{{ $i }}] ? 'line-through opacity-60' : ''">{{ $ex['name'] }}</p>
                            @if(!empty($ex['note']))<p class="text-[11px] text-muted-foreground">{{ $ex['note'] }}</p>@endif
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-sm font-black" style="color: {{ $s['color'] }};">{{ $ex['sets'] }}×{{ $ex['reps'] }}</p>
                            <p class="text-[10px] text-muted-foreground">sets × reps</p>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== Cool-down ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-snow text-sky-500"></i> Cool-down</h2>
            <ul class="mt-3 space-y-2">
                @foreach($s['workout']['cooldown'] as $c)
                    <li class="flex items-start gap-2.5 text-sm text-muted-foreground">
                        <i class="bi bi-dot text-lg leading-none" style="color: {{ $s['color'] }};"></i><span>{{ $c }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- ===== Action ===== --}}
    <div class="px-4 mt-4">
        <button type="button" @click="complete()" x-show="!done"
                class="m-press w-full py-3.5 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2"
                style="background: {{ $s['color'] }};">
            <i class="bi bi-play-circle-fill"></i> Start &amp; complete session
        </button>
        <div x-show="done" x-cloak class="m-card rounded-2xl p-4 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl grid place-items-center text-white m-float" style="background: #10b981;"><i class="bi bi-check2-circle text-2xl"></i></div>
            <p class="text-sm font-bold text-green-600 mt-2">Session completed</p>
            <p class="text-xs text-muted-foreground mt-0.5">Logged to {{ $member['name'] }}’s training history.</p>
        </div>
    </div>

</div>
@endsection
