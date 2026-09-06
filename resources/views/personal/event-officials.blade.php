{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.personal-mobile')

@section('title', __('personal.event_manage_officials'))

{{--
    The officiating sheet — reading only.

    Who is running this competition, grouped by the job, in the order the sport
    itself lists them. There is nothing to act on here for anybody: an organiser
    sees exactly what a first-time competitor sees, and appointing happens on the
    event's edit screen, where the authority to appoint lives.

    Three facts per person — name, job, country — because that is what an
    officiating sheet has always printed. No email, no phone, no fee: those are
    appointment paperwork and stay behind the organiser-guarded JSON endpoint. A
    photo appears only where the member published one.

    Expects $e (the event view), $groups (role → people) and $total.
--}}

@php
    $flagClass = function ($code) {
        $c = strtolower(substr(preg_replace('/[^a-zA-Z]/', '', (string) $code), 0, 2));

        return strlen($c) === 2 ? 'fi fi-'.$c : null;
    };
@endphp

@section($contentSection ?? 'content')
{{-- ⚠️ The hero band below is full-bleed (Design Rule #6), so the page wrapper's
     `px-4 py-4` has to be cancelled — but ONLY where there is one to cancel.
     This page fills `content`, which on the member path replaces
     layouts.personal-mobile's own section and lands in an UNPADDED <main>;
     inside the sealed event shell (entry/shell) it fills `personal-content` and
     lands in a padded one. Cancelling unconditionally would fix the sealed page
     and push the member page 16px past both screen edges, so the cancellation
     follows the shell. --}}
<div class="{{ isset($shell) ? '-mx-4 -mt-4' : '' }}">
    {{-- ===== Header ===== Design Rule #6: full-bleed hero band, the first card
         riding up over its tail. --}}
    <header class="m-hero px-5 pt-5 pb-8 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        {{-- Back is the round 40px control with a TAIL-LESS chevron and no words
             (Design Rule #6, 2026-09-04); the destination is its aria-label. --}}
        <div class="flex items-center justify-between gap-2 relative z-50">
            {{-- Inside the sealed event app, back from a sub-screen means the
                     CONSOLE — the screen it was opened from. On the platform it
                     still means the event page. Same pill, honest label either
                     way (the audit: "'Event' means two different pages"). --}}
                <a href="{{ isset($shell) ? url('/e/'.$e['key'].'/admin/manage') : route('me.events.show', $e['key']) }}" data-shell-link data-route="me.events"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline"
           aria-label="{{ isset($shell) ? __('personal.event_manage_title') : __('personal.event_show_event') }}" title="{{ isset($shell) ? __('personal.event_manage_title') : __('personal.event_show_event') }}">
                <i class="bi bi-chevron-left"></i>
            </a>

            {{-- Appointing lives here, on the trailing edge, where every other
                 page action on this platform lives (Design Rule #6). It renders
                 nothing at all for a reader who may only look. --}}
            <x-event-officials :event="$e['key']" :can-manage="$canManage ?? false" />
        </div>

        {{-- Identity: chips, the title, then whose event it is. --}}
        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-person-badge"></i> {{ trans_choice('personal.event_manage_officials_count', $total, ['count' => $total]) }}
                </span>
                @if(!empty($e['sport_label']))
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi {{ $e['sport_icon'] ?? 'bi-dribbble' }}"></i> {{ $e['sport_label'] }}</span>
                @endif
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('personal.event_manage_officials') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>{{ $e['club'] }}
            </p>
        </div>
    </header>

    {{-- No negative margin: nothing rides up over the band here. The cards are a
         plain list, and pulling the first one onto the colour clipped it against
         the title. The band is therefore sized to its own content — pb-8, a little
         more than its pt so the owner line is not sitting on the edge, and no
         empty colour beneath it since nothing overlaps. --}}
    <div class="px-4 mt-4 relative z-10 pb-8 space-y-2.5 mobile-stagger">
        @forelse($groups as $g)
            @foreach($g['people'] as $p)
                @php $flag = $flagClass($p['nationality']); @endphp

                {{-- One card per person, in the sport's own order of jobs — no
                     heading over each group: the job is already on the card,
                     beside the name, and printing it twice made the page read as
                     a list of roles that happen to have people in them rather
                     than a list of people.

                     Same card as a competitor on the roster — same portrait, same
                     shape — because they are the same kind of thing: a person at
                     this event. An <a> with no href when they have no public
                     profile: still a card, just not a door. --}}
                <a @if($p['uuid']) href="{{ route('people.show', $p['uuid']) }}" @endif
                    class="m-card m-press flex items-start gap-3 bg-white rounded-2xl border border-gray-100 shadow-sm p-3 {{ $p['uuid'] ? 'hover:shadow-md transition-shadow' : '' }}">

                    {{-- Portrait, or the silhouette when they have not published a
                         picture. Never an initial-letter crest: invented detail
                         about a person reads as fact. --}}
                    <div class="flex-shrink-0">
                        @if($p['photo'])
                            <img src="{{ $p['photo'] }}" alt="" class="w-12 h-16 rounded-2xl object-cover border border-gray-100">
                        @else
                            <x-gender-avatar :gender="$p['gender']" class="w-12 h-16 rounded-2xl border border-gray-100" />
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        {{-- Who, and what they are doing here — one sentence in two
                             pieces. --}}
                        <div class="flex items-baseline gap-2 min-w-0">
                            <p class="font-bold text-sm text-foreground truncate">{{ $p['name'] }}</p>
                            <span class="px-2 py-0.5 rounded-full bg-accent text-primary text-[10px] font-bold flex-shrink-0">{{ $g['label'] }}</span>
                        </div>

                        {{-- Where they are from, spelled out. Not "BH" — two
                             letters is a form value, and a sheet is read at a
                             glance by people. --}}
                        @if($flag || $p['country'])
                            <div class="flex items-center gap-2 mt-1 min-w-0">
                                @if($flag)
                                    <span class="{{ $flag }} w-6 h-[18px] rounded-[3px] ring-1 ring-black/5 shadow-sm flex-shrink-0"
                                          style="background-size:cover"></span>
                                @endif
                                <span class="text-[11px] font-semibold text-muted-foreground truncate">{{ $p['country'] }}</span>
                            </div>
                        @endif

                        {{-- What the job actually is. Part of the card, not a strip
                             bolted under it: it is the third line of the same
                             thought, so it reads as one card and not as two. --}}
                        @if($g['hint'])
                            <p class="text-[11px] leading-snug text-muted-foreground mt-1.5">{{ $g['hint'] }}</p>
                        @endif
                    </div>

                    @if($p['uuid'])
                        <i class="bi bi-chevron-right text-muted-foreground text-xs flex-shrink-0 mt-1"></i>
                    @endif
                </a>
            @endforeach
        @empty
            <div class="m-card bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center">
                <div class="w-14 h-14 rounded-2xl bg-accent text-primary grid place-items-center mx-auto">
                    <i class="bi bi-person-badge text-2xl"></i>
                </div>
                <p class="text-sm font-bold text-foreground mt-3">{{ __('personal.personal_event_officials_none') }}</p>
                <p class="text-[12px] text-muted-foreground mt-1">{{ __('personal.personal_event_officials_intro') }}</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
