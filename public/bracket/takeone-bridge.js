/**
 * TAKEONE ⇄ Combat Sports Bracket — the bridge.
 *
 * The board on this page is the design file `drafts/Combat Sports Bracket.html`
 * running AS ITSELF: its own React 18 UMD, its own `dc-runtime`, its own
 * `<x-dc>` template and its own `class Component extends DCLogic`, all shipped
 * byte-for-byte out of that file. Nothing here re-implements any of it.
 *
 * What this file does is the one thing the design file cannot: make it real.
 * The draft ships a demo roster in `state.fighters` and keeps every result in
 * memory. This replaces that seed with the event's actual draw, and sends every
 * change the organiser makes back to the platform.
 *
 * ── How it attaches ───────────────────────────────────────────────────────
 *
 * `dc-runtime` evaluates the draft's script with
 *   new Function('DCLogic','StreamableLogic','React', src + ';return Component')
 * so `Component` is a local of that evaluation, not a global — it cannot be
 * patched from outside. Eight lines are therefore appended to the END of the
 * draft's own script (after the closing brace of the class, touching not one
 * line of it) which hand the instance to `attach()` below.
 *
 * ── What it maps ──────────────────────────────────────────────────────────
 *
 *   BracketView payload            the draft's state
 *   ───────────────────            ────────────────
 *   rounds[0].matches[i][side]  →  fighters[2*i + side]
 *   match.winner                →  results['<r>-<i>'].w   (a competitor id)
 *   match.{a,b}.score           →  results['<r>-<i>'].{sa,sb}
 *   the 'Third place' round     →  results['B']
 *   bench                       →  roster
 *
 * The geometry lines up because both sides use the same rule: bout i feeds
 * bout ⌊i/2⌋ of the next round, side a when i is even. BracketView documents
 * it; `Component.partBase()` implements it; nothing is translated between them.
 *
 * ── What it writes ────────────────────────────────────────────────────────
 *
 *   a winner picked / unpicked  →  POST me.events.outcome   {winner}
 *   a score typed               →  POST me.events.outcome   {a_score|b_score}
 *   a competitor moved          →  PUT  me.events.bracket.arrange {from,to}
 *
 * Every one of those re-authorises server-side, exactly as it did before. This
 * file decides nothing about permission; it is a wire.
 */
