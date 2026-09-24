<?php

namespace App\Federations\Services;

use RuntimeException;

/**
 * The federation dashboard, served EXACTLY as it was designed.
 *
 * The design arrived as a working prototype ("Sports events management
 * dashboard.zip") and the decision was to port it as-is rather than re-draw it:
 * its files live, byte-for-byte, in app/Federations/resources/app/. Nothing in
 * that folder is edited by hand. Everything the platform has to change about
 * them happens HERE, at serve time, as a list of named rewrites — so a new drop
 * of the prototype is a file copy, and every rewrite that no longer matches
 * fails loudly instead of silently shipping the old behaviour.
 *
 * Two kinds of rewrite:
 *  - SELF-HOST: every CDN the prototype loads (React, Leaflet, pdf.js, mammoth,
 *    SheetJS, Google Fonts, flagcdn) points at a vendored copy under
 *    resources/app/vendor/ instead (Self-Host Everything, CLAUDE.md Part 5).
 *  - PLATFORM: the few places the prototype does something the platform does
 *    for real — sign-in, 2FA, card payments — are handed to the platform.
 */
class FederationApp
{
    /** Files a signed-in federation staff member may open. */
    public const STAFF_PAGES = [
        'Events Dashboard.dc.html',
        'Karate Statistics.dc.html',
    ];

    /** The public invitation a club opens from a link. */
    public const PUBLIC_PAGES = [
        'Club Invitation.dc.html',
    ];

    /** Static files — code, fonts, images. Public: they hold no data. */
    private const ASSET_TYPES = [
        'js' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'woff2' => 'font/woff2',
        'png' => 'image/png',
        'json' => 'application/json',
    ];

    public function root(): string
    {
        return dirname(__DIR__).'/resources/app';
    }

    public function isStaffPage(string $file): bool
    {
        return in_array($file, self::STAFF_PAGES, true);
    }

    public function isPublicPage(string $file): bool
    {
        return in_array($file, self::PUBLIC_PAGES, true);
    }

    /**
     * Resolve a static asset to an absolute path, or null. Only files that
     * really sit under resources/app with a whitelisted extension; never a
     * page, never a path that climbs out.
     */
    public function assetPath(string $file): ?string
    {
        if (! preg_match('#^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+){0,2}$#', $file) || str_contains($file, '..')) {
            return null;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        // The one JSON file: image-slot.js's sidecar holding the invitation's
        // hero artwork. Nothing else in the folder is JSON, and nothing else
        // with a leading dot is served.
        if ($file === '.image-slots.state.json') {
            $ext = 'json';
        } elseif (str_starts_with(basename($file), '.') || ! isset(self::ASSET_TYPES[$ext]) || $ext === 'json') {
            return null;
        }

        $root = realpath($this->root());
        $path = realpath($this->root().'/'.$file);

        return ($root && $path && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path)) ? $path : null;
    }

