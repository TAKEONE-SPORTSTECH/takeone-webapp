  // The Schedule tab's RUNNING ORDER (FederationApp::scheduleRewrites) — the
  // platform event's own day, drawn in the dashboard's language: the day as a
  // timeline on the left, a mat's bout sheet on the right, joined by selection
  // (owner, 2026-10-14). Design source: the approved preview.
  //
  // It reads and writes ONLY through the platform's own running-order doors
  // (TakeOneFed.runOrder → the same endpoints /me/events/{uuid}/order uses),
  // which re-authorise every request. The doors are present only for a viewer
  // they will serve; the write doors only for an organiser. Nothing here
  // decides who may do what — it decides what to OFFER.
  //
  // Drag and drop: a division moves within its own level (the day, or its
  // group); a bout moves to another bout's place on its mat, which is the order
  // board's own move (that number is then held by hand). Alt+↑/↓ moves the
  // selected division without a mouse. Live: other people's changes arrive over
  // realtime (`realtime:events` refresh/draw for this event) and re-read.

  roSet(patch) { this.setState(st => ({ro: {...(st.ro || {}), ...patch}})); }

  roToast(msg, ok) {
    clearTimeout(this._roToastT);
    this.roSet({toast: msg, toastOk: ok !== false});
    this._roToastT = setTimeout(() => this.roSet({toast: ''}), 3200);
  }

  roLoad(doors, day) {
    const T = window.TakeOneFed || {};
    if (!T.roFetch) return;
    const cur = this.state.ro && this.state.ro.event === doors.event ? this.state.ro : null;
    const d = day || (cur && cur.day) || 1;
    this._roLoading = doors.event;
    Promise.all([
      T.roFetch('GET', doors.mats + '?day=' + d),
      doors.day ? T.roFetch('GET', doors.day + '?day=' + d) : Promise.resolve(null),
    ]).then(([plan, sheet]) => {
      this._roLoading = null;
      this.setState(st => ({ro: {
        ...(st.ro && st.ro.event === doors.event ? st.ro : {view: doors.day ? 'split' : 'mats', mat: 0}),
        event: doors.event, day: d, busy: false,
        plan: plan && plan.__ok ? plan : null,
        sheet: sheet && sheet.__ok ? sheet.sheet : null,
        err: plan && plan.__ok ? '' : ((plan && plan.message) || 'The running order could not be loaded. Try again.'),
      }}));
    });
  }

  roListen(doors) {
    if (this._roRT === doors.event) return;
    if (this._roRTfn) window.removeEventListener('realtime:events', this._roRTfn);
    this._roRT = doors.event;
    this._roRTfn = e => {
      const d = (e && e.detail) || {};
      if (d.event !== doors.event || ['refresh', 'draw'].indexOf(d.action) < 0) return;
      clearTimeout(this._roRTt);
      this._roRTt = setTimeout(() => {
        const ro = this.state.ro || {};
        if (!ro.busy && !this._roDrag && !this._roBout && this.state.tab === 'schedule') this.roLoad(doors);
      }, 600);
    };
    window.addEventListener('realtime:events', this._roRTfn);
  }

  roVals(sel) {
    const s = this.state, T = window.TakeOneFed || {};
    const doors = (T.runOrder && sel && sel.id) ? T.runOrder(sel.id) : null;
    if (!doors) return {on: false};
    this.roListen(doors);
    this._roDoors = doors;

    const ro = s.ro && s.ro.event === doors.event ? s.ro : null;
    if (!ro && this._roLoading !== doors.event) setTimeout(() => this.roLoad(doors), 0);
    const loading = !ro, plan = ro && ro.plan, sheet = ro && ro.sheet, manage = !!doors.manage;
    // A PHONE (owner, 2026-10-16): one column — the day OR the mats, never
    // squeezed side by side — a slimmer day list, and press-and-hold to drag
    // (a touch screen sends no HTML5 drag events). Re-read on resize.
    if (!this._roRz && typeof window !== 'undefined') {
      this._roRz = true;
      window.addEventListener('resize', () => {
        clearTimeout(this._roRzT);
        this._roRzT = setTimeout(() => { if ((this.state || {}).tab === 'schedule') this.roSet({vw: window.innerWidth}); }, 150);
      });
    }
    const narrow = typeof window !== 'undefined' && window.innerWidth < 760;
    const busy = !!(ro && ro.busy);
    let view = (ro && ro.view) || (doors.day ? (narrow ? 'day' : 'split') : 'mats');
    if (narrow && view === 'split') view = 'day';
    const css = c => this.cssToObj(c);

    // ── time helpers ──────────────────────────────────────────────────────
    const fmt = min => {
      const m = ((min % 1440) + 1440) % 1440;
      let h = Math.floor(m / 60); const mm = m % 60, ap = h >= 12 ? 'PM' : 'AM';
      h = h % 12 || 12;
      return h + ':' + String(mm).padStart(2, '0') + ' ' + ap;
    };
    const clean = t => String(t || '').replace(/ /g, ' ');
    const ink = name => {
      const m = /^(.*?)\b(Male|Female)\b(.*)$/.exec(String(name || ''));
      return m ? {a: m[1], g: m[2], b: m[3], gStyle: css('font-weight:700;color:' + (m[2] === 'Female' ? '#db2777' : '#2563eb'))}
               : {a: String(name || ''), g: '', b: '', gStyle: {}};
    };

    // ── the day ───────────────────────────────────────────────────────────
    // Displayed the way the platform's own board shows it: each top-level row,
    // and a group's members straight under their group.
    const rawRows = (sheet && sheet.rows) || [];
    const rows = [];
    rawRows.filter(r => !r.group).forEach(r => {
      rows.push(r);
      if (r.kind === 'group') rawRows.filter(m => m.group === r.uuid).forEach(m => rows.push(m));
    });
    const partLabel = (sheet && sheet.part_labels) || {};
    const PART = {prelims: 'Prelims', semis: 'Semis', finals: 'Final', awarding: 'Awards'};
    const zone = ro && ro.zone, selId = ro && ro.sel;

    // ── Live drag (owner, 2026-10-16): no grip and no drop line — while a card
    // is held, the cards around it SLIDE to open the space it will land in,
    // and the held card stands there as a faded copy. Positions are judged
    // against where the cards were when the drag began (measured once, in
    // roDragBegin), so the moving cards never move the target under the
    // pointer. A group travels with its classes; a group never lands inside
    // another group.
    const drag = (ro && ro.drag && this._roDrag) ? ro.drag : null;   // {uuid, k, g}
    const blockOf = u => {
      const r = rows.find(x => x.uuid === u);
      if (!r) return [];
      return r.kind === 'group' ? [r.uuid].concat(rows.filter(x => x.group === r.uuid).map(x => x.uuid)) : [r.uuid];
    };
    const oldIndexOf = u => rows.findIndex(x => x.uuid === u);
    let shiftOf = {}, newGroupOf = {}, footShift = {};
    if (drag) {
      const geo = this._roGeo || {};
      const hOf = u => (geo[u] ? geo[u].h : 60);
      const block = blockOf(drag.uuid), inBlock = {}; block.forEach(u => { inBlock[u] = true; });
      const rest = rows.filter(r => !inBlock[r.uuid]).map(r => r.uuid);
      const k = Math.max(0, Math.min(rest.length, drag.k));
      const next = rest.slice(0, k).concat(block, rest.slice(k));
      const oldTop = {}, newTop = {};
      let y = 0; rows.forEach(r => { oldTop[r.uuid] = y; y += hOf(r.uuid); });
      y = 0; next.forEach(u => { newTop[u] = y; y += hOf(u); });
      rows.forEach(r => { shiftOf[r.uuid] = newTop[r.uuid] - oldTop[r.uuid]; });
      newGroupOf[drag.uuid] = drag.g;
      // Each group box's foot follows the box's last class, before and after.
      const groupNow = r => (r.uuid === drag.uuid ? (drag.g || null) : (r.group || null));
      rows.filter(r => r.kind === 'group').forEach(gr => {
        const end = (members, top) => Math.max(top[gr.uuid] + hOf(gr.uuid), ...members.map(m => top[m.uuid] + hOf(m.uuid)));
        footShift[gr.uuid] = end(rows.filter(m => groupNow(m) === gr.uuid), newTop) - end(rows.filter(m => m.group === gr.uuid), oldTop);
      });
    }
    // (dragTarget and dragDropFn are kept on the component too, below, so a
    // touch drag — driven from document listeners — always calls this render's.)
    // Where the held card would land, from the pointer — over the list as it
    // was when the drag began.
    const dragTarget = (e, box) => {
      const src = rows.find(x => x.uuid === this._roDrag);
      if (!src) return null;
      const geo = this._roGeo || {}, block = blockOf(src.uuid), inBlock = {}; block.forEach(u => { inBlock[u] = true; });
      const rest = rows.filter(r => !inBlock[r.uuid]);
      const y = e.clientY - box.top;
      let k = rest.filter(r => geo[r.uuid] && geo[r.uuid].mid < y).length;
      let g = null;
      if (src.kind === 'group') {
        // Never between a group's header and its classes, or among them.
        while (k < rest.length && rest[k].group) k++;
      } else {
        const above = rest[k - 1], below = rest[k];
        if (below && below.group) g = below.group;
        else if (above && above.kind === 'group') g = above.uuid;
        else if (above && above.group) g = above.group;
        // Over the time column: out of any group.
        if (g && !(below && below.group) && e.clientX - box.left < 74) g = null;
        if (!doors.move) g = src.group || null;   // no move door: only within its own list
      }
      return {uuid: src.uuid, k, g};
    };
    const dragDropFn = () => {
      const d = (this.state.ro || {}).drag, srcUuid = this._roDrag;
      this._roDrag = null;
      if (!d || !srcUuid || busy) { this.roSet({drag: null}); return; }
      const src = rows.find(x => x.uuid === srcUuid), block = blockOf(srcUuid), inBlock = {}; block.forEach(u => { inBlock[u] = true; });
      const rest = rows.filter(r => !inBlock[r.uuid]);
      const k = Math.max(0, Math.min(rest.length, d.k));
      const next = rest.slice(0, k).concat(block.map(u => rows.find(x => x.uuid === u)), rest.slice(k));
      const toGroup = src.kind === 'group' ? null : (d.g || null);
      // The list it lands in, and its place in that list.
      const list = next.filter(r => r.uuid === srcUuid || (toGroup ? r.group === toGroup : !r.group)).map(r => r.uuid);
      const at = list.indexOf(srcUuid);
      const before = (toGroup ? rows.filter(r => r.group === toGroup) : rows.filter(r => !r.group)).map(r => r.uuid);
      if ((src.group || null) === toGroup && before.indexOf(srcUuid) === at) { this.roSet({drag: null}); return; }
      const day = (ro && ro.day) || 1;
      const req = doors.move
        ? T.roFetch('PUT', doors.move, {day, slot: srcUuid, group: toGroup, index: at})
        : T.roFetch('PUT', doors.reorder, {day, order: list});
      this.roSet({busy: true, drag: null});
      req.then(r => {
        if (!r.__ok) { this.roSet({busy: false}); this.roToast(r.message || 'That move did not save. Try again.', false); return; }
        const moved = ((r.sheet && r.sheet.rows) || []).find(x => x.uuid === srcUuid);
        this.roSet({sheet: r.sheet || sheet, sel: src.category_id || src.uuid});
        this.roLoad(doors, day);
        this.roToast((src.name || 'Moved') + (moved ? ' now starts ' + clean(moved.starts_label) : '') + ' · bout numbers updated');
      });
    };
    const dragUuid = this._roDrag || null;
    const byUuid = {}; rows.forEach(r => { byUuid[r.uuid] = r; });
    const siblings = r => rows.filter(x => (x.group || null) === (r.group || null));
    // Where a drop on `tgt` puts the dragged row: beside it, in tgt's list —
    // or INTO a group when dropped on the lower half of a group's own row.
    const into = (src, tgt, after) => tgt.kind === 'group' && after && src.kind !== 'group' && !!doors.move;

    const moveSlot = (srcUuid, tgtUuid, after, keyboard) => {
      const src = byUuid[srcUuid], tgt = byUuid[tgtUuid];
      if (!src || !tgt || src.uuid === tgt.uuid || busy) return;
      const inGroup = !keyboard && into(src, tgt, after);
      const toGroup = inGroup ? tgt.uuid : (tgt.group || null);
      if (src.kind === 'group' && toGroup) { this.roToast('A group cannot go inside another group.', false); return; }
      if (!doors.move && (src.group || null) !== toGroup) { this.roToast('Move it within its own group — or open the full editor to change groups.', false); return; }
      const list = (toGroup ? rows.filter(x => x.group === toGroup) : rows.filter(x => !x.group)).map(x => x.uuid).filter(u => u !== src.uuid);
      const at = inGroup ? 0 : list.indexOf(tgt.uuid) + (after ? 1 : 0);
      list.splice(at, 0, src.uuid);
      const day = (ro && ro.day) || 1;
      const req = doors.move
        ? T.roFetch('PUT', doors.move, {day, slot: src.uuid, group: toGroup, index: at})
        : T.roFetch('PUT', doors.reorder, {day, order: list});
      this.roSet({busy: true, zone: null});
      req.then(r => {
        if (!r.__ok) { this.roSet({busy: false}); this.roToast(r.message || 'That move did not save. Try again.', false); return; }
        const moved = ((r.sheet && r.sheet.rows) || []).find(x => x.uuid === src.uuid);
        this.roSet({sheet: r.sheet || sheet, sel: src.category_id || src.uuid});
        this.roLoad(doors, day);
        this.roToast((src.name || 'Moved') + (moved ? ' now starts ' + clean(moved.starts_label) : '') + ' · bout numbers updated');
      });
    };

    const previewStart = (src, tgt, after) => {
      let t = after ? tgt.ends_at : tgt.starts_at;
      if (src.starts_at < tgt.starts_at) t -= src.duration;
      return fmt(t);
    };

    this._roDT = dragTarget; this._roDD = dragDropFn;

    const dayItems = [];
    let lunchDone = !(sheet && sheet.event_break), crossed = false;
    rows.forEach((r, i) => {
      if (!lunchDone && r.starts_at >= sheet.event_break.starts_at) {
        dayItems.push({key: 'lunch', isLunch: true, label: 'Lunch break · ' + clean(sheet.event_break.starts_label) + ' – ' + clean(sheet.event_break.ends_label)});
        lunchDone = true;
      }
      if (!crossed && r.starts_at >= 1440) { dayItems.push({key: 'midnight', isMidnight: true}); crossed = true; }

      const isDiv = r.kind === 'division', isGroup = r.kind === 'group';
      const empty = isDiv && !r.entrants;
      const gender = r.gender === 'female' ? 'f' : r.gender === 'male' ? 'm' : (isDiv ? 'x' : '');
      const rail = {m: '#2563eb', f: '#db2777', x: '#7c6bf5'}[gender] || (isGroup ? '#1e2c4f' : '#d0a854');
      const soft = {m: '#eef4ff', f: '#fdf0f6', x: '#f3f1ff'}[gender] || (isGroup ? '#eef1f7' : '#fdf8ec');
      const selected = selId && (selId === r.category_id || selId === r.uuid);
      const dragging = !!drag && drag.uuid === r.uuid;
      const held = !!drag && blockOf(drag.uuid).indexOf(r.uuid) >= 0;
      const shift = drag ? (shiftOf[r.uuid] || 0) : 0;
      // A group is a BOX (owner, 2026-10-16 — the mobile board's look): its
      // header is the box's navy band, its classes sit inside, and the box
      // closes under the last of them with "+ Add classes". The held card
      // wears the box's sides when it would land inside a group.
      const inGroup = dragging ? !!newGroupOf[r.uuid] : !!r.group;
      const indent = 0;
      const BOX = '#1e2c4f', TINT = 'rgba(30,44,79,.045)';
      const boxStyle = isGroup ? 'border:2px solid ' + BOX + ';border-bottom:0;border-radius:16px 16px 0 0;background:' + TINT + ';padding:6px 6px 2px;'
        : inGroup ? 'border-left:2px solid ' + BOX + ';border-right:2px solid ' + BOX + ';background:' + TINT + ';padding:0 6px;' : '';
      const mode = isGroup ? ((r.group_head && r.group_head.mode) || 'class') : null;
      const modes = isGroup && manage && doors.session ? [['class', 'One class at a time'], ['round', 'Round by round'], ['manual', 'Manual']].map(([k, l]) => ({
        key: k, label: l,
        style: css('height:26px;padding:0 10px;border-radius:7px;border:0;font-family:inherit;font-size:.66rem;font-weight:700;cursor:pointer;'
          + (mode === k ? 'background:#edcd85;color:#1e2c4f' : 'background:rgba(255,255,255,.14);color:#fff')),
        onClick: e => {
          if (e && e.stopPropagation) e.stopPropagation();
          if (busy || mode === k) return;
          this.roSet({busy: true});
          T.roFetch('PUT', doors.session, {day: (ro && ro.day) || 1, group: r.uuid, mode: k}).then(res => {
            this.roSet({busy: false});
            this.roToast(res.__ok ? r.name + ': ' + l.toLowerCase() + ' · bout numbers updated' : (res.message || 'That did not save. Try again.'), res.__ok);
            if (res.__ok) this.roLoad(doors);
          });
        },
        stop: e => { if (e && e.stopPropagation) e.stopPropagation(); },
      })) : [];
      const n = ink(r.name);
      const chips = (r.parts || []).map(p => ({label: PART[p] || partLabel[p] || p,
        style: css('font-size:.62rem;font-weight:600;padding:2px 8px;border-radius:999px;' + (p === 'finals' ? 'background:#fdf6e6;color:#8a6d20;border:1px solid #e8d3a0' : 'background:#fff;color:#6b7689;border:1px solid #e3e7ee'))}));
      if (isDiv && r.entrants) chips.push({label: r.duration + ' min', style: css('font-size:.62rem;font-weight:600;padding:2px 8px;border-radius:999px;background:#fff;color:#6b7689;border:1px solid #e3e7ee')});

      dayItems.push({
        key: r.uuid, isRow: true, uuid: r.uuid,
        clock: clean(r.starts_label), next: r.starts_at >= 1440,
        clockStyle: css('font-size:' + (narrow ? '.6rem' : '.7rem') + ';color:#6b7689;text-align:right;padding:10px ' + (narrow ? '6px' : '12px') + ' 0 0;font-variant-numeric:tabular-nums;font-family:ui-monospace,Menlo,monospace'),
        numsStyle: css('grid-row:span 2;display:flex;gap:' + (narrow ? '8px' : '14px') + ';align-items:center;font-variant-numeric:tabular-nums'),
        // Press and hold, then drag: the phone's way in (roTouchStart).
        onTouchStart: e => { if (manage && !busy) this.roTouchStart(e, r.uuid); },
        a: n.a, g: n.g, b: n.b, gStyle: n.gStyle,
        sub: isGroup ? 'Group' + (r.session_mode ? ' · ' + r.session_mode.replace(/_/g, ' ') : '') : (!isDiv ? clean(r.starts_label) + ' – ' + clean(r.ends_label) : ''),
        hasNums: isDiv && !!r.entrants, entrants: String(r.entrants || 0), bouts: String(r.bouts || 0),
        chips, hasChips: chips.length > 0, empty, emptyText: 'No entrants yet — nothing will run',
        draggable: manage && !busy ? 'true' : 'false', grip: '',
        // The row (time and card together) slides; the held card's own row
        // eases into its new place as a faded copy.
        rowStyle: css('display:grid;grid-template-columns:' + (narrow ? '44px' : '70px') + ' minmax(0,1fr);padding-left:' + indent + 'px;'
          + 'transform:translateY(' + shift + 'px);transition:transform .22s cubic-bezier(.2,.8,.2,1),padding-left .18s ease;'
          + (held ? 'position:relative;z-index:3;' : '')),
        boxStyle: css(boxStyle),
        canRemove: isGroup && manage && !!doors.store,
        removeLabel: (ro && ro.rmArm === r.uuid) ? 'Remove box?' : '✕',
        removeStyle: css('height:30px;min-width:30px;padding:0 ' + ((ro && ro.rmArm === r.uuid) ? '10px' : '0') + ';border-radius:999px;font-family:inherit;font-size:.7rem;font-weight:700;cursor:pointer;'
          + ((ro && ro.rmArm === r.uuid) ? 'border:0;background:#b4483c;color:#fff' : 'border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.12);color:#fff')),
        removeTitle: 'Remove this group — its classes stay on the day, out of the box',
        onRemove: e => {
          if (e && e.stopPropagation) e.stopPropagation();
          if (busy || !doors.store) return;
          if ((this.state.ro || {}).rmArm !== r.uuid) { this.roSet({rmArm: r.uuid}); clearTimeout(this._roRmT); this._roRmT = setTimeout(() => this.roSet({rmArm: null}), 4000); return; }
          this.roSet({busy: true, rmArm: null});
          T.roFetch('DELETE', doors.store + '/' + r.uuid).then(res => {
            this.roSet({busy: false});
            this.roToast(res.__ok ? r.name + ' removed · its classes stay on the day' : (res.message || 'That did not go through. Try again.'), res.__ok);
            if (res.__ok) this.roLoad(doors);
          });
        },
        nameStyle: css('font-size:' + (isGroup ? '.9rem;font-weight:800;color:#fff' : '.84rem;font-weight:600;color:#1e2c4f') + ';min-width:0;overflow-wrap:anywhere'),
        subStyle: css('display:block;font-size:.7rem;font-weight:500;color:' + (isGroup ? 'rgba(255,255,255,.75)' : '#6b7689')),
        modes, hasModes: modes.length > 0,
        slotStyle: css('position:relative;margin:3px 0;border-radius:12px;padding:' + (narrow ? '8px 10px 8px 11px' : '9px 12px 9px 14px') + ';display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:4px ' + (narrow ? '8px' : '12px') + ';align-items:center;-webkit-touch-callout:none;-webkit-user-select:none;user-select:none;cursor:' + (manage ? (drag ? 'grabbing' : 'grab') : 'pointer') + ';transition:box-shadow .15s,opacity .15s;'
          + 'min-height:' + Math.max(50, Math.min(140, (r.duration || 10) * 1.25)) + 'px;'
          + (isGroup ? 'background:linear-gradient(150deg,#1e2c4f,#2b3d68);border:1px solid ' + (selected ? '#d0a854' : 'transparent') + ';'
            : empty ? 'background:transparent;border:1px dashed #cfd5df;' : 'background:' + soft + ';border:1px solid ' + (selected ? '#d0a854' : 'transparent') + ';')
          + 'box-shadow:' + (held ? '0 10px 26px rgba(30,44,79,.16)' : selected ? '0 0 0 3px rgba(208,168,84,.28)' : 'none') + ';'
          + (held ? 'opacity:.45;' : '')
          + 'border-left:4px solid ' + (isGroup ? '#d0a854' : empty ? '#cfd5df' : rail)),
        zoneLabel: '', hasZone: false, zoneStyle: {},
        onClick: () => { this.roSet({sel: r.category_id || r.uuid}); setTimeout(() => this.roScrollTo(r.category_id), 0); },
        onKeyDown: e => {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.roSet({sel: r.category_id || r.uuid}); setTimeout(() => this.roScrollTo(r.category_id), 0); return; }
          if (!manage || !e.altKey || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
          e.preventDefault();
          const sib = siblings(r), k = sib.findIndex(x => x.uuid === r.uuid);
          const t = e.key === 'ArrowUp' ? sib[k - 1] : sib[k + 1];
          if (t) moveSlot(r.uuid, t.uuid, e.key === 'ArrowDown', true);
        },
        onDragStart: e => {
          if (!manage || busy) { e.preventDefault(); return; }
          try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', r.uuid); } catch (x) {}
          this.roDragBegin(r.uuid);
          const k = rows.filter(x => blockOf(r.uuid).indexOf(x.uuid) < 0).findIndex(x => oldIndexOf(x.uuid) > oldIndexOf(r.uuid));
          // Painted a frame later, so the browser takes its drag picture first.
          requestAnimationFrame(() => this.roSet({drag: {uuid: r.uuid, k: k < 0 ? rows.length : k, g: r.group || null}}));
        },
        onDragOver: undefined, onDrop: undefined,
        onDragEnd: () => { this._roDrag = null; if ((this.state.ro || {}).drag) this.roSet({drag: null}); },
      });

      // The box closes under the group's last class (or straight under its
      // band when it has none) with the way to add classes to it.
      const gid = isGroup ? r.uuid : r.group;
      const nextRow = rows[i + 1];
      if (gid && (!nextRow || nextRow.group !== gid)) {
        const head = isGroup ? r : rows.find(x => x.uuid === gid);
        dayItems.push({
          key: 'foot-' + gid, isFoot: true,
          canAdd: manage && !!doors.store,
          addLabel: '+ Add classes to ' + ((head && head.name) || 'this group'),
          onAdd: () => this.roGaOpen(head),
          footStyle: css('display:grid;grid-template-columns:' + (narrow ? '44px' : '70px') + ' minmax(0,1fr);transform:translateY(' + (drag ? (footShift[gid] || 0) : 0) + 'px);transition:transform .22s cubic-bezier(.2,.8,.2,1)'),
          boxStyle: css('border:2px solid ' + BOX + ';border-top:0;border-radius:0 0 16px 16px;background:' + TINT + ';padding:4px 6px 8px;margin-bottom:8px'),
        });
      }
    });

    // ── the mats ──────────────────────────────────────────────────────────
    const mats = (plan && plan.mats) || [];
    const matIx = Math.min((ro && ro.mat) || 0, Math.max(0, mats.length - 1));
    const mat = mats[matIx] || null;
    const ROUND = {Final: 'Final', Semifinal: 'SF', 'Semi-final': 'SF', Quarterfinal: 'QF', 'Round of 16': 'R16', 'Round of 32': 'R32', 'Third place': 'Bronze'};
    const startOf = {}; rows.forEach(r => { if (r.category_id) startOf[r.category_id] = clean(r.starts_label); });
    const genderOf = {}; rows.forEach(r => { if (r.category_id) genderOf[r.category_id] = r.gender; });
    const overId = ro && ro.over;
    const groups = [];
    if (mat) {
      let cur = null;
      mat.bouts.forEach((b, i) => {
        if (!cur || cur.id !== b.division_id) {
          const n = ink(b.division);
          cur = {key: b.division_id + '-' + i, id: b.division_id, domId: 'ro-g-' + b.division_id, a: n.a, g: n.g, b: n.b, gStyle: n.gStyle,
            time: startOf[b.division_id] || '', bouts: [],
            dotStyle: css('width:9px;height:9px;border-radius:50%;flex:none;background:' + (genderOf[b.division_id] === 'female' ? '#db2777' : '#2563eb')),
            headStyle: css('position:sticky;top:0;z-index:1;display:flex;align-items:center;gap:8px;padding:8px 6px 6px;border-bottom:1px solid #eef1f6;'
              + (selId === b.division_id ? 'background:#fdf6e6;border-radius:8px 8px 0 0' : 'background:#fff'))};
          groups.push(cur);
        }
        const rd = ROUND[b.round] || b.round || '';
        const canDrag = manage && !b.decided && !busy;
        cur.bouts.push({
          key: b.id, no: b.no ? '#' + b.no : '—', pinned: !!b.pinned, decided: !!b.decided,
          a: b.a || 'To be decided', b: b.b || 'To be decided', round: rd,
          roundStyle: css('font-size:.6rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:3px 8px;border-radius:999px;' + (rd === 'Final' ? 'background:#fdf6e6;color:#8a6d20' : 'background:#f1f3f7;color:#6b7689')),
          draggable: canDrag ? 'true' : 'false',
          rowStyle: css('display:grid;grid-template-columns:16px 76px minmax(0,1fr) auto;gap:10px;align-items:center;padding:7px 6px;border-bottom:1px dashed #eef1f6;'
            + (canDrag ? 'cursor:grab;' : '') + (b.decided ? 'opacity:.55;' : '') + (overId === b.id ? 'box-shadow:inset 0 3px 0 #d0a854;' : '')),
          onDragStart: e => { if (!canDrag) { e.preventDefault(); return; } this._roBout = b; try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', 'b' + b.id); } catch (x) {} },
          onDragOver: e => { if (!this._roBout || this._roBout.id === b.id) return; e.preventDefault(); if ((this.state.ro || {}).over !== b.id) this.roSet({over: b.id}); },
          onDrop: e => {
            e.preventDefault();
            const src = this._roBout; this._roBout = null;
            if (!src || src.id === b.id) { this.roSet({over: null}); return; }
            const position = mat.bouts.findIndex(x => x.id === b.id);
            this.roSet({busy: true, over: null});
            T.roFetch('PUT', doors.boutMove, {match: src.id, court: mat.court, position, day: (ro && ro.day) || 1}).then(r => {
              if (!r.__ok) { this.roSet({busy: false}); this.roToast(r.message || 'That move did not save. Try again.', false); return; }
              this.roSet({busy: false, plan: r.mats ? r : plan});
              this.roToast(r.message || ('Bout set to ' + (b.no ? '#' + b.no : 'that place') + ' by hand · the rest numbered around it'));
              if (!r.mats) this.roLoad(doors);
            });
          },
          onDragEnd: () => { this._roBout = null; if ((this.state.ro || {}).over) this.roSet({over: null}); },
        });
      });
    }
    const pinnedCount = mat ? mat.bouts.filter(b => b.pinned).length : 0;

    // ── the day, in numbers ───────────────────────────────────────────────
    const first = rows[0], last = rows[rows.length - 1];
    const startMin = first ? first.starts_at : null, endMin = last ? last.ends_at : null;
    const nextDay = endMin != null && endMin > 1440;
    const totalBouts = mats.reduce((n, m) => n + m.bouts.length, 0);
    const dur = startMin != null ? endMin - startMin : 0;
    const longDay = !!(sheet && rows.length && (nextDay || dur > 14 * 60));
    const tile = warn => css('background:#fff;border-radius:14px;padding:12px 14px;border-top:3px solid ' + (warn ? '#b45309' : '#d8b25f') + ';box-shadow:0 8px 24px rgba(30,44,79,.06);min-width:0');
    const stats = sheet && rows.length ? [
      {k: 'Starts', v: clean(sheet.starts_label) || fmt(startMin), extra: '', vStyle: css('font-size:1.2rem;font-weight:800;margin-top:2px'),
        // When the day starts is the organiser's to set (owner, 2026-10-16): tap the tile.
        canEdit: manage && !!doors.start, editLabel: '✎ Change',
        style: manage && doors.start ? Object.assign({}, tile(false), css('cursor:pointer')) : tile(false),
        onClick: () => { if (manage && doors.start) this.roStOpen(sheet); }},
      {k: 'Ends', v: clean(sheet.finishes_label) || fmt(endMin), extra: nextDay ? '+1 day' : '', style: tile(nextDay), vStyle: css('font-size:1.2rem;font-weight:800;margin-top:2px;color:' + (nextDay ? '#b45309' : '#1e2c4f'))},
      {k: 'On the mats', v: totalBouts + ' bouts', extra: '', sub: mats.length + (mats.length === 1 ? ' mat' : ' mats') + ' · ' + rows.filter(r => r.kind === 'division').length + ' divisions', style: tile(false), vStyle: css('font-size:1.2rem;font-weight:800;margin-top:2px')},
      {k: 'Lunch', v: sheet.event_break ? clean(sheet.event_break.starts_label) + ' – ' + clean(sheet.event_break.ends_label) : 'None', extra: '', style: tile(false), vStyle: css('font-size:1.2rem;font-weight:800;margin-top:2px')},
    ] : [];
    const matCount = Math.max(1, mats.length);
    const projections = longDay ? [1, 2, 3].map(k => ({key: k, label: (matCount + k) + ' mats → about ' + fmt(startMin + Math.ceil(dur * matCount / (matCount + k)))})) : [];

    const segBtn = on => css('border:0;height:32px;padding:0 13px;border-radius:9px;font-family:inherit;font-size:.76rem;font-weight:600;cursor:pointer;' + (on ? 'background:#1e2c4f;color:#fff' : 'background:transparent;color:#6b7689'));
    const views = (doors.day ? (narrow ? [['day', 'Day'], ['mats', 'Mats']] : [['split', 'Day + Mats'], ['day', 'Day'], ['mats', 'Mats']]) : [['mats', 'Mats']])
      .map(([k, l]) => ({key: k, label: l, pressed: view === k ? 'true' : 'false', style: segBtn(view === k), onClick: () => this.roSet({view: k})}));
    const matTabs = mats.length > 1 ? mats.map((m, i) => ({key: m.court, label: m.court + ' · ' + m.bouts.length, style: segBtn(i === matIx), onClick: () => this.roSet({mat: i})})) : [];
    const days = (plan && plan.days) || 1, curDay = (ro && ro.day) || 1;
    const dayTabs = days > 1 ? Array.from({length: days}, (_, i) => i + 1).map(d => ({key: d, label: 'Day ' + d, style: segBtn(d === curDay), onClick: () => { this.roSet({busy: true}); this.roLoad(doors, d); }})) : [];

    const showDay = !!sheet && view !== 'mats', showMats = view !== 'day' || !sheet;
    const evDate = sel && sel.startDate ? new Date(String(sel.startDate).slice(0, 10) + 'T00:00:00') : null;
    if (evDate && curDay > 1) evDate.setDate(evDate.getDate() + curDay - 1);

    return {
      on: true, loading, hasErr: !!(ro && ro.err), err: (ro && ro.err) || '', ready: !loading && !(ro && ro.err),
      manage, busy, title: (evDate && !isNaN(evDate.getTime()) ? evDate.toLocaleDateString('en-GB', {weekday: 'short', day: 'numeric', month: 'short', year: 'numeric'}) + ' · ' : '') + 'Day ' + curDay,
      views, matTabs, hasMatTabs: matTabs.length > 0, dayTabs, hasDayTabs: dayTabs.length > 0,
      stats, hasStats: stats.length > 0, longDay, projections,
      longText: nextDay ? 'The day runs ' + Math.floor(dur / 60) + ' h ' + (dur % 60) + ' min on ' + matCount + (matCount === 1 ? ' mat' : ' mats') + ' — past midnight.' : 'The day runs ' + Math.floor(dur / 60) + ' h ' + (dur % 60) + ' min.',
      gridStyle: css('display:grid;gap:16px;align-items:start;grid-template-columns:' + (showDay && showMats ? 'minmax(0,1.25fr) minmax(0,1fr)' : 'minmax(0,1fr)')),
      showDay, showMats, dayItems, hasRows: rows.length > 0, noRows: !!sheet && !rows.length,
      ga: this.roGaVals(rows, sheet, css),
      // Another group on the day (owner, 2026-10-16).
      canNewGroup: manage && !!doors.store && !!sheet,
      newGroupRow: css('display:grid;grid-template-columns:' + (narrow ? '44px' : '70px') + ' minmax(0,1fr);margin-top:8px'),
      onNewGroup: () => this.roSet({ng: {open: true, name: 'Group ' + (rows.filter(r => r.kind === 'group').length + 1), mode: 'round', busy: false}}),
      ng: this.roNgVals(css),
      st: this.roStVals(css),
      // The day list itself takes the drag, so the target is judged by the
      // pointer over the list rather than by whichever card is under it.
      onDayDragOver: e => {
        if (!this._roDrag) return;
        e.preventDefault();
        try { e.dataTransfer.dropEffect = 'move'; } catch (x) {}
        // Measured against the day list itself — the same element roDragBegin
        // measured the cards from. `e.currentTarget` is not reliably the list
        // through the dashboard's renderer, and reading it put every drop one
        // place too low (a class could never be dragged out above a group).
        const list = document.getElementById('ro-daylist');
        if (!list) return;
        const t = dragTarget(e, list.getBoundingClientRect()), d = (this.state.ro || {}).drag;
        if (t && (!d || d.uuid !== t.uuid || d.k !== t.k || d.g !== t.g)) this.roSet({drag: t});
      },
      onDayDrop: e => { e.preventDefault(); if (this._roDrag) dragDropFn(); },
      dayMeta: rows.filter(r => r.kind === 'division').length + ' divisions · ' + rows.filter(r => r.kind === 'division' && !r.entrants).length + ' with no entrants',
      matName: mat ? mat.court : 'Mats', matMeta: mat ? mat.bouts.length + ' bouts · ' + pinnedCount + ' numbered by hand' : '',
      groups, hasBouts: groups.length > 0, noBouts: !!plan && !groups.length,
      hint: manage && narrow ? 'Press and hold a card, then drag it — the others make room · into a group: drop it among its classes, or use + Add classes' : manage ? 'Drag a card to move it — the others make room · into a group: drop it among its classes · or select it and press Alt+↑/↓ · drag a bout onto another to give it that number' : 'You can read the running order; the organiser arranges it.',
      canRenumber: manage && !!doors.renumber, renumberLabel: busy ? 'Working…' : 'Renumber bouts',
      onRenumber: () => {
        if (busy || !doors.renumber) return;
        if (!(this.state.ro || {}).renumberAsk) { this.roSet({renumberAsk: true}); return; }
        this.roSet({busy: true, renumberAsk: false});
        T.roFetch('POST', doors.renumber).then(r => { this.roSet({busy: false}); this.roToast(r.message || (r.__ok ? 'Bouts renumbered.' : 'Renumbering did not go through.'), r.__ok); if (r.__ok) this.roLoad(doors); });
      },
      renumberAsk: !!(ro && ro.renumberAsk), renumberIdle: !(ro && ro.renumberAsk), onRenumberCancel: () => this.roSet({renumberAsk: false}),
      // Auto-schedule (owner, 2026-10-16): every division not on the day yet,
      // youngest → lightest → male first; what is already placed stays. Then the
      // mats are laid out from the sheet (hand-set bout numbers kept).
      canAuto: manage && !!doors.auto, autoLabel: (ro && ro.autoBusy) ? 'Scheduling…' : '✦ Auto-schedule',
      onAuto: () => {
        if (busy || !doors.auto) return;
        this.roSet({busy: true, autoBusy: true});
        T.roFetch('POST', doors.auto, {day: curDay}).then(r => {
          this.roSet({busy: false, autoBusy: false});
          this.roToast(r.message || (r.__ok ? 'Scheduled.' : 'Auto-schedule did not go through.'), r.__ok);
          if (r.__ok) this.roLoad(doors);
        });
      },
      fullUrl: doors.full || '', hasFull: !!doors.full && manage,
      onRetry: () => { this.roSet({err: ''}); this.roLoad(doors); },
      toast: (ro && ro.toast) || '', hasToast: !!(ro && ro.toast),
      toastStyle: css('position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:60;max-width:calc(100vw - 32px);padding:10px 16px;border-radius:12px;font-size:.8rem;font-weight:600;box-shadow:0 12px 30px rgba(0,0,0,.22);color:#fff;background:' + (ro && ro.toastOk === false ? '#b4483c' : '#1e2c4f')),
    };
  }

  // ── A new group on the day (owner, 2026-10-16) ───────────────────────────
  // Its name and how it calls its bouts; it lands at the end of the day as an
  // empty box, ready for classes (dragged in, or "+ Add classes").
  roNgVals(css) {
    const ro = this.state.ro || {}, ng = ro.ng || {}, doors = this._roDoors || {};
    if (!ng.open) return {open: false};
    const sheetUp = window.innerWidth < 760, name = String(ng.name || '');
    const modes = [['round', 'Round by round', 'Every class fights its first round, then the next — finals last (World Taekwondo)'],
      ['class', 'One class at a time', 'Each class runs from its first bout to its final before the next'],
      ['manual', 'Manual', 'You set the bout order yourself on the mats']];
    return {
      open: true, sheet: sheetUp, name,
      onName: e => this.roSet({ng: {...ng, name: e.target.value}}),
      modes: modes.map(([k, l, d]) => {
        const on = (ng.mode || 'round') === k;
        return {key: k, label: l, desc: d, onClick: () => this.roSet({ng: {...ng, mode: k}}),
          style: css('width:100%;display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:12px;text-align:left;font-family:inherit;cursor:pointer;' + (on ? 'border:2px solid #1e2c4f;background:#f3f5f9' : 'border:1px solid #e3e7ee;background:#fff')),
          dot: css('width:20px;height:20px;border-radius:50%;flex:none;' + (on ? 'border:6px solid #1e2c4f' : 'border:2px solid #cfd5df'))};
      }),
      overlayStyle: css('position:fixed;inset:0;z-index:80;display:flex;justify-content:center;background:rgba(15,21,36,.45);' + (sheetUp ? 'align-items:flex-end;padding:0' : 'align-items:center;padding:16px')),
      panelStyle: css('width:' + (sheetUp ? '100%' : 'min(520px,100%)') + ';max-height:' + (sheetUp ? '92vh' : 'calc(100vh - 32px)') + ';display:flex;flex-direction:column;background:#fff;overflow:hidden;box-shadow:0 30px 80px rgba(15,21,36,.35);'
        + (sheetUp ? 'border-radius:22px 22px 0 0' : 'border-radius:18px') + ';animation:rise .25s cubic-bezier(.22,.68,.35,1) both'),
      footStyle: css('flex:none;display:flex;justify-content:flex-end;gap:8px;padding:12px 20px;border-top:1px solid #eef1f6;' + (sheetUp ? 'padding-bottom:calc(12px + env(safe-area-inset-bottom))' : '')),
      saveLabel: ng.busy ? 'Creating…' : 'Create group',
      saveStyle: css('height:44px;padding:0 20px;border:0;border-radius:12px;font-family:inherit;font-size:.84rem;font-weight:700;cursor:pointer;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854);' + (ng.busy || !name.trim() ? 'opacity:.5;cursor:default' : '')),
      onSave: () => {
        const T = window.TakeOneFed || {};
        if (ng.busy || !name.trim() || !doors.store) return;
        this.roSet({ng: {...ng, busy: true}});
        T.roFetch('POST', doors.store, {day: ro.day || 1, kind: 'group', label: name.trim().slice(0, 160), mode: ng.mode || 'round'}).then(res => {
          if (!res.__ok) { this.roSet({ng: {...ng, busy: false}}); this.roToast(res.message || 'The group was not created. Try again.', false); return; }
          this.roSet({ng: {open: false}, sheet: res.sheet || ro.sheet});
          this.roLoad(doors);
          this.roToast(name.trim() + ' added at the end of the day — drag classes in, or use + Add classes');
        });
      },
      onClose: () => { if (!ng.busy) this.roSet({ng: {open: false}}); },
      stop: e => { if (e && e.stopPropagation) e.stopPropagation(); },
    };
  }

  // ── When the day starts (owner, 2026-10-16) ─────────────────────────────
  // The STARTS tile opens this: hours and minutes stepped with − / + (five
  // minutes at a time), never a native time box. Saved through the running
  // order's own door (updateDayStart); every time on the day then follows.
  roStOpen(sheet) {
    const m = /^(\d{1,2}):(\d{2})/.exec(String((sheet && sheet.start_time) || '09:00'));
    this.roSet({st: {open: true, h: m ? parseInt(m[1], 10) : 9, m: m ? parseInt(m[2], 10) : 0, busy: false}});
  }

  roStVals(css) {
    const ro = this.state.ro || {}, st = ro.st || {}, doors = this._roDoors || {};
    if (!st.open) return {open: false};
    const h = st.h, m = st.m, pad = n => String(n).padStart(2, '0');
    const ap = h >= 12 ? 'PM' : 'AM', h12 = h % 12 || 12;
    const set = (nh, nm) => this.roSet({st: {...st, h: ((nh % 24) + 24) % 24, m: ((nm % 60) + 60) % 60}});
    const sheetUp = window.innerWidth < 760;
    const step = css('width:48px;height:48px;border-radius:14px;border:1px solid #d5dae3;background:#fff;color:#1e2c4f;font-family:inherit;font-size:1.3rem;font-weight:700;cursor:pointer');
    return {
      open: true, sheet: sheetUp, busy: !!st.busy,
      time: h12 + ':' + pad(m), ap,
      hourLabel: pad(h12) + ' ' + ap, minLabel: pad(m),
      stepStyle: step,
      onHourUp: () => set(h + 1, m), onHourDown: () => set(h - 1, m),
      onMinUp: () => m + 5 >= 60 ? set(h + 1, 0) : set(h, m + 5 - (m % 5)),
      onMinDown: () => m === 0 ? set(h - 1, 55) : set(h, m % 5 ? m - (m % 5) : m - 5),
      presets: [['07:00', '7:00 AM'], ['08:00', '8:00 AM'], ['09:00', '9:00 AM'], ['10:00', '10:00 AM']].map(([v, l]) => {
        const on = v === pad(h) + ':' + pad(m);
        return {key: v, label: l, onClick: () => set(parseInt(v, 10), 0),
          style: css('height:34px;padding:0 12px;border-radius:999px;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer;' + (on ? 'border:2px solid #1e2c4f;background:#f3f5f9;color:#1e2c4f' : 'border:1px solid #d5dae3;background:#fff;color:#1e2c4f'))};
      }),
      overlayStyle: css('position:fixed;inset:0;z-index:80;display:flex;justify-content:center;background:rgba(15,21,36,.45);' + (sheetUp ? 'align-items:flex-end;padding:0' : 'align-items:center;padding:16px')),
      panelStyle: css('width:' + (sheetUp ? '100%' : 'min(440px,100%)') + ';max-height:' + (sheetUp ? '92vh' : 'calc(100vh - 32px)') + ';display:flex;flex-direction:column;background:#fff;overflow:hidden;box-shadow:0 30px 80px rgba(15,21,36,.35);'
        + (sheetUp ? 'border-radius:22px 22px 0 0' : 'border-radius:18px') + ';animation:rise .25s cubic-bezier(.22,.68,.35,1) both'),
      footStyle: css('flex:none;display:flex;justify-content:flex-end;gap:8px;padding:12px 20px;border-top:1px solid #eef1f6;' + (sheetUp ? 'padding-bottom:calc(12px + env(safe-area-inset-bottom))' : '')),
      saveLabel: st.busy ? 'Saving…' : 'Start the day at ' + h12 + ':' + pad(m) + ' ' + ap,
      saveStyle: css('height:44px;padding:0 20px;border:0;border-radius:12px;font-family:inherit;font-size:.84rem;font-weight:700;cursor:pointer;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854);' + (st.busy ? 'opacity:.6;cursor:wait' : '')),
      onSave: () => {
        const T = window.TakeOneFed || {};
        if (st.busy || !doors.start) return;
        this.roSet({st: {...st, busy: true}});
        T.roFetch('PUT', doors.start, {day: ro.day || 1, start: pad(h) + ':' + pad(m)}).then(res => {
          if (!res.__ok) {
            this.roSet({st: {...st, busy: false}});
            const first = res.errors ? Object.values(res.errors)[0] : null;
            this.roToast((Array.isArray(first) ? first[0] : first) || res.message || 'That did not save. Try again.', false);
            return;
          }
          this.roSet({st: {open: false}, sheet: res.sheet || ro.sheet});
          this.roLoad(doors);
          this.roToast('The day now starts at ' + h12 + ':' + pad(m) + ' ' + ap + ' · every time follows');
        });
      },
      onClose: () => { if (!st.busy) this.roSet({st: {open: false}}); },
      stop: e => { if (e && e.stopPropagation) e.stopPropagation(); },
    };
  }

  // ── "+ Add classes to <group>" (owner, 2026-10-16: the mobile board's box) ─
  // Every class not in this group — already on the day (in no group, or in
  // another one) or not on the day yet — ticked and added in ONE save,
  // through the running order's own door (storeRunSlot with `group`), which
  // re-checks the organiser and that the group is this event's.
  roGa(patch) { this.setState(st => ({ro: {...(st.ro || {}), ga: {...((st.ro || {}).ga || {}), ...patch}}})); }

  roGaOpen(head) {
    if (!head) return;
    this.roSet({ga: {open: true, group: head.uuid, name: head.name, pick: {}, busy: false}});
  }

  roGaVals(rows, sheet, css) {
    const ro = this.state.ro || {}, ga = ro.ga || {}, doors = this._roDoors || {};
    if (!ga.open) return {open: false};
    const groupName = {}; rows.filter(r => r.kind === 'group').forEach(r => { groupName[r.uuid] = r.name; });
    const onDay = rows.filter(r => r.kind === 'division' && r.group !== ga.group)
      .map(r => ({key: 's:' + r.uuid, name: r.name, gender: r.gender, sub: r.group ? 'In ' + (groupName[r.group] || 'another group') : 'On the day · in no group'}));
    const bench = ((sheet && sheet.unscheduled) || []).map(c => ({key: 'c:' + c.id, name: c.name, gender: null, sub: 'Not on the day yet'}));
    const all = onDay.concat(bench), pick = ga.pick || {};
    const count = all.filter(c => pick[c.key]).length, allOn = all.length > 0 && count === all.length;
    const card = c => {
      const on = !!pick[c.key], ink = c.gender === 'female' ? '#db2777' : c.gender === 'male' ? '#2563eb' : '#1e2c4f';
      return {key: c.key, name: c.name, sub: c.sub,
        nameStyle: css('font-size:.84rem;font-weight:700;color:' + ink + ';overflow-wrap:anywhere'),
        style: css('width:100%;display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;text-align:left;font-family:inherit;cursor:pointer;transition:border-color .15s,background .15s;'
          + (on ? 'border:2px solid #1e2c4f;background:#f3f5f9' : 'border:1px solid #e3e7ee;background:#fff')),
        tick: on ? '✓' : '',
        tickStyle: css('width:22px;height:22px;border-radius:7px;flex:none;display:grid;place-items:center;font-size:.8rem;font-weight:800;'
          + (on ? 'background:#1e2c4f;color:#edcd85' : 'border:2px solid #cfd5df;color:transparent')),
        onClick: () => { const next = {...pick}; if (next[c.key]) delete next[c.key]; else next[c.key] = true; this.roGa({pick: next}); }};
    };
    // A bottom sheet on a phone (rises from the edge, footer clear of the home
    // bar); a centred window on a desk.
    const sheetUp = window.innerWidth < 760;
    return {
      open: true, title: ga.name || 'Group', busy: !!ga.busy, sheet: sheetUp,
      overlayStyle: css('position:fixed;inset:0;z-index:80;display:flex;justify-content:center;background:rgba(15,21,36,.45);' + (sheetUp ? 'align-items:flex-end;padding:0' : 'align-items:center;padding:16px')),
      panelStyle: css('width:' + (sheetUp ? '100%' : 'min(640px,100%)') + ';max-height:' + (sheetUp ? '92vh' : 'calc(100vh - 32px)') + ';display:flex;flex-direction:column;background:#fff;overflow:hidden;box-shadow:0 30px 80px rgba(15,21,36,.35);'
        + (sheetUp ? 'border-radius:22px 22px 0 0;animation:rise .28s cubic-bezier(.22,.68,.35,1) both' : 'border-radius:18px;animation:rise .25s cubic-bezier(.22,.68,.35,1) both')),
      footStyle: css('flex:none;display:flex;justify-content:flex-end;gap:8px;padding:12px 20px;border-top:1px solid #eef1f6;' + (sheetUp ? 'padding-bottom:calc(12px + env(safe-area-inset-bottom))' : '')),
      onDay: onDay.map(card), hasOnDay: onDay.length > 0, bench: bench.map(card), hasBench: bench.length > 0, none: !all.length, hasAny: all.length > 0,
      allLabel: allOn ? 'Clear' : 'Select all', onAll: () => { const next = {}; if (!allOn) all.forEach(c => { next[c.key] = true; }); this.roGa({pick: next}); },
      goLabel: ga.busy ? 'Adding…' : (count ? 'Add ' + count + ' to ' + (ga.name || 'the group') : 'Choose classes to add'),
      goStyle: css('height:44px;padding:0 22px;border:0;border-radius:12px;font-family:inherit;font-size:.84rem;font-weight:700;cursor:pointer;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854);' + (count && !ga.busy ? '' : 'opacity:.5;cursor:default')),
      onGo: () => {
        const T = window.TakeOneFed || {};
        if (!count || ga.busy || !doors.store) return;
        const slots = all.filter(c => pick[c.key] && c.key.indexOf('s:') === 0).map(c => c.key.slice(2));
        const divisions = all.filter(c => pick[c.key] && c.key.indexOf('c:') === 0).map(c => ({id: parseInt(c.key.slice(2), 10), parts: null}));
        this.roGa({busy: true});
        T.roFetch('POST', doors.store, {day: ro.day || 1, group: ga.group, slots, divisions}).then(res => {
          if (!res.__ok) { this.roGa({busy: false}); this.roToast(res.message || 'Nothing was added. Try again.', false); return; }
          this.roSet({ga: {open: false}, sheet: res.sheet || ro.sheet});
          this.roLoad(doors);
          this.roToast(count + (count === 1 ? ' class' : ' classes') + ' added to ' + (ga.name || 'the group') + ' · bout numbers updated');
        });
      },
      onClose: () => { if (!ga.busy) this.roSet({ga: {open: false}}); },
      stop: e => { if (e && e.stopPropagation) e.stopPropagation(); },
    };
  }

  // ── Press and hold, then drag — the phone's drag (owner, 2026-10-16) ─────
  // A touch screen sends no HTML5 drag events, so this drives the SAME live
  // drag (roDragBegin → dragTarget → dragDropFn) from touch events. Holding
  // still for 350 ms picks the card up (a short buzz where the phone can);
  // moving before that is a scroll and is left to the page. While a card is
  // held the page does not scroll under the finger, and it creeps along when
  // the finger nears the top or bottom edge.
  roTouchStart(e, uuid) {
    const t = e.touches && e.touches[0];
    if (!t || this._roDrag || (e.touches && e.touches.length > 1)) return;
    const x0 = t.clientX, y0 = t.clientY;
    let active = false, last = {clientX: x0, clientY: y0};
    const place = () => {
      const list = document.getElementById('ro-daylist');
      if (!list || !this._roDT) return;
      const tg = this._roDT(last, list.getBoundingClientRect()), d = (this.state.ro || {}).drag;
      if (tg && (!d || d.uuid !== tg.uuid || d.k !== tg.k || d.g !== tg.g)) this.roSet({drag: tg});
    };
    const cleanup = () => {
      clearTimeout(this._roLP); clearInterval(this._roEdge);
      document.removeEventListener('touchmove', move, {passive: false});
      document.removeEventListener('touchend', end);
      document.removeEventListener('touchcancel', cancel);
    };
    const move = ev => {
      const p = ev.touches && ev.touches[0];
      if (!p) return;
      last = {clientX: p.clientX, clientY: p.clientY};
      if (!active) { if (Math.abs(p.clientX - x0) + Math.abs(p.clientY - y0) > 10) cleanup(); return; }
      ev.preventDefault();
      place();
    };
    const end = () => { const was = active; cleanup(); if (was && this._roDD) this._roDD(); };
    const cancel = () => { const was = active; cleanup(); if (was) { this._roDrag = null; this.roSet({drag: null}); } };
    document.addEventListener('touchmove', move, {passive: false});
    document.addEventListener('touchend', end);
    document.addEventListener('touchcancel', cancel);
    clearTimeout(this._roLP);
    this._roLP = setTimeout(() => {
      active = true;
      try { if (navigator.vibrate) navigator.vibrate(12); } catch (x) {}
      this.roDragBegin(uuid);
      place();
      // Near an edge, keep scrolling while the finger rests there.
      this._roEdge = setInterval(() => {
        const H = window.innerHeight;
        const dy = last.clientY < 90 ? -14 : last.clientY > H - 120 ? 14 : 0;
        if (dy) { window.scrollBy(0, dy); place(); }
      }, 30);
    }, 350);
  }

  // A drag begins: remember what is held, and measure every card once — where
  // its middle is and how tall it is — relative to the day list. Every later
  // decision reads these, never the cards as they slide.
  roDragBegin(uuid) {
    this._roDrag = uuid;
    const box = document.getElementById('ro-daylist'), geo = {};
    if (box) {
      const top = box.getBoundingClientRect().top;
      box.querySelectorAll('[data-ro-row]').forEach(el => {
        const b = el.getBoundingClientRect();
        geo[el.getAttribute('data-ro-row')] = {mid: b.top - top + b.height / 2, h: b.height};
      });
    }
    this._roGeo = geo;
  }

  // Bring a division's bouts into view on the mat sheet.
  roScrollTo(id) {
    const box = document.getElementById('ro-matbox'), g = id && document.getElementById('ro-g-' + id);
    if (box && g) box.scrollTo({top: g.offsetTop - 6, behavior: 'smooth'});
  }

