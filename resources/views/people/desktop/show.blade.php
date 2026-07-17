@extends('layouts.app')

@section('title', $person->full_name)

@section('content')
@php
    $avatar = $person->profile_picture ? asset('storage/'.$person->profile_picture).'?v='.optional($person->updated_at)->timestamp : null;
@endphp
<div class="px-4 sm:px-6 lg:px-8 py-6" x-data="{ following: {{ $isFollowing ? 'true' : 'false' }} }">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: identity + actions --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center">
                <span class="w-28 h-[149px] rounded-[22px] overflow-hidden grid place-items-center ring-1 ring-gray-100 shadow-sm mx-auto">
                    @if($avatar)
                        <img src="{{ $avatar }}" alt="{{ $person->full_name }}" class="w-28 h-[149px] object-cover">
                    @else
                        <x-gender-avatar :gender="$person->gender" class="w-28 h-[149px]" />
                    @endif
                </span>
                <h1 class="mt-4 text-xl font-bold text-gray-900">{{ $person->full_name }}</h1>
                <div class="mt-1 flex items-center gap-2 flex-wrap justify-center">
                    @if($person->is_personal_trainer)
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-accent text-primary"><i class="bi bi-mortarboard-fill"></i>{{ __('personal.people_trainer') }}</span>
                    @endif
                </div>
                <p class="text-xs text-muted-foreground mt-2"><i class="bi bi-calendar3 mr-1"></i>{{ __('personal.member_since') }} {{ optional($person->created_at)->format('M Y') }}</p>

                <div class="mt-5 flex flex-col gap-2">
                    <button type="button" @click="
                            const was = following; following = !was;
                            fetch('{{ url('u') }}/{{ $person->slug }}/follow', { method: was ? 'DELETE' : 'POST', headers: { 'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,'Accept':'application/json' }, credentials:'same-origin' }).then(r=>{if(!r.ok)throw r}).catch(()=>{ following = was; window.showToast && window.showToast('error','Could not update'); });"
                            class="text-sm font-semibold py-2.5 rounded-lg transition-colors"
                            :class="following ? 'bg-muted text-muted-foreground' : 'bg-primary text-white hover:bg-primary/90'"
                            x-text="following ? '{{ __('personal.following') }}' : '{{ __('personal.follow') }}'"></button>
                    @if($canMessage)
                        <form method="POST" action="{{ route('messages.start', $person) }}">
                            @csrf
                            <button type="submit" class="w-full text-sm font-semibold py-2.5 rounded-lg border border-primary text-primary bg-white hover:bg-accent transition-colors">
                                <i class="bi bi-chat-dots mr-1"></i>{{ __('personal.message') }}
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('me.challenge.create') }}" class="w-full text-sm font-semibold py-2.5 rounded-lg border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 transition-colors text-center">
                        <i class="bi bi-lightning-charge-fill mr-1 text-primary"></i>{{ __('personal.challenge') }}
                    </a>
                </div>

                <div class="grid grid-cols-3 gap-2 mt-5 pt-5 border-t border-gray-100">
                    <div><p class="text-lg font-extrabold text-gray-900">{{ $activeAffil->count() }}</p><p class="text-[11px] text-muted-foreground">{{ __('personal.active_clubs') }}</p></div>
                    <div><p class="text-lg font-extrabold text-gray-900">{{ $awards->count() }}</p><p class="text-[11px] text-muted-foreground">{{ __('personal.medals') }}</p></div>
                    <div><p class="text-lg font-extrabold text-gray-900">{{ $winRate }}%</p><p class="text-[11px] text-muted-foreground">{{ __('personal.win_rate') }}</p></div>
                </div>
            </div>
        </div>

        {{-- Right: clubs, skills, medals --}}
        <div class="lg:col-span-2 space-y-6">
            @if($skills->count())
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-sm font-bold text-gray-900 mb-3">{{ __('personal.skills') }}</h2>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($skills as $s)<span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $s }}</span>@endforeach
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-sm font-bold text-gray-900 mb-3">{{ __('personal.active_clubs') }}</h2>
                @forelse($activeAffil as $a)
                    @include('people.partials.club-row', ['a' => $a, 'active' => true])
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('personal.no_public_clubs') }}</p>
                @endforelse

                @if($pastAffil->count())
                    <h2 class="text-sm font-bold text-gray-900 mt-5 mb-3">{{ __('personal.previous_clubs') }}</h2>
                    @foreach($pastAffil as $a)
                        @include('people.partials.club-row', ['a' => $a, 'active' => false])
                    @endforeach
                @endif
            </div>

            @if($awards->count())
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-sm font-bold text-gray-900 mb-3">{{ __('personal.medals') }}</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($awards as $a)
                            @php $r = mb_strtolower($a->member_award ?? ''); $emoji = str_contains($r,'gold')?'🥇':(str_contains($r,'silver')?'🥈':(str_contains($r,'bronze')?'🥉':'🏅')); @endphp
                            <div class="flex items-center gap-3 border border-gray-100 rounded-xl p-3">
                                <span class="w-11 h-11 rounded-full bg-amber-50 grid place-items-center text-xl flex-shrink-0">{{ $emoji }}</span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-sm text-gray-900 truncate">{{ $a->member_award ?: __('member.award_default') }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">{{ $a->tenant?->club_name }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
