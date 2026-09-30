{{--
    x-competition-brackets — a knockout draw you can drop into any view.

    The Combat Sports Bracket design (rounds → final → podium, red/blue
    corners, gold connectors, the pulsing next bout, champion card with
    confetti, third-place bout) as a standalone component. It is a port of
    the board in app/Events/resources/views/personal/partials/bracket-board.html
    — same look, same rules — onto Blade + Alpine, so a page needs no React and
    no dc-runtime to show a draw.

    ── Usage ────────────────────────────────────────────────────────────────

        x-competition-brackets   (as a component tag)
            id="u18-kumite"
            :competitors="$slots"          first-round slots in draw order; null = bye
            :results="$results"            ['0-0' => ['w' => 12, 'sa' => '3', 'sb' => '1'], 'B' => [...]]
            :editable="$canManage"         offers the padlock; everything stays shut until it is opened
            height="760px" />

    A competitor is `['id', 'name', 'country' (alpha-2/alpha-3/name), 'club',
    'club_ab', 'photo', 'gender']` — every key but `name` optional.

    ── Props ────────────────────────────────────────────────────────────────

      id            DOM id, also the `detail.id` on every event (default: generated)
      competitors   first-round slots, in draw order (slot 2i = red corner of bout i)
      size          slots in the draw: 2·4·8·16·32·64 (default: fits the competitors)
      results       keyed 'round-match' (0-based), 'B' (bronze), 'P<id>' (play-in):
                    ['w' => winner id, 'sa' => red score, 'sb' => blue score]
      playins       [['id', 'r', 'i', 'side', 'entrant' => competitor], …]
      roster        people not yet in the draw — dragged or auto-placed in
      basket        people lifted out of the draw, waiting for a slot
      thirdPlace    'match' (a bronze bout) | 'shared' (two bronzes)
      layout        'linear' (one-sided) | 'split' (halves meet at the final)
      mats          how many mats bouts are spread across (numbering only)
      width/height  the component's box (px, %, vh, rem …); the draw fits inside
      zoom          'fit' (default) or a number to open at
      minZoom/maxZoom
      background    canvas colour (hex)
      editable      offer the padlock and every editing action
      recordOnly    with editable: the ONLY edit is picking winners — no re-seeding,
                    names, adding, removing, resetting or third-place switch. For a
                    host whose draw is fixed elsewhere and which persists winners only.
      locked        start shut (default true — a catch that remembers being open is no catch)
      addable       offer "Add competitor" (default: = editable)
      resizable     offer the draw-size buttons (clears results — default false)
      toolbar       show the zoom / padlock / menu controls
      showClubs, celebration

    ── What it does ─────────────────────────────────────────────────────────

    Pan (drag, one finger), zoom (wheel, pinch, buttons), double-click to fit.
    Unlocked: tap a corner to mark the winner (again to clear), type scores,
    drag first-round competitors to re-seed (a displaced one goes to the
    holding basket), drag from the basket or the roster onto a slot, auto-place
    a late entry (bye fill → pigtail play-in, the design's rules), add a new
    competitor, edit names, remove to a bye, reset results. On a phone, hold a
    competitor to pick them up and tap where they go.

    ── Events out (bubble from the root; `detail.id` is the board) ──────────

      bracket:winner {key, round, match | bronze | playin, winner}
      bracket:score  {key, side: 'a'|'b', value}
      bracket:move   {from, to, competitor, displaced}
      bracket:place  {competitor, slot, via: bye|roster|basket|expand, displaced?}
      bracket:basket {competitor, from}
      bracket:playin {playin, competitor, opponent}
      bracket:add    {competitor}         bracket:rename {competitor, name}
      bracket:remove {slot, competitor}   bracket:reset  {}
      bracket:resize {size, via}          bracket:third-place {mode}
      bracket:layout {layout}             bracket:lock {locked}
      bracket:change {action, state}      after every one of the above

    ── In ───────────────────────────────────────────────────────────────────

      CompetitionBrackets.get(id)  → .load(state) · .getState() · .fit() · .zoomBy(f)
      window event 'competition-brackets:load' {id, state}   (e.g. from a realtime push)

    ⚠️ IT DECIDES NOTHING ABOUT PERMISSION. `editable` decides what is OFFERED;
    the host that persists a `bracket:*` event posts it to an endpoint that
    authorises the actor itself. It also never decides whose face may be shown:
    pass `photo` only where the host's own rules (profile_picture_is_public,
    the event's visibility) allow it — a missing photo draws the placeholder.
--}}
@props([
    'id' => null,
    'competitors' => [],
    'size' => null,
    'results' => [],
    'playins' => [],
    'roster' => [],
    'basket' => [],
    'thirdPlace' => 'match',
    'layout' => 'linear',
    'mats' => 2,
    'width' => '100%',
    'height' => '760px',
    'zoom' => 'fit',
    'minZoom' => 0.3,
    'maxZoom' => 3,
    'background' => '#0b132b',
    'editable' => false,
    'recordOnly' => false,
    'locked' => true,
    'addable' => null,
    'resizable' => false,
    'toolbar' => true,
    'showClubs' => true,
    'celebration' => true,
])

@php
    $cid = is_string($id) && preg_match('/^[A-Za-z][\w-]{0,63}$/', $id) ? $id : 'cb-'.\Illuminate\Support\Str::random(8);
    $box = fn ($v, $d) => is_string($v) && preg_match('/^(\d+(\.\d+)?(px|%|vh|vw|dvh|svh|rem|em)|auto)$/', $v) ? $v : $d;
    $bg = is_string($background) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $background) ? $background : '#0b132b';

    // Every competitor normalised here, so the page never carries a raw row.
    $normal = function ($p) {
        if (is_object($p)) {
            $p = method_exists($p, 'toArray') ? $p->toArray() : (array) $p;
        }
        if (! is_array($p) || trim((string) ($p['name'] ?? '')) === '') {
            return null;
        }
        $id = $p['id'] ?? null;
        $id = (is_int($id) || (is_string($id) && preg_match('/^[\w-]{1,64}$/', $id))) ? $id : null;
        $photo = (string) ($p['photo'] ?? $p['img'] ?? '');
        $photo = preg_match('#^(https?://|/(?!/))#i', $photo) ? $photo : '';

        return [
            'id' => $id,
            'name' => mb_substr(trim((string) $p['name']), 0, 120),
            'cc' => \App\Support\Flag::iso2($p['country'] ?? $p['cc'] ?? null) ?? '',
            'club' => mb_substr(trim((string) ($p['club'] ?? '')), 0, 120),
            'ab' => mb_substr(strtoupper((string) ($p['club_ab'] ?? $p['ab'] ?? '')), 0, 3),
            'img' => $photo !== '' ? $photo : \App\Members\Support\Avatar::placeholder($p['gender'] ?? null),
        ];
    };

    $list = collect($competitors)->values()->map($normal)->all();
    $wanted = (int) ($size ?: max(2, count($list)));
    $slots = 2;
    while ($slots < $wanted && $slots < 64) {
        $slots *= 2;
    }
    // Never drop a person: a list longer than `size` grows the draw.
    while ($slots < count($list) && $slots < 64) {
        $slots *= 2;
    }

    $cleanResults = [];
    foreach ((array) $results as $k => $r) {
        if (! is_string($k) || ! preg_match('/^(\d{1,2}-\d{1,3}|B|P[\w-]{1,40})$/', $k) || ! is_array($r)) {
            continue;
        }
        $w = $r['w'] ?? null;
        $cleanResults[$k] = [
            'w' => (is_int($w) || (is_string($w) && preg_match('/^[\w-]{1,64}$/', $w))) ? $w : null,
            'sa' => mb_substr((string) ($r['sa'] ?? ''), 0, 12),
            'sb' => mb_substr((string) ($r['sb'] ?? ''), 0, 12),
        ];
    }

    $cleanPlayins = collect($playins)->map(fn ($p) => is_array($p) && ($e = $normal($p['entrant'] ?? null)) ? [
        'id' => preg_match('/^[\w-]{1,40}$/', (string) ($p['id'] ?? '')) ? $p['id'] : null,
        'r' => (int) ($p['r'] ?? 0), 'i' => (int) ($p['i'] ?? 0), 'side' => ! empty($p['side']) ? 1 : 0,
        'entrant' => $e,
    ] : null)->filter()->values()->all();

    $editable = (bool) $editable;
    $recordOnly = (bool) $recordOnly && $editable;
    $addable = ! $recordOnly && ($addable === null ? $editable : ((bool) $addable && $editable));

    $flags = collect(glob(public_path(\App\Support\Flag::DIR.'/*.svg')) ?: [])
        ->map(fn ($f) => basename($f, '.svg'))->filter(fn ($c) => preg_match('/^[a-z]{2}$/', $c))->values()->all();

    $labels = [
        'roundOf' => __('Round of {n}'), 'quarterfinal' => __('Quarterfinal'), 'quarterfinals' => __('Quarterfinals'),
        'semifinal' => __('Semifinal'), 'semifinals' => __('Semifinals'), 'final' => __('Final'), 'theFinal' => __('the Final'),
        'podium' => __('Podium'), 'podiumFinal' => __('Podium / Final'), 'playIn' => __('Play-in'),
        'match' => __('Match {n}'), 'mat' => __('Mat {n}'), 'byeNoBout' => __('Bye — no bout'), 'bye' => __('Bye'),
        'awaitingWinner' => __('Awaiting winner'), 'playinWinner' => __('Play-in winner'), 'semifinalLoser' => __('Semifinal loser'),
        'winner' => __('Winner'), 'champion' => __('Champion'), 'championHere' => __('Champion decided here'),
        'tbd' => __('To be decided'), 'thirdPlaceMatch' => __('3rd Place · Match {n}'), 'winnerTo' => __('Winner → {slot}'),
        'topSlot' => __('top slot'), 'bottomSlot' => __('bottom slot'),
        'slotOf' => __('{pos} of {round} {n}'), 'slotOfFinal' => __('{pos} of the Final'),
        'expanded' => __('No fight had started, so the bracket expanded from {from} to {to} slots — the standard procedure. Every original competitor received a bye; {name} enters against the No. 1 seed, {seed}.'),
        'complete' => __('Every match is already decided — the bracket is complete, so a late entry is not possible. Reset a result to open a spot.'),
        'byeFilled' => __('{name} filled the open bye in {round} {n} — no extra bout needed. The opponent now fights instead of advancing on a bye. No played match was changed.'),
        'byeFilledShort' => __('{name} filled the open bye in {round} {n} — no extra bout needed. No played match was changed.'),
        'pigtail' => __('{name} enters via a pigtail (play-in) bout against {opp} — late entries forfeit seeding; the winner advances against the highest available seed and the loser is eliminated. The winner takes the {slot}, and that match moves to the end of its round so the play-in winner gets rest. No played match was changed.'),
        'pigtailShort' => __('{name} enters via a pigtail (play-in) bout against {opp}. The winner takes the {slot}. No played match was changed.'),
        'reseedFirstOnly' => __('Competitors can only be re-seeded within the first round — later slots are earned by winning.'),
        'reseedDecided' => __('That move would disturb a match that is already decided — only untouched first-round matches can be re-seeded.'),
        'playinAttached' => __('A play-in bout is attached to that slot — it cannot be re-seeded.'),
        'tookPlace' => __('{a} took {b}’s place — {b} is now in the holding basket; drag them into another slot.'),
        'movedOpen' => __('{a} moved to the open slot — their previous slot is now open.'),
        'placeFirstOnly' => __('Competitors can only be placed in first-round slots — later slots are earned by winning.'),
        'pathDecided' => __('That match’s path already has a decided result — pick an untouched first-round slot.'),
        'tookPlaceBasket' => __('{a} took {b}’s place — {b} moved to the holding basket.'),
        'placedInto' => __('{a} placed into the {slot}.'),
        'toBasket' => __('{a} moved to the holding basket — their slot is now open.'),
        'matchDecided' => __('That match is already decided — drop the competitor on an unplayed slot.'),
        'slotHasPlayin' => __('That slot already has a play-in bout attached.'),
        'byeBlocked' => __('That bye cannot be filled — a later match on this path is already decided.'),
        'slotEmpty' => __('That slot has no competitor yet — drop onto a competitor or a bye.'),
        'lockedNote' => __('The board is locked. Open the padlock to change the draw, the scores or the results.'),
        'carry' => __('Tap a slot to place {name}.'), 'cancel' => __('Cancel'), 'dismiss' => __('Dismiss'),
        'lock' => __('Lock the board'), 'unlock' => __('Unlock the board'),
        'zoomIn' => __('Zoom in'), 'zoomOut' => __('Zoom out'), 'fit' => __('Fit to screen'), 'menu' => __('Bracket options'),
        'layout' => __('Layout'), 'oneSided' => __('One-sided'), 'split' => __('Split halves'),
        'thirdPlace' => __('Third place'), 'thirdMatch' => __('3rd place match'), 'twoBronzes' => __('Two bronzes'),
        'drawSize' => __('Draw size'), 'editNames' => __('Edit names'), 'done' => __('Done'),
        'addCompetitor' => __('Add competitor'), 'resetResults' => __('Reset results'),
        'resetConfirm' => __('Clear every winner and score on this draw?'),
        'holdingBasket' => __('Holding basket ({n})'), 'basketHint' => __('Drag a competitor into any open first-round slot.'),
        'removeTitle' => __('Remove competitor (creates a bye)'),
        'fromList' => __('From the list'), 'newOne' => __('New competitor'),
        'search' => __('Search name, club or country'), 'noMatches' => __('Nobody matches'), 'place' => __('Place'),
        'rosterHint' => __('Drag onto a slot, or press Place to put them in the next open spot.'),
        'fullName' => __('Full name'), 'country' => __('Country'), 'club' => __('Club'),
        'chooseCountry' => __('Choose a country'), 'searchCountry' => __('Search countries'),
        'chooseClub' => __('Choose a club'), 'newClub' => __('New club…'), 'newClubName' => __('Club name'),
        'independent' => __('Independent'), 'addToBracket' => __('Add to the bracket'), 'close' => __('Close'),
    ];

    $config = [
        'id' => $cid,
        'size' => $slots,
        'competitors' => array_slice(array_pad($list, $slots, null), 0, $slots),
        'results' => (object) $cleanResults,
        'playins' => $cleanPlayins,
        'roster' => collect($roster)->map($normal)->filter()->values()->all(),
        'basket' => collect($basket)->map($normal)->filter()->values()->all(),
        'third' => $thirdPlace === 'shared' ? 'shared' : 'match',
        'layout' => $layout === 'split' ? 'split' : 'linear',
        'mats' => max(1, min(32, (int) $mats)),
        'zoom' => is_numeric($zoom) ? (float) $zoom : 'fit',
        'minZoom' => max(0.05, (float) $minZoom),
        'maxZoom' => min(8, max(1, (float) $maxZoom)),
        'editable' => $editable,
        'locked' => (bool) $locked,
        'addable' => $addable,
        'resizable' => (bool) $resizable && $editable && ! $recordOnly,
        'recordOnly' => $recordOnly,
        'toolbar' => (bool) $toolbar,
        'showClubs' => (bool) $showClubs,
        'celebration' => (bool) $celebration,
        'flags' => $flags,
        'flagBase' => rtrim(asset(\App\Support\Flag::DIR), '/'),
        'placeholder' => \App\Members\Support\Avatar::placeholder(null),
        'countries' => $addable
            ? collect(\App\Support\Countries::all())->filter(fn ($c) => in_array($c['code'], $flags, true))
                ->map(fn ($c) => ['cc' => $c['code'], 'name' => $c['name']])->values()->all()
            : [],
        'labels' => $labels,
    ];
