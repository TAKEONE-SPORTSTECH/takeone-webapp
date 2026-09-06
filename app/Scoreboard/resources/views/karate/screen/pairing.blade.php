{{--
    What a Karate court screen shows before it knows which mat it is.

    The whole page is the shared `<x-screen-pairing>` component — see it for the
    design, and for why every pairing door in the product looks the same. This
    view supplies only what is this package's own: its claim address, its status
    endpoint, and its realtime link.

    The status endpoint here answers `{claimed}` rather than an address, because
    THIS url renders the board once the screen is claimed — so the screen reloads
    into itself rather than being sent somewhere. The realtime link below is the
    fast path: it jumps to the board the moment the screen is paired instead of
    waiting out the poll.
--}}
<x-screen-pairing
    :code="$code"
    :claim-url="$claimUrl"
    :status-url="route('karate-court-display.status', $token, false)"
    poll-mode="claimed"
    :poll-every="5000">

    @isset($screenLink)
        @include('scoreboard::karate.screen.partials.screen-link')
    @endisset
</x-screen-pairing>
