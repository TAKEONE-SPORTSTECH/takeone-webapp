{{--
    The sport-neutral waiting room: one address any screen can be pointed at,
    before anybody knows which package will own it.

    The whole page is `<x-screen-pairing>` — see that component for the design
    and for why all three pairing doors share it. What is specific to THIS door
    is only the shape of its status endpoint: it hands back the address the
    screen should go to, because a machine that enrolled here has no board of its
    own to reload into.
--}}
<x-screen-pairing
    :code="$code"
    :claim-url="$claimUrl"
    :status-url="$statusUrl"
    poll-mode="go"
    :poll-every="4000" />
