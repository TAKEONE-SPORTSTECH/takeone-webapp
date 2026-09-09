{{--
    Tournament-bracket runtime — shared by the mobile and desktop bracket views.

    Pure logic + node styling (no page chrome), so editing a page layout can
    never break the renderer. Included INLINE inside the content section so it
    re-executes after a mobile-shell AJAX swap. Guarded so re-running is safe.

    Deliberately the same shape as the family-tree runtime
    (family/partials/tree-runtime.blade.php): a viewport that owns the gestures,
    a canvas that carries ONE transform, and SVG connectors measured from the
    laid-out DOM. That runtime is the only pan/zoom in this project that
    survives a scrollable mobile shell and the Android WebView, so brackets pan
    and zoom exactly the way the family tree does rather than inventing a second
    set of gestures.

    Public API (window.BracketBoard):
      mount(cfg)      — cfg: { viewportId, dataUrl, arrangeUrl, clearUrl, csrf,
                               eventUuid, canArrange, rtl }
      reload()        — re-fetch and re-render (called on realtime nudges)
      show(id)        — switch to a division
      zoomIn/zoomOut/fit()
      toggleArrange() — enter/leave arrange mode (no-op without permission)

    Events dispatched on the viewport element:
      bracket:loaded   { divisions, division, canArrange, locked }
      bracket:state    { arrange, picked }
      bracket:match    { match }   — a bout was tapped (view mode)
--}}

