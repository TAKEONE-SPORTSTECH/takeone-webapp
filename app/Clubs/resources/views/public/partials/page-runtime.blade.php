{{--
    The club front page's behaviour, registered ONCE as Alpine.data('clubPage') so
    the desktop and the mobile file run the same code (see
    reference: Alpine state must be registered, not inline).

    Reads its data from <script type="application/json" id="cp-json">, written by
    the page. Every write goes to an endpoint that authorises on its own:
    clubs.timeline.like / .comment / .comment.delete and clubs.perks.collect.
    Everything a reader typed or a server returned is drawn with x-text, never
    innerHTML.
--}}
@once
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    var register = function () {
        if (!window.Alpine || window.__clubPageRegistered) return;
        window.__clubPageRegistered = true;

        window.Alpine.data('clubPage', function () {
            var raw = document.getElementById('cp-json');
            var data = {};
            try { data = JSON.parse(raw ? raw.textContent : '{}'); } catch (e) { data = {}; }

            return {
                d: data,
                dark: false,
                cat: 'all',
                eligibleOnly: false,
                shopCat: 'all',
                day: data.today || 'monday',
                tab: 'packages',
                ach: null, achImg: 0,
                post: null, draft: '', sending: false,
                newsAll: false,
                gIndex: -1,
                video: false,
                stats: false,
                perk: { open: false, view: 'loading', url: '', title: '', value: '', message: '', collectedAt: '', members: [], copied: false },

                init() {
                    try { this.dark = localStorage.getItem('cp-dark') === '1'; } catch (e) {}
                    var self = this;
                    this._esc = function (e) { if (e.key === 'Escape') self.closeAll(); if (self.gIndex > -1) { if (e.key === 'ArrowRight') self.step(1); if (e.key === 'ArrowLeft') self.step(-1); } };
                    window.addEventListener('keydown', this._esc);
                },
                destroy() { window.removeEventListener('keydown', this._esc); },

                toggleDark() {
                    this.dark = !this.dark;
                    try { localStorage.setItem('cp-dark', this.dark ? '1' : '0'); } catch (e) {}
                },
                go(id) {
                    var el = document.getElementById(id);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    this.tab = id;
                },
                pick(cat) { this.cat = cat; this.go('packages'); },
                shows(cats, applicable) {
                    if (this.eligibleOnly && !applicable) return false;
                    return this.cat === 'all' || (cats || []).indexOf(this.cat) !== -1;
                },
                lock(on) { document.body.style.overflow = on ? 'hidden' : ''; },
                closeAll() {
                    this.ach = null; this.post = null; this.newsAll = false; this.gIndex = -1; this.video = false; this.stats = false; this.perk.open = false;
                    this.lock(false);
                },

                // ---- joining: the club's own enrol flow (partials/_join-flow, join-club-modal) ----
                join() {
                    if (!this.d.auth) { window.location.href = this.d.registerUrl; return; }
                    if (window._joinModal && window._joinModal.show) window._joinModal.show();
                },
                select(id) {
                    if (!this.d.auth) { window.location.href = this.d.registerUrl; return; }
                    if (typeof window.openSelectPackageModal === 'function') window.openSelectPackageModal(id);
                },

                share() {
                    var url = this.d.pageUrl || window.location.href;
                    if (navigator.share) { navigator.share({ title: this.d.name, url: url }).catch(function () {}); return; }
                    if (navigator.clipboard) navigator.clipboard.writeText(url).then(function () { if (window.showToast) window.showToast('success', data.i18n.copied); });
                },

                // ---- achievements ----
                openAch(i) { this.ach = this.d.achievements[i] || null; this.achImg = 0; this.lock(!!this.ach); },

                // ---- news ----
                openPost(i) { this.post = this.d.posts[i] || null; this.draft = ''; this.newsAll = false; this.lock(!!this.post); },
                openNews() { this.newsAll = true; this.lock(true); },
                like(p) {
                    if (!p.like_url) { window.location.href = this.d.loginUrl; return; }
                    var self = this;
                    fetch(p.like_url, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.d.csrf, 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (res) { p.liked = !!res.liked; p.likes = res.count; })
                        .catch(function () { if (window.showToast) window.showToast('error', self.d.i18n.error); });
                },
                comment(p) {
                    var body = (this.draft || '').trim();
                    if (!body || this.sending) return;
                    if (!p.comment_url) { window.location.href = this.d.loginUrl; return; }
                    var self = this; this.sending = true;
                    fetch(p.comment_url, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.d.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ body: body }) })
                        .then(function (r) { if (!r.ok) throw r; return r.json(); })
                        .then(function (c) { p.comments.push(c); self.draft = ''; })
                        .catch(function () { if (window.showToast) window.showToast('error', self.d.i18n.error); })
                        .finally(function () { self.sending = false; });
                },
                uncomment(p, c) {
                    if (!c.delete_url) return;
                    var self = this;
                    fetch(c.delete_url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': this.d.csrf, 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (res) { if (res.success) p.comments = p.comments.filter(function (x) { return x.id !== c.id; }); })
                        .catch(function () { if (window.showToast) window.showToast('error', self.d.i18n.error); });
                },

                // ---- gallery ----
                open(i) { this.gIndex = i; this.lock(true); },
                step(n) { var len = this.d.gallery.length; this.gIndex = (this.gIndex + n + len) % len; },
                openVideo() { this.video = true; this.lock(true); },

                // ---- club in numbers (desktop) ----
                openStats() { this.stats = true; this.lock(true); this.$nextTick(function () { if (window.cpInitStats) window.cpInitStats(); }); },

                // ---- perks: clubs.perks.collect decides members-only, picker and once-only ----
                collect(url) {
                    if (!this.d.auth) { window.location.href = this.d.loginUrl; return; }
                    this.perk.url = url; this.perk.copied = false; this.perk.open = true; this.lock(true);
                    this.collectFor(null);
                },
                collectFor(forId) {
                    var self = this, p = this.perk;
                    p.view = 'loading';
                    fetch(p.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.d.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                                   body: forId ? JSON.stringify({ for_user_id: forId }) : null })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            if (res.members_only) { p.message = res.message || ''; p.view = 'members_only'; return; }
                            if (res.already_collected) { p.collectedAt = res.collected_at || ''; p.view = 'already'; return; }
                            if (res.requires_selection) { p.members = res.members || []; p.view = 'picker'; return; }
                            if (!res.success) { p.message = res.message || ''; p.view = 'error'; return; }
                            p.title = res.title; p.value = res.perk_value;
                            p.view = res.perk_type === 'code' ? 'code' : 'qr';
                            if (p.view === 'qr') self.$nextTick(function () { self.drawQr(); });
                        })
                        .catch(function () { p.view = 'error'; });
                },
                drawQr() {
                    var el = this.$refs.perkQr;
                    if (!el || typeof QRCode === 'undefined') return;
                    el.innerHTML = '';
                    new QRCode(el, { text: this.perk.value, width: 180, height: 180, correctLevel: QRCode.CorrectLevel.M });
                },
                copyCode() {
                    var p = this.perk;
                    if (navigator.clipboard) navigator.clipboard.writeText(p.value).then(function () { p.copied = true; setTimeout(function () { p.copied = false; }, 2000); });
                },
            };
        });
    };
    if (window.Alpine) { register(); }
    document.addEventListener('alpine:init', register);
})();
</script>
@endpush
@endonce
