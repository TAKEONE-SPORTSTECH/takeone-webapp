{{--
    The derived podium sheet, as the two event consoles include it.

    The sheet itself is `<x-event-podium-sheet>` — one component, reusable from
    any page. This file stays as the include the consoles already write, and
    does the one thing a component cannot: read the console's `$e` view array
    and its `$sealed` flag, and pass the console's own `resultsOpen` Alpine
    flag through so nothing about how they open it changes.

    Expects `$e` (the event view: `divisions`, `bracket_results`, `title`,
    `key`).
--}}
<x-event-podium-sheet
    :divisions="$e['divisions'] ?? []"
    :results="$e['bracket_results'] ?? []"
    :title="$e['title']"
    :draw-url="($sealed ?? false)
        ? url('/e/'.$e['key'].'/admin/brackets/manage')
        : route('me.events.bracket.manage', $e['key'])"
    {{-- Where a medallist's row goes. ⚠️ Inside a SEALED event page the
         platform is out of bounds (App\Events\Middleware\SealEventPage), so the
         person opens on the event's own mirror of that profile instead —
         `sealed.people.show`, same controller, same authorization. --}}
    :profile-url="($sealed ?? false)
        ? url('/e/'.$e['key'].'/admin/person/__UUID__')
        : route('people.show', '__UUID__')"
    flag="resultsOpen" />
