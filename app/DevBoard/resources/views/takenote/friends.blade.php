{{--
    Friends and language — included INSIDE takenote/script's closure (after
    folders, for sheetShell).

    Friends: the owner invites people by email; each one gets a TakeNote of
    their own (Support\Notebooks — nobody sees anybody else's notes). Only the
    owner's notebook sees the button ([data-friends], C.canInvite) and the
    server re-checks every request. An invitation waits until a verified
    account with that email opens TakeNote; the owner shares the link.

    Language: TakeNote's own six (config devboard.locales), chosen per device
    — the server sets a cookie and the page comes back in that language.
--}}
    /* ── friends & language ──────────────────────────────────────────── */
    document.querySelectorAll('[data-lang]').forEach(function (b) { b.addEventListener('click', langSheet); });
    document.querySelectorAll('[data-friends]').forEach(function (b) {
        if (!C.canInvite || !U.friends) { b.remove(); return; }
        b.addEventListener('click', friendsSheet);
    });

    function langSheet() {
        var root = sheetShell(T.lg_title, T.lg_sub, 'bi-translate', '#7c6bf5');
        var list = h('div', 'fo-pick');
        (C.langs || []).forEach(function (l) {
            var on = l.code === C.lang;
            var b = h('button', 'fo-opt' + (on ? ' is-on' : '')); b.type = 'button';
            b.lang = l.code.replace('_', '-');
            b.append(icon(on ? 'bi-check-circle-fill' : 'bi-circle'), document.createTextNode(l.name));
            b.addEventListener('click', function () {
                if (on) { root.close(); return; }
                b.disabled = true;
                api('POST', U.locale, { locale: l.code }).then(function () {
                    // an open note saves as it closes; then the page comes back in the new language
                    var wait = !!P;
                    if (P) P.page.close();
                    setTimeout(function () { location.reload(); }, wait ? 700 : 0);
                }).catch(function (err) { b.disabled = false; fail(err); });
            });
            list.appendChild(b);
        });
        root.body.appendChild(list);
    }

    function friendsSheet() {
        var root = sheetShell(T.fr_title, T.fr_sub, 'bi-people-fill', '#16a394');
        var form = h('form', 'fr-form');
        var inp = h('input', 'fo-in'); inp.type = 'email'; inp.required = true; inp.maxLength = 190; inp.autocomplete = 'off';
        inp.placeholder = T.fr_email; inp.setAttribute('aria-label', T.fr_email); inp.setAttribute('enterkeyhint', 'send');
        var go = h('button', 'ss-go fr-go', T.fr_invite); go.type = 'submit';
        form.append(inp, go);

        // a LINK first (the owner's way: send it on WhatsApp) — no email needed; whoever opens it first gets their own notebook, once
        var lk = h('div', 'fr-lk');
        var lkForm = h('form', 'fr-form');
        var lkName = h('input', 'fo-in'); lkName.type = 'text'; lkName.maxLength = 80; lkName.placeholder = T.lk_label; lkName.setAttribute('aria-label', T.lk_label);
        var lkGo = h('button', 'ss-go fr-go', T.lk_make); lkGo.type = 'submit';
        lkForm.append(lkName, lkGo);
        var lkOut = h('div', 'fr-out'); lkOut.hidden = true;
        lk.append(h('h4', null, T.lk_title), h('p', null, T.lk_hint), lkForm, lkOut);
        lkForm.addEventListener('submit', function (e) {
            e.preventDefault();
            lkGo.disabled = true;
            api('POST', U.friendLink, { label: lkName.value.trim() || null }).then(function (r) {
                lkName.value = ''; toast(r.message); paint(r.friends);
                var url = h('span', 'fr-url', r.link);
                var copy = h('button', 'fr-btn'); copy.type = 'button'; copy.append(icon('bi-copy'), document.createTextNode(T.fr_copy));
                copy.addEventListener('click', function () { if (navigator.clipboard) navigator.clipboard.writeText(r.link).then(function () { toast(T.fr_copied); }).catch(function () {}); });
                var acts = h('div', 'fr-acts'); acts.appendChild(copy);
                if (navigator.share) {
                    var share = h('button', 'fr-btn is-main'); share.type = 'button'; share.append(icon('bi-share'), document.createTextNode(T.lk_share));
                    share.addEventListener('click', function () { navigator.share({ title: 'TakeNote', text: T.lk_share_text, url: r.link }).catch(function () {}); });
                    acts.appendChild(share);
                }
                lkOut.replaceChildren(url, acts); lkOut.hidden = false;
            }).catch(function (err) {
                if (err && err.status === 422 && err.data && err.data.message) toast(err.data.message, { error: true });
                else fail(err);
            }).finally(function () { lkGo.disabled = false; });
        });

        var list = h('div', 'fr-list');
        // or by email: we send the invitation; they sign in (or sign up) with that address
        var byMail = h('div', 'fr-mail');
        byMail.append(h('h4', null, T.fr_by_email), h('p', null, T.fr_by_email_hint), form);
        root.body.append(lk, byMail, list);
        setTimeout(function () { lkName.focus(); }, 60);

        function paint(friends) {
            if (!friends.length) { list.replaceChildren(h('p', 'fr-none', T.fr_none)); return; }
            list.replaceChildren.apply(list, friends.map(function (f) {
                var row = h('div', 'fr-row');
                var title = f.name || f.email || f.label || T.lk_unnamed;
                var face = h('span', 'fr-face' + (f.link && !f.joined ? ' is-link' : ''), f.link && !f.joined ? '' : title.trim().charAt(0).toUpperCase());
                if (f.link && !f.joined) face.appendChild(icon('bi-link-45deg'));
                var who = h('div', 'fr-who');
                who.append(h('b', null, title), h('span', 'fr-state' + (f.joined ? ' is-in' : ''), f.joined ? T.fr_joined : T.fr_waiting));
                var sub = f.name ? (f.email || (f.label && f.label !== f.name ? f.label : '')) : (f.link && f.label ? T.lk_unnamed : '');
                if (sub) who.appendChild(h('small', null, sub));
                var rm = h('button', 'fr-rm', T.fr_remove); rm.type = 'button';
                rm.addEventListener('click', function () {
                    if (!rm.classList.contains('is-sure')) { rm.classList.add('is-sure'); rm.textContent = T.fr_remove_sure; return; }
                    rm.disabled = true;
                    api('DELETE', U.friend.replace(ID0, f.uuid)).then(function (r) { toast(r.message); paint(r.friends); })
                        .catch(function (err) { rm.disabled = false; fail(err); });
                });
                row.append(face, who, rm);
                return row;
            }));
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var email = inp.value.trim();
            if (!email) return;
            go.disabled = true;
            api('POST', U.friends, { email: email }).then(function (r) {
                inp.value = ''; toast(r.message); paint(r.friends);
            }).catch(function (err) {
                // the server says why (already invited, already in your notebook…) — show its words
                if (err && err.status === 422 && err.data && err.data.message) toast(err.data.message, { error: true });
                else fail(err);
            }).finally(function () { go.disabled = false; });
        });

        list.appendChild(h('div', 'tile is-skeleton'));
        api('GET', U.friends).then(function (r) { paint(r.friends); }).catch(fail);
    }

