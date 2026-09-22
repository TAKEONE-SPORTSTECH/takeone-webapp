{{-- ===== Who won =====
     The result sheet, as it would be pinned to the wall of the hall: one card
     per division, the places in order, the champion first.

     ⚠️ THE CARDS ARE `<x-event-podium-list>`, the same component the organiser's
     own results sheet draws. This page used to render its own version of them,
     and the two drifted exactly as you would expect: the organiser's sheet was
     taught that a championship can award ONE bronze and a real fourth place,
     and this one carried on labelling every place below silver as bronze. A
     medal is a fact about a competition and cannot look like two different
     facts on two pages of the same site.

     A medal is also public: this page is the competition's own face, so the
     names are here in plain text — and nothing else. No entry ids, no
     photographs, no scores; the same restraint as the entry list.

     Only DECIDED divisions arrive here (`PublicEvent::results()` skips a
     division with no podium), which is why no division list is passed: a
     visitor is reading a result sheet, not an organiser's outstanding work. --}}

{{-- The section lays out its own surface (see section-mobile), so the page's
     gutters are already on it: no `px-4` here, or every card is inset twice. --}}
<div class="pt-1 pb-4">
    @if($e['results']['divisions'] ?? [])
        {{-- A medallist opens on the event's OWN person page, never the
             platform's: this poster is a standalone app and may not hand a
             reader out of it. PodiumFaces already withheld the key for anybody
             whose profile would not open for this viewer — which for a signed
             -out visitor is a narrow rule: discoverable, and not a minor — so
             the rows that are not links are the ones with nowhere to go. --}}
        <x-event-podium-list :results="$e['results']['divisions']"
                             :profile-url="url('/e/'.$e['key'].'/admin/person/__UUID__')" />
    @else
        @include('entry.public.partials.empty', [
            'ev' => $ev, 'icon' => 'bi-trophy',
            'title' => __('events.public_results_empty_title'),
            'flush' => true,
        ])
    @endif
</div>
