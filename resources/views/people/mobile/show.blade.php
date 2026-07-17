@extends('layouts.app')

@section('hide-navbar', true)
@section('title', $person->full_name)

@section('content')
@php
    $avatar = $person->profile_picture ? asset('storage/'.$person->profile_picture).'?v='.optional($person->updated_at)->timestamp : null;
@endphp
<div class="min-h-screen bg-background pb-20" x-data="{ following: {{ $isFollowing ? 'true' : 'false' }} }">

    {{-- Glass back bar, floating over the hero (same treatment as the member profile). --}}
    <div class="fixed top-0 inset-x-0 z-50 flex items-center px-3 h-14">
        <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('me.people') }}')"
                class="m-press w-10 h-10 rounded-full bg-white/20 backdrop-blur border border-white/30 flex items-center justify-center text-white"
                aria-label="{{ __('shared.back') }}">
            <i class="bi bi-arrow-left text-lg"></i>
        </button>
    </div>

    {{-- ===== Hero ===== --}}
    <header class="m-hero relative px-5 pt-20 pb-14 text-white text-center">
        <div class="relative z-10">
            <span class="w-28 h-[149px] mx-auto rounded-[22px] overflow-hidden grid place-items-center ring-4 ring-white/25 shadow-xl block">
                @if($avatar)
                    <img src="{{ $avatar }}" alt="{{ $person->full_name }}" class="w-28 h-[149px] object-cover">
                @else
                    <x-gender-avatar :gender="$person->gender" class="w-28 h-[149px]" />
                @endif
            </span>

            <h1 class="mt-3.5 text-2xl font-extrabold leading-tight">{{ $person->full_name }}</h1>

            <div class="mt-2 flex items-center gap-2 flex-wrap justify-center">
                @if($person->is_personal_trainer)
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-white/20 backdrop-blur border border-white/25">
                        <i class="bi bi-mortarboard-fill"></i>{{ __('personal.people_trainer') }}
                    </span>
                @endif
                <span class="inline-flex items-center gap-1 text-[11px] font-medium px-2.5 py-1 rounded-full bg-white/10 text-white/85">
                    <i class="bi bi-calendar3"></i>{{ __('personal.member_since') }} {{ optional($person->created_at)->format('M Y') }}
                </span>
            </div>

            {{-- Actions --}}
            <div class="mt-5 flex items-center justify-center gap-2">
                <button type="button" @click="
                        const was = following; following = !was;
                        fetch('{{ url('u') }}/{{ $person->slug }}/follow', { method: was ? 'DELETE' : 'POST', headers: { 'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,'Accept':'application/json' }, credentials:'same-origin' }).then(r=>{if(!r.ok)throw r}).catch(()=>{ following = was; window.showToast && window.showToast('error','Could not update'); });"
                        class="m-press min-w-[7.5rem] text-sm font-bold py-2.5 px-5 rounded-full transition-colors shadow-sm"
                        :class="following ? 'bg-white/20 backdrop-blur border border-white/30 text-white' : 'bg-white text-primary'"
                        x-text="following ? '{{ __('personal.following') }}' : '{{ __('personal.follow') }}'"></button>

                @if($canMessage)
                    <form method="POST" action="{{ route('messages.start', $person) }}">
                        @csrf
                        <button type="submit" class="m-press w-11 h-11 rounded-full bg-white/20 backdrop-blur border border-white/30 grid place-items-center text-white"
                                aria-label="{{ __('personal.message') }}">
                            <i class="bi bi-chat-dots text-lg"></i>
                        </button>
                    </form>
                @endif

                <a href="{{ route('me.challenge.create') }}"
                   class="m-press w-11 h-11 rounded-full bg-white/20 backdrop-blur border border-white/30 grid place-items-center text-white"
                   aria-label="{{ __('personal.challenge') }}">
                    <i class="bi bi-lightning-charge-fill text-lg"></i>
                </a>
            </div>
        </div>
    </header>

    <div class="px-4 mobile-stagger" x-data="{ section: 'clubs' }">

        {{-- ===== Stats — floats over the hero's lower edge; also the tab switcher for the card below ===== --}}
        <div class="m-card -mt-8 relative z-10 grid grid-cols-3 py-3.5">
            <button type="button" class="text-center m-press" @click="section = 'clubs'">
                <p class="text-xl font-extrabold leading-none" :class="section === 'clubs' ? 'text-primary' : 'text-foreground'">{{ $activeAffil->count() }}</p>
                <p class="text-[10px] mt-1.5" :class="section === 'clubs' ? 'text-primary' : 'text-muted-foreground'">{{ __('personal.active_clubs') }}</p>
            </button>
            <button type="button" class="text-center border-x border-border/70 m-press" @click="section = 'medals'">
                <p class="text-xl font-extrabold leading-none" :class="section === 'medals' ? 'text-primary' : 'text-foreground'">{{ $awards->count() }}</p>
                <p class="text-[10px] mt-1.5" :class="section === 'medals' ? 'text-primary' : 'text-muted-foreground'">{{ __('personal.medals') }}</p>
            </button>
            <button type="button" class="text-center m-press" @click="section = 'challenges'">
                <p class="text-xl font-extrabold leading-none" :class="section === 'challenges' ? 'text-primary' : 'text-foreground'">{{ $winRate }}%</p>
                <p class="text-[10px] mt-1.5" :class="section === 'challenges' ? 'text-primary' : 'text-muted-foreground'">{{ __('personal.win_rate') }}</p>
            </button>
        </div>

        {{-- ===== Skills ===== --}}
        @if($skills->count())
            <div class="m-card mt-3 px-4 py-4">
                <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2.5">{{ __('personal.skills') }}</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($skills as $s)
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $s }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ===== Clubs / Medals / Challenges — one card, content swaps with the stat tapped above ===== --}}
        <div class="m-card mt-3 px-4 py-4">
            {{-- Clubs --}}
            <div x-show="section === 'clubs'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2.5">{{ __('personal.active_clubs') }}</p>
                @forelse($activeAffil as $a)
                    @include('people.partials.club-row', ['a' => $a, 'active' => true])
                @empty
                    <div class="py-6 text-center">
                        <i class="bi bi-buildings text-2xl text-muted-foreground/50"></i>
                        <p class="text-sm text-muted-foreground mt-1.5">{{ __('personal.no_public_clubs') }}</p>
                    </div>
                @endforelse

                @if($pastAffil->count())
                    <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mt-5 mb-2.5">{{ __('personal.previous_clubs') }}</p>
                    @foreach($pastAffil as $a)
                        @include('people.partials.club-row', ['a' => $a, 'active' => false])
                    @endforeach
                @endif
            </div>

            {{-- Medals --}}
            <div x-show="section === 'medals'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2.5">{{ __('personal.medals') }}</p>
                @if($awards->count())
                    <div class="space-y-2">
                        @foreach($awards as $a)
                            @php $r = mb_strtolower($a->member_award ?? ''); $emoji = str_contains($r,'gold')?'🥇':(str_contains($r,'silver')?'🥈':(str_contains($r,'bronze')?'🥉':'🏅')); @endphp
                            <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-2.5">
                                <span class="w-10 h-10 rounded-full bg-amber-50 grid place-items-center text-xl flex-shrink-0">{{ $emoji }}</span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-sm text-foreground truncate">{{ $a->member_award ?: __('member.award_default') }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">{{ $a->tenant?->club_name }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 text-center">
                        <i class="bi bi-award text-2xl text-muted-foreground/50"></i>
                        <p class="text-sm text-muted-foreground mt-1.5">{{ __('personal.no_medals') }}</p>
                    </div>
                @endif
            </div>

            {{-- Challenges --}}
            <div x-show="section === 'challenges'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2.5">{{ __('personal.challenges') }}</p>
                @forelse($duels as $d)
                    @php
                        $resultStyle = match($d->result) {
                            'win' => ['bg-green-100', 'text-green-700', __('personal.challenge_win')],
                            'loss' => ['bg-red-100', 'text-red-700', __('personal.challenge_loss')],
                            default => ['bg-gray-100', 'text-gray-500', __('personal.challenge_draw')],
                        };
                    @endphp
                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-2.5 mb-2 last:mb-0">
                        <span class="w-10 h-10 rounded-full bg-muted grid place-items-center overflow-hidden flex-shrink-0 ring-1 ring-gray-100">
                            @if($d->rival_picture)
                                <img src="{{ asset('storage/'.$d->rival_picture) }}" alt="" class="w-10 h-10 object-cover">
                            @else
                                <i class="bi bi-lightning-charge-fill text-muted-foreground"></i>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm text-foreground truncate">{{ $d->rival_name }}</p>
                            <p class="text-[11px] text-muted-foreground truncate">{{ $d->discipline ?: \App\Models\Duel::formatLabel($d->format) }} · {{ optional($d->completed_at)->format('M j, Y') }}</p>
                        </div>
                        <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $resultStyle[0] }} {{ $resultStyle[1] }}">{{ $resultStyle[2] }}</span>
                    </div>
                @empty
                    <div class="py-6 text-center">
                        <i class="bi bi-lightning-charge text-2xl text-muted-foreground/50"></i>
                        <p class="text-sm text-muted-foreground mt-1.5">{{ __('personal.no_challenges') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
