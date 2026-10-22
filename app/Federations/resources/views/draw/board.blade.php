{{--
    The federation dashboard's draw, drawn by the shared <x-competition-brackets>.

    The dashboard is a prototype page (not Blade), so the board lives in this
    same-origin frame and the two talk by postMessage:

      dashboard → frame   {type: 'fed-draw:load', state}      the division as it stands
      frame → dashboard   {type: 'fed-draw:ready'}
                          {type: 'fed-draw:winner', key, winner, a, b}

    The frame persists NOTHING and holds no data of its own: every winner goes
    back to the dashboard, which opens its own result sheet (how the bout was
    won) and saves through federations.api.draws — where FederationDraws
    re-checks the athlete really was in that bout. `recordOnly`: the draw
    itself (who meets whom) is fixed by the dashboard's fair draw and locked by
    the first result, so picking winners is the only edit offered.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    {{-- Dark by design (a wall screen): say so, so no browser re-darkens it. --}}
    <meta name="color-scheme" content="dark">
    <meta name="darkreader-lock">
    <meta name="robots" content="noindex">
    <title>{{ __('Draw') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    @vite(['resources/css/app.css'])
    <style>
        html, body { margin: 0; height: 100%; background: #0b132b; overflow: hidden; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body>
    <x-competition-brackets id="fed-draw" :competitors="[]" :editable="$canRecord" :record-only="true"
                            third-place="shared" width="100%" height="100%" />

    <script>
    (function () {
        'use strict';
        var parentWin = window.parent !== window ? window.parent : null;
        if (!parentWin) return;
        var origin = window.location.origin;
        var pending = null;

        function board() { return window.CompetitionBrackets && window.CompetitionBrackets.get('fed-draw'); }
        function apply(state) {
            var b = board();
            if (!b) { pending = state; return; }
            b.load(state);
        }
        function send(msg) { parentWin.postMessage(msg, origin); }
        function who(f) { return f ? {id: String(f.id), name: String(f.name || '')} : null; }

        window.addEventListener('message', function (e) {
            if (e.origin !== origin || e.source !== parentWin || !e.data || e.data.type !== 'fed-draw:load') return;
            apply(e.data.state || {});
        });

        document.addEventListener('bracket:winner', function (e) {
            var d = e.detail || {}, b = board();
            if (!b || typeof d.round !== 'number') return;   // federation draws have no bronze / play-in bouts
            send({type: 'fed-draw:winner', key: d.key, winner: d.winner == null ? null : String(d.winner),
                a: who(b.part(d.round, d.match, 0)), b: who(b.part(d.round, d.match, 1))});
        });

        // The board mounts when Alpine starts; hand it anything that arrived first.
        (function wait(n) {
            if (board()) { if (pending) { board().load(pending); pending = null; } send({type: 'fed-draw:ready'}); return; }
            if (n < 200) setTimeout(function () { wait(n + 1); }, 25);
        })(0);
    })();
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
