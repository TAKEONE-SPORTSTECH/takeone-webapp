{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.app')

@section('title', $person->full_name)

@section($contentSection ?? 'content')
@php
    // Resolved by the controller: their own profile picture when they have
    // one, otherwise the competition photograph their public entry already
    // shows (App\Events\Support\EntryPhoto::publicFaceFor).
    $avatar = $avatarUrl ?? ($person->profile_picture ? file_url($person->profile_picture).'?v='.optional($person->updated_at)->timestamp : null);

    // Same country the mobile profile shows, from the same controller value:
    // the club's, falling back to the account's nationality only when this person
    // has no club at all. Lower-cased because it becomes a flag-icons class.
    $flag = $countryCode ? mb_strtolower($countryCode) : null;
    $flagLabel = $countryCode ? (\App\Support\Countries::name($countryCode) ?: mb_strtoupper($countryCode)) : null;
@endphp
<div class="px-4 sm:px-6 lg:px-8 py-6" x-data="{ following: {{ $isFollowing ? 'true' : 'false' }} }">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: identity + actions --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center">
                {{-- Silhouette underneath, photograph on top: a picture that
                     404s (bytes gone, or a face this viewer may not read —
                     App\Support\FileAccess) falls back to a face rather than a
                     broken-image glyph. Mirrors people/mobile/show. --}}
                <span class="relative w-28 h-[149px] rounded-[22px] overflow-hidden grid place-items-center ring-1 ring-gray-100 shadow-sm mx-auto">
                    <x-gender-avatar :gender="$person->gender" class="w-28 h-[149px]" />
                    @if($avatar)
                        <img src="{{ $avatar }}" alt="{{ $person->full_name }}" onerror="this.remove()"
                             class="absolute inset-0 w-28 h-[149px] object-cover">
                    @endif
                </span>
                <h1 class="mt-4 text-xl font-bold text-gray-900">{{ $person->full_name }}</h1>
                <div class="mt-1 flex items-center gap-2 flex-wrap justify-center">
                    @if($flag)
                        {{-- Who they represent. Named as well as flagged: a 22px flag
                             is not readable as a country on its own. --}}
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-muted text-foreground" title="{{ $flagLabel }}">
                            <span class="fi fi-{{ $flag }}" style="flex-shrink:0;width:18px;height:13px;border-radius:2px;background-size:cover;box-shadow:0 0 0 1px rgba(0,0,0,.08)"></span>{{ $flagLabel }}
                        </span>
                    @endif
                    @if($person->is_personal_trainer)
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-accent text-primary"><i class="bi bi-mortarboard-fill"></i>{{ __('personal.people_trainer') }}</span>
                    @endif
                </div>
                <p class="text-xs text-muted-foreground mt-2"><i class="bi bi-calendar3 mr-1"></i>{{ __('personal.member_since') }} {{ optional($person->created_at)->format('M Y') }}</p>

                <div class="mt-5 flex flex-col gap-2">
                    {{-- A guest is offered sign-in rather than a Follow button that
                         would fail the moment they pressed it. --}}
                    @if($isGuest ?? false)
                        <a href="{{ route('login') }}"
                           class="text-sm font-semibold py-2.5 rounded-lg bg-primary text-white hover:bg-primary/90 transition-colors text-center">
                            <i class="bi bi-box-arrow-in-right mr-1"></i>{{ __('personal.sign_in_to_connect') }}
                        </a>
                    @else
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
                    @endif
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
            {{-- Competition record — bouts fought at club events. Kept apart from
                 the challenge win-rate on purpose: a friendly duel and a national
                 final are not the same achievement. Hidden entirely when there is
                 nothing to show, rather than printing a row of zeros. --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <i class="bi bi-trophy text-primary"></i>{{ __('personal.competition_record') }}
                    </h2>
                    <div class="grid grid-cols-4 gap-3 text-center">
                        <div>
                            <p class="text-2xl font-extrabold text-gray-900 leading-none">{{ $competition['fought'] }}</p>
                            <p class="text-[11px] text-muted-foreground mt-1.5">{{ __('personal.bouts') }}</p>
                        </div>
                        <div>
                            <p class="text-2xl font-extrabold text-green-600 leading-none">{{ $competition['won'] }}</p>
                            <p class="text-[11px] text-muted-foreground mt-1.5">{{ __('personal.won') }}</p>
                        </div>
                        <div>
                            <p class="text-2xl font-extrabold text-gray-400 leading-none">{{ $competition['lost'] }}</p>
                            <p class="text-[11px] text-muted-foreground mt-1.5">{{ __('personal.lost') }}</p>
                        </div>
                        <div>
                            <p class="text-2xl font-extrabold text-primary leading-none">{{ $competition['rate'] === null ? '—' : $competition['rate'].'%' }}</p>
                            <p class="text-[11px] text-muted-foreground mt-1.5">{{ __('personal.win_rate') }}</p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100 space-y-2">
                        @foreach($competitionBouts as $b)
                            @php $boutHref = $b['video_url'] ?: $b['bout_url']; @endphp
                            <{{ $boutHref ? 'a' : 'div' }} @if($boutHref) href="{{ $boutHref }}" @endif
                                class="flex items-center gap-3 {{ $boutHref ? 'rounded-lg -mx-2 px-2 py-1 hover:bg-accent/60 transition-colors' : '' }}">
                                <span class="w-8 h-8 rounded-full grid place-items-center flex-shrink-0 {{ $b['won'] ? 'bg-green-100' : 'bg-muted' }}">
                                    <i class="bi {{ ! $b['decided'] ? 'bi-hourglass-split text-amber-600' : ($b['won'] ? 'bi-trophy-fill text-green-700' : 'bi-dash-lg text-muted-foreground') }} text-xs"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ __('personal.vs_opponent', ['name' => $b['opponent']]) }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">{{ collect([$b['event'], $b['division'], $b['round']])->filter()->implode(' · ') }}</p>
                                </div>
                                @if($b['my_score'] !== null || $b['their_score'] !== null)
                                    <span class="text-sm font-extrabold tabular-nums text-gray-900">{{ $b['my_score'] ?? '–' }}–{{ $b['their_score'] ?? '–' }}</span>
                                @endif
                                @if($b['video_url'])
                                    <i class="bi bi-play-circle-fill text-primary flex-shrink-0" title="{{ __('personal.watch_bout') }}"></i>
                                @endif
                            </{{ $boutHref ? 'a' : 'div' }}>
                        @endforeach
                        @if(empty($competitionBouts))
                            <p class="text-sm text-muted-foreground">{{ __('personal.no_bouts') }}</p>
                        @endif
                        <p class="text-xs text-muted-foreground pt-2">
                            {{ trans_choice('personal.across_n_events', $competition['events'], ['count' => $competition['events']]) }}
                        </p>
                    </div>
                </div>

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
                    @include('members::people.partials.club-row', ['a' => $a, 'active' => true])
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('personal.no_public_clubs') }}</p>
                @endforelse

                @if($pastAffil->count())
                    <h2 class="text-sm font-bold text-gray-900 mt-5 mb-3">{{ __('personal.previous_clubs') }}</h2>
                    @foreach($pastAffil as $a)
                        @include('members::people.partials.club-row', ['a' => $a, 'active' => false])
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