<style id="bk-styles">
    /* ---- Viewport & canvas: identical mechanics to the family tree ---- */
    .bk-viewport{position:relative;overflow:hidden;touch-action:none;cursor:grab;
        background:
            radial-gradient(circle at 1px 1px, hsl(250 30% 88% / .5) 1px, transparent 0) 0 0/24px 24px,
            linear-gradient(180deg, hsl(250 40% 98%), hsl(220 30% 96%));}
    .bk-viewport:active{cursor:grabbing;}
    .bk-viewport.is-arranging{cursor:default;}
    .bk-canvas{position:absolute;top:0;left:0;transform-origin:0 0;will-change:transform;}
    .bk-links{position:absolute;top:0;left:0;pointer-events:none;overflow:visible;}
    .bk-layer{position:relative;}

    /* ---- Round headings ---- */
    .bk-round-label{position:absolute;text-align:center;font-size:.68rem;font-weight:800;
        letter-spacing:.08em;text-transform:uppercase;color:hsl(250 30% 52%);
        background:#fff;border:1px solid hsl(250 40% 92%);border-radius:9999px;padding:3px 0;
        box-shadow:0 2px 6px hsl(250 40% 40% / .07);}

    /* ---- Match card ---- */
    .bk-match{position:absolute;border-radius:14px;background:#fff;
        border:1px solid hsl(220 15% 90%);box-shadow:0 6px 18px hsl(250 30% 40% / .10);
        overflow:hidden;opacity:0;transform:translateY(8px) scale(.98);
        animation:bkPop .38s cubic-bezier(.2,.8,.2,1) forwards;}
    @keyframes bkPop{to{opacity:1;transform:none;}}
    .bk-match.is-live{border-color:hsl(38 92% 55%);box-shadow:0 0 0 3px hsl(38 92% 55% / .18),0 6px 18px hsl(250 30% 40% / .12);}
    .bk-match.is-mine{border-color:hsl(250 65% 65%);box-shadow:0 0 0 3px hsl(250 65% 65% / .20),0 8px 20px hsl(250 40% 40% / .16);}

    .bk-meta{display:flex;align-items:center;gap:6px;padding:3px 8px;
        background:hsl(250 40% 97%);border-bottom:1px solid hsl(220 15% 93%);
        font-size:.6rem;font-weight:700;color:hsl(250 25% 50%);white-space:nowrap;}
    .bk-meta .bk-dot{width:5px;height:5px;border-radius:9999px;background:hsl(220 10% 72%);flex:0 0 auto;}
    .bk-match.is-live .bk-meta .bk-dot{background:hsl(38 92% 52%);animation:bkPulse 1.4s ease-in-out infinite;}
    .bk-match.is-done .bk-meta .bk-dot{background:hsl(145 55% 45%);}
    @keyframes bkPulse{50%{opacity:.35;}}

    /* ---- One competitor slot ---- */
    .bk-slot{display:flex;align-items:center;gap:7px;padding:5px 8px;position:relative;
        min-height:34px;transition:background .16s,box-shadow .16s;}
    .bk-slot + .bk-slot{border-top:1px solid hsl(220 15% 93%);}
    .bk-slot.is-win{background:hsl(250 60% 97%);}
    .bk-slot.is-win:before{content:"";position:absolute;inset-inline-start:0;top:0;bottom:0;
        width:3px;background:hsl(250 65% 65%);}
    .bk-slot.is-lose{opacity:.55;}
    .bk-slot.is-empty .bk-name{color:hsl(220 12% 62%);font-style:italic;font-weight:500;}

    .bk-av{width:24px;height:24px;border-radius:9999px;flex:0 0 auto;overflow:hidden;
        display:flex;align-items:center;justify-content:center;color:#fff;font-size:.58rem;
        font-weight:800;background:hsl(250 55% 60%);box-shadow:0 1px 4px hsl(250 40% 40% / .22);}
    .bk-av img{width:100%;height:100%;object-fit:cover;}
    .bk-av.is-bye{background:hsl(220 10% 72%);}

    .bk-name{flex:1 1 auto;min-width:0;font-size:.74rem;font-weight:700;color:hsl(220 22% 20%);
        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .bk-seed{font-size:.55rem;font-weight:800;color:hsl(250 35% 58%);background:hsl(250 60% 95%);
        border-radius:9999px;padding:0 5px;flex:0 0 auto;}
    .bk-score{font-size:.72rem;font-weight:900;color:hsl(220 20% 35%);flex:0 0 auto;
        min-width:18px;text-align:center;}
    .bk-slot.is-win .bk-score{color:hsl(145 55% 34%);}
    .bk-flag{width:16px;height:11px;border-radius:2px;overflow:hidden;flex:0 0 auto;
        border:1px solid #fff;box-shadow:0 1px 3px rgb(0 0 0 / .25);}
    .bk-flag .fi{display:block;width:100%;height:100%;background-size:cover;background-position:50%;}
    .bk-prov{width:6px;height:6px;border-radius:9999px;background:hsl(38 92% 55%);flex:0 0 auto;}

    /* ---- Arrange mode ---- */
    .is-arranging .bk-slot[data-arrangeable="1"]{cursor:grab;}
    .is-arranging .bk-slot[data-arrangeable="1"]:hover{background:hsl(250 60% 96%);}
    .bk-slot.is-picked{background:hsl(250 65% 92%);box-shadow:inset 0 0 0 2px hsl(250 65% 65%);}
    .bk-slot.is-target{background:hsl(145 60% 94%);box-shadow:inset 0 0 0 2px hsl(145 55% 45%);}
    .bk-slot.is-source{opacity:.35;}

    .bk-ghost{position:fixed;z-index:80;pointer-events:none;display:flex;align-items:center;gap:7px;
        padding:6px 10px;border-radius:12px;background:#fff;font-size:.74rem;font-weight:800;
        color:hsl(220 22% 20%);box-shadow:0 12px 28px hsl(250 40% 30% / .3);
        border:2px solid hsl(250 65% 65%);transform:translate(-50%,-50%) rotate(-2deg);}

    /* ---- Bench (entrants tray) — outside the canvas, so it never zooms ---- */
    /* ---- Group table: the standings, on the canvas with the cards ---- */
    .bk-table{position:absolute;inset-inline-start:40px;top:24px;z-index:15;background:#fff;
        border:1px solid hsl(220 15% 90%);border-radius:14px;overflow:hidden;
        box-shadow:0 4px 14px hsl(250 30% 40% / .10);}
    .bk-table-row{display:flex;align-items:center;gap:6px;padding:5px 10px;font-size:.7rem;
        border-bottom:1px solid hsl(220 15% 95%);}
    .bk-table-row:last-child{border-bottom:0;}
    .bk-table-head{background:hsl(250 40% 97%);font-weight:800;color:hsl(250 25% 45%);
        text-transform:uppercase;font-size:.58rem;letter-spacing:.06em;}
    .bk-table-row.is-through{background:hsl(145 60% 97%);}
    .bk-table-name{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;
        white-space:nowrap;font-weight:700;color:hsl(220 20% 20%);}
    .bk-table-num{flex:0 0 20px;text-align:center;color:hsl(220 10% 45%);font-weight:700;}
    .bk-table-num.is-pts{color:hsl(250 55% 50%);font-weight:800;}

    .bk-bench{position:absolute;z-index:20;background:#fff;border:1px solid hsl(220 15% 90%);
        box-shadow:0 10px 30px hsl(250 30% 35% / .16);display:flex;flex-direction:column;overflow:hidden;}
    .bk-bench-head{display:flex;align-items:center;gap:8px;padding:9px 12px;flex:0 0 auto;
        border-bottom:1px solid hsl(220 15% 93%);font-size:.72rem;font-weight:800;color:hsl(220 22% 25%);}
    .bk-bench-count{font-size:.6rem;font-weight:800;color:hsl(250 45% 55%);
        background:hsl(250 60% 95%);border-radius:9999px;padding:1px 7px;}
    .bk-bench-list{flex:1 1 auto;overflow-y:auto;padding:8px;display:flex;flex-direction:column;gap:6px;}
    .bk-bench-item{display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:10px;
        background:hsl(220 15% 97%);border:1px solid hsl(220 15% 91%);cursor:grab;
        transition:background .16s,transform .12s,box-shadow .16s;}
    .bk-bench-item:hover{background:hsl(250 60% 96%);box-shadow:0 3px 10px hsl(250 30% 40% / .12);}
    .bk-bench-item:active{transform:scale(.98);}
    .bk-bench-item.is-picked{background:hsl(250 65% 92%);box-shadow:inset 0 0 0 2px hsl(250 65% 65%);}
    .bk-bench-empty{padding:16px 12px;text-align:center;font-size:.7rem;color:hsl(220 12% 55%);}
    .bk-bench.is-target{box-shadow:0 0 0 3px hsl(145 55% 45% / .45),0 10px 30px hsl(250 30% 35% / .16);}

    /* The control column steps aside for the bench while arranging: on a wide
       screen it swaps to the opposite edge, on a phone it lifts above the
       bottom sheet. Without this it sits on top of the drop area. */
    .bk-controls{transition:inset .2s ease, bottom .2s ease;}
    .bk-viewport.is-arranging ~ .bk-controls{inset-inline-end:auto;inset-inline-start:12px;}
    @media (max-width: 639px){
        .bk-viewport.is-arranging ~ .bk-controls{
            inset-inline-start:auto;inset-inline-end:12px;bottom:calc(36% + 20px);}
    }

    .bk-empty{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;
        justify-content:center;text-align:center;padding:2rem;color:hsl(220 15% 45%);gap:6px;}

    @media (prefers-reduced-motion: reduce){
        .bk-match{animation:none;opacity:1;transform:none;}
        .bk-match.is-live .bk-meta .bk-dot{animation:none;}
    }
</style>

<script>
window.BracketBoard = window.BracketBoard || (function () {
    'use strict';

    // Card geometry (canvas units — the transform scales them). CARD_H is only
    // a first guess: the real height is MEASURED from a rendered card before
    // anything is positioned, because borders and line-height make the true
    // height differ by a few pixels — and a few pixels is the difference
    // between a connector meeting a card and missing it.
    const CARD_W = 190, SLOT_H = 34, META_H = 18;
    const CARD_H_GUESS = META_H + SLOT_H * 2;
    const COL_GAP = 74, ROW_GAP = 22;

    const el = (t, c) => { const n = document.createElement(t); if (c) n.className = c; return n; };
    const initials = n => String(n || '').trim().split(/\s+/).slice(0, 2).map(p => p[0] || '').join('').toUpperCase();

    let S = null; // active instance state

    function mount(cfg) {
        const vp = document.getElementById(cfg.viewportId);
        if (!vp) return;

        vp.classList.add('bk-viewport');
        vp.innerHTML = '';
        const canvas = el('div', 'bk-canvas');
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'bk-links');
        const layer = el('div', 'bk-layer');
        canvas.append(svg, layer);
        vp.appendChild(canvas);

        S = {
            cfg, vp, canvas, svg, layer,
            scale: 1, tx: 0, ty: 0, moved: false,
            divisions: [], division: null, locked: false, hiddenMsg: null,
            canArrange: false,       // server truth, refreshed on every load
            arrange: false,          // arrange mode on/off
            pick: null,              // { from, name } — tap-to-place selection
            drag: null,              // active pointer drag
            bench: null,
        };

        bindPanZoom();
        bindArrangeInput();
        bindRealtime();
        load();
    }

    // -----------------------------------------------------------------
    // Data
    // -----------------------------------------------------------------
    async function load(keepView) {
        try {
            const res = await fetch(S.cfg.dataUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('load failed');
            const data = await res.json();

            S.divisions = data.divisions || [];
            S.locked = !!data.locked;
            /* A draw the organiser has not let out yet. The server sends no
               divisions AND says why, so the board can name the day it opens
               instead of drawing nothing — which reads as "nobody entered". */
            S.hiddenMsg = data.hidden ? (data.hidden_message || null) : null;
            // Permission is whatever the SERVER just said — never a client flag
            // that a stale page is still carrying.
            S.canArrange = !!data.can_arrange;
            if (!S.canArrange) { S.arrange = false; S.pick = null; }

            const stillThere = S.divisions.some(d => d.id === S.division && !d.is_heading);
            if (!stillThere) {
                // Nothing selected yet (first load), or the selection is gone.
                // The host may have asked for a particular division — a bout's
                // "View draw" names the bout's own — otherwise the first.
                //
                // A HEADING is never selected: it is the organiser's title for
                // the divisions beneath it, with no entrants and no draw, so
                // landing on one would draw an empty bracket and read as "the
                // draw is not out yet".
                const drawable = S.divisions.filter(d => !d.is_heading);
                const wanted = drawable.find(d => String(d.id) === String(S.cfg.initialDivision ?? ''));
                S.division = wanted ? wanted.id : (drawable[0] ? drawable[0].id : null);
            }

            render(keepView);
            emit('bracket:loaded', {
                divisions: S.divisions.map(d => ({ id: d.id, name: d.name, entrants: d.entrants,
                                              bench: d.bench.length, is_heading: !!d.is_heading })),
                division: S.division, canArrange: S.canArrange, locked: S.locked,
            });
        } catch (e) {
            S.layer.innerHTML = '';
            S.svg.innerHTML = '';
            const box = el('div', 'bk-empty');
            const icon = el('i', 'bi bi-diagram-3 bracket-icon text-4xl'); box.appendChild(icon);
            const msg = el('div'); msg.textContent = S.cfg.text.loadFailed; box.appendChild(msg);
            S.canvas.appendChild(box);
        }
    }

    const current = () => S.divisions.find(d => d.id === S.division) || null;

    function show(id) {
        const d = S.divisions.find(x => String(x.id) === String(id));
        if (!d) return;
        // A heading is a label, not a draw — see the note in load().
        if (d.is_heading) return;
        S.division = d.id;
        S.pick = null;
        render();
        emitState();
    }

    // -----------------------------------------------------------------
    // Layout + render
    //
    // Positions are computed, not floated: round 0's bouts are stacked evenly
    // and every later bout sits at the midpoint of the two it is fed by. That
    // is the same "bout i feeds bout ⌊i/2⌋" rule the server advances winners
    // by (Advancement::nextBout), so the drawing and the engine can never
    // disagree about which line goes where.
    // -----------------------------------------------------------------
    function render(keepView) {
        const div = current();
        S.layer.innerHTML = '';
        S.svg.innerHTML = '';
        S.vp.querySelectorAll('.bk-empty').forEach(n => n.remove());

        if (!div || !div.rounds.length) {
            renderBench(div);
            const box = el('div', 'bk-empty');
            /* Withheld and not-yet-drawn are different facts and get different
               faces: a padlock plus the day it opens, or the bracket glyph. */
            const icon = el('i', S.hiddenMsg ? 'bi bi-lock-fill text-4xl' : 'bi bi-diagram-3 bracket-icon text-4xl');
            box.appendChild(icon);
            const msg = el('div'); msg.textContent = S.hiddenMsg || S.cfg.text.noDraw; box.appendChild(msg);
            S.canvas.appendChild(box);
            return;
        }

        const rounds = div.rounds;
        const firstCount = rounds[0].matches.length;
        const totalW = rounds.length * CARD_W + (rounds.length - 1) * COL_GAP + 80;

        S.layer.style.width = totalW + 'px';

        // Build every card first, then measure one, then position them all —
        // so the layout is driven by the height cards ACTUALLY have.
        const cards = rounds.map((round, r) => round.matches.map((m, i) => {
            const card = buildMatch(div, m, r, i);
            card.style.width = CARD_W + 'px';
            card.style.visibility = 'hidden';
            S.layer.appendChild(card);
            return card;
        }));

        const cardH = (cards[0] && cards[0][0] && cards[0][0].offsetHeight) || CARD_H_GUESS;
        // A group's table sits above the columns, so everything starts lower.
        const topPad = (div.standings && div.standings.length) ? 60 + div.standings.length * 26 : 0;
        const totalH = Math.max(1, firstCount) * (cardH + ROW_GAP) + 90 + topPad;

        S.layer.style.height = totalH + 'px';
        S.svg.setAttribute('width', totalW);
        S.svg.setAttribute('height', totalH);

        // y-centre of every bout, round by round: round 0 stacks evenly, and
        // every later bout sits at the midpoint of the two that feed it.
        const centres = [];
        const stacked = i => 70 + topPad + i * (cardH + ROW_GAP) + cardH / 2;
        rounds.forEach((round, r) => {
            centres[r] = round.matches.map((m, i) => {
                // Same reason as drawLinks: after a group, a bout's position is
                // its own, not the midpoint of two that do not feed it.
                if (r === 0 || rounds[r - 1].group) return stacked(i);
                const a = centres[r - 1][i * 2], b = centres[r - 1][i * 2 + 1];
                if (a === undefined) return stacked(i);
                return b === undefined ? a : (a + b) / 2;
            });
        });

        rounds.forEach((round, r) => {
            const x = columnX(r, rounds.length, totalW);

            const label = el('div', 'bk-round-label');
            label.style.left = x + 'px';
            label.style.top = (24 + topPad) + 'px';
            label.style.width = CARD_W + 'px';
            label.textContent = round.name;
            S.layer.appendChild(label);

            round.matches.forEach((m, i) => {
                const card = cards[r][i];
                card.style.left = x + 'px';
                card.style.top = (centres[r][i] - cardH / 2) + 'px';
                card.style.visibility = '';
                card.style.animationDelay = Math.min(r * 70 + i * 18, 400) + 'ms';
            });
        });

        drawLinks(rounds, centres, totalW);
        renderStandings(div, totalW);
        renderBench(div);
        applyArrangeClasses();

        if (!keepView) requestAnimationFrame(fit);
    }

    /** x of a round's column — mirrored in RTL so the final lands on the left. */
    function columnX(r, roundCount, totalW) {
        const step = CARD_W + COL_GAP;
        return S.cfg.rtl ? (totalW - 40 - CARD_W - r * step) : (40 + r * step);
    }

    function buildMatch(div, m, roundIndex, matchIndex) {
        const card = el('div', 'bk-match');
        card.dataset.matchId = m.id;
        card.dataset.round = roundIndex;
        if (m.status === 'live') card.classList.add('is-live');
        if (m.status === 'done') card.classList.add('is-done');
        if (isMine(m)) card.classList.add('is-mine');

        // Meta strip: bout number, mat, time — only what a bracket needs.
        const meta = el('div', 'bk-meta');
        meta.appendChild(el('span', 'bk-dot'));
        const bits = [];
        if (m.no) bits.push('#' + m.no);
        if (m.court) bits.push(m.court);
        if (m.time) bits.push(m.time);
        const metaText = el('span');
        metaText.textContent = bits.length ? bits.join(' · ') : S.cfg.text.tbd;
        meta.appendChild(metaText);
        card.appendChild(meta);

        card.appendChild(buildSlot(div, m, 'a', roundIndex));
        card.appendChild(buildSlot(div, m, 'b', roundIndex));

        card.addEventListener('click', () => {
            if (S.moved || S.arrange) return;
            emit('bracket:match', { match: m, division: div.id });
        });

        return card;
    }

    function buildSlot(div, m, side, roundIndex) {
        const p = m[side] || {};
        const slot = el('div', 'bk-slot');
        slot.dataset.matchId = m.id;
        slot.dataset.side = side;
        slot.dataset.competitorId = p.competitor_id || '';

        // Only the first round is arrangeable — later rounds are DERIVED from
        // it, so letting anyone drop a name there would invent a result.
        if (roundIndex === 0) slot.dataset.arrangeable = '1';

        if (!p.name) slot.classList.add('is-empty');
        else if (m.winner === side) slot.classList.add('is-win');
        else if (m.winner) slot.classList.add('is-lose');

        const av = el('div', 'bk-av');
        if (!p.name) {
            av.classList.add('is-bye');
            av.appendChild(el('i', 'bi bi-dash'));
        } else if (p.photo) {
            const img = el('img'); img.src = p.photo; img.alt = ''; img.loading = 'lazy';
            av.appendChild(img);
        } else {
            av.textContent = initials(p.name);
        }
        slot.appendChild(av);

        const name = el('div', 'bk-name');
        // textContent, never innerHTML — an athlete's name is untrusted input.
        name.textContent = p.name || (m.winner ? S.cfg.text.bye : S.cfg.text.tbd);
        slot.appendChild(name);

        if (p.country) slot.appendChild(flag(p.country));
        if (p.provisional) {
            const dot = el('span', 'bk-prov');
            dot.title = S.cfg.text.provisional;
            slot.appendChild(dot);
        }
        if (p.seed) { const s = el('span', 'bk-seed'); s.textContent = p.seed; slot.appendChild(s); }

        const score = el('div', 'bk-score');
        score.textContent = p.score || '';
        slot.appendChild(score);

        return slot;
    }

    function flag(code) {
        const box = el('div', 'bk-flag');
        const c = String(code).toLowerCase().replace(/[^a-z]/g, '').slice(0, 2);
        if (c.length !== 2) return box;
        const span = el('span');
        span.className = 'fi fi-' + c;   // built via className, never innerHTML
        box.appendChild(span);
        return box;
    }

    function isMine(m) {
        const me = S.cfg.myCompetitorIds || [];
        return me.includes(m.a?.competitor_id) || me.includes(m.b?.competitor_id);
    }

    // -----------------------------------------------------------------
    // Connectors — elbow paths from each feeding bout into the one it feeds
    // -----------------------------------------------------------------
    function drawLinks(rounds, centres, totalW) {
        const sign = S.cfg.rtl ? -1 : 1;

        for (let r = 0; r < rounds.length - 1; r++) {
            // A group feeds the knockout through the TABLE, not by position:
            // bout ⌊i/2⌋ of the next round is a semifinal nobody has qualified
            // for yet, and a line drawn to it would state a progression that
            // does not exist.
            if (rounds[r].group) continue;

            const fromX = columnX(r, rounds.length, totalW) + (S.cfg.rtl ? 0 : CARD_W);
            const toX = columnX(r + 1, rounds.length, totalW) + (S.cfg.rtl ? CARD_W : 0);
            const midX = (fromX + toX) / 2;

            rounds[r].matches.forEach((m, i) => {
                const childIndex = Math.floor(i / 2);
                if (centres[r + 1][childIndex] === undefined) return;

                const y1 = centres[r][i];
                const y2 = centres[r + 1][childIndex];
                const decided = m.status === 'done' && m.winner;

                line(
                    'M' + fromX + ' ' + y1 + ' H' + midX + ' V' + y2 + ' H' + toX,
                    decided ? 'done' : ''
                );
            });
            void sign;
        }
    }

    function line(d, cls) {
        const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        p.setAttribute('d', d);
        p.setAttribute('fill', 'none');
        p.setAttribute('stroke', cls === 'done' ? 'hsl(250 55% 72%)' : 'hsl(250 25% 84%)');
        p.setAttribute('stroke-width', '2');
        p.setAttribute('stroke-linecap', 'round');
        p.setAttribute('stroke-linejoin', 'round');
        S.svg.appendChild(p);
    }

    // -----------------------------------------------------------------
    // Bench — the entrants tray. Lives OUTSIDE the transformed canvas so it
    // stays legible at any zoom, and only exists for someone who may arrange.
    // -----------------------------------------------------------------
    /**
     * The group table.
     *
     * A ladder needs no such thing — it IS the record of who beat whom. A group
     * answers "who is doing best", which lives in none of its bouts and has to
     * be shown as a table beside them. Drawn into the same transformed canvas
     * as the cards, so it pans and zooms with the board rather than floating
     * over it.
     */
    function renderStandings(div, totalW) {
        if (!div.standings || !div.standings.length) return;

        const box = el('div', 'bk-table');

        /* Wide enough to READ a name in, whatever the board is. A group with
           one column makes a narrow canvas, and sizing the table to that
           squeezed every competitor down to two letters and an ellipsis. */
        const width = Math.max(300, Math.min(totalW - 80, 420));
        box.style.width = width + 'px';

        // The canvas has to be at least as wide as the table, or fit() zooms to
        // the columns and crops it.
        if (width + 80 > parseFloat(S.layer.style.width || 0)) {
            S.layer.style.width = (width + 80) + 'px';
            S.svg.setAttribute('width', width + 80);
        }

        const head = el('div', 'bk-table-row bk-table-head');
        ['#', '', 'P', 'W', 'D', 'L', 'Pts'].forEach((h, i) => {
            const c = el('span', i === 1 ? 'bk-table-name' : 'bk-table-num');
            c.textContent = h;
            head.appendChild(c);
        });
        box.appendChild(head);

        div.standings.forEach(row => {
            const line = el('div', 'bk-table-row');
            if (row.position <= 2) line.classList.add('is-through');

            const pos = el('span', 'bk-table-num'); pos.textContent = row.position; line.appendChild(pos);

            const name = el('span', 'bk-table-name');
            name.textContent = row.name || '';
            line.appendChild(name);

            [row.played, row.won, row.drawn, row.lost].forEach(v => {
                const c = el('span', 'bk-table-num'); c.textContent = v; line.appendChild(c);
            });

            const pts = el('span', 'bk-table-num is-pts'); pts.textContent = row.points; line.appendChild(pts);

            box.appendChild(line);
        });

        S.layer.appendChild(box);
    }

    function renderBench(div) {
        if (S.bench) { S.bench.remove(); S.bench = null; }
        if (!S.arrange || !S.canArrange || !div) return;

        const bench = el('div', 'bk-bench');
        bench.dataset.benchDrop = '1';

        const head = el('div', 'bk-bench-head');
        const icon = el('i', 'bi bi-people-fill'); head.appendChild(icon);
        const title = el('span'); title.textContent = S.cfg.text.bench; head.appendChild(title);
        const count = el('span', 'bk-bench-count'); count.textContent = div.bench.length; head.appendChild(count);
        bench.appendChild(head);

        const list = el('div', 'bk-bench-list');
        if (!div.bench.length) {
            const empty = el('div', 'bk-bench-empty');
            empty.textContent = S.cfg.text.benchEmpty;
            list.appendChild(empty);
        } else {
            div.bench.forEach(p => {
                const item = el('div', 'bk-bench-item');
                item.dataset.competitorId = p.competitor_id;
                item.tabIndex = 0;

                const av = el('div', 'bk-av');
                if (p.photo) { const img = el('img'); img.src = p.photo; img.alt = ''; img.loading = 'lazy'; av.appendChild(img); }
                else av.textContent = initials(p.name);
                item.appendChild(av);

                const name = el('div', 'bk-name');
                name.textContent = p.name;
                item.appendChild(name);

                if (p.country) item.appendChild(flag(p.country));
                if (p.provisional) { const d = el('span', 'bk-prov'); d.title = S.cfg.text.provisional; item.appendChild(d); }

                list.appendChild(item);
            });
        }
        bench.appendChild(list);

        S.vp.appendChild(bench);
        S.bench = bench;
        positionBench();
    }

    /**
     * Docked to the side on a wide viewport, to the bottom on a phone.
     *
     * Two things share this space and must not be sat on: the arrange banner
     * across the top, and the zoom/arrange control column. The bench clears the
     * banner vertically, and the CONTROLS move out of its way horizontally
     * (see `.bk-controls` in the stylesheet) rather than the bench dodging them
     * — the bench is the thing you drag into, so it keeps the roomier lane.
     */
    const BANNER_CLEARANCE = 52;   // arrange banner height + its top inset
    const CONTROL_LANE = 60;       // width of the zoom/arrange button column

    function positionBench() {
        if (!S.bench) return;
        const narrow = S.vp.clientWidth < 640;
        const b = S.bench.style;

        if (narrow) {
            // Bottom sheet, clear of the control column on its own side.
            b.top = 'auto'; b.bottom = '10px';
            b.insetInlineStart = '10px';
            b.insetInlineEnd = CONTROL_LANE + 'px';
            b.width = 'auto'; b.maxHeight = '36%';
            b.borderRadius = '18px';
        } else {
            b.top = BANNER_CLEARANCE + 'px'; b.bottom = '12px';
            b.width = '230px';
            b.insetInlineStart = 'auto';
            b.insetInlineEnd = '12px';
            b.maxHeight = 'none';
            b.borderRadius = '16px';
        }
    }

    // -----------------------------------------------------------------
    // Pan + zoom — three input paths kept apart so they never double-fire.
    // Lifted from the family-tree runtime: native touch (so a scrollable
    // mobile shell / WebView can't swallow the gesture), pointer events for
    // mouse & pen, and wheel for desktop zoom.
    // -----------------------------------------------------------------
    function apply() {
        S.canvas.style.transform = 'translate(' + S.tx + 'px,' + S.ty + 'px) scale(' + S.scale + ')';
    }

    const clampScale = s => Math.min(2.4, Math.max(0.2, s));

    function zoomTo(target, px, py) {
        const ns = clampScale(target);
        const k = ns / S.scale;
        S.tx = px - (px - S.tx) * k;
        S.ty = py - (py - S.ty) * k;
        S.scale = ns;
        apply();
    }
    function zoomAt(factor, px, py) { zoomTo(S.scale * factor, px, py); }

    /**
     * Fit the whole draw in view, then centre it.
     *
     * Waits for the viewport to actually have a size. A board mounted inside
     * something not yet laid out (a hidden tab, a sheet mid-transition, a page
     * whose stylesheet hasn't applied) would otherwise fit against a zero-sized
     * box and collapse to the minimum zoom — leaving a board nobody can even
     * click. Retries a few frames, then gives up gracefully at scale 1.
     */
    function fit(attempt) {
        const w = S.layer.offsetWidth, h = S.layer.offsetHeight;
        const vw = S.vp.clientWidth, vh = S.vp.clientHeight;

        if (!w || !h || vw < 40 || vh < 40) {
            if ((attempt || 0) < 20) {
                requestAnimationFrame(() => fit((attempt || 0) + 1));
            } else {
                S.scale = 1; S.tx = 0; S.ty = 0; apply();
            }
            return;
        }

        const pad = 16;
        const s = clampScale(Math.min((vw - pad * 2) / w, (vh - pad * 2) / h, 1.2));
        S.scale = s;
        S.tx = (vw - w * s) / 2;
        S.ty = (vh - h * s) / 2;
        apply();
    }

    /**
     * Pointer capture keeps a drag alive when the pointer leaves the element,
     * but it THROWS if the browser has no active pointer with that id — which
     * a synthetic or already-released pointer produces. Capture is an
     * optimisation, never a requirement: losing it degrades to normal event
     * flow, so it must never take the gesture down with it.
     */
    function capture(el, pointerId) {
        try { el.setPointerCapture(pointerId); } catch (e) { /* capture is optional */ }
    }

    /** In arrange mode a press on a competitor drags them, not the canvas. */
    const grabbable = target => S.arrange && S.canArrange && !!(
        target.closest('.bk-bench-item') ||
        target.closest('.bk-slot[data-arrangeable="1"]')
    );

    function bindPanZoom() {
        const vp = S.vp;
        const list = t => [...t].map(p => ({ x: p.clientX, y: p.clientY }));
        let last = [], pinchDist = 0, pinchScale = 1;

        // ---- Touch ----
        vp.addEventListener('touchstart', e => {
            S.moved = false;
            if (e.touches.length === 1 && grabbable(e.target)) { last = []; return; }
            last = list(e.touches);
            if (last.length === 2) {
                pinchDist = Math.hypot(last[0].x - last[1].x, last[0].y - last[1].y) || 1;
                pinchScale = S.scale;
            }
        }, { passive: false });

        vp.addEventListener('touchmove', e => {
            if (S.drag) return;                       // a competitor is being dragged
            if (!last.length) return;                 // gesture started on a draggable
            e.preventDefault();                       // block the shell from scrolling
            const now = list(e.touches);
            if (now.length >= 2 && last.length >= 2) {
                const d = Math.hypot(now[0].x - now[1].x, now[0].y - now[1].y);
                const r = vp.getBoundingClientRect();
                zoomTo(pinchScale * (d / pinchDist), (now[0].x + now[1].x) / 2 - r.left, (now[0].y + now[1].y) / 2 - r.top);
                S.moved = true;
            } else if (now.length === 1) {
                const dx = now[0].x - last[0].x, dy = now[0].y - last[0].y;
                if (Math.abs(dx) + Math.abs(dy) > 2) S.moved = true;
                S.tx += dx; S.ty += dy; apply();
            }
            last = now;
        }, { passive: false });

        const tend = e => { last = list(e.touches); if (last.length < 2) pinchDist = 0; };
        vp.addEventListener('touchend', tend);
        vp.addEventListener('touchcancel', tend);

        // ---- Mouse / pen ----
        const pts = new Map();
        vp.addEventListener('pointerdown', e => {
            if (e.pointerType === 'touch' || grabbable(e.target)) return;
            capture(vp, e.pointerId);
            pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
            S.moved = false;
        });
        vp.addEventListener('pointermove', e => {
            if (e.pointerType === 'touch') return;
            const prev = pts.get(e.pointerId);
            if (!prev) return;
            const cur = { x: e.clientX, y: e.clientY };
            pts.set(e.pointerId, cur);
            const dx = cur.x - prev.x, dy = cur.y - prev.y;
            if (Math.abs(dx) + Math.abs(dy) > 2) S.moved = true;
            S.tx += dx; S.ty += dy; apply();
        });
        const up = e => pts.delete(e.pointerId);
        vp.addEventListener('pointerup', up);
        vp.addEventListener('pointercancel', up);

        // ---- Wheel zoom ----
        vp.addEventListener('wheel', e => {
            e.preventDefault();
            const r = vp.getBoundingClientRect();
            zoomAt(e.deltaY < 0 ? 1.1 : 0.9, e.clientX - r.left, e.clientY - r.top);
        }, { passive: false });

        window.addEventListener('resize', positionBench);
    }

    // -----------------------------------------------------------------
    // Arranging — drag, and tap-to-place as the equal path
    //
    // Dragging inside a zoomed, transformed canvas is fiddly on a phone and
    // impossible with a keyboard, so every drag has a two-tap equivalent:
    // tap a competitor to pick them up, tap a slot to put them down.
    // -----------------------------------------------------------------
    function bindArrangeInput() {
        const vp = S.vp;

        vp.addEventListener('pointerdown', e => {
            if (!grabbable(e.target)) return;
            const origin = endpointOf(e.target);
            if (!origin || !origin.name) {
                // Tapping an empty slot while holding someone = place them.
                if (S.pick && origin) commit(S.pick.from, origin.to);
                return;
            }

            e.preventDefault();
            S.drag = {
                from: origin.to, name: origin.name, id: e.pointerId,
                startX: e.clientX, startY: e.clientY, moved: false, ghost: null,
                sourceEl: origin.el,
            };
            capture(vp, e.pointerId);
        });

        vp.addEventListener('pointermove', e => {
            if (!S.drag || e.pointerId !== S.drag.id) return;
            const dx = e.clientX - S.drag.startX, dy = e.clientY - S.drag.startY;

            if (!S.drag.moved && Math.abs(dx) + Math.abs(dy) < 6) return;
            if (!S.drag.moved) {
                S.drag.moved = true;
                S.drag.ghost = ghostFor(S.drag.name);
                document.body.appendChild(S.drag.ghost);
                S.drag.sourceEl?.classList.add('is-source');
            }

            S.drag.ghost.style.left = e.clientX + 'px';
            S.drag.ghost.style.top = e.clientY + 'px';
            highlight(dropTargetAt(e.clientX, e.clientY));
        });

        const finish = e => {
            if (!S.drag || e.pointerId !== S.drag.id) return;
            const drag = S.drag;
            S.drag = null;
            drag.ghost?.remove();
            drag.sourceEl?.classList.remove('is-source');
            highlight(null);

            if (!drag.moved) { pickUp(drag.from, drag.name, drag.sourceEl); return; }

            const target = dropTargetAt(e.clientX, e.clientY);
            if (target) commit(drag.from, target.to);
        };
        vp.addEventListener('pointerup', finish);
        vp.addEventListener('pointercancel', e => {
            if (!S.drag || e.pointerId !== S.drag.id) return;
            S.drag.ghost?.remove();
            S.drag.sourceEl?.classList.remove('is-source');
            S.drag = null;
            highlight(null);
        });

        // Keyboard equivalent of tap-to-place.
        vp.addEventListener('keydown', e => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            if (!grabbable(e.target)) return;
            e.preventDefault();
            const point = endpointOf(e.target);
            if (!point) return;
            if (S.pick) commit(S.pick.from, point.to);
            else if (point.name) pickUp(point.to, point.name, point.el);
        });

        // Escape drops what you're holding.
        vp.addEventListener('keydown', e => {
            if (e.key === 'Escape' && S.pick) { S.pick = null; applyArrangeClasses(); emitState(); }
        });
    }

    /** Describe whatever is under an element: which end of a move it is. */
    function endpointOf(node) {
        const item = node.closest ? node.closest('.bk-bench-item') : null;
        if (item) {
            return {
                el: item,
                name: item.querySelector('.bk-name')?.textContent || '',
                to: { type: 'bench', competitor_id: Number(item.dataset.competitorId) },
            };
        }

        const slot = node.closest ? node.closest('.bk-slot[data-arrangeable="1"]') : null;
        if (slot) {
            return {
                el: slot,
                name: slot.classList.contains('is-empty') ? '' : (slot.querySelector('.bk-name')?.textContent || ''),
                to: { type: 'slot', match_id: Number(slot.dataset.matchId), side: slot.dataset.side },
            };
        }

        if (node.closest && node.closest('[data-bench-drop="1"]')) {
            return { el: S.bench, name: '', to: { type: 'bench' } };
        }

        return null;
    }

    function pickUp(from, name, sourceEl) {
        S.pick = (S.pick && sameEnd(S.pick.from, from)) ? null : { from, name, el: sourceEl };
        applyArrangeClasses();
        emitState();
    }

    const sameEnd = (a, b) =>
        a.type === b.type && a.match_id === b.match_id && a.side === b.side && a.competitor_id === b.competitor_id;

    function ghostFor(name) {
        const g = el('div', 'bk-ghost');
        const av = el('div', 'bk-av'); av.textContent = initials(name);
        g.appendChild(av);
        const t = el('span'); t.textContent = name;
        g.appendChild(t);
        return g;
    }

    function dropTargetAt(x, y) {
        const node = document.elementFromPoint(x, y);
        if (!node) return null;
        const point = endpointOf(node);
        if (!point) return null;
        // A competitor may be dropped into a first-round slot or back onto the
        // bench — nothing else is a target.
        if (point.to.type === 'bench') return { el: S.bench, to: { type: 'bench' } };
        return point;
    }

    function highlight(target) {
        S.vp.querySelectorAll('.is-target').forEach(n => n.classList.remove('is-target'));
        S.bench?.classList.remove('is-target');
        if (!target) return;
        if (target.to.type === 'bench') S.bench?.classList.add('is-target');
        else target.el?.classList.add('is-target');
    }

    function applyArrangeClasses() {
        S.vp.classList.toggle('is-arranging', S.arrange && S.canArrange);
        S.vp.querySelectorAll('.is-picked').forEach(n => n.classList.remove('is-picked'));
        if (!S.pick) return;

        // Re-find the picked element by identity — the DOM may have been
        // re-rendered since it was picked up.
        const sel = S.pick.from.type === 'bench'
            ? '.bk-bench-item[data-competitor-id="' + S.pick.from.competitor_id + '"]'
            : '.bk-slot[data-match-id="' + S.pick.from.match_id + '"][data-side="' + S.pick.from.side + '"]';
        S.vp.querySelector(sel)?.classList.add('is-picked');
    }

    // -----------------------------------------------------------------
    // Saving a move
    // -----------------------------------------------------------------
    async function commit(from, to) {
        S.pick = null;
        if (sameEnd(from, to)) { applyArrangeClasses(); emitState(); return; }

        // Optimism is not worth it here: a move can be refused server-side
        // (locked draw, competitor no longer entered), and a bracket that
        // silently disagrees with the server is worse than a brief wait.
        try {
            const res = await fetch(S.cfg.arrangeUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': S.cfg.csrf, 'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ category_id: S.division, from, to }),
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok || !data.success) {
                window.showToast && window.showToast('error', data.message || S.cfg.text.moveFailed);
                await load(true);       // resync — the server is the truth
                return;
            }

            patchDivision(data.data?.division);
            window.showToast && window.showToast('success', data.message);
        } catch (e) {
            window.showToast && window.showToast('error', S.cfg.text.moveFailed);
            await load(true);
        }
    }

    /** Swap one division's data in and redraw it, keeping the current view. */
    function patchDivision(division) {
        if (!division) { load(true); return; }
        const i = S.divisions.findIndex(d => d.id === division.id);
        if (i === -1) { load(true); return; }
        S.divisions[i] = division;
        render(true);
        emitState();
    }

    async function clearDraw() {
        if (!S.canArrange || !S.division) return;
        const ok = await (window.confirmAction ? window.confirmAction({
            title: S.cfg.text.clearTitle,
            message: S.cfg.text.clearMessage,
            type: 'danger',
            confirmText: S.cfg.text.clearConfirm,
        }) : Promise.resolve(false));
        if (!ok) return;

        try {
            const res = await fetch(S.cfg.clearUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': S.cfg.csrf, 'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ category_id: S.division }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.message || S.cfg.text.moveFailed);
            patchDivision(data.data?.division);
            window.showToast && window.showToast('success', data.message);
        } catch (e) {
            window.showToast && window.showToast('error', e.message);
            load(true);
        }
    }

    // -----------------------------------------------------------------
    // Realtime — someone else's draw edit or a bout landing shows up here
    // without anyone refreshing.
    // -----------------------------------------------------------------
    function bindRealtime() {
        // The mobile shell re-runs inline scripts on every AJAX nav, so the
        // previous handler is removed before a new one is added.
        if (window.__bracketRealtime) {
            window.removeEventListener('realtime:events', window.__bracketRealtime);
        }
        let pending = null;
        window.__bracketRealtime = e => {
            const d = e.detail || {};
            if (!S || d.event !== S.cfg.eventUuid) return;
            if (!['draw', 'outcome', 'entrants', 'podium'].includes(d.action)) return;
            // Coalesce a burst (a draw generates one message per division).
            clearTimeout(pending);
            pending = setTimeout(() => load(true), 250);
        };
        window.addEventListener('realtime:events', window.__bracketRealtime);
    }

    // -----------------------------------------------------------------
    function emit(name, detail) { S.vp.dispatchEvent(new CustomEvent(name, { detail, bubbles: true })); }
    function emitState() {
        emit('bracket:state', {
            arrange: S.arrange && S.canArrange,
            picked: S.pick ? S.pick.name : null,
            division: S.division,
            divisions: S.divisions.map(d => ({ id: d.id, name: d.name, entrants: d.entrants,
                                              bench: d.bench.length, is_heading: !!d.is_heading })),
        });
    }

    function toggleArrange(on) {
        if (!S.canArrange) return;
        S.arrange = on === undefined ? !S.arrange : !!on;
        S.pick = null;
        renderBench(current());
        applyArrangeClasses();
        emitState();
    }

    return {
        mount, reload: keep => load(keep !== false), show, clearDraw, toggleArrange,
        zoomIn: () => zoomAt(1.18, S.vp.clientWidth / 2, S.vp.clientHeight / 2),
        zoomOut: () => zoomAt(0.85, S.vp.clientWidth / 2, S.vp.clientHeight / 2),
        fit,
        state: () => ({ arrange: S.arrange, canArrange: S.canArrange, division: S.division }),
    };
})();
</script>
