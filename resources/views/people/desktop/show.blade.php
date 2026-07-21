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

            @php $medalEmoji = fn($mt) => ['1st'=>'🥇','2nd'=>'🥈','3rd'=>'🥉','special'=>'🏆'][$mt] ?? '🏅'; @endphp
            @if($awards->count() || $verifiedMedals->count())
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
                        {{-- Club-verified tournament medals (attested → safe to show publicly) --}}
                        @foreach($verifiedMedals as $t)
                            @foreach($t->performanceResults as $r)
                                <div class="flex items-center gap-3 border border-gray-100 rounded-xl p-3">
                                    <span class="w-11 h-11 rounded-full bg-amber-50 grid place-items-center text-xl flex-shrink-0">{{ $medalEmoji($r->medal_type) }}</span>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-sm text-gray-900 truncate">{{ $t->title }}</p>
                                        <p class="text-[11px] text-muted-foreground truncate flex items-center gap-1"><i class="bi bi-patch-check-fill text-green-600"></i>{{ $t->verifiedByTenant?->tr('club_name') ?? $t->verifiedByTenant?->club_name }}</p>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Self-reported achievements awaiting peer/coach attestation --}}
            @if($vouchable->count())
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" x-data="peopleVouch()">
                    <h2 class="text-sm font-bold text-gray-900 mb-1">{{ __('Awaiting verification') }}</h2>
                    <p class="text-xs text-muted-foreground mb-3">{{ __('Self-reported — not yet confirmed. If you witnessed this, you can vouch for it.') }}</p>
                    <div class="space-y-2">
                        @foreach($vouchable as $t)
                            <div class="flex items-center justify-between gap-3 border border-gray-100 rounded-xl p-3" data-vouch-card="{{ $t->uuid }}">
                                <div class="min-w-0">
                                    <p class="font-semibold text-sm text-gray-900 truncate">{{ $t->title }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">{{ $t->sport }} · {{ optional($t->date)->format('M Y') }}</p>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <x-verification-badge :status="$t->verification_status" size="xs" />
                                    @if($canVouch)
                                        <button type="button" @click="openVouch('{{ route('attestations.vouch', ['achievement', $t->uuid]) }}', $el.closest('[data-vouch-card]'))" class="text-xs font-medium text-primary border border-primary rounded-lg px-3 py-1.5 hover:bg-primary hover:text-white transition-colors">{{ __('Vouch') }}</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Vouch modal --}}
                    <template x-teleport="body">
                        <div x-show="showModal" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" @keydown.escape.window="showModal=false">
                            <div x-show="showModal" x-transition.opacity class="absolute inset-0 bg-black/50" @click="showModal=false"></div>
                            <div x-show="showModal" x-transition class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-5">
                                <h3 class="font-bold text-gray-900 mb-1">{{ __('Vouch for this achievement') }}</h3>
                                <p class="text-xs text-muted-foreground mb-4">{{ __('Only vouch for what you personally witnessed. False attestations can be reversed.') }}</p>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('Your relationship') }}</label>
                                <div class="grid grid-cols-2 gap-2 mb-4">
                                    <template x-for="opt in relOptions" :key="opt.v">
                                        <button type="button" @click="relationship=opt.v" class="px-3 py-2.5 rounded-xl border text-sm text-start transition-colors" :class="relationship===opt.v ? 'border-primary bg-primary/5 text-primary font-medium' : 'border-gray-200 text-gray-600'" x-text="opt.l"></button>
                                    </template>
                                </div>
                                <textarea x-model="note" rows="2" placeholder="{{ __('Add a note (optional)') }}" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent mb-4"></textarea>
                                <div class="flex gap-2">
                                    <button type="button" @click="showModal=false" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-xl text-sm font-medium">{{ __('shared.cancel') }}</button>
                                    <button type="button" @click="submit()" :disabled="saving" class="flex-1 bg-primary text-white py-2.5 rounded-xl text-sm font-medium disabled:opacity-60">{{ __('Submit vouch') }}</button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <script>
                function peopleVouch() {
                    return {
                        showModal: false, saving: false, relationship: 'teammate', note: '', url: '', card: null,
                        relOptions: [
                            { v: 'coach', l: @js(__('Coach')) },
                            { v: 'official', l: @js(__('Official')) },
                            { v: 'teammate', l: @js(__('Teammate')) },
                            { v: 'other', l: @js(__('Other')) },
                        ],
                        openVouch(url, card) { this.url = url; this.card = card; this.relationship = 'teammate'; this.note = ''; this.showModal = true; },
                        async submit() {
                            this.saving = true;
                            try {
                                const res = await fetch(this.url, {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                    body: JSON.stringify({ stance: 'vouch', relationship: this.relationship, note: this.note }),
                                });
                                const data = await res.json();
                                if (data.success) {
                                    if (this.card && data.verification && data.verification.status === 'verified') this.card.remove();
                                    window.showToast && window.showToast('success', data.message);
                                    this.showModal = false;
                                } else {
                                    window.showToast && window.showToast('error', data.message || @js(__('Could not record your vouch.')));
                                }
                            } catch (e) { window.showToast && window.showToast('error', @js(__('Something went wrong.'))); }
                            this.saving = false;
                        },
                    };
                }
                </script>
            @endif
        </div>
    </div>
</div>
@endsection