@endphp

@once
    @include('components.competition-brackets.styles')
    @include('components.competition-brackets.runtime')
@endonce

<div id="{{ $cid }}" {{ $attributes->class(['cb-root']) }}
     style="--cb-bg: {{ $bg }}; width: {{ $box($width, '100%') }}; height: {{ $box($height, '760px') }};"
     role="region" aria-label="{{ __('Competition bracket') }}"
     x-data="competitionBrackets(@js($config))"
     x-effect="render()"
     @mousedown="panStart($event)" @dblclick="onDbl($event)"
     @keydown.escape="cancelCarry(); menuOpen = false">

    {{-- ── The canvas ─────────────────────────────────────────────────── --}}
    <div class="cb-inner" x-ref="inner">

        {{-- Play-in bouts, when a late entry made one --}}
        <template x-if="vm.playinCol">
            <div class="cb-col" :style="'width:' + vm.playinCol.w + ';animation:cb-in .6s ease both'">
                <div class="cb-colhead" x-text="t.playIn"></div>
                <div style="flex:1;position:relative">
                    <template x-for="pc in vm.playinCol.cards" :key="pc.key">
                        <div :style="pc.style">
                            <div class="cb-card" :style="'animation:' + pc.m.anim">
                                <div class="cb-tag"><span x-text="pc.m.tagL"></span><span class="cb-mat" x-text="pc.m.tagR"></span></div>
                                <template x-for="s in pc.m.slots" :key="s.key">
                                    @include('components.competition-brackets.slot')
                                </template>
                                <div class="cb-note" x-text="pc.m.note"></div>
                            </div>
                            <template x-if="pc.link"><div :style="pc.lineStyle"></div></template>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- The rounds, the final and the podium --}}
        <template x-for="col in vm.cols" :key="col.key">
            <div class="cb-col" :style="col.style">
                <div class="cb-colhead" x-text="col.name"></div>

                <template x-if="col.podium">
                    <div :style="col.podStyle">
                        <template x-if="vm.champ">
                            <div class="cb-champ">
                                <template x-if="vm.celebrate">
                                    <div aria-hidden="true">
                                        <span class="cb-conf" style="left:8%;width:6px;height:10px;background:#1e2c4f;animation-duration:1.7s;animation-delay:.1s"></span>
                                        <span class="cb-conf" style="left:22%;width:5px;height:9px;background:#fff;animation-duration:2.1s;animation-delay:.5s"></span>
                                        <span class="cb-conf" style="left:38%;width:6px;height:11px;background:#1e2c4f;animation-duration:1.9s;animation-delay:.9s"></span>
                                        <span class="cb-conf" style="left:55%;width:5px;height:9px;background:#fff;animation-duration:2.3s;animation-delay:.3s"></span>
                                        <span class="cb-conf" style="left:70%;width:6px;height:10px;background:#1e2c4f;animation-duration:1.8s;animation-delay:.7s"></span>
                                        <span class="cb-conf" style="left:86%;width:5px;height:11px;background:#fff;animation-duration:2s;animation-delay:1.1s"></span>
                                    </div>
                                </template>
                                <div class="cb-champ-head">
                                    <svg width="16" height="12" viewBox="0 0 16 12" aria-hidden="true"><path d="M1 10h14L14 3l-3.4 3L8 1 5.4 6 2 3z" fill="#1e2c4f"></path></svg>
                                    <span x-text="t.champion"></span>
                                </div>
                                <div class="cb-champ-body">
                                    <div class="cb-champ-photo" :style="'background-image:' + vm.champ.imgCss"></div>
                                    <div style="min-width:0">
                                        <div class="cb-champ-name" x-text="vm.champ.name"></div>
                                        <div class="cb-champ-club">
                                            <template x-if="vm.champ.flagCss"><span class="cb-flag" :style="'background-image:' + vm.champ.flagCss"></span></template>
                                            <span x-text="vm.champ.club"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template x-if="!vm.champ">
                            <div class="cb-champ-empty" x-text="t.championHere"></div>
                        </template>
                        <template x-for="(p, pi) in vm.podium" :key="'pod' + pi">
                            <div class="cb-chip">
                                <span class="cb-medal" :style="'background:' + p.mc" x-text="p.pos"></span>
                                <template x-if="p.f">
                                    <div class="cb-x" style="display:contents">
                                        <div class="cb-chip-photo" :style="'background-image:' + p.f.imgCss"></div>
                                        <div style="flex:1;min-width:0">
                                            <div class="cb-chip-name" x-text="p.f.name"></div>
                                            <div class="cb-chip-club" x-text="p.f.club"></div>
                                        </div>
                                        <template x-if="p.f.flagCss"><span class="cb-flag" :style="'background-image:' + p.f.flagCss"></span></template>
                                    </div>
                                </template>
                                <template x-if="!p.f"><span class="cb-tbd" x-text="t.tbd"></span></template>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="cb-rows">
                    <template x-for="(pair, pi) in col.pairs" :key="col.key + '-' + pi">
                        <div class="cb-pair">
                            <template x-for="m in pair.matches" :key="m.key">
                                <div class="cb-card" :style="'animation:' + m.anim">
                                    <div class="cb-tag"><span x-text="m.tagL"></span><span class="cb-mat" x-text="m.tagR"></span></div>
                                    <template x-for="s in m.slots" :key="s.key">
                                        @include('components.competition-brackets.slot')
                                    </template>
                                </div>
                            </template>
                            <template x-for="(ln, li) in pair.lines" :key="li">
                                <div :style="ln"></div>
                            </template>
                        </div>
                    </template>
                </div>

                <template x-if="col.bz && vm.bronze">
                    <div :style="col.bzStyle">
                        <div class="cb-card cb-card--bronze" :style="'animation:' + vm.bronze.anim">
                            <div class="cb-tag"><span x-text="vm.bronze.tag"></span><span class="cb-mat" x-text="vm.bronze.matTag"></span></div>
                            <template x-for="s in vm.bronze.slots" :key="s.key">
                                @include('components.competition-brackets.slot')
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- ── Controls: padlock · zoom · fit · menu ─────────────────────── --}}
    <template x-if="cfg.toolbar">
        <div class="cb-tools" data-cb-ui @dblclick.stop>
            <template x-if="cfg.editable">
                <button type="button" class="cb-btn" :class="{ 'cb-on': !locked }" @click="toggleLock()"
                        :title="locked ? t.unlock : t.lock" :aria-label="locked ? t.unlock : t.lock" :aria-pressed="(!locked).toString()">
                    <i class="bi" :class="locked ? 'bi-lock-fill' : 'bi-unlock-fill'"></i>
                </button>
            </template>
            <button type="button" class="cb-btn" @click="zoomBy(0.83)" :title="t.zoomOut" :aria-label="t.zoomOut"><i class="bi bi-dash-lg"></i></button>
            <button type="button" class="cb-btn" @click="zoomBy(1.2)" :title="t.zoomIn" :aria-label="t.zoomIn"><i class="bi bi-plus-lg"></i></button>
            <button type="button" class="cb-btn" @click="fit()" :title="t.fit" :aria-label="t.fit"><i class="bi bi-fullscreen"></i></button>
            <div style="position:relative" @click.outside="menuOpen = false">
                <button type="button" class="cb-btn" :class="{ 'cb-on': menuOpen }" @click="menuOpen = !menuOpen"
                        :title="t.menu" :aria-label="t.menu" :aria-expanded="menuOpen.toString()">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <div class="cb-menu" x-show="menuOpen" x-cloak
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="cb-menu-h" x-text="t.layout"></div>
                    <div class="cb-seg">
                        <button type="button" :class="{ 'cb-on': layout === 'linear' }" @click="setLayout('linear')" x-text="t.oneSided"></button>
                        <button type="button" :class="{ 'cb-on': layout === 'split' }" @click="setLayout('split')" x-text="t.split"></button>
                    </div>
                    <template x-if="size >= 4 && !cfg.recordOnly">
                        <div>
                            <div class="cb-menu-h" x-text="t.thirdPlace"></div>
                            <div class="cb-seg">
                                <button type="button" :class="{ 'cb-on': third === 'match' }" @click="setThird('match')" x-text="t.thirdMatch"></button>
                                <button type="button" :class="{ 'cb-on': third === 'shared' }" @click="setThird('shared')" x-text="t.twoBronzes"></button>
                            </div>
                        </div>
                    </template>
                    <template x-if="cfg.resizable">
                        <div>
                            <div class="cb-menu-h" x-text="t.drawSize"></div>
                            <div class="cb-seg">
                                <template x-for="n in [4, 8, 16, 32, 64]" :key="n">
                                    <button type="button" :class="{ 'cb-on': size === n }" @click="setSize(n)" x-text="n"></button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="cfg.editable && !cfg.recordOnly">
                        <div>
                            <div class="cb-sep"></div>
                            <button type="button" class="cb-item" @click="toggleEdit()"><i class="bi bi-pencil"></i><span x-text="edit ? t.done : t.editNames"></span></button>
                            <template x-if="cfg.addable">
                                <button type="button" class="cb-item" @click="openAdd()"><i class="bi bi-person-plus"></i><span x-text="t.addCompetitor"></span></button>
                            </template>
                            <button type="button" class="cb-item cb-danger" @click="resetResults()"><i class="bi bi-arrow-counterclockwise"></i><span x-text="t.resetResults"></span></button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    {{-- ── Notice / carrying ──────────────────────────────────────────── --}}
    <template x-if="carry">
        <div class="cb-notice" data-cb-ui role="status">
            <span style="flex:1" x-text="t.carry.replace('{name}', carry.name)"></span>
            <button type="button" @click="cancelCarry()" x-text="t.cancel"></button>
        </div>
    </template>
    <template x-if="notice && !carry">
        <div class="cb-notice" data-cb-ui role="status">
            <span style="flex:1" x-text="notice"></span>
            <button type="button" @click="notice = ''" :aria-label="t.dismiss">✕</button>
        </div>
    </template>

    {{-- ── Holding basket ─────────────────────────────────────────────── --}}
    <template x-if="basket.length">
        <div class="cb-basket" data-cb-ui
             @dragover="if (dragSlot != null) $event.preventDefault()" @drop.prevent="dropToBasket()" @click="basketTap()">
            <div class="cb-basket-h">
                <div class="cb-basket-t" x-text="t.holdingBasket.replace('{n}', basket.length)"></div>
                <button type="button" class="cb-basket-min" @click.stop="basketMin = !basketMin" x-text="basketMin ? '+' : '–'"></button>
            </div>
            <template x-if="!basketMin">
                <div>
                    <div class="cb-basket-list">
                        <template x-for="f in basket" :key="'bk' + f.id">
                            <div class="cb-person" :draggable="locked ? 'false' : 'true'"
                                 @dragstart="dragStartBasket($event, f)" @dragend="dragEnd()"
                                 @touchstart="pressStart($event, {type: 'basket', f: f})" @touchmove="pressMove($event)" @touchend="pressEnd()">
                                <div class="cb-photo" :style="'background-image:' + f.imgCss"></div>
                                <div style="min-width:0">
                                    <div class="cb-line">
                                        <span class="cb-name" x-text="f.name"></span>
                                        <template x-if="f.flagCss"><span class="cb-flag" :style="'background-image:' + f.flagCss"></span></template>
                                    </div>
                                    <div class="cb-club" x-text="f.club"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="cb-hint" x-text="t.basketHint"></div>
                </div>
            </template>
        </div>
    </template>

    {{-- ── Add a competitor ───────────────────────────────────────────── --}}
    <template x-if="addOpen && cfg.addable">
        <div class="cb-add" data-cb-ui @mousedown.stop @dblclick.stop @wheel.stop @touchstart.stop>
            <div class="cb-add-h">
                <i class="bi bi-person-plus"></i><b x-text="t.addCompetitor"></b>
                <button type="button" @click="addOpen = false" :aria-label="t.close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="cb-add-b">
                <div class="cb-seg">
                    <button type="button" :class="{ 'cb-on': addMode === 'known' }" @click="addMode = 'known'" x-text="t.fromList"></button>
                    <button type="button" :class="{ 'cb-on': addMode === 'new' }" @click="addMode = 'new'" x-text="t.newOne"></button>
                </div>

                <template x-if="addMode === 'known'">
                    <div>
                        <input type="search" class="cb-field" x-model="search" :placeholder="t.search">
                        <div class="cb-hint" x-text="t.rosterHint"></div>
                        <div style="margin-top:8px;display:flex;flex-direction:column;gap:2px">
                            <template x-for="f in rosterList()" :key="'ro' + f.id">
                                <div class="cb-roster-row" :draggable="locked ? 'false' : 'true'"
                                     @dragstart="dragStartRoster($event, f)" @dragend="dragEnd()"
                                     @touchstart="pressStart($event, {type: 'roster', f: f})" @touchmove="pressMove($event)" @touchend="pressEnd()">
                                    <div class="cb-photo" :style="'background-image:' + f.imgCss"></div>
                                    <div style="flex:1;min-width:0">
                                        <div class="cb-line">
                                            <span class="cb-name" x-text="f.name"></span>
                                            <template x-if="f.flagCss"><span class="cb-flag" :style="'background-image:' + f.flagCss"></span></template>
                                        </div>
                                        <div class="cb-club" x-text="f.club"></div>
                                    </div>
                                    <button type="button" class="cb-mini" @click="placeF(f, {})" x-text="t.place"></button>
                                </div>
                            </template>
                            <template x-if="!rosterList().length">
                                <div class="cb-empty" style="padding:14px 4px;text-align:center" x-text="t.noMatches"></div>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="addMode === 'new'">
                    <div>
                        <label class="cb-label" x-text="t.fullName"></label>
                        <input type="text" class="cb-field" maxlength="120" x-model="addName" @keydown.enter.prevent="addFighter()">

                        <label class="cb-label" x-text="t.country"></label>
                        <div class="cb-dd">
                            <button type="button" class="cb-dd-btn" :class="{ 'cb-open': countryOpen }" @click="countryOpen = !countryOpen; clubOpen = false">
                                <template x-if="addCountry"><span class="cb-flag" :style="'background-image:' + flagCss(addCountry)"></span></template>
                                <span x-text="addCountry ? countryName(addCountry) : t.chooseCountry"></span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="cb-dd-panel" x-show="countryOpen" x-cloak>
                                <div style="padding:6px"><input type="search" class="cb-field" x-model="countryQ" :placeholder="t.searchCountry"></div>
                                <template x-for="c in countryList()" :key="'cc' + c.cc">
                                    <button type="button" class="cb-dd-row" :class="{ 'cb-sel': addCountry === c.cc }" @click="addCountry = c.cc; countryOpen = false">
                                        <span class="cb-flag" :style="'background-image:' + flagCss(c.cc)"></span><span x-text="c.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <label class="cb-label" x-text="t.club"></label>
                        <div class="cb-dd">
                            <button type="button" class="cb-dd-btn" :class="{ 'cb-open': clubOpen }" @click="clubOpen = !clubOpen; countryOpen = false">
                                <span x-text="addClubSel === '__new' ? t.newClub : (addClubSel || t.chooseClub)"></span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="cb-dd-panel" x-show="clubOpen" x-cloak>
                                <template x-for="c in clubList()" :key="'cl' + c.name">
                                    <button type="button" class="cb-dd-row" :class="{ 'cb-sel': addClubSel === c.name }" @click="addClubSel = c.name; clubOpen = false">
                                        <span class="cb-ab" x-text="c.ab"></span><span x-text="c.name"></span>
                                    </button>
                                </template>
                                <button type="button" class="cb-dd-row" @click="addClubSel = '__new'; clubOpen = false">
                                    <i class="bi bi-plus-circle" style="color:#8a6d20"></i><span x-text="t.newClub"></span>
                                </button>
                            </div>
                        </div>
                        <template x-if="addClubSel === '__new'">
                            <input type="text" class="cb-field" style="margin-top:8px" maxlength="120" x-model="addClubNew" :placeholder="t.newClubName">
                        </template>

                        <button type="button" class="cb-primary" :disabled="!addName.trim()" @click="addFighter()" x-text="t.addToBracket"></button>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