    public function contentType(string $path): string
    {
        return self::ASSET_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))];
    }

    /** A static asset's body — JS gets the self-host rewrites too. */
    public function asset(string $path): string
    {
        $body = (string) file_get_contents($path);

        // The image-slot sidecar holds the SAMPLE event's artwork. A real
        // invitation draws its own event's artwork (the slot's `src`), so the
        // sidecar it reads is empty.
        if (basename($path) === '.image-slots.state.json') {
            return '{}';
        }

        if (basename($path) === 'support.js') {
            $body = $this->apply($body, $this->supportRewrites(), 'support.js');
        }

        return $body;
    }

    /**
     * A page, ready to send: the prototype's own file, with the rewrites
     * applied and the platform bootstrap placed before its runtime loads.
     */
    public function page(string $file, array $boot, ?string $base = null, string $head = ''): string
    {
        $html = (string) file_get_contents($this->root().'/'.$file);
        $html = $this->apply($html, $this->pageRewrites($file), $file);

        // Served at a clean address (/federation) rather than its file name:
        // the base keeps every relative load and sibling fetch resolving
        // against the file's real folder, and names the page for the runtime.
        if ($base !== null) {
            $html = $this->apply($html, ['base' => ['<head>', '<head>'."\n".'<base href="'.e($base).'">', 1]], $file);
        }

        $json = json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $inject = '<meta name="csrf-token" content="'.e($boot['csrf'] ?? '').'">'."\n"
            .(isset($boot['favicon']) ? '<link rel="icon" href="'.e($boot['favicon']).'">'."\n" : '')
            .'<script>window.__TAKEONE_FED = '.$json.';</script>'."\n"
            .'<script src="./takeone-bridge.js"></script>'."\n".$head;

        return $this->apply($html, [
            'bootstrap' => ['<script src="./support.js"></script>', $inject.'<script src="./support.js"></script>', 1],
        ], $file);
    }

    /**
     * @param  array<string, array{0:string|array{0:string,1:string},1:string,2:int|null}>  $rules  name => [search (or [from, to] span), replace, expected count|null=any>0]
     */
    private function apply(string $body, array $rules, string $file): string
    {
        foreach ($rules as $name => [$search, $replace, $expected]) {
            // A span: [start marker, end marker] — everything from the start
            // of one to the end of the other (a whole demo data block).
            if (is_array($search)) {
                [$from, $to] = $search;
                $i = strpos($body, $from);
                $j = $i === false ? false : strpos($body, $to, $i + strlen($from));
                if ($i === false || $j === false || substr_count($body, $from) !== 1) {
                    throw new RuntimeException("Federation app span rewrite '{$name}' did not match exactly once in {$file}.");
                }
                $body = substr($body, 0, $i).$replace.substr($body, $j + strlen($to));

                continue;
            }
            $count = substr_count($body, $search);
            if ($count === 0 || ($expected !== null && $count !== $expected)) {
                // The prototype changed underneath us. Refuse to serve a page
                // that would quietly load a CDN or skip a platform hand-off.
                throw new RuntimeException("Federation app rewrite '{$name}' expected ".($expected ?? '≥1')." match(es) in {$file}, found {$count}.");
            }
            $body = str_replace($search, $replace, $body);
        }

        return $body;
    }

    private function supportRewrites(): array
    {
        return [
            'react' => ['https://unpkg.com/react@18.3.1/umd/react.production.min.js', './vendor/react.production.min.js', 1],
            'react-dom' => ['https://unpkg.com/react-dom@18.3.1/umd/react-dom.production.min.js', './vendor/react-dom.production.min.js', 1],
            'babel' => ['https://unpkg.com/@babel/standalone@7.29.0/babel.min.js', './vendor/babel.min.js', 1],
        ];
    }

    private function pageRewrites(string $file): array
    {
        $flags = ['flags' => ['https://flagcdn.com/w40/', '/federation/app/flags/', null]];

        return match ($file) {
            'Events Dashboard.dc.html' => $flags + [
                'font' => ['https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap', './vendor/fonts/poppins.css', 1],
                'leaflet-css' => ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', './vendor/leaflet/leaflet.css', 1],
                'leaflet-js' => ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', './vendor/leaflet/leaflet.js', 1],
                'mammoth' => ['https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js', '/federation/app/vendor/mammoth.browser.min.js', 1],
                'pdfjs' => ['https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js', '/federation/app/vendor/pdf.min.js', 1],
                'pdfjs-worker' => ['https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js', '/federation/app/vendor/pdf.worker.min.js', 1],
                'sheetjs' => ['https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js', '/federation/app/vendor/xlsx.full.min.js', 1],

                // PLATFORM: signing out ends the platform session (a CSRF POST).
                'logout' => ['doLogout: () => { try {', 'doLogout: () => { if (window.TakeOneFed) return window.TakeOneFed.logout(); try {', 1],
                // PLATFORM: 2FA is the platform's own (server-verified TOTP),
                // not the prototype's browser-side secret.
                'security' => ['openSecurity: () => this.setState({securityOpen: true,', 'openSecurity: () => (window.TakeOneFed && window.TakeOneFed.security()) || this.setState({securityOpen: true,', 1],
                // PLATFORM: no payment gateway (deferred) — the subscription is
                // paid by bank transfer and confirmed by hand, so no card form.
                'pay-bank-only' => ["const payMethod = s.payMethod || 'card';", "const payMethod = 'bank';", 1],
                'pay-methods' => ["methodOpts: [['card', 'Card'], ['bank', 'Bank transfer']]", "methodOpts: [['bank', 'Bank transfer']]", 1],
                'pay-no-card' => ['No card saved. You can add one when you pay.', 'Subscriptions are paid by bank transfer.', 1],
                // PLATFORM: a new body's id is a uuid (Unpredictable Resource
                // Identifiers), which the server keeps as its public id.
                'org-id' => ["{id: 'o-' + Date.now().toString(36), name: ad.name.trim()", "{id: window.TakeOneFed.newId(), name: ad.name.trim()", 1],
                // PLATFORM: staff are invited by email to set their own
                // password — nobody is handed a shared temporary one.
                'staff-no-pass' => ["{email: em, pass: 'Welcome@2026', name: ad.name.trim()", '{email: em, name: ad.name.trim()', 1],
                'staff-msg' => ["staffMsg: 'Invitation sent to ' + em + '. Temporary password: Welcome@2026'", "staffMsg: 'Invitation sent to ' + em + '. They choose their own password from the email.'", 1],
                // PLATFORM: events are real records. The demo list and the
                // demo registrations go; the server's events come in.
                'events-data' => [["    events: [\n      {id:1, name:'Gulf Karate Open 2026'", "    ],\n    regs: ["], "    events: window.TakeOneFed.events(),\n    regs: [", 1],
                'regs-data' => [["    regs: [\n      {id:1, name:'Yusuf Al Doseri'", "\n    ]\n  };"], "    regs: []\n  };", 1],
                // With no events yet the page would read fields off `undefined`.
                'event-sel' => ['const sel = s.events.find(e => e.id === s.selectedId) || s.events[0];', 'const sel = s.events.find(e => e.id === s.selectedId) || s.events[0] || window.TakeOneFed.blankEvent();', 1],
                'event-id' => ["const target = s.eMode === 'create' ? {id: Date.now(), reg: 0, checkins: 0, divisions: 0} : null;", "const target = s.eMode === 'create' ? {id: window.TakeOneFed.newId(), reg: 0, checkins: 0, divisions: 0} : null;", 1],
                'event-save' => ["        this.setState({events: target ? [apply(target), ...s.events] : s.events.map(e => e.id === sel.id ? apply(e) : e),\n          editOpen: false, eMode: null, filter: target ? 'All' : s.filter});", "        const __ev = target ? apply(target) : apply(sel); window.TakeOneFed.saveEvent(__ev, this);\n        this.setState({events: target ? [__ev, ...s.events] : s.events.map(e => e.id === sel.id ? __ev : e),\n          editOpen: false, eMode: null, filter: target ? 'All' : s.filter});", 1],
                'event-quick-id' => ['const ev = {id: Date.now(), name: s.fName.trim(), sport: s.fSport,', 'const ev = {id: window.TakeOneFed.newId(), name: s.fName.trim(), sport: s.fSport,', 1],
                'event-quick-save' => ["        this.setState({events: [ev, ...s.events], createOpen: false, filter: 'All', fName: '', fVenue: '', fDate: ''});", "        window.TakeOneFed.saveEvent(ev, this);\n        this.setState({events: [ev, ...s.events], createOpen: false, filter: 'All', fName: '', fVenue: '', fDate: ''});", 1],
                // Documents upload as they are picked; the event save attaches them.
                'event-docs' => ['      url: URL.createObjectURL(f)}));', '      url: window.TakeOneFed.stageDoc(f)}));', 1],
                // Registrations are per event: read the open event's, again when another opens.
                'regs-load' => ["      const ext = JSON.parse(localStorage.getItem('takeone_club_regs') || '[]');\n      const rows = [];", "      const ext = window.TakeOneFed.clubRegs(this.state.selectedId || ((this.state.events || [])[0] || {}).id);\n      const rows = [];", 1],
                'regs-open-1' => ["onOpen: () => this.setState({nav: 'events', view: 'detail', selectedId: ev.id, tab: 'overview'})", "onOpen: () => this.setState({nav: 'events', view: 'detail', selectedId: ev.id, tab: 'overview'}, () => this.loadClubRegs())", 1],
                'regs-open-2' => ["onOpen: () => this.setState({view: 'detail', selectedId: ev.id, tab: 'overview'})", "onOpen: () => this.setState({view: 'detail', selectedId: ev.id, tab: 'overview'}, () => this.loadClubRegs())", 1],
                // PLATFORM: the store is the server's (same function names).
                'store' => ['<script src="./federation-store.js"></script>', '<script src="./federation-store.platform.js"></script>', 1],
                // PLATFORM: an invitation is to ONE event, and a club's personal
                // link is its secret token — never its id.
                'invite-copy' => ["const url = new URL('Club Invitation.dc.html', location.href).href;", "const url = new URL('Club Invitation.dc.html?event=' + encodeURIComponent(sel.id || ''), document.baseURI).href;", 1],
                'invite-url' => ["      inviteUrl: 'takeone.events/invite/' + sel.name.toLowerCase().replace(/[^a-z0-9]+/g, '-'),", "      inviteUrl: new URL('Club Invitation.dc.html?event=' + encodeURIComponent(sel.id || ''), document.baseURI).href, inviteHref: 'Club Invitation.dc.html?event=' + encodeURIComponent(sel.id || ''),", 1],
                'invite-open' => ['<a href="Club Invitation.dc.html" style=', '<a href="{{ inviteHref }}" style=', 1],
                // The page lives at /federation; its siblings sit beside the file, not the address.
                'invite-origin' => ["const origin = (() => { try { return new URL('Club Invitation.dc.html', location.href).href;", "const origin = (() => { try { return new URL('Club Invitation.dc.html', document.baseURI).href;", 1],
                'club-link-href' => ["href: origin + '?club=' + encodeURIComponent(c.id), copyLabel", "href: window.TakeOneFed.clubLink(origin, c.id), copyLabel", 1],
                'club-link-copy' => ["onCopy: () => { const u = origin + '?club=' + encodeURIComponent(c.id);", "onCopy: () => { const u = window.TakeOneFed.clubLink(origin, c.id);", 1],
                // PLATFORM: a submitted athlete carries their real Takeone ID,
                // national ID and review state, and Approve is saved.
                'reg-ids' => ["nat: a.nat || '—', natId: '—', takeoneId: 'Pending', dob: a.dob, coach: sub.coach || ''});", "nat: a.nat || '—', natId: a.natId || '—', takeoneId: a.takeoneId || 'Pending', entryId: sub.entryId, dob: a.dob, coach: sub.coach || ''});", 1],
                'reg-status' => ["division: this.weightClassOf(g, ag, kg), status: 'Pending', mobile: sub.phone || '—', email: sub.email || '—',", "division: this.weightClassOf(g, ag, kg), status: a.approval === 'Approved' ? 'Approved' : 'Pending', mobile: sub.phone || '—', email: sub.email || '—',", 1],
                'reg-approve' => ["onApprove: () => this.setState({regs: s.regs.map(x => x.id === r.id ? {...x, status: 'Approved'} : x)})", "onApprove: () => { window.TakeOneFed.approveReg(r); this.setState({regs: s.regs.map(x => x.id === r.id ? {...x, status: 'Approved'} : x)}); }", 1],
                // PLATFORM: the Athletes page lists real athletes, not its sample.
                'athletes-data' => [["    const athData = [\n      {name:'Yusuf Al Doseri'", "\n    ];\n    const q = s.athQuery"], "    const athData = window.TakeOneFed.athleteDirectory();\n    const q = s.athQuery", 1],
                'athletes-dedupe' => ["    (s.regs || []).filter(r => String(r.id).indexOf('ext-') === 0).forEach(r => {\n      athData.push({", "    (s.regs || []).filter(r => String(r.id).indexOf('ext-') === 0).forEach(r => {\n      if (!athData.some(x => x.tk === r.takeoneId)) athData.push({", 1],
                // PLATFORM: the officials clubs actually named, not the sample list.
                'officials-data' => [["const offData = [\n      ['Hassan Al Khalifa'", "\n    ];\n    const officials = offData.map("], "const offData = window.TakeOneFed.officials();\n    const officials = offData.map(", 1],
                // PLATFORM: no figure without a record behind it (PLATFORM_SPEC §9).
                'fin-payouts' => ['<span style="color:#6b7689">Outstanding payouts</span><span style="font-weight:700">BD 14,200</span>', '<span style="color:#6b7689">Outstanding payouts</span><span style="font-weight:700">—</span>', 1],
                'fin-refunds' => ['<span style="color:#6b7689">Pending refunds</span><span style="font-weight:700">BD 1,050</span>', '<span style="color:#6b7689">Pending refunds</span><span style="font-weight:700">—</span>', 1],
                // PLATFORM: the bank reference is unique across every
                // federation, so a transfer can be matched to its payer.
                'invoice-no' => ["const nextInvNo = 'INV-' + new Date().getFullYear() + '-' + String((b.invoices || []).length + 1).padStart(4, '0');", 'const nextInvNo = window.TakeOneFed.invoiceNo(acc.id, (b.invoices || []).length + 1);', 1],
                // PLATFORM: the draw and its results are kept per event and
                // division (FederationDraws). The first result locks the
                // bracket; the server's copy is what the tab reads.
                'draw-slots' => ['const slots = this.fairDraw(entrants);', 'const slots = window.TakeOneFed.drawSlots(this._drawKey, entrants, () => this.fairDraw(entrants));', 1],
                'draw-results' => ['const divResults = (s.results || {})[divKey] || {};', 'this._drawKey = divKey; const divResults = window.TakeOneFed.divResults(divKey, entrants);', 1],
                'draw-pick' => ['const pickFn = (id, a, b, winner) => this.setState({resultPick: {id, a, b, winner, divKey}});', 'const pickFn = (id, a, b, winner, ids) => this.setState({resultPick: {id, a, b, winner, divKey, ids}});', 1],
                'draw-pick-row' => ['pickFn(id, a.name, b.name, p.name)', 'pickFn(id, a.name, b.name, p.name, [a.takeoneId, b.takeoneId])', 1],
                'draw-pick-card' => ['pickFn(id, a.name, b.name, null)', 'pickFn(id, a.name, b.name, null, [a.takeoneId, b.takeoneId])', 1],
                'draw-save' => ['all[pk.divKey] = {...(all[pk.divKey] || {}), [pk.id]: {winner: pk.winner, by}};', 'all[pk.divKey] = {...(all[pk.divKey] || {}), [pk.id]: {winner: pk.winner, by}}; window.TakeOneFed.saveResult(pk, by, this);', 1],
                'draw-clear' => ['delete dv[pk.id];', 'delete dv[pk.id]; window.TakeOneFed.saveResult(pk, null, this);', 1],
                // PLATFORM: messages go to the club admins' own Messenger
                // (FederationMessages). The thread list is the server's, read
                // from storage on every draw so a live refresh shows at once;
                // only clubs on the platform can be written to.
                'msg-read' => ["const msgs = s.msgs || this.loadJSON('fed-messages', {});", "const msgs = this.loadJSON('fed-messages', {});", 1],
                'msg-send' => ["saveMsgs({...msgs, [club]: [...(msgs[club] || []), {from: 'fed', text: t, ts: Date.now()}]});", "saveMsgs({...msgs, [club]: [...(msgs[club] || []), {from: 'fed', text: t, ts: Date.now()}]}); window.TakeOneFed.sendMessage(club, t);", 1],
                'msg-clubs' => [".filter(c => c && c !== '\\u2014'))].sort();", ".filter(c => c && c !== '\\u2014' && window.TakeOneFed.canMessage(c)))].sort();", 1],
                // PLATFORM: TakeOne's real account (config federations.takeone_bank).
                'tk-bank-vals' => ["      bankRef: nextInvNo,", "      bankRef: nextInvNo, ...window.TakeOneFed.takeoneBank(),", 1],
                'tk-bank-rows' => [['<span style="color:#6b7689">Beneficiary</span><strong>TakeOne Sports Technologies W.L.L.</strong>', '<strong style="overflow-wrap:anywhere">[BH00 XXXX 0000 0000 0000 00]</strong>'],
                    '<span style="color:#6b7689">Beneficiary</span><strong>{{ tkBeneficiary }}</strong><sc-if value="{{ tkHasBankName }}" hint-placeholder-val="{{ false }}"><span style="color:#6b7689">Bank</span><strong>{{ tkBank }}</strong></sc-if><span style="color:#6b7689">IBAN</span><sc-if value="{{ tkHasIban }}" hint-placeholder-val="{{ true }}"><strong style="overflow-wrap:anywhere;font-family:ui-monospace,Menlo,monospace;letter-spacing:.04em">{{ tkIban }}</strong></sc-if><sc-if value="{{ tkNoIban }}" hint-placeholder-val="{{ false }}"><strong style="color:#a8620f">Being set up — contact TakeOne support</strong></sc-if><sc-if value="{{ tkHasSwift }}" hint-placeholder-val="{{ false }}"><span style="color:#6b7689">SWIFT</span><strong>{{ tkSwift }}</strong></sc-if>', 1],
                'pay-footnote' =>['Payments are processed securely. Card details are never stored in full.', 'Your subscription activates once the transfer is confirmed.', 1],
            ],
            'Karate Statistics.dc.html' => [
                'home-link' => ['<a href="Events Dashboard.dc.html" style="color:#5d6880">Federation</a>', '<a href="/federation" style="color:#5d6880">Federation</a>', 1],
                'font' => ['https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap', './vendor/fonts/poppins.css', 1],
                'leaflet-css' => ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', './vendor/leaflet/leaflet.css', 1],
                'leaflet-js' => ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', './vendor/leaflet/leaflet.js', 1],
            ],
            'Club Invitation.dc.html' => $flags + [
                'preconnect' => ['<link rel="preconnect" href="https://fonts.googleapis.com">', '', 1],
                // PLATFORM: the store is the server's (same function names).
                'store' => ['<script src="./federation-store.js"></script>', '<script src="./federation-store.platform.js"></script>', 1],
                // PLATFORM: a real code goes by email; nothing on screen gives it away,
                // and whether a contact belongs to a club is never revealed.
                'code-demo-box' => ['<div style="margin-top:10px;padding:10px 12px;border-radius:10px;background:#fdf6e6;border:1px dashed #caa14f;font-size:.74rem;color:#7a5a12">{{ demoCodeMsg }}</div>', '', 1],
                'code-demo-hint' => ['<div style="margin-top:14px;padding:10px 12px;border-radius:10px;background:#f4f6fa;font-size:.72rem;color:#5d6880;line-height:1.5">{{ t.idDemoHint }}</div>', '', 1],
                'code-sent-en' => ["idSentTo: 'Code sent to {c}.'", "idSentTo: 'If a club is registered with {c}, a code is on its way to the club’s email.'", 1],
                'code-sent-ar' => ["idSentTo: 'تم إرسال الرمز إلى {c}.'", "idSentTo: 'إذا كان هناك نادٍ مسجّل بـ {c} فقد أُرسل الرمز إلى بريد النادي.'", 1],
                'code-send' => ["sendCode: () => { const c = st && st.findClub(s.idContact); if (!c) return this.setState({error: t.eNoClub, codeSent: false});\n        const code = String(Math.floor(100000 + Math.random() * 900000)); this._pendingClub = c.id; this.setState({codeSent: true, demoCode: code, codeInput: '', error: ''}); },", "sendCode: () => { if (!(s.idContact || '').trim()) return this.setState({error: t.eNoClub, codeSent: false});\n        window.TakeOneFed.sendCode(s.idContact); this.setState({codeSent: true, demoCode: '', codeInput: '', error: ''}); },", 1],
                'code-verify' => ["verifyCode: () => { if (s.codeInput !== s.demoCode) return this.setState({error: t.eCode}); const c = st && st.club(this._pendingClub); if (c) { this.loadClub(c, false); if (st.entry(c.id)) setTimeout(() => this.go(6), 0); } },", "verifyCode: () => { window.TakeOneFed.verifyCode(s.idContact, s.codeInput).then(id => { if (!id) return this.setState({error: t.eCode}); const c = st && st.club(id); if (c) { this.loadClub(c, false); if (st.entry(c.id)) setTimeout(() => this.go(6), 0); } }); },", 1],
                // PLATFORM: a personal link was proved by the server; so was a
                // session the coach already opened — both come back signed in.
                'club-link' => ['const c = id && window.FedStore.club(id); if (c) this.loadClub(c, true);', 'const vk = window.FedStore.verifiedClub(); const c = vk && window.FedStore.club(vk); if (c) this.loadClub(c, !!id);', 1],
                // PLATFORM: one server call does the whole submission.
                'submit' => [["  submit() {\n", "\n  renderVals() {"], "  submit() {\n    const s = this.state, t = this.T();\n    if (this._submitting) return; this._submitting = true;\n    const dialC0 = this.countries().find(c => c.i3 === s.clubDial) || {};\n    const clubRec = {name: s.clubName.trim(), coach: s.coachName.trim(), email: s.clubEmail.trim(), phone: (dialC0.code || '') + ' ' + s.clubPhone.trim(), address: s.clubAddress.trim(), logo: s.clubLogo || null, logoShape: s.clubLogoShape || 'circle'};\n    const entries = this.entries();\n    window.TakeOneFed.submitEntry({clubMode: s.clubMode, club: clubRec, staff: s.staffData, coachName: s.coachName.trim(), entries, transferReq: s.transferReq, teams: this.teamsState()}).then(r => {\n      this._submitting = false;\n      if (!r.ok) return this.setState({error: r.message || t.eNone});\n      const payMsg = r.balance > 0 ? t.transferMsg.replace('{a}', this.money(r.balance)) : t.nothingDue;\n      this.setState({clubMode: 'existing', verified: true, clubId: r.clubId || s.clubId, doneInfo: {ref: r.ref, payMsg}, picks: {}, pickTypes: {}, athletes: [], transferReq: [], teams: null, amend: false, cNum: '', cCvc: '', cExp: '', confirm: false, draft: null, submitted: true, step: 5, subClub: s.clubName.trim(), subCount: entries.length});\n      try { window.scrollTo({top: 0}); } catch (e) {}\n    });\n  }\n  renderVals() {", 1],
                // PLATFORM: no gateway — entry fees are paid by bank transfer,
                // to the federation that runs the event.
                'club-pay-default' => ["payMethod: 'card'", "payMethod: 'bank'", 1],
                'club-pay-opts' => ["v.payOpts = [['card', t.payCardL, t.payCardS], ['bank', t.payBankL, t.payBankS]]", "v.payOpts = [['bank', t.payBankL, t.payBankS]]", 1],
                // PLATFORM: the event's own words and artwork, not the sample event's.
                'event-texts' => ["    return this.state.lang === 'ar' ? ar : en;\n  }", "    const __tx = window.TakeOneFed && window.TakeOneFed.eventTexts && window.TakeOneFed.eventTexts(); if (__tx) { Object.assign(en, __tx.en); Object.assign(ar, __tx.ar); }\n    return this.state.lang === 'ar' ? ar : en;\n  }", 1],
                'cover-art' => ['<image-slot id="invite-cover-art" shape="rect" placeholder="Drop the competition artwork here"', '<image-slot id="invite-cover-art" shape="rect" placeholder=" " src="{{ coverSrc }}"', 1],
                'cover-art-side' => ['<image-slot id="invite-cover-art" shape="rect" placeholder="Competition artwork"', '<image-slot id="invite-cover-art" shape="rect" placeholder=" " src="{{ coverSrc }}"', 1],
                'club-beneficiary' => ['<b>Bahrain Karate Federation</b>', '<b>{{ fedBeneficiary }}</b>', 1],
                'club-beneficiary-val' => ["v.payCard = s.payMethod === 'card'; v.payBank = s.payMethod === 'bank';", "v.payCard = s.payMethod === 'card'; v.payBank = s.payMethod === 'bank'; v.fedBeneficiary = window.TakeOneFed.beneficiary(); v.coverSrc = window.TakeOneFed.coverArt(); const __bk = window.TakeOneFed.bank(); v.fedHasBank = !!__bk; v.fedNoBank = !__bk; v.fedBankName = (__bk && __bk.bank) || ''; v.fedHasBankName = !!(__bk && __bk.bank); v.fedIban = (__bk && __bk.iban) || ''; v.fedSwift = (__bk && __bk.swift) || ''; v.fedHasSwift = !!(__bk && __bk.swift);", 1],
                // PLATFORM: the real account, from the federation's record — or,
                // until it is filled in, an honest line instead of a fake IBAN.
                'club-iban' => ['<span style="color:#6b7689">IBAN</span><b style="overflow-wrap:anywhere">BH00 XXXX 0000 0000 0000 00</b>', '<sc-if value="{{ fedHasBankName }}" hint-placeholder-val="{{ false }}"><span style="color:#6b7689">Bank</span><b>{{ fedBankName }}</b></sc-if><span style="color:#6b7689">IBAN</span><sc-if value="{{ fedHasBank }}" hint-placeholder-val="{{ true }}"><b style="overflow-wrap:anywhere;font-family:ui-monospace,Menlo,monospace;letter-spacing:.04em">{{ fedIban }}</b></sc-if><sc-if value="{{ fedNoBank }}" hint-placeholder-val="{{ false }}"><b style="color:#a8620f">Not provided yet — ask the federation for its bank details</b></sc-if><sc-if value="{{ fedHasSwift }}" hint-placeholder-val="{{ false }}"><span style="color:#6b7689">SWIFT</span><b>{{ fedSwift }}</b></sc-if>', 1],
                'font' => ['https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap', './vendor/fonts/poppins-tajawal.css', 1],
            ],
            default => [],
        };
    }
}
