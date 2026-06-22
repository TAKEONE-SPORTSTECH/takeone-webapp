@extends('layouts.personal-mobile')

@section('title', $e['title'])

{{--
    Event detail — mobile. DUMMY content driven by PersonalMobileController@eventShow.
    Stylish, animated single-event page: gradient cover, going/like actions,
    capacity, agenda timeline, attendees, location, share. Reuses the shared
    mobile motion vocabulary (m-hero, m-card, m-press, m-bar-fill, m-float) and
    design tokens. Wire the action buttons to real endpoints when ready.
--}}
@section('personal-content')
@php
    $pPaid   = !str_contains(strtolower($e['participant_fee']), 'free') && !str_contains(strtolower($e['participant_fee']), 'qualified');
    $byQual  = str_contains(strtolower($e['participant_fee']), 'qualified');
    $hasTicket = !empty($e['spectator']);
    $ticketPaid = $hasTicket && !str_contains(strtolower($e['spectator']['fee']), 'free');
@endphp
<div x-data="{
        going: false,
        watching: false,
        liked: false,
        likes: 36,
        goingCount: {{ $e['going'] }},
        spectators: {{ $hasTicket ? $e['spectator']['count'] : 0 }},
        cap: {{ $e['cap'] }},
        byQual: {{ $byQual ? 'true' : 'false' }},
        toggleGoing() {
            if (this.byQual) { window.showToast('info','Entry is by qualification only'); return; }
            this.going = !this.going;
            this.goingCount += this.going ? 1 : -1;
            @if($pPaid)
                if (this.going) { window.showToast('success', 'Spot reserved · {{ $e['participant_fee'] }} — payment confirmed at the club'); }
                else { window.showToast('info', 'You left this event'); }
            @else
                window.showToast(this.going ? 'success' : 'info', this.going ? 'You\'re in! See you there 🎉' : 'You left this event');
            @endif
        },
        toggleWatch() {
            this.watching = !this.watching;
            this.spectators += this.watching ? 1 : -1;
            @if($ticketPaid)
                window.showToast(this.watching ? 'success' : 'info', this.watching ? 'Ticket booked · {{ $e['spectator']['fee'] }} — show this in the app at the door' : 'Ticket released');
            @else
                window.showToast(this.watching ? 'success' : 'info', this.watching ? 'You\'re on the guest list 🎟️' : 'Removed from guest list');
            @endif
        },
        toggleLike() {
            this.liked = !this.liked;
            this.likes += this.liked ? 1 : -1;
        },
        get pct() { return Math.round(this.goingCount / this.cap * 100); }
     }"
     class="-mx-4 -mt-4 pb-4">

    {{-- ===== Cover ===== --}}
    <header class="m-hero px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        {{-- top bar --}}
        <div class="flex items-center justify-between relative z-10">
            <a href="{{ route('me.events') }}" data-shell-link data-route="me.events"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="Back">
                <i class="bi bi-arrow-left text-lg"></i>
            </a>
            <button type="button" @click="$dispatch('share-event')"
                    class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="Share">
                <i class="bi bi-share text-base"></i>
            </button>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi {{ $e['icon'] }}"></i> {{ $e['type'] }}
                </span>
                @if($pPaid || $byQual)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-cash-coin"></i> Paid entry</span>
                @endif
                @if($ticketPaid)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-ticket-perforated"></i> Ticketed</span>
                @endif
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>{{ $e['club'] }}
            </p>
        </div>
    </header>

    {{-- ===== Quick facts card (overlaps cover) ===== --}}
    <div class="px-4 -mt-10 relative z-10">
        <div class="bg-white rounded-3xl shadow-lg border border-gray-100 p-4">
            <div class="grid grid-cols-3 gap-2 text-center">
                <div>
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-calendar3"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5">{{ $e['wday'] }} {{ $e['day'] }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ $e['mon'] }}</p>
                </div>
                <div class="border-x border-gray-100">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-clock"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5">{{ $e['time'] }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ $e['duration'] }}</p>
                </div>
                <div>
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-cash-coin"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5">{{ $e['participant_fee'] }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ $byQual ? 'Entry' : 'To join' }}</p>
                </div>
            </div>

            {{-- capacity --}}
            <div class="mt-4">
                <div class="flex items-center justify-between text-[11px] mb-1.5">
                    <span class="font-semibold text-foreground"><span x-text="goingCount">{{ $e['going'] }}</span> going</span>
                    <span class="text-muted-foreground"><span x-text="cap - goingCount">{{ $e['cap'] - $e['going'] }}</span> spots left</span>
                </div>
                <div class="h-2 rounded-full bg-muted overflow-hidden">
                    <div class="m-bar-fill h-full rounded-full" :style="`width:${pct}%; background:{{ $e['color'] }}`" style="width: {{ round($e['going'] / $e['cap'] * 100) }}%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Like / interest row ===== --}}
    <div class="px-4 mt-3">
        <div class="flex items-center gap-2">
            <button type="button" @click="toggleLike()"
                    class="m-press flex-1 py-2.5 rounded-2xl border text-sm font-semibold flex items-center justify-center gap-2 transition-colors"
                    :class="liked ? 'bg-red-50 border-red-200 text-red-600' : 'bg-white border-gray-100 text-muted-foreground'">
                <i class="bi" :class="liked ? 'bi-heart-fill' : 'bi-heart'"></i>
                <span x-text="likes">36</span>
            </button>
            <button type="button" @click="window.showToast('info','Reminder set 1h before')"
                    class="m-press flex-1 py-2.5 rounded-2xl border border-gray-100 bg-white text-muted-foreground text-sm font-semibold flex items-center justify-center gap-2">
                <i class="bi bi-bell"></i> Remind
            </button>
            <button type="button" @click="$dispatch('share-event')"
                    class="m-press flex-1 py-2.5 rounded-2xl border border-gray-100 bg-white text-muted-foreground text-sm font-semibold flex items-center justify-center gap-2">
                <i class="bi bi-share"></i> Share
            </button>
        </div>
    </div>

    {{-- ===== About ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-info-circle text-primary"></i> About this event</h2>
            <p class="text-sm text-muted-foreground leading-relaxed mt-2">{{ $e['about'] }}</p>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach($e['tags'] as $t)
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-muted text-muted-foreground">#{{ $t }}</span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== Prize & divisions (tournaments / championships) ===== --}}
    @if(!empty($e['prize']) || !empty($e['divisions']))
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl p-4">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-trophy text-primary"></i> Prize &amp; divisions</h2>
                @if(!empty($e['prize']))
                    <div class="mt-3 rounded-xl p-3 flex items-center gap-3" style="background: {{ $e['color'] }}0d; border: 1px solid {{ $e['color'] }}26;">
                        <div class="w-10 h-10 rounded-xl grid place-items-center text-white flex-shrink-0" style="background: {{ $e['color'] }};"><i class="bi bi-award-fill text-lg"></i></div>
                        <div>
                            <p class="text-[10px] text-muted-foreground uppercase tracking-wide">Prize pool</p>
                            <p class="text-sm font-black text-foreground">{{ $e['prize'] }}</p>
                        </div>
                    </div>
                @endif
                @if(!empty($e['divisions']))
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach($e['divisions'] as $d)
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-muted text-foreground">{{ $d }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ===== Requirements (belt tests / gradings) ===== --}}
    @if(!empty($e['requirements']))
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl p-4">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-clipboard-check text-primary"></i> Requirements</h2>
                <ul class="mt-3 space-y-2.5">
                    @foreach($e['requirements'] as $req)
                        <li class="flex items-start gap-2.5 text-sm text-muted-foreground">
                            <span class="w-5 h-5 rounded-full grid place-items-center flex-shrink-0 mt-0.5 text-white text-[10px]" style="background: {{ $e['color'] }};"><i class="bi bi-check-lg"></i></span>
                            <span>{{ $req }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- ===== Pricing & tickets ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-tag text-primary"></i> Entry &amp; tickets</h2>

            {{-- Participant fee --}}
            <div class="mt-3 flex items-center gap-3 rounded-xl border border-gray-100 p-3">
                <div class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 {{ $pPaid ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-green-600' }}"><i class="bi bi-person-check text-lg"></i></div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-foreground">{{ $byQual ? 'Take part' : 'Join as participant' }}</p>
                    <p class="text-[11px] text-muted-foreground">
                        @if($byQual) Reserved for qualified finalists
                        @elseif($pPaid) Fee paid to the club (proof at reception)
                        @else Free for members @endif
                    </p>
                </div>
                <span class="text-sm font-black flex-shrink-0 {{ $pPaid ? 'text-amber-600' : 'text-foreground' }}">{{ $e['participant_fee'] }}</span>
            </div>

            {{-- Spectator ticket --}}
            @if($hasTicket)
                <div class="mt-2 flex items-center gap-3 rounded-xl border border-gray-100 p-3">
                    <div class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 {{ $ticketPaid ? 'bg-purple-50 text-primary' : 'bg-sky-50 text-sky-600' }}"><i class="bi bi-ticket-perforated text-lg"></i></div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-foreground">Spectator ticket</p>
                        <p class="text-[11px] text-muted-foreground"><span x-text="spectators">{{ $e['spectator']['count'] }}</span> watching{{ $ticketPaid ? ' · entry to watch the matches' : ' · free to watch' }}</p>
                    </div>
                    <span class="text-sm font-black flex-shrink-0 {{ $ticketPaid ? 'text-primary' : 'text-sky-600' }}">{{ $e['spectator']['fee'] }}</span>
                </div>
                <button type="button" @click="toggleWatch()"
                        class="m-press mt-3 w-full py-2.5 rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-colors border"
                        :class="watching ? 'bg-green-50 text-green-700 border-green-200' : 'border-gray-200 text-foreground'">
                    <i class="bi" :class="watching ? 'bi-check2-circle' : 'bi-ticket-perforated'"></i>
                    <span x-text="watching ? 'Ticket booked' : '{{ $ticketPaid ? 'Buy ticket to watch · '.$e['spectator']['fee'] : 'Get free spectator pass' }}'"></span>
                </button>
            @endif
        </div>
    </div>

    {{-- ===== Tournament lifecycle timeline (phases) ===== --}}
    @if(!empty($e['phases']))
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl p-4">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-signpost-split text-primary"></i> Tournament timeline</h2>
                <div class="mt-3">
                    @foreach($e['phases'] as $i => $ph)
                        @php
                            $done = $ph['status'] === 'done'; $active = $ph['status'] === 'active';
                            $dot = $done ? '#10b981' : ($active ? $e['color'] : '#d1d5db');
                        @endphp
                        <div class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <span class="w-7 h-7 rounded-full grid place-items-center text-white text-[11px] flex-shrink-0" style="background: {{ $dot }};">
                                    <i class="bi {{ $done ? 'bi-check-lg' : $ph['icon'] }}"></i>
                                </span>
                                @if(!$loop->last)<span class="w-0.5 flex-1 my-1" style="background: {{ $done ? '#10b981' : '#e5e7eb' }};"></span>@endif
                            </div>
                            <div class="pb-4 -mt-0.5 min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-bold {{ $active ? '' : 'text-foreground' }}" style="{{ $active ? 'color:'.$e['color'] : '' }}">{{ $ph['label'] }}</p>
                                    <span class="text-[11px] font-semibold text-muted-foreground flex-shrink-0">{{ $ph['date'] }}</span>
                                </div>
                                <p class="text-[11px] text-muted-foreground">{{ $ph['note'] }}</p>
                                @if($active)<span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[9px] font-bold text-white" style="background: {{ $e['color'] }};">NOW</span>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Brackets & draws entry ===== --}}
    @if(!empty($e['categories']))
        @php
            $catCount = count($e['categories']);
            $athleteTotal = collect($e['categories'])->sum('joined');
        @endphp
        <div class="px-4 mt-4">
            <a href="{{ route('me.events.bracket', $e['id']) }}" data-shell-link data-route="me.events"
               class="block m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg"
               style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937);">
                <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
                <div class="relative flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                        <i class="bi bi-diagram-3-fill text-2xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-black text-base leading-tight">Brackets &amp; draws</h3>
                        <p class="text-xs text-white/85 mt-0.5">{{ $catCount }} weight categories · {{ $athleteTotal }} athletes · live results</p>
                    </div>
                    <i class="bi bi-chevron-right text-white/80"></i>
                </div>
            </a>
        </div>
    @endif

    {{-- ===== Agenda timeline ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-list-check text-primary"></i> Schedule</h2>
            <div class="mt-3 space-y-0">
                @foreach($e['agenda'] as $i => $a)
                    <div class="flex gap-3">
                        <div class="flex flex-col items-center">
                            <span class="w-3 h-3 rounded-full" style="background: {{ $e['color'] }};"></span>
                            @if(!$loop->last)<span class="w-0.5 flex-1 bg-gray-100 my-1"></span>@endif
                        </div>
                        <div class="pb-4 -mt-1">
                            <p class="text-xs font-bold text-foreground">{{ $a['t'] }}</p>
                            <p class="text-xs text-muted-foreground">{{ $a['d'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== Participants (people who already joined) ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2">
                    <i class="bi bi-people text-primary"></i> {{ $byQual ? 'Finalists' : 'Who’s joined' }}
                </h2>
                <span class="text-[11px] font-semibold text-primary" x-text="`${goingCount} in`">{{ $e['going'] }} in</span>
            </div>
            <div class="mt-3 space-y-2.5">
                @foreach($e['participants'] as $i => $pp)
                    @php $initials = collect(explode(' ', $pp['name']))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''); @endphp
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0"
                             style="background: hsl({{ ($i * 67) % 360 }} 55% 58%);">{{ $initials }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground truncate">{{ $pp['name'] }}</p>
                            <p class="text-[11px] text-muted-foreground truncate">{{ $pp['meta'] }}</p>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-600 flex-shrink-0"><i class="bi bi-check2"></i> Joined</span>
                    </div>
                @endforeach
                @php $more = max($e['going'] - count($e['participants']), 0); @endphp
                @if($more > 0)
                    <p class="text-[11px] text-muted-foreground text-center pt-1">+ {{ $more }} more joined</p>
                @endif
            </div>

            @if($hasTicket)
                <div class="mt-3 pt-3 border-t border-gray-100 flex items-center gap-2 text-[12px] text-muted-foreground">
                    <i class="bi bi-eye text-sky-500"></i>
                    <span><span x-text="spectators">{{ $e['spectator']['count'] }}</span> {{ str_contains(strtolower($e['spectator']['fee']),'free') ? 'spectators watching free' : 'spectators with tickets' }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- ===== Location ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl overflow-hidden">
            <div class="h-28 relative grid place-items-center"
                 style="background: linear-gradient(135deg, {{ $e['color'] }}22, {{ $e['color'] }}11);">
                <i class="bi bi-geo-alt-fill text-3xl m-float" style="color: {{ $e['color'] }};"></i>
            </div>
            <div class="p-4 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-foreground">{{ $e['location'] }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ $e['address'] }}</p>
                </div>
                <button type="button" @click="window.showToast('info','Opening directions…')"
                        class="m-press flex-shrink-0 px-3 py-1.5 rounded-lg bg-accent text-primary text-xs font-bold flex items-center gap-1.5">
                    <i class="bi bi-cursor"></i> Directions
                </button>
            </div>
        </div>
    </div>

    {{-- ===== Join action ===== --}}
    <div class="px-4 mt-4">
        @if($byQual)
            {{-- Spectator-first event: participation is by qualification, so the
                 primary CTA is the ticket to watch. --}}
            <div class="m-card rounded-2xl p-4 flex items-center gap-3">
                <div class="leading-tight">
                    <p class="text-[10px] text-muted-foreground uppercase tracking-wide">Ticket</p>
                    <p class="text-base font-black text-foreground">{{ $hasTicket ? $e['spectator']['fee'] : '—' }}</p>
                </div>
                <button type="button" @click="toggleWatch()"
                        class="m-press flex-1 py-3 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 transition-colors"
                        :class="watching ? 'bg-green-50 text-green-700 border border-green-200' : 'text-white'"
                        :style="watching ? '' : 'background: {{ $e['color'] }}'">
                    <i class="bi" :class="watching ? 'bi-check2-circle' : 'bi-ticket-perforated'"></i>
                    <span x-text="watching ? 'Ticket booked' : 'Buy ticket to watch'"></span>
                </button>
            </div>
        @else
            <div class="m-card rounded-2xl p-4 flex items-center gap-3">
                <div class="leading-tight">
                    <p class="text-[10px] text-muted-foreground uppercase tracking-wide">{{ $pPaid ? 'Entry fee' : 'Entry' }}</p>
                    <p class="text-base font-black text-foreground">{{ $e['participant_fee'] }}</p>
                </div>
                <button type="button" @click="toggleGoing()"
                        class="m-press flex-1 py-3 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 transition-colors"
                        :class="going ? 'bg-green-50 text-green-700 border border-green-200' : 'text-white'"
                        :style="going ? '' : 'background: {{ $e['color'] }}'">
                    <i class="bi" :class="going ? 'bi-check2-circle' : 'bi-plus-circle'"></i>
                    <span x-text="going ? 'You\'re going' : '{{ $pPaid ? 'Register · '.$e['participant_fee'] : 'Join event' }}'"></span>
                </button>
            </div>
        @endif
    </div>

    {{-- Share handler (dummy) --}}
    <div x-init="$el.addEventListener('share-event-fired', () => {})"
         @share-event.window="
            if (navigator.share) { navigator.share({ title: '{{ addslashes($e['title']) }}', text: 'Join me at {{ addslashes($e['title']) }}!' }).catch(()=>{}); }
            else { window.showToast('success', 'Event link copied'); }
         "></div>

</div>
@endsection
