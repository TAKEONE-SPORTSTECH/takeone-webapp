{{--
    Shared folders and storage, in the folder column (takenote/script, inside its closure).

    - "Share…" in a folder's sheet: who has this folder, and an email to share it with
      (TakeNoteShareController). Only in one's own notebook.
    - "Shared with me": the folders other people shared; a tap opens one — the page
      then works inside it (Support\Notebooks fences every query) — and the banner at
      the top of the column says whose folder this is, with the way back.
    - The storage gauge: a donut of how much of their OWN notebook's space is used.
--}}
    /* ── shared folders + storage ─────────────────────────────────────── */
    function drawSharing() {
        var nav = document.getElementById('folders');
        if (!nav || !nav.parentNode) return;
        ['space-bar', 'shared-box', 'storage-box'].forEach(function (id) { var old = document.getElementById(id); if (old) old.remove(); });
        document.body.classList.toggle('in-space', !!S.space);

        // inside somebody's folder: whose it is, and the way back to one's own notebook
        if (S.space) {
            var bar = h('div', 'sp-bar'); bar.id = 'space-bar';
            if (/^#[0-9a-f]{6}$/i.test(S.space.color || '')) bar.style.setProperty('--sc', S.space.color);
            var ic = h('span', 'sp-ic'); ic.appendChild(icon('bi-folder-symlink-fill'));
            var tx = h('div', 'sp-tx');
            var nm = h('b', null, S.space.name || ''); nm.dir = 'auto';
            tx.append(h('small', null, T.sh_in), nm, h('span', null, t('sh_by', { name: S.space.owner || '' })));
            var back = h('button', 'sp-back'); back.type = 'button';
            back.append(icon('bi-arrow-return-left'), document.createTextNode(T.sh_back));
            back.addEventListener('click', function () { openSpace(null, back); });
            bar.append(ic, tx, back);
            if (!DESK) bar.classList.add('nb-home-only');
            nav.parentNode.insertBefore(bar, nav);
        }

        var after = nav;
        // the folders other people shared with me
        if (S.sharing && S.shared.length) {
            var box = h('section', 'sp-shared'); box.id = 'shared-box';
            box.appendChild(h('p', 'dk-sec sp-head', T.sh_with_me));
            S.shared.forEach(function (sf) {
                var on = S.space && S.space.uuid === sf.uuid;
                var b = h('button', 'sp-row' + (on ? ' is-on' : '')); b.type = 'button';
                var fi = icon('bi-folder-symlink-fill'); if (/^#[0-9a-f]{6}$/i.test(sf.color || '')) fi.style.color = sf.color;
                var w = h('span', 'sp-w'); var n2 = h('b', null, sf.name); n2.dir = 'auto';
                w.append(n2, h('small', null, t('sh_by', { name: sf.owner })));
                b.append(fi, w);
                b.title = sf.name;
                b.addEventListener('click', function () { if (!on) openSpace(sf.uuid, b); });
                box.appendChild(b);
            });
            if (!DESK) box.classList.add('nb-home-only');
            after.parentNode.insertBefore(box, after.nextSibling); after = box;
        }

        // how full my notebook is: a donut that reads as a gauge
        if (S.storage) {
            var used = S.storage.used, lim = S.storage.limit, pct = Math.min(1, used / Math.max(1, lim));
            var st = h('section', 'st-box' + (pct >= 0.95 ? ' is-full' : pct >= 0.8 ? ' is-high' : '')); st.id = 'storage-box';
            st.setAttribute('role', 'meter'); st.setAttribute('aria-valuemin', '0'); st.setAttribute('aria-valuemax', String(lim)); st.setAttribute('aria-valuenow', String(used));
            st.setAttribute('aria-label', T.st_title);
            var R = 15.5, C = 2 * Math.PI * R, arc = Math.max(pct > 0 ? 1.5 : 0, C * pct);
            var ring = '<svg viewBox="0 0 40 40" width="46" height="46" aria-hidden="true"><circle cx="20" cy="20" r="' + R + '" fill="none" stroke="currentColor" stroke-opacity=".14" stroke-width="5"/>'
                + '<circle class="st-arc" cx="20" cy="20" r="' + R + '" fill="none" stroke-width="5" stroke-linecap="round" transform="rotate(-90 20 20)" stroke-dasharray="' + arc.toFixed(2) + ' ' + C.toFixed(2) + '"/></svg>';
            var dial = h('span', 'st-dial'); dial.innerHTML = ring; // our own numbers, never data
            dial.appendChild(h('em', null, (pct > 0 && pct < 0.01 ? '<1' : Math.round(pct * 100)) + '%'));
            var words = h('span', 'st-w');
            words.append(h('b', null, T.st_title), h('span', null, t('st_used', { used: size(used), limit: size(lim) })), h('small', null, t('st_left', { left: size(Math.max(0, lim - used)) })));
            st.append(dial, words);
            if (!DESK) st.classList.add('nb-home-only');
            after.parentNode.insertBefore(st, after.nextSibling);
        }
    }
    function size(b) {
        if (b >= 1000 * 1048576) return (b / 1073741824).toFixed(1).replace(/\.0$/, '') + ' GB'; // 1019 MB reads as 1 GB
        if (b >= 1048576) return (b / 1048576).toFixed(b >= 10485760 ? 0 : 1).replace(/\.0$/, '') + ' MB';
        return Math.max(0, Math.round(b / 1024)) + ' KB';
    }
    /* Opening a shared folder (or going home): the server remembers it for this session, the page starts over in it. */
    function openSpace(uuid, btn) {
        if (btn) btn.disabled = true;
        api('POST', U.space, { share: uuid }).then(function () { location.reload(); })
            .catch(function (err) { if (btn) btn.disabled = false; fail(err); });
    }

    /* "Share…" — who has this folder; share it by email; take it back. */
    function shareSheet(f) {
        var root = sheetShell(t('sh_title', { folder: f.name }), T.sh_sub, 'bi-folder-symlink-fill', f.color);
        var form = h('form', 'fr-form');
        var inp = h('input', 'fo-in'); inp.type = 'email'; inp.required = true; inp.maxLength = 190; inp.autocomplete = 'off';
        inp.placeholder = T.sh_email; inp.setAttribute('aria-label', T.sh_email); inp.setAttribute('enterkeyhint', 'send');
        var go = h('button', 'ss-go fr-go', T.sh_go); go.type = 'submit';
        form.append(inp, go);
        var list = h('div', 'fr-list');
        root.body.append(form, h('h4', null, T.sh_people), list);
        setTimeout(function () { inp.focus(); }, 60);

        function paint(shares) {
            f.shared = shares.length; drawFolders();
            if (!shares.length) { list.replaceChildren(h('p', 'fr-none', T.sh_none)); return; }
            list.replaceChildren.apply(list, shares.map(function (sh) {
                var row = h('div', 'fr-row');
                var title = sh.name || sh.email;
                var face = h('span', 'fr-face', title.trim().charAt(0).toUpperCase());
                var who = h('div', 'fr-who');
                var tb = h('b', null, title); tb.dir = 'auto';
                who.append(tb, h('span', 'fr-state' + (sh.joined ? ' is-in' : ''), sh.joined ? T.sh_joined : T.sh_waiting));
                if (sh.name) who.appendChild(h('small', null, sh.email));
                var rm = h('button', 'fr-rm', T.sh_remove); rm.type = 'button';
                rm.addEventListener('click', function () {
                    if (!rm.classList.contains('is-sure')) { rm.classList.add('is-sure'); rm.textContent = T.sh_remove_sure; return; }
                    rm.disabled = true;
                    api('DELETE', U.share.replace(ID0, f.uuid).replace(ID1S, sh.uuid)).then(function (r) { paint(r.shares); })
                        .catch(function (err) { rm.disabled = false; fail(err); });
                });
                row.append(face, who, rm);
                return row;
            }));
        }
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var email = inp.value.trim(); if (!email || go.disabled) return;
            go.disabled = true;
            api('POST', url('shares', f.uuid), { email: email }).then(function (r) {
                inp.value = ''; toast(r.message); paint(r.shares);
            }).catch(function (err) {
                if (err && err.status === 422 && err.data && err.data.message) toast(err.data.message, { error: true }); else fail(err);
            }).finally(function () { go.disabled = false; });
        });
        list.appendChild(h('div', 'tile is-skeleton'));
        api('GET', url('shares', f.uuid)).then(function (r) { paint(r.shares); }).catch(fail);
    }
    var ID1S = '00000000-0000-0000-0000-000000000001';
