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
                @php $medalEmoji = fn($mt) => ['1st'=>'🥇','2nd'=>'🥈','3rd'=>'🥉','special'=>'🏆'][$mt] ?? '🏅'; @endphp
                @if($awards->count() || $verifiedMedals->count())
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
                        @foreach($verifiedMedals as $t)
                            @foreach($t->performanceResults as $r)
                                <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-2.5">
                                    <span class="w-10 h-10 rounded-full bg-amber-50 grid place-items-center text-xl flex-shrink-0">{{ $medalEmoji($r->medal_type) }}</span>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-sm text-foreground truncate">{{ $t->title }}</p>
                                        <p class="text-[11px] text-muted-foreground truncate flex items-center gap-1"><i class="bi bi-patch-check-fill text-green-600"></i>{{ $t->verifiedByTenant?->tr('club_name') ?? $t->verifiedByTenant?->club_name }}</p>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                @else
                    <div class="py-6 text-center">
                        <i class="bi bi-award text-2xl text-muted-foreground/50"></i>
                        <p class="text-sm text-muted-foreground mt-1.5">{{ __('personal.no_medals') }}</p>
                    </div>
                @endif

                {{-- Awaiting peer/coach verification --}}
                @if($vouchable->count())
                    <div class="mt-4" x-data="peopleVouchMobile()">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('Awaiting verification') }}</p>
                        <div class="space-y-2">
                            @foreach($vouchable as $t)
                                <div class="flex items-center justify-between gap-2 rounded-xl border border-gray-100 p-2.5" data-vouch-card="{{ $t->uuid }}">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-sm text-foreground truncate">{{ $t->title }}</p>
                                        <p class="text-[11px] text-muted-foreground truncate">{{ $t->sport }} · {{ optional($t->date)->format('M Y') }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <x-verification-badge :status="$t->verification_status" size="xs" />
                                        @if($canVouch)
                                            <button type="button" @click="openVouch('{{ route('attestations.vouch', ['achievement', $t->uuid]) }}', $el.closest('[data-vouch-card]'))" class="text-xs font-medium text-primary border border-primary rounded-lg px-2.5 py-1">{{ __('Vouch') }}</button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Vouch bottom-sheet (teleported) --}}
                        <template x-teleport="body">
                            <div x-show="showModal" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="showModal=false">
                                <div x-show="showModal" x-transition.opacity class="absolute inset-0 bg-black/50" @click="showModal=false"></div>
                                <div x-show="showModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                                     class="absolute inset-x-0 bottom-0 bg-background rounded-t-3xl shadow-2xl p-5" style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));">
                                    <div class="w-10 h-1 rounded-full bg-gray-300 mx-auto mb-3"></div>
                                    <h3 class="font-bold text-foreground mb-1">{{ __('Vouch for this achievement') }}</h3>
                                    <p class="text-xs text-muted-foreground mb-3">{{ __('Only vouch for what you personally witnessed.') }}</p>
                                    <div class="grid grid-cols-2 gap-2 mb-3">
                                        <template x-for="opt in relOptions" :key="opt.v">
                                            <button type="button" @click="relationship=opt.v" class="px-3 py-2.5 rounded-xl border text-sm text-start" :class="relationship===opt.v ? 'border-primary bg-primary/5 text-primary font-medium' : 'border-gray-200 text-gray-600'" x-text="opt.l"></button>
                                        </template>
                                    </div>
                                    <textarea x-model="note" rows="2" placeholder="{{ __('Add a note (optional)') }}" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent mb-3"></textarea>
                                    <button type="button" @click="submit()" :disabled="saving" class="w-full bg-primary text-white py-3 rounded-xl font-semibold disabled:opacity-60">{{ __('Submit vouch') }}</button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <script>
                    function peopleVouchMobile() {
                        return {
                            showModal: false, saving: false, relationship: 'teammate', note: '', url: '', card: null,
                            relOptions: [ { v:'coach', l:@js(__('Coach')) }, { v:'official', l:@js(__('Official')) }, { v:'teammate', l:@js(__('Teammate')) }, { v:'other', l:@js(__('Other')) } ],
                            openVouch(url, card) { this.url = url; this.card = card; this.relationship = 'teammate'; this.note = ''; this.showModal = true; },
                            async submit() {
                                this.saving = true;
                                try {
                                    const res = await fetch(this.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ stance: 'vouch', relationship: this.relationship, note: this.note }) });
                                    const data = await res.json();
                                    if (data.success) {
                                        if (this.card && data.verification && data.verification.status === 'verified') this.card.remove();
                                        window.showToast && window.showToast('success', data.message);
                                        this.showModal = false;
                                    } else { window.showToast && window.showToast('error', data.message || @js(__('Could not record your vouch.'))); }
                                } catch (e) { window.showToast && window.showToast('error', @js(__('Something went wrong.'))); }
                                this.saving = false;
                            },
                        };
                    }
                    </script>
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
