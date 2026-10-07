{{--
    The way in, for a VISITOR — moved verbatim out of entry/public/mobile so the
    member's way in (member-way-in) can offer the same two cards where they are
    the right answer for a member too: "my entry" for an entry made through the
    public door, and "enter" for somebody the member join cannot take.

    Reads `$e` (the poster payload) and `$mine`.
--}}
    <div class="px-4 mt-4">
        @if(($mine ?? null))
            {{-- Already in — so the one action here is the way back to what you
                 entered, not another way to enter. The event's own colour, not
                 the maroon of the entry door: this is a place you have been,
                 not a door you have yet to open.

                 It is read for the VIEWER only, so it says nothing about
                 anybody else on a page open to the world. --}}
            <a href="{{ route('events.public.my-entry', ['event' => $e['key']]) }}"
               class="m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5 no-underline"
               style="background: {{ \App\Support\Palette::eventBand($e['color']) }};">
                <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>

                <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi {{ $mine === 'pending' ? 'bi-hourglass-split' : 'bi-person-check-fill' }} text-2xl"></i>
                </div>
                <div class="relative min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight">{{ __('events::events.my_entry_cta') }}</h3>
                    <p class="text-xs text-white/85 mt-0.5">
                        {{ $mine === 'pending' ? __('events::events.my_entry_pending_hint') : __('events::events.my_entry_cta_hint') }}
                    </p>
                </div>
                <i class="bi bi-chevron-right text-white/80 relative flex-shrink-0"></i>
            </a>
        @elseif($e['enrol']['open'] || ! empty($e['club_entry']))
            <div class="space-y-3">
            @if($e['enrol']['open'])
            {{-- The same card as the doors above it — icon tile, title, sub-line,
                 chevron — so the way IN reads as one of the places you can go
                 rather than a button bolted underneath them. Maroon rather than
                 the event's own colour: it is the one action on this page, and
                 it should not be mistaken for another door. The hint that used
                 to sit under the button is now its sub-line, where a sub-line
                 already goes. --}}
            <a href="{{ route('events.public.enter', ['event' => $e['key']]) }}"
               class="m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5 no-underline"
               style="background: linear-gradient(135deg, #7f1d1d, #1f2937);">
                <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>

                <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi bi-person-plus-fill text-2xl"></i>
                </div>
                <div class="relative min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight">{{ __('events::events.public_enrol_cta') }}</h3>
                    <p class="text-xs text-white/85 mt-0.5">{{ __('events::events.public_enrol_cta_hint') }}</p>
                </div>
                <i class="bi bi-chevron-right text-white/80 relative flex-shrink-0"></i>
            </a>
            @endif
            @if(! empty($e['club_entry']))
                {{-- "Register your club" — the club's way in (PublicEvent →
                     `club_entry`): rules first, then the club, its athletes,
                     review. The only card on a clubs-only event. --}}
                <a href="{{ $e['club_entry'] }}"
                   class="m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5 no-underline"
                   style="background: linear-gradient(135deg, #7f1d1d, #1f2937);">
                    <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>

                    <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                        <i class="bi bi-building-add text-2xl"></i>
                    </div>
                    <div class="relative min-w-0 flex-1">
                        <h3 class="font-black text-base leading-tight">{{ __('events::events.club_entry_cta') }}</h3>
                        <p class="text-xs text-white/85 mt-0.5">{{ __('events::events.club_entry_cta_hint') }}</p>
                    </div>
                    <i class="bi bi-chevron-right rtl:rotate-180 text-white/80 relative flex-shrink-0"></i>
                </a>
            @endif
            </div>
        @else
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center">
                <i class="bi bi-lock-fill text-xl" style="color: {{ $e['color'] }}"></i>
                <p class="text-sm font-bold text-foreground mt-2">{{ __('events::events.public_entries_closed') }}</p>
                <p class="text-[12px] text-muted-foreground mt-1 leading-relaxed">{{ $e['enrol']['note'] ?: __('events::events.public_how_to_enter_body', ['club' => $e['host']]) }}</p>
            </div>
        @endif
    </div>