(function () {
    'use strict';

    var CFG = window.TAKEONE_BRACKET || {};
    var S = null;              // the mounted draft component
    var DATA = null;           // the division we are showing, as the server sent it
    var INDEX = null;          // draft keys → our ids
    var FITTED = null;         // division the board has been fitted to
    var applying = false;      // true while WE are writing state, so we don't echo

    /* ── Reading ──────────────────────────────────────────────────────── */

    function headers(json) {
        var h = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (json) {
            h['Content-Type'] = 'application/json';
            h['X-CSRF-TOKEN'] = CFG.csrf || '';
        }
        return h;
    }

    function divisionOf(payload) {
        var list = (payload.divisions || []).filter(function (d) { return !d.is_heading; });
        if (!list.length) return null;

        var wanted = list.filter(function (d) { return String(d.id) === String(CFG.division); })[0];

        return wanted || list[0];
    }

    /** A competitor, in the shape the draft's cards draw. */
    function person(side) {
        if (!side || !side.name) return null;

        return {
            /* The draft keys everything off `id` — who won, who is being
               dragged, who is already placed. Our competitor id IS that
               identity. A corner with a name but no entry behind it (typed at
               the desk) gets a stable negative id so the board can still tell
               it apart, and `moveable()` refuses to drag it. */
            id: side.competitor_id || -Math.abs(hash(side.name)),
            name: side.name,
            cc: (side.country || '').toLowerCase(),
            club: side.club || '',
            ab: side.club_ab || '',
            img: side.photo || '',
        };
    }

    function hash(s) {
        var h = 0;
        for (var i = 0; i < String(s).length; i++) { h = ((h << 5) - h + String(s).charCodeAt(i)) | 0; }
        return h || 1;
    }

    /**
     * The whole payload → the draft's state, plus the index that lets a change
     * to that state be sent back to the right row.
     */
    function toDraft(div) {
        var rounds = (div.rounds || []).filter(function (r) { return r.name !== 'Third place'; });
        var third = (div.rounds || []).filter(function (r) { return r.name === 'Third place'; })[0] || null;

        /* ⚠️ A DIVISION WITH NO DRAW YET.
        
           The design file always has a bracket — its demo state seeds one —
           and `size: 0` renders nothing at all. A real event has divisions
           that have not been cut yet, so those get the smallest board the file
           knows how to draw, with every entrant waiting in the roster and the
           server's own "no draw yet" sentence in the notice panel.
        
           Nothing on such a board can be moved: there are no bouts to move
           anybody INTO, and the arrange endpoint would refuse it. `index.size`
           is 0, which is what `firstMove()` checks before it posts. Cutting
           the draw is a draw verb on the event console, as it always was. */
        var first = rounds.length ? rounds[0].matches.length : 0;
        var size = first ? first * 2 : 4;

        var fighters = [];
        var results = {};
        var index = { match: {}, slot: {}, size: first ? size : 0 };

        if (rounds.length) {
            rounds[0].matches.forEach(function (m, i) {
                fighters[2 * i] = person(m.a);
                fighters[2 * i + 1] = person(m.b);
                index.slot[2 * i] = { match_id: m.id, side: 'a' };
                index.slot[2 * i + 1] = { match_id: m.id, side: 'b' };
            });
        }

        rounds.forEach(function (round, r) {
            round.matches.forEach(function (m, i) {
                var key = r + '-' + i;
                index.match[key] = m.id;

                var cell = {};
                if (m.winner && m[m.winner]) {
                    var w = person(m[m.winner]);
                    if (w) cell.w = w.id;
                }
                if (m.a && (m.a.score === 0 || m.a.score)) cell.sa = String(m.a.score);
                if (m.b && (m.b.score === 0 || m.b.score)) cell.sb = String(m.b.score);
                if (Object.keys(cell).length) results[key] = cell;
            });
        });

        if (third && third.matches.length) {
            var b = third.matches[0];
            index.match.B = b.id;
            var cell = {};
            if (b.winner && b[b.winner]) {
                var bw = person(b[b.winner]);
                if (bw) cell.w = bw.id;
            }
            if (b.a && (b.a.score === 0 || b.a.score)) cell.sa = String(b.a.score);
            if (b.b && (b.b.score === 0 || b.b.score)) cell.sb = String(b.b.score);
            if (Object.keys(cell).length) results.B = cell;
        }

        var roster = (div.bench || []).map(function (p) {
            return {
                id: p.competitor_id, name: p.name,
                cc: (p.country || '').toLowerCase(),
                club: p.club || '', ab: p.club_ab || '', img: p.photo || '',
            };
        });

        return {
            drawn: !!first,
            state: {
                size: size, fighters: fighters, results: results,
                roster: roster, playins: [], basket: [],
                /* The board draws the convention the CHAMPIONSHIP is run under.
                   It used to be inferred from whether a Third place round
                   happened to be in the payload, which is a different question:
                   a division cut before the switch was turned on has no bout
                   yet and would have drawn two bronzes under a one-bronze
                   event. */
                third: (CFG.thirdPlace ?? third) ? 'match' : 'shared',
            },
            index: index,
        };
    }

    /**
     * Every division in the payload, in the order the server put them, with
     * the one on the board named. This is what the page's division picker
     * draws — the board itself has none: the draft renders ONE bracket, and
     * giving it a switcher would mean editing its template.
     */
    function announce() {
        var list = ((DATA || {}).divisions || []).filter(function (d) { return !d.is_heading; });
        var on = divisionOf(DATA || {});

        var detail = {
            divisions: list.map(function (d) {
                return { id: d.id, name: d.name, entrants: d.entrants ?? (d.bench || []).length };
            }),
            division: on ? on.id : null,
        };

        /* Kept as well as dispatched: the payload can land before the picker's
           Alpine scope exists, and an event nobody was listening for is gone.
           The picker reads this on init and the event thereafter. */
        window.__takeoneDivisions = detail;

        window.dispatchEvent(new CustomEvent('bracket:divisions', { detail: detail }));
    }

    /**
     * Put a division on the board.
     *
     * The payload already holds every division, so switching is a re-draw and
     * not a second request — and `FITTED` differing from the new division is
     * what makes the board fit itself to a draw of a different size.
     */
    function show(id) {
        if (!DATA) return;

        CFG.division = id;
        render();
    }

    /** Draw whatever division CFG currently names, from the payload in hand. */
    function render() {
        var div = divisionOf(DATA || {});
        if (!div) return;

        // Said before the board is touched, so the picker is right even on a
        // page whose board has not mounted yet.
        announce();

        var mapped = toDraft(div);
        INDEX = mapped.index;

        if (!S) return;

        applying = true;
        var refit = FITTED !== div.id;
        S.setState(mapped.state, function () {
            applying = false;
            /* The board fitted itself on mount, against the draft's demo
               roster — a different size and a different shape from this
               event's draw. Fit it again now that it is drawing the real
               one, or it opens off-centre. Once per division: an organiser
               who has panned and zoomed keeps their view when a realtime
               refresh brings the same draw back. */
            if (refit) {
                FITTED = div.id;
                try { S.fitBoard(); } catch (e) { /* fit is cosmetic */ }
            }
            // Say so once, rather than leaving an empty board to be read as
            // "nobody entered" — which is a different fact entirely.
            if (!mapped.drawn) say(CFG.text.noDraw);
        });
    }

    async function load(keepNotice) {
        var res = await fetch(CFG.dataUrl, { headers: headers(false), credentials: 'same-origin' });
        if (!res.ok) return;

        var payload = await res.json();
        if (!divisionOf(payload)) return;

        DATA = payload;
        CFG.canArrange = !!payload.can_arrange;
        CFG.canManage = !!payload.can_manage;
        CFG.thirdPlace = !!payload.third_place_match;

        render();
    }

    /* ── Writing ──────────────────────────────────────────────────────── */

    async function post(url, body, method) {
        try {
            var res = await fetch(url, {
                method: method || 'POST',
                headers: headers(true),
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            var data = await res.json().catch(function () { return {}; });

            if (!res.ok || data.success === false) {
                say(data.message || CFG.text.failed);
                await load();
                return false;
            }

            if (data.message) say(data.message);
            await load(true);
            return true;
        } catch (e) {
            say(CFG.text.failed);
            await load();
            return false;
        }
    }

    /** Put a sentence in the draft's own notice panel. */
    function say(text) {
        if (!S || !text) return;
        applying = true;
        S.setState({ notice: text }, function () { applying = false; });
    }

    function outcomeUrl(matchId) {
        return CFG.outcomeUrl ? CFG.outcomeUrl.replace('__ID__', matchId) : null;
    }

    /** Which side of OUR bout a draft fighter id is standing on. */
    function sideOf(key, fighterId) {
        var div = divisionOf(DATA || {});
        if (!div) return null;

        var rounds = (div.rounds || []);
        var m = null;

        if (key === 'B') {
            var t = rounds.filter(function (r) { return r.name === 'Third place'; })[0];
            m = t && t.matches[0];
        } else {
            var parts = key.split('-');
            var ladder = rounds.filter(function (r) { return r.name !== 'Third place'; });
            var round = ladder[Number(parts[0])];
            m = round && round.matches[Number(parts[1])];
        }

        if (!m) return null;

        if (m.a && person(m.a) && person(m.a).id === fighterId) return 'a';
        if (m.b && person(m.b) && person(m.b).id === fighterId) return 'b';

        return null;
    }

    /* ── The diff: what did the organiser just change? ────────────────── */

    function onChange(prev, next) {
        // 1. A winner picked, or taken back off.
        var keys = Object.keys(next.results || {}).concat(Object.keys(prev.results || {}));
        var seen = {};

        for (var i = 0; i < keys.length; i++) {
            var k = keys[i];
            if (seen[k]) continue;
            seen[k] = true;

            var a = (prev.results || {})[k] || {};
            var b = (next.results || {})[k] || {};
            var id = INDEX && INDEX.match[k];
            if (!id) continue;

            if (a.w !== b.w) {
                if (!CFG.outcomeUrl) { revert(); return; }
                post(outcomeUrl(id), { winner: b.w ? (sideOf(k, b.w) || '') : '' });
                return;
            }

            if (a.sa !== b.sa || a.sb !== b.sb) {
                if (!CFG.outcomeUrl) { revert(); return; }
                var body = {};
                if (a.sa !== b.sa) body.a_score = String(b.sa == null ? '' : b.sa);
                if (a.sb !== b.sb) body.b_score = String(b.sb == null ? '' : b.sb);
                post(outcomeUrl(id), body);
                return;
            }
        }

        // 2. Somebody moved in the first round — a re-seed, a bye filled, or a
        //    competitor put back in the basket.
        if (prev.fighters !== next.fighters && INDEX) {
            var move = firstMove(prev.fighters || [], next.fighters || [], prev, next);
            if (move) {
                if (!CFG.arrangeUrl) { revert(); return; }
                post(CFG.arrangeUrl, {
                    category_id: Number(CFG.division),
                    from: move.from, to: move.to,
                }, 'PUT');
            }
        }
    }

    /**
     * Read one move out of two `fighters` arrays.
     *
     * The draft edits its own array however it likes — a swap, a move into an
     * empty slot, a removal to the basket. The SERVER takes one move at a time
     * and applies the rules to it, so the diff is reduced to the single move
     * that explains it: who left a slot, and where they turned up.
     */
    function firstMove(before, after, prevState, nextState) {
        // Nothing to move anybody into: this division has no draw yet.
        if (!INDEX || !INDEX.size) return null;

        var slots = Math.max(before.length, after.length);
        var left = null, arrived = null;

        for (var i = 0; i < slots; i++) {
            var a = before[i], b = after[i];
            var aid = a && a.id, bid = b && b.id;
            if (aid === bid) continue;

            if (aid && !arrivedElsewhere(after, aid, i)) left = left || { index: i, id: aid };
            if (bid) arrived = arrived || { index: i, id: bid };
        }

        if (arrived) {
            if (!INDEX.slot[arrived.index]) return null;
            var fromSlot = indexOfId(before, arrived.id);
            return {
                from: fromSlot >= 0
                    ? { type: 'slot', match_id: INDEX.slot[fromSlot].match_id, side: INDEX.slot[fromSlot].side }
                    : { type: 'bench', competitor_id: arrived.id },
                to: { type: 'slot', match_id: INDEX.slot[arrived.index].match_id, side: INDEX.slot[arrived.index].side },
            };
        }

        if (left) {
            if (!INDEX.slot[left.index]) return null;
            return {
                from: { type: 'slot', match_id: INDEX.slot[left.index].match_id, side: INDEX.slot[left.index].side },
                to: { type: 'bench' },
            };
        }

        return null;
    }

    function indexOfId(list, id) {
        for (var i = 0; i < list.length; i++) if (list[i] && list[i].id === id) return i;
        return -1;
    }
    function arrivedElsewhere(after, id, notAt) {
        for (var i = 0; i < after.length; i++) {
            if (i !== notAt && after[i] && after[i].id === id) return true;
        }
        return false;
    }

    /** The server said no, or we are not allowed to ask: put the board back. */
    function revert() {
        say(CFG.text.readonly);
        load();
    }

    /* ── Attach ───────────────────────────────────────────────────────── */

    function attach(instance) {
        S = instance;

        /* Wrap THIS INSTANCE's setState, not the prototype: everything the
           draft does to its own state passes through here, and the previous
           state is what React hands the callback. Our own writes set
           `applying`, so a reload can never look like an edit. */
        var original = S.setState.bind(S);

        S.setState = function (updater, callback) {
            var before = S.state;

            /* ⚠️ WHETHER THIS WRITE IS OURS IS DECIDED NOW, NOT LATER.

               `applying` is cleared in the callback of our own seeding write,
               and React runs that callback BEFORE anything below it — so
               reading the flag inside the callback found it already false and
               the freshly-seeded board was diffed as though the organiser had
               just re-arranged the entire draw. One page load would have
               posted a move. Captured at call time instead: a write knows
               whose it is when it is made. */
            var quiet = applying;

            original(updater, function () {
                if (callback) callback();

                /* The board's Actions menu is drawn INSIDE the board's own
                   stacking context (#takeone-bracket-root, z-index 70), so
                   nothing in it can paint above a sibling of that root however
                   high its z-index — and the division picker is exactly such a
                   sibling, because it has to escape the shell's transform.
                   Rather than fight that, the picker is told when the menu
                   opens and gets out of the way. */
                if (before.menuOpen !== S.state.menuOpen) {
                    window.dispatchEvent(new CustomEvent('bracket:menu', {
                        detail: { open: !!S.state.menuOpen },
                    }));
                }

                if (quiet) return;
                try { onChange(before, S.state); } catch (e) { /* never break the board */ }
            });
        };

        load();

        /* Somebody else's draw edit, or a bout landing on a mat, arrives here
           without anyone refreshing — the same realtime channel every other
           bracket surface listens on. */
        if (window.__takeoneBracketRealtime) {
            window.removeEventListener('realtime:events', window.__takeoneBracketRealtime);
        }
        var pending = null;
        window.__takeoneBracketRealtime = function (e) {
            var d = e.detail || {};
            if (d.event !== CFG.event) return;
            if (['draw', 'outcome', 'entrants', 'podium', 'entry-updated'].indexOf(d.action) < 0) return;
            clearTimeout(pending);
            pending = setTimeout(function () { load(true); }, 250);
        };
        window.addEventListener('realtime:events', window.__takeoneBracketRealtime);
    }

    /* `toDraft` is exported so the mapping can be exercised against a real
       payload without a browser — see tests/Feature/Events/BracketBoardBridgeTest
       and the node harness it documents. It reads nothing and writes nothing. */
    /**
     * Turn the play-off for bronze on or off for this championship.
     *
     * A real setting, unlike the menu item this replaces: it is stored on the
     * event, the draw is reconciled server-side (bouts appear, or unfought ones
     * go), and the board redraws from the answer.
     */
    async function setThirdPlace(on) {
        if (!CFG.thirdPlaceUrl) { revert(); return; }

        var data = await post(CFG.thirdPlaceUrl, { play_off: !!on }, 'PUT');

        if (data) { CFG.thirdPlace = !!on; }
    }

    window.TakeOneBracket = {
        attach: attach, reload: load, toDraft: toDraft, show: show,
        setThirdPlace: setThirdPlace,
        thirdPlace: function () { return !!CFG.thirdPlace; },
    };
})();
