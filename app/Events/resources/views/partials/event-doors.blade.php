{{-- ===== The doors out of an event page =====

     One list, one order, one piece of markup — rendered by the PUBLIC poster
     (`/e/{uuid}`) and by the member page (`/me/events/{uuid}`) alike. The two
     surfaces show the same event to two audiences and had grown apart: the
     public one pushed each thing behind a door, while the member one stacked
     the entry list, the winners and a league's whole table inline and pushed
     the way in a screen and a half below the fold. They are the same event.

     Each caller supplies WHERE its doors lead — the public sections, or the
     member routes — and this decides what a door LOOKS like and what order
     they come in. A key that is absent is simply not a door.

     Expects:
       $e      the event payload (for its colour)
       $doors  ['draw'|'table'|'officials'|'gallery'|'participants'|'results' => [
                   'icon'  => 'bi-…',
                   'label' => '…',
                   'sub'   => '…',
                   'href'  => '…',           a page, or
                   'click' => 'alpine expr', a sheet on this page
                   'shell' => bool           member routes swap in place
                   'style' => '…'            a door that is not the event's colour
               ]]

     The ORDER is deliberate and is the reading order of a competition: how it
     is being played (the draw, and the tables of the classes that are not
     drawn), who is running it, what was filmed, who is in it, and who won. --}}

@php
    $rows = collect(['draw', 'table', 'officials', 'gallery', 'participants', 'results'])
        ->map(fn (string $key) => ($doors[$key] ?? null) ? $doors[$key] + ['key' => $key] : null)
        ->filter()
        ->values();
@endphp

@if($rows->isNotEmpty())
    <div class="px-4 mt-4 space-y-3">
        @foreach($rows as $d)
            @php
                $tile = 'm-press w-full rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5 no-underline text-start';
                $paint = $d['style'] ?? 'background: linear-gradient(135deg, '.$e['color'].', #1f2937);';
            @endphp

            {{-- A door that opens a PAGE is a link; one that opens a sheet on
                 this page is a button. Same tile either way — a reader should
                 not have to know which. --}}
            @if(! empty($d['click']))
                <button type="button" @click="{{ $d['click'] }}" class="{{ $tile }}" style="{{ $paint }}">
            @else
                <a href="{{ $d['href'] }}" @if(! empty($d['shell'])) data-shell-link data-route="me.events" @endif
                   class="{{ $tile }}" style="{{ $paint }}">
            @endif

                <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>

                {{-- inline-block via .bracket-icon: a bare <i> is an inline box
                     and CSS transforms do not apply to those, so the bracket's
                     quarter turn would silently do nothing. --}}
                <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi {{ $d['icon'] }} text-2xl"></i>
                </div>

                <div class="relative min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight">{{ $d['label'] }}</h3>
                    <p class="text-xs text-white/85 mt-0.5 truncate">{!! $d['sub'] !!}</p>
                </div>

                <i class="bi bi-chevron-right rtl:rotate-180 text-white/80 relative flex-shrink-0"></i>

            @if(! empty($d['click']))
                </button>
            @else
                </a>
            @endif
        @endforeach
    </div>
@endif
