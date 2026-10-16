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

    /** A cache-buster for a served file: changes whenever the file does. */
    private function version(string $file): string
    {
        return substr(md5((string) @filemtime($this->root().'/'.$file)), 0, 8);
    }

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
        if (! preg_match('#^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+){0,3}$#', $file) || str_contains($file, '..')) {
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
        // Light by design (as layouts/app): without this a computer in dark
        // mode gets the page inverted by the browser's auto-dark or Dark Reader.
        $inject = '<meta name="color-scheme" content="light">'."\n".'<meta name="darkreader-lock">'."\n"
            .'<style>html { color-scheme: only light; }</style>'."\n"
            .'<meta name="csrf-token" content="'.e($boot['csrf'] ?? '').'">'."\n"
            .(isset($boot['favicon']) ? '<link rel="icon" href="'.e($boot['favicon']).'">'."\n" : '')
            .'<script>window.__TAKEONE_FED = '.$json.';</script>'."\n"
            // Versioned: served with a cache lifetime, so a changed bridge
            // must arrive under a new address or browsers keep the old one.
            .'<script src="./takeone-bridge.js?v='.$this->version('takeone-bridge.js').'"></script>'."\n"
            .'<script src="./takeone-test-mode.js?v='.$this->version('takeone-test-mode.js').'" defer></script>'."\n"
            .'<script src="./takeone-featuring.js?v='.$this->version('takeone-featuring.js').'" defer></script>'."\n"
            .'<script src="./takeone-rulebook.js?v='.$this->version('takeone-rulebook.js').'" defer></script>'."\n"
            // The dashboard's own image cropper (TakeOneFed.cropImage) — faces 3:4, logos square.
            .'<script src="./takeone-cropper.js?v='.$this->version('takeone-cropper.js').'" defer></script>'."\n"
            // The shared karate weight-class library, table embedded (public/libraries/karate-shotukan-weightclasses).
            .\App\Support\KarateShotukanWeightclasses::tags()."\n".$head;

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

    /**
     * PLATFORM: the Statistics page counts the federation's own records
     * (FederationStatistics, fetched from /federation/api/statistics). Its
     * sample tables and guessed shares go; the three method spans are replaced
     * by resources/patches/statistics.js, and every sentence that stated a
     * sample figure states the real one — or says it is not recorded.
     */
    private function statisticsRewrites(): array
    {
        $patch = (string) file_get_contents(dirname(__DIR__).'/resources/patches/statistics.js');
        $part = function (string $name) use ($patch): string {
            if (! preg_match('#^//@@ '.$name.'\n(.*?)(?=^//@@ |\z)#ms', $patch, $m)) {
                throw new RuntimeException("Statistics patch section '{$name}' is missing.");
            }

            return rtrim($m[1])."\n";
        };
        $muted = '<div style="padding:24px 12px;text-align:center;border-radius:14px;border:1px dashed rgba(30,44,79,0.161);font-size:.8rem;color:#5d6880">';

        return [
            // The data layer: records in, filters applied (spans first — later
            // rules match markup only).
            'stats-data' => [["  data() {\n    if (this._d) return this._d;", "      kAth: popF / locF, kAct: actF / locF};\n  }\n"], $part('data'), 1],
            'stats-map' => [['  initMap(el) {', "      c.setTooltipContent(r.name + ' · ' + r.metricLabel); });\n  }\n"], $part('map'), 1],
            'stats-render' => [['  renderVals() {', "    return v;\n  }\n"], $part('render'), 1],
            'stats-season' => ["DEF = {season: '2026',", 'DEF = {season: String(new Date().getFullYear()),', 1],
            'stats-state' => ['toast: null, errCleared: false};', 'toast: null, errCleared: false, stats: null, fetching: false, loadErr: false};', 1],
            'stats-load' => ["    this._rs(); window.addEventListener('resize', this._rs);", "    this._rs(); window.addEventListener('resize', this._rs);\n    this.load();\n    this.liveOn();", 1],
            // PLATFORM: live — a FederationPulse re-reads the figures quietly.
            'stats-live-off' => ['  componentWillUnmount() { window.removeEventListener(\'resize\', this._rs);', '  componentWillUnmount() { this.liveOff(); window.removeEventListener(\'resize\', this._rs);', 1],
            'stats-refresh' => ["  refresh() { this.setState({loading: true, errCleared: true, more: false}); clearTimeout(this._lt); this._lt = setTimeout(() => { this.setState({loading: false, updated: Date.now()}); this.toast('Statistics refreshed'); }, 1100); }",
                '  refresh() { this.setState({errCleared: true, more: false}); this.load(true); }', 1],
            'stats-csv' => ["['Bahrain Karate Federation — statistics export (mock data)']", "[(this.data().federation || 'Federation') + ' — statistics export']", 1],

            // The header: the Events page's own pattern (eyebrow, title, actions right),
            // naming the federation — no breadcrumb, no "Mock data" badge.
            'stats-header' => [['<header style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:16px 24px;padding:clamp(20px,3vw,34px) 0 18px">', "Season {{ season }}</span>\n    </div>\n  </div>"],
                '<header style="{{ headStyle }}"><div style="min-width:0"><div style="display:flex;align-items:center;gap:12px;margin-bottom:10px"><span style="display:block;width:38px;height:3px;border-radius:2px;background:#d8b25f"></span><span style="font-size:.7rem;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:#8a6d20">{{ fedName }} · Season {{ season }}</span></div><h1 style="margin:0;font-size:1.65rem;font-weight:700;letter-spacing:-.01em">Statistics</h1></div>', 1],
            'stats-actions' => ['<div style="display:flex;gap:10px;flex-wrap:wrap">'."\n".'      <button onClick="{{ doRefresh }}"', '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-left:auto">'."\n".'      <button onClick="{{ doRefresh }}"', 1],
            'stats-error' => ['The results service did not respond. You are viewing cached figures from 08:42 today.', '{{ errMsg }}', 1],
            'stats-empty' => ['No statistics match these filters</h2>', '{{ emptyTitle }}</h2>', 1],

            // PLATFORM: a federation with bodies under it sees one row per sub-federation
            // (its own sub-bodies rolled in), counted from the same filtered records.
            'stats-bodies' => ["<div style=\"display:flex;flex-wrap:wrap;gap:18px;margin-top:18px\">\n  <section id=\"growth\"",
                '<sc-if value="{{ sub.show }}" hint-placeholder-val="{{ false }}"><section aria-labelledby="h-sub" style="margin-top:18px;background:#fff;border:1px solid rgba(30,44,79,0.081);border-radius:18px;padding:clamp(16px,2vw,22px);box-sizing:border-box;box-shadow:0 14px 40px rgba(30,44,79,.08)">'
                .'<div style="display:flex;align-items:center;gap:10px;margin-bottom:4px"><span style="width:22px;height:2px;border-radius:2px;background:#d8b25f"></span><span style="font-size:.62rem;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:#8a6d20">Federation structure</span></div>'
                .'<h2 id="h-sub" style="margin:0 0 4px;font-size:1.02rem;font-weight:600">{{ sub.title }}</h2>'
                .'<div style="font-size:.7rem;color:#6b7689;margin-bottom:12px">{{ sub.intro }}</div>'
                .'<div style="overflow-x:auto"><div style="display:grid;grid-template-columns:minmax(160px,1.6fr) 56px 70px 88px 64px 58px;gap:8px;padding:0 10px 8px;min-width:520px;font-size:.6rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#6b7689;border-bottom:1px solid rgba(30,44,79,0.081)">'
                .'<span>Federation</span><span style="text-align:right">Clubs</span><span style="text-align:right">Athletes</span><span style="text-align:right">Competitions</span><span style="text-align:right">Entries</span><span style="text-align:right">Medals</span></div>'
                .'<div style="display:flex;flex-direction:column;gap:4px;margin-top:6px;min-width:520px"><sc-for list="{{ sub.rows }}" as="r" hint-placeholder-count="2">'
                .'<div style="position:relative;display:grid;grid-template-columns:minmax(160px,1.6fr) 56px 70px 88px 64px 58px;gap:8px;align-items:center;padding:10px;border-radius:10px"><span style="{{ r.heat }}"></span>'
                .'<span style="position:relative;min-width:0"><span style="display:block;font-size:.8rem;font-weight:600;overflow-wrap:anywhere">{{ r.name }}</span><sc-if value="{{ r.hasNote }}" hint-placeholder-val="{{ false }}"><span style="display:block;font-size:.66rem;color:#6b7689;margin-top:2px">{{ r.note }}</span></sc-if></span>'
                .'<span style="position:relative;text-align:right;font-size:.78rem;font-variant-numeric:tabular-nums">{{ r.clubs }}</span>'
                .'<span style="position:relative;text-align:right;font-size:.78rem;font-variant-numeric:tabular-nums">{{ r.athletes }}</span>'
                .'<span style="position:relative;text-align:right;font-size:.78rem;font-variant-numeric:tabular-nums">{{ r.comps }}</span>'
                .'<span style="position:relative;text-align:right;font-size:.78rem;font-variant-numeric:tabular-nums">{{ r.entries }}</span>'
                .'<span style="position:relative;text-align:right;font-size:.78rem;font-variant-numeric:tabular-nums">{{ r.medals }}</span>'
                .'</div></sc-for></div></div></section></sc-if>'
                ."\n\n<div style=\"display:flex;flex-wrap:wrap;gap:18px;margin-top:18px\">\n  <section id=\"growth\"", 1],

            // What each KPI counts, as it is counted.
            'tip-ath' => ['Athletes with a valid license and at least one training or competition record this season. {{ k.registered }} athlete profiles are registered in total.', 'Athletes entered in at least one competition this season. {{ k.registered }} athlete profiles are registered with the federation in total.', 1],
            'tip-clubs' => ['Clubs with a current federation affiliation for the selected season and region.', 'Clubs affiliated with the federation, in the selected region.', 1],
            'tip-comps' => ['All sanctioned competitions on the season calendar, including cancelled and pending events.', 'Every event on the season calendar, whatever its status.', 1],
            'tip-medals' => ['Individual medals from completed, approved results. Team events count one medal per team.', 'Medals from results recorded in the draw: the final’s winner and loser, and each semi-final loser.', 1],
            'tip-off' => ['Referees and judges with a valid license. Athlete filters do not change this figure.', 'Referees and team managers that clubs named on their entries this season.', 1],
            'tip-part' => ['Share of active athletes who entered at least one competition this season.', 'Share of registered athletes who entered at least one competition this season.', 1],
            'kpi-off-label' => ['Licensed officials</span>', 'Officials named</span>', 1],
            'kpi-off-sub' => ['&#9650; 6 licenses expiring soon', '{{ k.offSub }}', 1],

            'growth-note' => ['Athletes (count). Registered = all licensed profiles; active = trained or competed this season; participants = entered at least one competition.',
                'Athletes (count). Registered = athlete profiles with the federation; active = entered a competition of that season; participants = entered one that had started.', 1],
            'clubs-activity-sub' => ['36 affiliated clubs · last 90 days', '{{ cl.activitySub }}', 1],
            'clubs-inactive' => [['<div style="display:flex;gap:10px;align-items:flex-start;margin-top:18px;padding:12px;border-radius:12px;background:rgba(239,91,91,.08)', "in the last 90 days.</span>\n    </div>"],
                '<sc-if value="{{ cl.hasInactive }}" hint-placeholder-val="{{ false }}"><div style="display:flex;gap:10px;align-items:flex-start;margin-top:18px;padding:12px;border-radius:12px;background:rgba(239,91,91,.08);border:1px solid rgba(239,91,91,.3)"><span style="color:#b4483c;font-weight:700">!</span><span style="font-size:.76rem;line-height:1.5;color:#8f2f25">{{ cl.inactiveMsg }}</span></div></sc-if>', 1],
            'comp-stack' => ['aria-label="14 competitions: 9 completed, 3 upcoming, 1 pending approval, 1 cancelled"', 'aria-label="{{ cm.stackLabel }}"', 1],
            'comp-none' => ['<sc-for list="{{ cm.rows }}" as="c" hint-placeholder-count="9">', '<sc-if value="{{ cm.none }}" hint-placeholder-val="{{ false }}">'.$muted.'No competitions with entries this season yet.</div></sc-if><sc-for list="{{ cm.rows }}" as="c" hint-placeholder-count="9">', 1],
            'comp-legend' => [['<span style="display:flex;align-items:center;gap:6px"><span style="width:9px;height:9px;border-radius:50%;background:#4fbf8a"></span>Completed</span>', '#ef5b5b"></span>Cancelled</span>'],
                '<span style="display:flex;align-items:center;gap:6px"><span style="width:9px;height:9px;border-radius:50%;background:#4fbf8a"></span>Completed</span>'
                .'<span style="display:flex;align-items:center;gap:6px"><span style="width:9px;height:9px;border-radius:50%;background:#d8b25f"></span>Live</span>'
                .'<span style="display:flex;align-items:center;gap:6px"><span style="width:9px;height:9px;border-radius:50%;background:#5ec8e5"></span>Registration open</span>'
                .'<span style="display:flex;align-items:center;gap:6px"><span style="width:9px;height:9px;border-radius:50%;background:#f0a94b"></span>Planning</span>', 1],

            // Kumite: how bouts were won is recorded; the score is not.
            'ku-margin-note' => ['Point difference at the end of the bout (matches). 8+ = technical superiority.', 'Scores are not recorded on the platform yet — the draw keeps who won and how, not the points.', 1],
            'ku-weight-note' => ['Bar = matches contested. Amber = closest categories (average winning margin ≤ 1.6 points).', 'Bar = bouts recorded in each division.', 1],
            'ka-cat-note' => ['Average final score out of 10 (scale shown from 6.0). Technical 70% · athletic 30%.', 'Kata scores are not recorded on the platform yet.', 1],
            'ka-season-note' => ['Average kata score per season (out of 10).', 'Kata scores are not recorded on the platform yet.', 1],
            'ka-judges-note' => ['Distribution of individual judge scores (count). Narrow spread = consistent judging.', 'Judge scores are not recorded on the platform yet.', 1],
            'ka-axis-lo' => ['<div style="position:absolute;left:-6px;top:88%;transform:translate(-100%,-50%);font-size:.6rem;color:#6b7689">6.8</div>', '', 1],
            'ka-axis-hi' => ['<div style="position:absolute;left:-6px;top:10%;transform:translate(-100%,-50%);font-size:.6rem;color:#6b7689">7.6</div>', '', 1],

            // No season goals are kept: the card says what the medal was, nothing more.
            'em-goal' => ['<span style="font-size:.64rem;color:#6b7689">Season goal {{ a.prog }}</span>', '', 1],
            'em-goal-bar' => ['<div style="height:6px;border-radius:3px;background:rgba(30,44,79,0.081)"><div style="{{ a.bar }}"></div></div>', '', 1],
            'md-none' => ['<sc-for list="{{ md.rows }}" as="m" hint-placeholder-count="8">', '<sc-if value="{{ md.none }}" hint-placeholder-val="{{ false }}"><div style="padding:24px 6px;text-align:center;font-size:.8rem;color:#5d6880">No medals yet. They appear as finals and semi-finals are recorded in the draw.</div></sc-if><sc-for list="{{ md.rows }}" as="m" hint-placeholder-count="8">', 1],
            'rk-none' => ['No ranked athletes in this group for the current filters.', 'Ranking lists are not kept on the platform yet.', 1],
            'em-none' => ['<sc-for list="{{ em }}" as="a" hint-placeholder-count="4">', '<sc-if value="{{ emNone }}" hint-placeholder-val="{{ false }}">'.$muted.'Athletes appear here when they win their first medal on record.</div></sc-if><sc-for list="{{ em }}" as="a" hint-placeholder-count="4">', 1],
            'geo-aria' => ['Map of Bahrain with circles sized by the selected metric per region', 'Map of the federation’s clubs, with circles sized by the selected metric per body', 1],
            'hl-none' => ['<sc-for list="{{ hl.rows }}" as="a" hint-placeholder-count="5">', '<sc-if value="{{ hl.none }}" hint-placeholder-val="{{ false }}">'.$muted.'Nothing needs attention right now.</div></sc-if><sc-for list="{{ hl.rows }}" as="a" hint-placeholder-count="5">', 1],
            'of-banner' => ['<b style="color:#a8620f">&#9650; 6</b> license expirations in the next 45 days', '{{ of.banner }}', 1],
            'of-lic' => ['Officials by license level', 'Referees by licence, as clubs entered it', 1],
            'ins-none' => ['<sc-for list="{{ ins }}" as="i" hint-placeholder-count="5">', '<sc-if value="{{ insNone }}" hint-placeholder-val="{{ false }}">'.$muted.'Insights appear once there are enough entries and results to compare.</div></sc-if><sc-for list="{{ ins }}" as="i" hint-placeholder-count="5">', 1],
            'footer' => ['All figures on this page are mock data for the prototype. Athlete and club names are fictional.', 'Figures are counted from this federation’s own records — entries, draws and results. Where nothing is recorded the page shows —.', 1],
        ];
    }

    /**
     * PLATFORM: the event's Statistics tab, next to Overview.
     *
     * The tab is its own prototype drop — resources/app/Event Statistics.dc.html,
     * byte-for-byte as designed, never edited — and this lifts its markup and
     * its methods into the dashboard's event view at serve time. A new drop of
     * that file is a file copy; if its shape changes, the markers below stop
     * matching and the page refuses to serve rather than half-render.
     *
     * The tab reads the dashboard's own state: the open event (`selectedId`)
     * and that event's registrations (`regs`, loaded per event by
     * loadClubRegs), and each division's real draw and recorded results —
     * see resources/patches/event-statistics.js. Nothing on it is simulated
     * except the file's own "Preview with sample data", which says so.
     */
    private function eventStatisticsRewrites(): array
    {
        $file = 'Event Statistics.dc.html';
        $src = (string) @file_get_contents($this->root().'/'.$file);
        $open = '<div style="max-width:1400px;margin:0 auto">';
        $i = strpos($src, $open);
        $j = strrpos($src, "</div>\n</div>\n</x-dc>");
        $m0 = strpos($src, '  esSampleRoster() {');
        $m1 = strpos($src, '  renderVals() {');
        if ($i === false || $j === false || $j < $i || $m0 === false || $m1 === false || $m1 < $m0) {
            throw new RuntimeException("{$file} no longer has the shape the Statistics tab is lifted from.");
        }
        $markup = trim(substr($src, $i + strlen($open), $j - $i - strlen($open)));
        $methods = substr($src, $m0, $m1 - $m0);
        $blank = '{kpis: [], sections: [], m: {stats: [], matches: []}}';

        // A real event reads real records (resources/patches/event-statistics.js);
        // the file's own simulation stays, renamed, for its sample preview only.
        $methods = $this->apply($methods, [
            'es-sample' => ['  evStatsVals() {', '  evStatsValsSample() {', 1],
        ], $file).(string) file_get_contents(dirname(__DIR__).'/resources/patches/event-statistics.js')."\n";

        // What is not recorded is not drawn: the banner only when there is one
        // to say, and the score-only parts (session filter, pace chart,
        // techniques, the athlete's target split) only for the sample preview.
        $wrap = function (string $body, string $from, string $to, string $cond) use ($file): string {
            $a = strpos($body, $from);
            $b = $a === false ? false : strpos($body, $to, $a);
            if ($a === false || $b === false || substr_count($body, $from) !== 1) {
                throw new RuntimeException("{$file}: Statistics markup marker '".substr($from, 0, 40)."' did not match exactly once.");
            }
            $b += strlen($to);

            return substr($body, 0, $a).'<sc-if value="{{ '.$cond.' }}" hint-placeholder-val="{{ false }}">'.substr($body, $a, $b - $a).'</sc-if>'.substr($body, $b);
        };
        $markup = $wrap($markup, '<div style="display:flex;gap:10px;align-items:flex-start;padding:10px 14px;border-radius:12px;background:#fdf6e6', '<span>{{ es.banner }}</span></div>', 'es.hasBanner');
        $markup = $wrap($markup, '<div style="position:relative"><select value="{{ es.fSes }}"', '</select><span style="position:absolute;right:11px;top:50%;transform:translateY(-50%);font-size:.62rem;color:#8a6d20;pointer-events:none">&#9662;</span></div>', 'es.hasScores');
        $markup = $wrap($markup, '<div style="background:#fff;border-radius:18px;border-top:3px solid #d8b25f;padding:clamp(16px,2vw,24px);box-shadow:0 14px 40px rgba(30,44,79,.1);min-width:0;box-sizing:border-box;flex:1.4 1 420px">', "{{ es.paceNote }}</div>\n                </div>", 'es.hasScores');
        $markup = $wrap($markup, '<section id="es-techniques"', '</section>', 'es.hasScores');
        // The event band already names the event, so the tab's own title
        // block goes, and its two buttons move into the filter bar beside
        // the dropdowns — the tab then reads like its sibling tabs.
        $b0 = strpos($markup, '<button onClick="{{ es.exportCsv }}"');
        $b1 = strpos($markup, 'Print report</button>');
        $r0 = strpos($markup, '<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:12px">');
        if ($b0 === false || $b1 === false || $r0 === false || $r0 > $b0) {
            throw new RuntimeException("{$file}: Statistics title row did not match.");
        }
        $b1 += strlen('Print report</button>');
        $r1 = strpos($markup, '</div>', $b1) + strlen('</div>');
        $buttons = substr($markup, $b0, $b1 - $b0);
        $markup = substr($markup, 0, $r0).substr($markup, $r1);
        $reset = 'color:#b4483c;cursor:pointer">Reset</button></sc-if>';
        $markup = $this->apply($markup, [
            'es-actions' => [$reset, $reset."\n            ".$buttons, 1],
            // No gap above the bar: other tabs start flush under the tab strip.
            'es-bar-top' => ['margin:0 -4px 16px;padding:10px 4px;', 'margin:0 -4px 16px;padding:0 4px 10px;', 1],
        ], $file);
        // The notice goes UNDER the filter bar, so the bar is flush with the
        // tab strip whether or not there is a notice to show.
        $n0 = strpos($markup, '<sc-if value="{{ es.hasBanner }}"');
        $n1 = $n0 === false ? false : strpos($markup, '</sc-if>', $n0);
        $end = $buttons."\n          </div>";
        if ($n1 === false || substr_count($markup, $end) !== 1 || strpos($markup, $end) < $n0) {
            throw new RuntimeException("{$file}: Statistics notice did not match.");
        }
        $n1 += strlen('</sc-if>');
        $notice = str_replace('margin-bottom:12px"><b', 'margin-bottom:16px"><b', substr($markup, $n0, $n1 - $n0));
        $markup = substr($markup, 0, $n0).substr($markup, $n1);
        $markup = str_replace($end, $end."\n          ".$notice, $markup);
        $markup = $this->apply($markup, [
            'es-close-note' => ['Close = decided by 1 point or less, Senshu or Hantei. Click a category to see its matches.', '<sc-if value="{{ es.hasScores }}" hint-placeholder-val="{{ false }}">Close = decided by 1 point or less, Senshu or Hantei. </sc-if>Click a category to see its matches.', 1],
            // Male blue, Female pink — as on every other tab (esGender()).
            'es-cat-gender' => ['<div style="font-weight:600">{{ c.label }}</div>', '<div style="font-weight:600"><span style="{{ c.genderStyle }}">{{ c.gender }}</span> {{ c.rest }}</div>', 1],
            // The categories table's numbers were fixed-width columns huddled
            // together, with all the spare width going to the two text ends.
            // They share it now, so the columns spread across the card.
            'es-cat-cols' => ['grid-template-columns:minmax(210px,1.6fr) 70px 96px 72px 84px 76px 76px minmax(170px,1.2fr);gap:10px', 'grid-template-columns:minmax(220px,2.4fr) repeat(6,minmax(64px,1fr)) minmax(150px,1.3fr);gap:18px', 2],
            // Leader sits flush with the card's right edge, so a "—" before any
            // bout is fought does not leave a blank strip down that side.
            'es-cat-leader-head' => ['<span>Leader</span>', '<span style="text-align:right">Leader</span>', 1],
            'es-cat-leader-cell' => ['<span style="font-size:.76rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ c.leader }}</span>', '<span style="font-size:.76rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:right">{{ c.leader }}</span>', 1],
            // The athletes table, the same way: the numbers share the width,
            // and Top technique sits flush right.
            'es-ath-cols' => ['grid-template-columns:34px minmax(180px,1.4fr) minmax(150px,1fr) 64px 60px 60px 64px 64px minmax(140px,1fr);gap:10px', 'grid-template-columns:34px minmax(200px,2fr) minmax(150px,1.3fr) repeat(5,minmax(60px,1fr)) minmax(120px,1fr);gap:18px', 2],
            'es-ath-tech-head' => ['<span>Top technique</span>', '<span style="text-align:right">Top technique</span>', 1],
            'es-ath-tech-cell' => ['<span style="font-size:.74rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ a.tech }}</span>', '<span style="font-size:.74rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:right">{{ a.tech }}</span>', 1],
            'es-ring-note' => ['Schedule assumes 4-minute slots from 09:00. Referee assignments are not tracked yet.', '{{ es.ringNote }}', 1],
            'es-targets' => ["<sc-if value=\"{{ es.m.isAth }}\" hint-placeholder-val=\"{{ false }}\">\n                <div", "<sc-if value=\"{{ es.m.hasTargets }}\" hint-placeholder-val=\"{{ false }}\">\n                <div", 1],
        ], $file);

        return [
            // The event's Statistics tab is parked (owner, 2026-10-15: "we will work
            // on it later"): not offered in the tab row, everything behind it kept.
            // To bring it back, insert ['stats','Statistics'] after Overview again.
            'es-tab' => ["['overview','Overview'],['regs','Registrations']", "['overview','Overview'],['regs','Registrations']", 1],
            // Computed only while the tab is open — the simulation is not free.
            'es-vals' => ["tabDraw: s.tab === 'draw', tabFin: s.tab === 'fin',", "tabDraw: s.tab === 'draw', tabFin: s.tab === 'fin', tabStats: s.tab === 'stats', es: s.tab === 'stats' ? this.evStatsVals() : {$blank},", 1],
            'es-methods' => ['  cssToObj(css) {', $methods.'  cssToObj(css) {', 1],
            'es-markup' => ['      <sc-if value="{{ tabFin }}" hint-placeholder-val="{{ false }}">',
                '      <sc-if value="{{ tabStats }}" hint-placeholder-val="{{ false }}">'."\n".$markup."\n      </sc-if>\n".'      <sc-if value="{{ tabFin }}" hint-placeholder-val="{{ false }}">', 1],
        ];
    }

    /**
     * PLATFORM: the Schedule tab reads the event's own days and sessions
     * (FederationSchedule) instead of the prototype's fixed demo programme,
     * and staff edit it in place. Logic: resources/patches/schedule.js;
     * markup: resources/patches/schedule.html (the prototype's visual
     * language — gold-topped day cards, time / what / where rows).
     */
    private function scheduleRewrites(): array
    {
        $dir = dirname(__DIR__).'/resources/patches';
        // The running order (Day + Mats) shares the tab: resources/patches/run-order.js.
        $methods = (string) file_get_contents($dir.'/schedule.js')."\n".(string) file_get_contents($dir.'/run-order.js');
        $markup = rtrim((string) file_get_contents($dir.'/schedule.html'));
        $blank = '{days: [], empty: false, has: false, can: false}';

        return [
            'sch-data' => [["    const schedule = [\n      {day: 'Day 1 — Eliminations'", "{time: '19:00', what: 'Closing gala', where: 'Grand ballroom'}]}\n    ];"],
                "    const schedule = [];\n    const sch = s.tab === 'schedule' ? this.schVals(sel) : {$blank};\n    const ro = s.tab === 'schedule' ? this.roVals(sel) : {on: false};", 1],
            'sch-vals' => ["tabSchedule: s.tab === 'schedule',", "tabSchedule: s.tab === 'schedule', sch, ro,", 1],
            'sch-methods' => ['  cssToObj(css) {', $methods.'  cssToObj(css) {', 1],
            'sch-markup' => [['      <sc-if value="{{ tabSchedule }}" hint-placeholder-val="{{ false }}">', "          </sc-for>\n        </div>\n      </sc-if>"], $markup, 1],
        ];
    }

    /**
     * PLATFORM: layout repairs on the Statistics page, measured on a real
     * desktop render. Markup only — the values they bind are computed in
     * resources/patches/statistics.js.
     *
     *  - The club table needs ~980px; beside the activity card it got ~700px
     *    and its right-hand columns fell off the edge. It now takes the row
     *    until there is room for both.
     *  - Names wrap instead of being cut ("Sparta self defense ac…").
     *  - The ranking table's header is drawn only when there are rows.
     *  - The region table fits its card: no forced width, so Growth is not
     *    clipped and a body's name wraps over two lines, not three.
     *  - The Participation KPI spans two columns so the KPI grid fills evenly.
     */
    private function statisticsLayoutRewrites(): array
    {
        $kpiCard = 'position:relative;background:#fff;border:1px solid rgba(30,44,79,0.081);border-radius:18px;padding:18px;cursor:pointer;transition:transform .18s ease,border-color .18s ease;scroll-snap-align:start;min-width:0';

        return [
            'lay-club-sec' => ['<section aria-labelledby="h-clubs" style="flex:2 1 600px;', '<section aria-labelledby="h-clubs" style="flex:2 1 1020px;', 1],
            'lay-club-head' => ['grid-template-columns:34px minmax(210px,1.7fr) 92px 74px 74px 62px 70px 56px minmax(120px,1fr) 58px;gap:10px;padding:0 8px 8px;min-width:940px',
                'grid-template-columns:34px minmax(200px,1.7fr) minmax(130px,1fr) 70px 70px 56px 64px 52px minmax(110px,1fr) 56px;gap:10px;padding:0 8px 8px;min-width:980px', 1],
            'lay-club-name' => ['<div style="font-size:.82rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ c.name }}</div>',
                '<div style="font-size:.82rem;font-weight:600;overflow-wrap:anywhere;line-height:1.3">{{ c.name }}</div>', 1],
            'lay-medal-name' => ['<div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ m.name }}</div>',
                '<div style="font-weight:600;overflow-wrap:anywhere;line-height:1.3">{{ m.name }}</div>', 1],
            'lay-rank-head' => ["<sc-if value=\"{{ notMobile }}\" hint-placeholder-val=\"{{ true }}\">\n          <div style=\"display:grid;grid-template-columns:minmax(170px,1.5fr) minmax(140px,1.2fr)",
                "<sc-if value=\"{{ rkShowHead }}\" hint-placeholder-val=\"{{ false }}\">\n          <div style=\"display:grid;grid-template-columns:minmax(170px,1.5fr) minmax(140px,1.2fr)", 1],
            'lay-region-head' => ['grid-template-columns:minmax(90px,1.2fr) 48px 70px 64px 58px 64px;gap:8px;padding:0 10px 8px;min-width:430px;',
                'grid-template-columns:minmax(110px,1fr) 44px 60px 54px 52px 54px;gap:8px;padding:0 10px 8px;', 1],
            'lay-comp-name' => ['<div style="font-size:.8rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ c.name }}</div><div style="font-size:.66rem;color:#6b7689">{{ c.date }}</div>',
                '<div style="font-size:.8rem;font-weight:600;overflow-wrap:anywhere;line-height:1.3">{{ c.name }}</div><div style="font-size:.66rem;color:#6b7689">{{ c.date }}</div>', 1],
            'lay-rank-name' => ['<div style="font-size:.8rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.name }}</div>',
                '<div style="font-size:.8rem;font-weight:600;overflow-wrap:anywhere;line-height:1.3">{{ r.name }}</div>', 1],
            // The medal table fits a phone: the name column gives way instead
            // of forcing 440px and pushing Total and Efficiency off-screen.
            'lay-medal-cols' => ['grid-template-columns:22px minmax(150px,1.4fr) 34px 34px 34px 42px 58px', 'grid-template-columns:22px minmax(0,1.4fr) 30px 30px 30px 40px 48px', 2],
            'lay-medal-width' => ["<div style=\"overflow-x:auto\">\n      <div style=\"min-width:440px\">", "<div style=\"overflow-x:auto\">\n      <div>", 1],
            'lay-kpi-part' => ['<div onClick="{{ goAthletes }}" role="button" tabindex="0" style="'.$kpiCard.'"',
                '<div onClick="{{ goAthletes }}" role="button" tabindex="0" style="{{ kpiPartStyle }}"', 1],
        ];
    }

    private function pageRewrites(string $file): array
    {
        $flags = ['flags' => ['https://flagcdn.com/w40/', '/federation/app/flags/', null]];

        return match ($file) {
            'Events Dashboard.dc.html' => $flags + $this->eventStatisticsRewrites() + $this->scheduleRewrites() + [
                // PLATFORM: an entry fee is settled with the organiser however they
                // direct, and the organiser marks it received here — so the invoice
                // list speaks of a payment, not a bank transfer. (Display only: the
                // stored status is still 'Awaiting transfer'.)
                // PLATFORM: a club's headcount is its whole roster — every member
                // of the platform club, active or not, plus the federation's own
                // athletes for it (FederationDesk::headcount). The prototype
                // counted only the federation's athlete records.
                // PLATFORM: a club history row always names its club — the server
                // sends `clubName` beside the key (FederationRegistry::clubNameOf),
                // so a club the directory no longer lists never prints as `fa-…`.
                'club-history-name' => ['{club: c ? c.name : x.clubId,', '{club: c ? c.name : (x.clubName || x.clubId),', 1],
                'club-headcount' => ['const roster = c => (FS && FS.roster ? FS.roster(c.id).length : 0);', 'const roster = c => (c.rosterCount != null ? +c.rosterCount : (FS && FS.roster ? FS.roster(c.id).length : 0));', 1],
                'entry-pay-pill' => ["['Awaiting transfer', '#a8620f', '#fdf1e0']", "['Awaiting payment', '#a8620f', '#fdf1e0']", 1],
                'entry-pay-confirm' => ['title="Mark the bank transfer as received"', 'title="Mark this club’s payment as received"', 1],
                'font' => ['https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap', './vendor/fonts/poppins.css', 1],
                'leaflet-css' => ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', './vendor/leaflet/leaflet.css', 1],
                'leaflet-js' => ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', './vendor/leaflet/leaflet.js', 1],
                'mammoth' => ['https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js', '/federation/app/vendor/mammoth.browser.min.js', 1],
                'pdfjs' => ['https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js', '/federation/app/vendor/pdf.min.js', 1],
                'pdfjs-worker' => ['https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js', '/federation/app/vendor/pdf.worker.min.js', 1],
                'sheetjs' => ['https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js', '/federation/app/vendor/xlsx.full.min.js', 1],

                // PLATFORM: OpenStreetMap refuses tile requests with no Referer, and
                // this page is served with `Referrer-Policy: same-origin`. The layer
                // sends the origin only (never the page's path), on OSM's current address.
                'osm-tiles' => ["L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; OpenStreetMap'})", "L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; <a href=\"https://www.openstreetmap.org/copyright\" target=\"_blank\" rel=\"noopener\">OpenStreetMap</a> contributors', referrerPolicy: 'strict-origin-when-cross-origin', maxZoom: 19})", 1],
                // PLATFORM: live updates — the bridge redraws this component
                // after a FederationPulse re-read (takeone-bridge.js › live).
                'live-comp' => ["    this.loadClubRegs();\n    this._onStorage = ", "    if (window.TakeOneFed) window.TakeOneFed.comp = this;\n    this.loadClubRegs();\n    this._onStorage = ", 1],
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
                // PLATFORM: a manager can delete an event from its edit form. Two
                // steps (ask, then confirm) — no native dialog — and the server
                // decides (FederationEvents::delete); the flag only offers it.
                // PLATFORM: one event editor (events.federation_hosts) — "Plan new
                // event" opens the shared editor with this federation chosen when
                // the boot data carries its address; otherwise the modal, as before.
                'event-create-editor' => ["openCreate: () => this.setState({editOpen: true, eMode: 'create',", "openCreate: () => (window.TakeOneFed && window.TakeOneFed.boot && window.TakeOneFed.boot.urls && window.TakeOneFed.boot.urls.eventCreate) ? (window.location.href = window.TakeOneFed.boot.urls.eventCreate) : this.setState({editOpen: true, eMode: 'create',", 1],
                'event-del-reset' => ["openEdit: () => this.setState({editOpen: true, eMode: 'edit',", "openEdit: () => this.setState({editOpen: true, eMode: 'edit', eDelAsk: false, eDelBusy: false, eDelErr: false,", 1],
                'event-del-vals' => ["eSaveLabel: s.eMode === 'create' ? 'Create event' : 'Save changes',",
                    "eSaveLabel: s.eMode === 'create' ? 'Create event' : 'Save changes',\n"
                    ."      eDelShow: s.eMode === 'edit' && !s.eDelAsk && !!sel.id && !!(window.TakeOneFed && window.TakeOneFed.canDeleteEvents && window.TakeOneFed.canDeleteEvents()),\n"
                    ."      eDelAsking: s.eMode === 'edit' && !!s.eDelAsk,\n"
                    ."      eDelText: s.eDelErr ? 'This event could not be deleted. Try again.' : 'Delete “' + (sel.name || 'this event') + '”? It leaves every list; its entries, draws and results are kept.',\n"
                    ."      eDelLabel: s.eDelBusy ? 'Deleting…' : 'Yes, delete',\n"
                    ."      onDelAsk: () => this.setState({eDelAsk: true, eDelErr: false}),\n"
                    ."      onDelCancel: () => this.setState({eDelAsk: false, eDelErr: false}),\n"
                    ."      onDelConfirm: () => { if (this.state.eDelBusy) return; this.setState({eDelBusy: true, eDelErr: false}); window.TakeOneFed.deleteEvent(sel.id, this).then(ok => { if (!ok) this.setState({eDelBusy: false, eDelErr: true}); }); },", 1],
                'event-del-button' => ['<div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 28px 18px;border-top:1px solid #eef1f6;background:#fbfcfe;flex:none">'."\n".'          <button onClick="{{ closeEdit }}"',
                    '<div style="display:flex;gap:10px;justify-content:flex-end;align-items:center;flex-wrap:wrap;padding:14px 28px 18px;border-top:1px solid #eef1f6;background:#fbfcfe;flex:none">'."\n"
                    .'          <sc-if value="{{ eDelShow }}" hint-placeholder-val="{{ false }}"><button onClick="{{ onDelAsk }}" style="height:44px;padding:0 18px;margin-inline-end:auto;border:1px solid #e3b4ae;border-radius:11px;background:#fff;font-family:inherit;font-size:.84rem;font-weight:600;color:#b4483c;cursor:pointer" style-hover="border-color:#b4483c;background:#fdf1ef">Delete event</button></sc-if>'."\n"
                    .'          <sc-if value="{{ eDelAsking }}" hint-placeholder-val="{{ false }}"><div style="flex:1 1 100%;display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:12px 14px;border-radius:12px;background:#fdf1ef;border:1px solid #e3b4ae">'
                    .'<span style="flex:1 1 240px;font-size:.8rem;line-height:1.5;color:#7a2e25">{{ eDelText }}</span>'
                    .'<button onClick="{{ onDelCancel }}" style="height:38px;padding:0 14px;border:1px solid #d5dae3;border-radius:10px;background:#fff;font-family:inherit;font-size:.8rem;font-weight:600;color:#6b7689;cursor:pointer">Keep it</button>'
                    .'<button onClick="{{ onDelConfirm }}" style="height:38px;padding:0 16px;border:0;border-radius:10px;background:#b4483c;font-family:inherit;font-size:.8rem;font-weight:600;color:#fff;cursor:pointer" style-hover="background:#9a3a30">{{ eDelLabel }}</button>'
                    .'</div></sc-if>'."\n"
                    .'          <button onClick="{{ closeEdit }}"', 1],
                'event-quick-id' => ['const ev = {id: Date.now(), name: s.fName.trim(), sport: s.fSport,', 'const ev = {id: window.TakeOneFed.newId(), name: s.fName.trim(), sport: s.fSport,', 1],
                'event-quick-save' => ["        this.setState({events: [ev, ...s.events], createOpen: false, filter: 'All', fName: '', fVenue: '', fDate: ''});", "        window.TakeOneFed.saveEvent(ev, this);\n        this.setState({events: [ev, ...s.events], createOpen: false, filter: 'All', fName: '', fVenue: '', fDate: ''});", 1],
                // Documents upload as they are picked; the event save attaches them.
                'event-docs' => ['      url: URL.createObjectURL(f)}));', '      url: window.TakeOneFed.stageDoc(f)}));', 1],
                // Registrations are per event: read the open event's, again when another opens.
                'regs-load' => ["      const ext = JSON.parse(localStorage.getItem('takeone_club_regs') || '[]');\n      const rows = [];", "      const ext = window.TakeOneFed.clubRegs(this.state.selectedId || ((this.state.events || [])[0] || {}).id);\n      const rows = [];", 1],
                'regs-open-1' => ["onOpen: () => this.setState({nav: 'events', view: 'detail', selectedId: ev.id, tab: 'overview'})", "onOpen: () => this.setState({nav: 'events', view: 'detail', selectedId: ev.id, tab: 'overview'}, () => this.loadClubRegs())", 1],
                'regs-open-2' => ["onOpen: () => this.setState({view: 'detail', selectedId: ev.id, tab: 'overview'})", "onOpen: () => this.setState({view: 'detail', selectedId: ev.id, tab: 'overview'}, () => this.loadClubRegs())", 1],
                // PLATFORM: the store is the server's (same function names).
                'store' => ['<script src="./federation-store.js"></script>', '<script src="./federation-store.platform.js?v='.$this->version('federation-store.platform.js').'"></script>', 1],
                // PLATFORM: an invitation is to ONE event, and a club's personal
                // link is its secret token — never its id.
                'invite-copy' => ["const url = new URL('Club Invitation.dc.html', location.href).href;", "const url = new URL('Club Invitation.dc.html?event=' + encodeURIComponent(sel.id || ''), document.baseURI).href;", 1],
                'invite-url' => ["      inviteUrl: 'takeone.events/invite/' + sel.name.toLowerCase().replace(/[^a-z0-9]+/g, '-'),", "      inviteUrl: new URL('Club Invitation.dc.html?event=' + encodeURIComponent(sel.id || ''), document.baseURI).href, inviteHref: 'Club Invitation.dc.html?event=' + encodeURIComponent(sel.id || ''), inviteQrHas: /^[0-9a-f-]{36}$/i.test(String(sel.id || '')), inviteQrStyle: 'width:124px;height:124px;flex:none;border-radius:12px;border:1px solid #e7cf94;background:#fff url(' + JSON.stringify(new URL('../invite-qr/' + encodeURIComponent(sel.id || '') + '.svg', document.baseURI).href) + ') center/108px 108px no-repeat', inviteQrPng: new URL('../invite-qr/' + encodeURIComponent(sel.id || '') + '.png?download=1', document.baseURI).href, inviteQrSvg: new URL('../invite-qr/' + encodeURIComponent(sel.id || '') + '.svg?download=1', document.baseURI).href,", 1],
                'invite-open' => ['<a href="Club Invitation.dc.html" style=', '<a href="{{ inviteHref }}" style=', 1],
                // PLATFORM: the invitation as a QR code too (FederationApiController::inviteQr),
                // for posters and messages. Only once the event is saved — an unsaved one has no uuid.
                'invite-qr' => ['Open the invitation page &rarr;</a>', 'Open the invitation page &rarr;</a>'
                    .'<sc-if value="{{ inviteQrHas }}" hint-placeholder-val="{{ false }}"><div style="display:flex;align-items:center;gap:16px;margin-top:16px;padding:14px;border-radius:14px;background:#fbf8f0;border:1px solid #f0e3c2">'
                    .'<div role="img" aria-label="QR code for the invitation link" style="{{ inviteQrStyle }}"></div>'
                    .'<div style="min-width:0;flex:1"><div style="font-size:.7rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:#8a6d20;margin-bottom:4px">QR code</div>'
                    .'<div style="font-size:.74rem;line-height:1.5;color:#6b7689;margin-bottom:10px">Clubs scan it to open the same invitation. Put it on a poster or send it with a message.</div>'
                    .'<div style="display:flex;gap:8px;flex-wrap:wrap"><a href="{{ inviteQrPng }}" style="display:inline-flex;align-items:center;height:32px;padding:0 14px;border-radius:9px;background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f;font-size:.74rem;font-weight:600;text-decoration:none">Download PNG</a>'
                    .'<a href="{{ inviteQrSvg }}" style="display:inline-flex;align-items:center;height:32px;padding:0 14px;border-radius:9px;border:1px solid #d5dae3;background:#fff;color:#1e2c4f;font-size:.74rem;font-weight:600;text-decoration:none">SVG</a></div></div></div></sc-if>'
                    // Test mode for this event — drawn by takeone-test-mode.js (managers only).
                    .'<div data-fed-test data-event="{{ sel.id }}"></div>'
                    // Paid featuring on the members' Events page — drawn by takeone-featuring.js (managers only).
                    .'<div data-fed-feature data-event="{{ sel.id }}"></div>'
                    // The rule book — category + belt system — drawn by takeone-rulebook.js (managers only).
                    .'<div data-fed-rulebook data-event="{{ sel.id }}"></div>', 1],
                // The page lives at /federation; its siblings sit beside the file, not the address.
                'invite-origin' => ["const origin = (() => { try { return new URL('Club Invitation.dc.html', location.href).href;", "const origin = (() => { try { return new URL('Club Invitation.dc.html', document.baseURI).href;", 1],
                'club-link-href' => ["href: origin + '?club=' + encodeURIComponent(c.id), copyLabel", "href: window.TakeOneFed.clubLink(origin, c.id), copyLabel", 1],
                'club-link-copy' => ["onCopy: () => { const u = origin + '?club=' + encodeURIComponent(c.id);", "onCopy: () => { const u = window.TakeOneFed.clubLink(origin, c.id);", 1],
                // PLATFORM: a submitted athlete carries their real Takeone ID,
                // national ID and review state, and Approve is saved.
                'reg-ids' => ["nat: a.nat || '—', natId: '—', takeoneId: 'Pending', dob: a.dob, coach: sub.coach || ''});", "nat: a.nat || '—', natId: a.natId || '—', takeoneId: a.takeoneId || 'Pending', entryId: sub.entryId, dob: a.dob, coach: sub.coach || ''});", 1],
                // The division (and its age group) is the one the EVENT placed the
                // athlete in, sent by the server (FederationRegistry::placements);
                // the page's own guess — today's age, the generic table — only
                // stands in for an event not on the platform yet.
                'reg-status' => ["division: this.weightClassOf(g, ag, kg), status: 'Pending', mobile: sub.phone || '—', email: sub.email || '—',", "division: a.division || this.weightClassOf(g, ag, kg), divisionName: a.divisionName || (a.unplaced ? 'Not in a division yet' : ''), divisionAges: a.divisionAges || '', divisionGender: a.divisionGender || '', divisionId: a.divisionId || 0, unplaced: !!a.unplaced, status: a.approval === 'Approved' ? 'Approved' : 'Pending', mobile: sub.phone || '—', email: sub.email || '—',", 1],
                // A stated age (no date of birth on file) is the athlete's age here too.
                'reg-declared-age' => ['const age = this.calcAge(a.dob);', 'const age = a.dob ? this.calcAge(a.dob) : (a.declaredAge != null ? +a.declaredAge : 0);', 1],
                'reg-age-group' => ['gender: g, age, ageGroup: ag, weight: kg,', 'gender: g, age, ageGroup: a.ageGroup || ag, weight: kg,', 1],
                // The readiness checklist was six fixed rows ("18 of 22" referees,
                // "Venue contract signed"…). It is the server's now — every row
                // derived from the event (FederationEventFigures).
                // The event's Officials tab: its own appointments, grouped by job
                // (FederationEventOfficials → resources/patches/event-officials.*).
                'offs-tab' => ["['regs','Registrations'],['divs','Divisions']", "['regs','Registrations'],['offs','Officials'],['divs','Divisions']", 1],
                'offs-vals' => ["tabStats: s.tab === 'stats',", "tabOffs: s.tab === 'offs', eo: s.tab === 'offs' ? this.evOfficialsVals(sel) : {chips: [], groups: [], rows: [], ed: {open: false}}, tabStats: s.tab === 'stats',", 1],
                'offs-methods' => ['  cssToObj(css) {', (string) file_get_contents(dirname(__DIR__).'/resources/patches/event-officials.js').'  cssToObj(css) {', 1],
                'offs-markup' => ['      <sc-if value="{{ tabFin }}" hint-placeholder-val="{{ false }}">',
                    rtrim((string) file_get_contents(dirname(__DIR__).'/resources/patches/event-officials.html'))."\n".'      <sc-if value="{{ tabFin }}" hint-placeholder-val="{{ false }}">', 1],
                // The event's Clubs tab: every club with an athlete entered and
                // its Male / Female split (FederationEventFigures::entryClubs →
                // resources/patches/event-clubs.*). Opened from the checklist's
                // "Clubs" row too. The Registrations tab reads "Athletes".
                'clubs-tab' => ["['regs','Registrations'],['offs','Officials']", "['regs','Participants'],['offs','Officials'],['clubs','Clubs']", 1],
                // One tab, called Draw (owner, 2026-10-15): the divisions editor is
                // the draw — each division's board opens from its card. The
                // prototype's own Draw tab is not offered; its markup stays, unreached.
                'draw-tab-off' => ["['divs','Divisions'],['draw','Draw'],", "['divs','Draw'],", 1],
                'clubs-vals' => ["tabOffs: s.tab === 'offs',", "tabClubs: s.tab === 'clubs', ec: s.tab === 'clubs' ? this.evClubsVals(sel) : {clubs: []}, tabOffs: s.tab === 'offs',", 1],
                'clubs-methods' => ['  cssToObj(css) {', (string) file_get_contents(dirname(__DIR__).'/resources/patches/event-clubs.js').'  cssToObj(css) {', 1],
                'clubs-markup' => ['      <sc-if value="{{ tabFin }}" hint-placeholder-val="{{ false }}">',
                    rtrim((string) file_get_contents(dirname(__DIR__).'/resources/patches/event-clubs.html'))."\n".'      <sc-if value="{{ tabFin }}" hint-placeholder-val="{{ false }}">', 1],
                // The Athletes tab edits an athlete in place: details, national
                // ID, weight and photo (FederationAthleteEditor -> resources/
                // patches/event-athlete-edit.*, cropper takeone-cropper.js).
                'athed-row' => ["      onApprove: () => this.setState({regs: s.regs.map(x => x.id === r.id ? {...x, status: 'Approved'} : x)})", "      canEdit: !!(r.entryId && r.takeoneId && r.takeoneId !== 'Pending'), onEdit: () => this.athEdOpen(r), stop: e => { if (e && e.stopPropagation) e.stopPropagation(); },\n      onApprove: () => this.setState({regs: s.regs.map(x => x.id === r.id ? {...x, status: 'Approved'} : x)})", 1],
                'athed-cols' => ['120px 100px 96px;gap:14px;align-items:center;min-width:1076px', '120px 100px 136px;gap:14px;align-items:center;min-width:1116px', 2],
                // The whole row opens the athlete (owner, 2026-10-15: "make the
                // entire row clickable, no edit button"). The tick box and the
                // Approve button stop the click so they keep their own job.
                'athed-list-btn' => ['                <div>'."\n".'                  <sc-if value="{{ r.pending }}" hint-placeholder-val="{{ false }}">',
                    '                <div onClick="{{ r.stop }}" style="display:flex;align-items:center;justify-content:flex-end;gap:6px">'."\n".'                  <sc-if value="{{ r.pending }}" hint-placeholder-val="{{ false }}">', 1],
                'athed-list-row' => ['padding:12px 0;border-bottom:1px solid #eef1f6">'."\n".'                <div onClick="{{ r.onToggleSel }}"',
                    'padding:12px 0;border-bottom:1px solid #eef1f6;cursor:pointer" role="button" aria-label="Open {{ r.name }}" onClick="{{ r.onEdit }}" style-hover="background:#faf7ef">'."\n".'                <div onClick="{{ r.onToggleSel }}"', 1],
                'athed-group-row' => ['padding:10px 0;border-bottom:1px solid #eef1f6">'."\n".'                    <div style="{{ r.p.avStyle }}">',
                    'padding:10px 0;border-bottom:1px solid #eef1f6;cursor:pointer" role="button" aria-label="Open {{ r.name }}" onClick="{{ r.onEdit }}" style-hover="background:#faf7ef">'."\n".'                    <div style="{{ r.p.avStyle }}">', 1],
                // A Weigh-in button on every Participants row (owner, 2026-10-15),
                // list and grouped views. The weight is saved through the
                // platform's weigh-in desk (TakeOneFed.weighIn); the row shows
                // the weight once signed off. Window: patches/event-athlete-edit.*.
                // The Weight column (and its sort/filter) reads the scale once weighed.
                'weigh-row-kg' => ['gender: g, age, ageGroup: a.ageGroup || ag, weight: kg,', 'gender: g, age, ageGroup: a.ageGroup || ag, weight: (a.weighedIn && a.weighedKg) ? +a.weighedKg : kg,', 1],
                'weigh-row-data' => ['entryId: sub.entryId, dob: a.dob,', 'entryId: sub.entryId, weighUrls: a.weighUrls || [], weighedIn: !!a.weighedIn, weighedKg: a.weighedKg || null, dob: a.dob,', 1],
                'weigh-row-vals' => ["onEdit: () => this.athEdOpen(r), stop:", "onEdit: () => this.athEdOpen(r), canWeigh: !!(r.weighUrls && r.weighUrls.length), weighLabel: r.weighedIn ? '\\u2713 ' + (r.weighedKg != null ? r.weighedKg + ' kg' : 'Weighed') : 'Weigh in', weighTitle: r.weighedIn ? 'Weighed in \\u2014 tap to weigh again' : 'Record this athlete\\u2019s weigh-in', noWeigh: !(r.weighUrls && r.weighUrls.length), weighStyle: this.cssToObj('width:100%;height:34px;padding:0 10px;font-family:inherit;font-size:.74rem;font-weight:600;border-radius:9px;cursor:pointer;white-space:nowrap;font-variant-numeric:tabular-nums;' + (r.weighedIn ? 'color:#fff;background:#2f7a4f;border:1px solid #2f7a4f;box-shadow:0 4px 12px rgba(47,122,79,.25)' : 'color:#8a6d20;background:#fff;border:1px solid #caa14f')), onWeigh: e => { if (e && e.stopPropagation) e.stopPropagation(); this.weighOpen(r); }, stop:", 1],
                'weigh-list-btn' => ['                <div><span style="{{ r.pillStyle }}">{{ r.status }}</span></div>'."\n",
                    '                <div><span style="{{ r.pillStyle }}">{{ r.status }}</span></div>'."\n"
                    .'                <div onClick="{{ r.stop }}"><sc-if value="{{ r.canWeigh }}" hint-placeholder-val="{{ false }}"><button onClick="{{ r.onWeigh }}" title="{{ r.weighTitle }}" style="{{ r.weighStyle }}">{{ r.weighLabel }}</button></sc-if><sc-if value="{{ r.noWeigh }}" hint-placeholder-val="{{ false }}"><span style="font-size:.76rem;color:#97a1b4">&#8212;</span></sc-if></div>'."\n", 1],
                'weigh-group-btn' => ['                    <span style="{{ r.pillStyle }}">{{ r.status }}</span>'."\n",
                    '                    <span style="{{ r.pillStyle }}">{{ r.status }}</span>'."\n"
                    .'                    <sc-if value="{{ r.canWeigh }}" hint-placeholder-val="{{ false }}"><button onClick="{{ r.onWeigh }}" title="{{ r.weighTitle }}" style="{{ r.weighStyle }}">{{ r.weighLabel }}</button></sc-if>'."\n", 1],
                'athed-vals' => ["tabClubs: s.tab === 'clubs',", "ae: this.evAthleteEditVals(), tabClubs: s.tab === 'clubs',", 1],
                'athed-methods' => ['  cssToObj(css) {', (string) file_get_contents(dirname(__DIR__).'/resources/patches/event-athlete-edit.js').'  cssToObj(css) {', 1],
                'athed-markup' => ['      <sc-if value="{{ tabFin }}" hint-placeholder-val="{{ false }}">',
                    rtrim((string) file_get_contents(dirname(__DIR__).'/resources/patches/event-athlete-edit.html'))."\n".'      <sc-if value="{{ tabFin }}" hint-placeholder-val="{{ false }}">', 1],
                // "Registrations by division" listed only the first 8 divisions
                // while the header counted them all. Every division is listed,
                // most athletes first (ties keep the gender/age/weight order).
                // Divisions were sorted as plain text, so "Kumite +55 kg" came
                // before "-30" and age groups ran alphabetically. Order them as the
                // event page does: age group youngest first, men then women, weight lightest
                // first ("+X" after "-X"); a non-weight name goes last.
                'div-sort-weight' => ['const divList2 = Object.values(divMap).sort((a, b) =>'."\n".'      a.gender.localeCompare(b.gender) || a.ageGroup.localeCompare(b.ageGroup) || String(a.division).localeCompare(String(b.division)));',
                    'const ageRank = (g) => { const i = window.KarateShotukanWeightclasses.ageCategories().indexOf(g); return i < 0 ? 999 : i; };'."\n"
                    .'    const kgRank = (n) => { const m = /([+-])\s*(\d+(?:\.\d+)?)/.exec(String(n)); return m ? +m[2] + (m[1] === \'+\' ? 0.5 : 0) : 9999; };'."\n"
                    .'    const divList2 = Object.values(divMap).sort((a, b) =>'."\n"
                    .'      (a.unplaced ? 1 : 0) - (b.unplaced ? 1 : 0) || ageRank(a.ageGroup) - ageRank(b.ageGroup) || String(b.gender).localeCompare(String(a.gender)) || kgRank(a.division) - kgRank(b.division) || String(a.division).localeCompare(String(b.division)));', 1],
                'div-bars-all' => ['const divBars = divList2.slice(0, 8).map(d => ({', 'const divBars = divList2.slice().sort((a, b) => b.count - a.count).map(d => ({', 1],
                // Each division's gender spelled out and coloured as everywhere
                // else on the page (Male blue, Female pink), not "M" / "W".
                'div-bars-gender' => ["label: (d.gender === 'F' ? 'W' : 'M') + ' · ' + d.ageGroup + ' · ' + d.division, count: d.count,",
                    "gender: d.name ? '' : (d.gender === 'F' ? 'Female' : 'Male'), sep: d.name ? '' : ' · ', genderStyle: this.cssToObj('font-weight:600;color:' + (d.gender === 'F' ? '#db2777' : '#2563eb')), label: d.name || (window.KarateShotukanWeightclasses.englishName(d.ageGroup) + ' · ' + d.division), count: d.count,", 1],
                'div-bars-gender-tpl' => ['<span style="font-weight:500">{{ d.label }}</span><span style="color:#6b7689">{{ d.count }}</span>',
                    '<span style="font-weight:500"><span style="{{ d.genderStyle }}">{{ d.gender }}</span>{{ d.sep }}{{ d.label }}</span><span style="color:#6b7689">{{ d.count }}</span>', 1],
                // The Divisions tab as Cards or as a Table (gender, age group,
                // division, entries, bracket), like the Clubs page's toggle
                // (resources/patches/event-divisions.*).
                'divs-card-fields' => ['const divCards = divList2.map(d => ({', "const divCards = divList2.map((d, i) => ({rank: String(i + 1), mixed: !!d.mixed, gender: d.unplaced ? '\u2014' : d.mixed ? 'Mixed' : d.gender === 'F' ? 'Female' : 'Male', genderStyle: this.cssToObj('font-size:.8rem;font-weight:600;color:' + (d.mixed ? '#7c6bf5' : d.gender === 'F' ? '#db2777' : '#2563eb')), ageGroup: d.name ? (d.ages ? 'Ages ' + d.ages : '\u2014') : window.KarateShotukanWeightclasses.englishName(d.ageGroup), division: d.division,", 1],
                // A division is NAMED as the event names it — the category's own
                // name ("Séniors Men -75 kg"), the same words the event's
                // divisions page and the draw show. The dashboard's key
                // ("Kumite -75 kg") stays underneath: saved draws are filed by it.
                'divname-map' => ['division: r.division, count: 0}', 'division: r.division, name: r.divisionName || \'\', ages: r.divisionAges || \'\', mixed: r.divisionGender === \'X\', divisionId: r.divisionId || 0, unplaced: !!r.unplaced, count: 0}', 1],
                // Every unplaced athlete is ONE card, not one per guessed division.
                'divname-key' => ["const key = r.gender + '|' + (r.ageGroup || 'Séniors') + '|' + r.division;", "const key = r.unplaced ? 'unplaced' : (r.divisionGender === 'X' ? 'X' : r.gender) + '|' + (r.ageGroup || 'Séniors') + '|' + r.division;", 1],
                'divname-card' => ["label: (d.gender === 'F' ? 'Female' : 'Male') + ' ' + d.ageGroup + ' — ' + d.division, count: d.count,", "unplaced: d.unplaced, label: d.name || ((d.gender === 'F' ? 'Female' : 'Male') + ' ' + window.KarateShotukanWeightclasses.englishName(d.ageGroup) + ' — ' + d.division), divName: d.name || d.division, count: d.count,", 1],
                'divname-detail' => ["detail: (d.gender === 'F' ? 'Women' : 'Men') + ' · ' + d.ageGroup + ' · ' + d.division,", "detail: d.unplaced ? 'Place them from the event\u2019s divisions page' : d.name ? (d.mixed ? 'Mixed' : d.gender === 'F' ? 'Female' : 'Male') + (d.ages ? ' · Ages ' + d.ages : '') : (d.gender === 'F' ? 'Female' : 'Male') + ' · ' + window.KarateShotukanWeightclasses.englishName(d.ageGroup) + ' · ' + d.division,", 1],
                'divname-bracket' => ["bracket: d.count >= 2 ? 'Ready to draw' : 'Needs 2+ entries',", "bracket: d.unplaced ? 'Not placed' : d.count >= 2 ? 'Ready to draw' : 'Needs 2+ entries',", 1],
                'divname-open' => ["onOpen: () => this.setState({tab: 'draw', drawGender: d.gender === 'F' ? 'Women' : 'Men', drawAge: d.ageGroup, drawDiv: d.division})}));", "onOpen: () => { const dp = window.TakeOneFed && window.TakeOneFed.drawPage ? window.TakeOneFed.drawPage(sel && sel.id) : null; return d.unplaced ? this.setState({tab: 'regs'}) : (dp && d.divisionId) ? window.location.assign(dp + '?division=' + encodeURIComponent(d.divisionId) + '&back=' + encodeURIComponent(window.location.pathname + window.location.search)) : null; }}));", 1],
                'divname-reg' => ["const regs = filteredRegs.map(r => ({\n      ...r,", "const regs = filteredRegs.map(r => ({\n      ...r, divLabel: r.divisionName || r.division,", 1],
                'divname-reg-tpl' => ['<div style="font-size:.76rem;font-weight:600;color:#1e2c4f">{{ r.division }}</div>', '<div style="font-size:.76rem;font-weight:600;color:#1e2c4f">{{ r.divLabel }}</div>', 1],
                'divname-draw' => ['const divCounts = divList.map(label => [label, byGA.filter(r => r.division === label).length]);', "const nameOfDiv = {}; byGA.forEach(r => { if (r.divisionName) nameOfDiv[r.division] = r.divisionName; });\n    const divCounts = divList.map(label => [label, byGA.filter(r => r.division === label).length]);", 1],
                'divname-draw-opts' => ['drawDivOpts: divCounts.map(([label, count]) => ({label, count:', 'drawDivOpts: divCounts.map(([label, count]) => ({label: nameOfDiv[label] || label, count:', 1],
                'divname-draw-label' => ["drawDivLabel: curDiv || 'No divisions yet'", "drawDivLabel: (curDiv && nameOfDiv[curDiv]) || curDiv || 'No divisions yet'", 1],
                // The age groups READ in English ("Under 12"), as the rule
                // book now names them; the French name stays the page's key.
                // The Division cell's second line repeated the name (gender, age
                // group) and the Weight column. It now says only what the name
                // does not: the division's age range (owner, 2026-10-15).
                // The federation-wide Officials page is gone from the menu (owner,
                // 2026-10-15); officials are managed in each event's Officials tab.
                'nav-no-officials' => ["['athletes','Athletes'],['officials','Officials'],['financials','Financials']", "['athletes','Athletes'],['financials','Financials']", 1],
                // Participants tab → the design room for certificates and badges
                // (designVals in resources/patches/event-officials.js).
                'regs-design-vals' => ["ae: this.evAthleteEditVals(), tabClubs: s.tab === 'clubs',", "ae: this.evAthleteEditVals(), dz: s.tab === 'regs' ? this.designVals(sel, 'participants') : {}, tabClubs: s.tab === 'clubs',", 1],
                'regs-design-btns' => ["            </div>\n            <sc-if value=\"{{ hasSel }}\" hint-placeholder-val=\"{{ false }}\">", <<<'HTML'
            </div>
            <sc-if value="{{ dz.any }}" hint-placeholder-val="{{ false }}">
              <div style="position:relative">
                <button type="button" onClick="{{ dz.onToggle }}" aria-haspopup="menu" aria-expanded="{{ dz.open }}" title="Design and print badges and certificates" style="{{ dz.style }}" style-hover="border-color:#caa14f;background:#fdf6e6"><span aria-hidden="true" style="color:#8a6d20">&#9113;</span>Printables<span aria-hidden="true" style="font-size:.7rem;color:#8a6d20">{{ dz.caret }}</span></button>
                <sc-if value="{{ dz.open }}" hint-placeholder-val="{{ false }}">
                  <div onClick="{{ dz.onClose }}" style="position:fixed;inset:0;z-index:45"></div>
                  <div role="menu" onClick="{{ dz.stop }}" style="position:absolute;top:46px;left:0;z-index:46;min-width:270px;padding:6px;background:#fff;border:1px solid #eef1f6;border-radius:14px;box-shadow:0 18px 40px rgba(30,44,79,.16);animation:rise .18s cubic-bezier(.22,.68,.35,1) both">
                    <sc-for list="{{ dz.items }}" as="i" hint-placeholder-count="2">
                      <button type="button" role="menuitem" onClick="{{ i.onClick }}" style="{{ i.style }}" style-hover="background:#fdf6e6"><span style="font-size:.82rem;font-weight:700;color:#1e2c4f">{{ i.label }}</span><span style="font-size:.7rem;color:#6b7689">{{ i.hint }}</span></button>
                    </sc-for>
                  </div>
                </sc-if>
              </div>
            </sc-if>
            <sc-if value="{{ hasSel }}" hint-placeholder-val="{{ false }}">
HTML, 1],
                // Participants → "Download": the consent form too, filled in for everyone or blank (regFormItems / regPdfUrl).
                'regpdf-form-url' => ["this.setState({rpBusy: kind, rpErr: ''});\n      fetch(url + '?kind=' + encodeURIComponent(kind),", "this.setState({rpBusy: kind, rpErr: ''});\n      fetch(this.regPdfUrl(url, kind),", 1],
                'regpdf-form-items' => ["['all', 'All Participants A–Z', 'Everyone once, alphabetical']]\n        .map(", "['all', 'All Participants A–Z', 'Everyone once, alphabetical']].concat(this.regFormItems())\n        .map(", 1],
                'div-cell-dedupe' => ['<div style="font-size:.68rem;color:#6b7689">{{ r.catLabel }} &#183; {{ r.weightLabel }}</div>',
                    '<sc-if value="{{ r.hasDivAges }}" hint-placeholder-val="{{ false }}"><div style="font-size:.68rem;color:#6b7689">Ages {{ r.divisionAges }}</div></sc-if>', 1],
                'div-cell-dedupe-vals' => ["const regs = filteredRegs.map(r => ({\n      ...r, divLabel: r.divisionName || r.division,", "const regs = filteredRegs.map(r => ({\n      ...r, divLabel: r.divisionName || r.division, hasDivAges: !!r.divisionAges,", 1],
                'en-catlabel' => ["catLabel: (r.gender === 'F' ? 'Female' : 'Male') + ' ' + (r.ageGroup || 'Séniors'),", "catLabel: (r.gender === 'F' ? 'Female' : 'Male') + ' ' + window.KarateShotukanWeightclasses.englishName(r.ageGroup || 'Séniors'),", 1],
                'en-draw-age' => ['drawAgeLabel: curAge,', 'drawAgeLabel: window.KarateShotukanWeightclasses.englishName(curAge),', 1],
                'en-draw-age-opts' => ['drawAgeOpts: ageCats.map(label => ({label, style:', 'drawAgeOpts: ageCats.map(label => ({label: window.KarateShotukanWeightclasses.englishName(label), style:', 1],
                'en-dir-cat' => ["cat: catR ? catR.ageGroup + ' \\u00b7 ' + catR.label : '',", "cat: catR ? window.KarateShotukanWeightclasses.englishName(catR.ageGroup) + ' \\u00b7 ' + catR.label : '',", 1],
                // The Draw tab's gender switch reads Male / Female (its state keeps 'Men'/'Women').
                'en-draw-gender' => ["drawGenderOpts: ['Men', 'Women'].map(label => ({label, style:", "drawGenderOpts: ['Men', 'Women'].map(label => ({label: label === 'Women' ? 'Female' : 'Male', style:", 1],
                'divs-vals' => ['checklist, divBars, divCards, divsEmpty,', "checklist, divBars, divCards, divsEmpty, divsAny: !divsEmpty, dv: s.tab === 'divs' ? this.evDivViewVals(divCards) : {rows: [], tabs: []}, dm: s.tab === 'divs' ? this.dmVals(sel, divCards) : {on: false, off: true},", 1],
                'divs-methods' => ['  cssToObj(css) {', (string) file_get_contents(dirname(__DIR__).'/resources/patches/event-divisions.js')
                    // The division editor (weight classes, categories, headings) — div-manage.js.
                    ."\n".(string) file_get_contents(dirname(__DIR__).'/resources/patches/div-manage.js').'  cssToObj(css) {', 1],
                'divs-markup' => [['      <sc-if value="{{ tabDivs }}" hint-placeholder-val="{{ false }}">', '      <sc-if value="{{ tabDraw }}" hint-placeholder-val="{{ false }}">'],
                    rtrim((string) file_get_contents(dirname(__DIR__).'/resources/patches/event-divisions.html'))."\n\n".'      <sc-if value="{{ tabDraw }}" hint-placeholder-val="{{ false }}">', 1],
                'readiness' => [["    const checklist = [\n      {done: true,  label: 'Venue contract signed'", "    ].map(c => ({...c, mark: c.done"], "    const rowGo = (url) => { if (url) window.location.assign(url); };\n"
                    ."    // A row that names a TAB opens that tab of this dashboard (with its job\n"
                    ."    // filter, for the officials) — nothing here leaves the federation view.\n"
                    ."    const tabGo = (u) => this.setState({tab: u.tab, ...(u.tab === 'offs' ? {offEv: sel.id, offJob: u.job || '', offQ: ''} : {})});\n"
                    ."    const checklist = (sel.readiness || []).map(c => ({...c, link: !!(c.url || c.tab), plain: !(c.url || c.tab), hint: (c.url || c.tab) ? (c.hint || 'Open this event\u2019s officials') : '',\n"
                    ."      onOpen: () => c.tab ? tabGo(c) : rowGo(c.url),\n"
                    ."      subs: (c.subs || []).map(u => ({...u, link: !!(u.url || u.tab), plain: !(u.url || u.tab), hint: (u.url || u.tab) ? (u.hint || 'Open this event\u2019s officials') : '', onOpen: () => u.tab ? tabGo(u) : rowGo(u.url),\n"
                    ."        rowStyle: this.cssToObj('display:flex;align-items:center;gap:10px;padding:6px 0 6px 34px;border-bottom:1px solid #f4f6f9'),\n"
                    ."        subDot: this.cssToObj('width:5px;height:5px;border-radius:50%;flex:none;background:' + (u.color || '#caa14f')),\n"
                    ."        subLabel: this.cssToObj('font-size:.78rem;flex:1;color:#1e2c4f;font-weight:500'),\n"
                    ."        subNote: this.cssToObj('font-size:.72rem;font-weight:600;color:#1e2c4f')})), mark: c.done", 1],
                // The checklist rows, a row's breakdown as indented sub-rows
                // beneath it (officials by job), and the rows that lead
                // somewhere (Officials → the Officials page, filtered to this
                // event and that job) clickable, with a chevron and a hover.
                'readiness-rows' => [['            <sc-for list="{{ checklist }}" as="c" hint-placeholder-count="6">', "{{ c.note }}</span>\n              </div>\n            </sc-for>"],
                    '            <sc-for list="{{ checklist }}" as="c" hint-placeholder-count="6">'."\n"
                    .'              <sc-if value="{{ c.link }}" hint-placeholder-val="{{ false }}">'."\n"
                    .'                <div onClick="{{ c.onOpen }}" title="{{ c.hint }}" style="display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid #eef1f6;cursor:pointer;border-radius:8px" style-hover="background:#f4f6fa">'."\n"
                    .'                  <span style="{{ c.dotStyle }}">{{ c.mark }}</span>'."\n"
                    .'                  <span style="font-size:.84rem;flex:1;color:#1e2c4f;font-weight:500">{{ c.label }}</span>'."\n"
                    .'                  <span style="font-size:.7rem;color:#6b7689">{{ c.note }}</span>'."\n"
                    .'                  <span aria-hidden="true" style="color:#caa14f;font-size:1rem;line-height:1;padding-inline-end:4px">›</span>'."\n"
                    .'                </div>'."\n"
                    .'              </sc-if>'."\n"
                    .'              <sc-if value="{{ c.plain }}" hint-placeholder-val="{{ true }}">'."\n"
                    .'                <div style="display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid #eef1f6">'."\n"
                    .'                  <span style="{{ c.dotStyle }}">{{ c.mark }}</span>'."\n"
                    .'                  <span style="font-size:.84rem;flex:1">{{ c.label }}</span>'."\n"
                    .'                  <span style="font-size:.7rem;color:#6b7689">{{ c.note }}</span>'."\n"
                    .'                </div>'."\n"
                    .'              </sc-if>'."\n"
                    .'              <sc-for list="{{ c.subs }}" as="u" hint-placeholder-count="0">'."\n"
                    .'                <sc-if value="{{ u.link }}" hint-placeholder-val="{{ false }}">'."\n"
                    .'                  <div onClick="{{ u.onOpen }}" title="{{ u.hint }}" style="display:flex;align-items:center;gap:10px;padding:6px 0 6px 34px;border-bottom:1px solid #f4f6f9;cursor:pointer;border-radius:8px" style-hover="background:#f4f6fa">'."\n"
                    .'                    <span style="{{ u.subDot }}"></span>'."\n"
                    .'                    <span style="font-size:.78rem;flex:1;color:#1e2c4f;font-weight:500">{{ u.label }}</span>'."\n"
                    .'                    <span style="font-size:.72rem;font-weight:600;color:#1e2c4f">{{ u.note }}</span>'."\n"
                    .'                    <span aria-hidden="true" style="color:#caa14f;font-size:.95rem;line-height:1;padding-inline-end:4px">›</span>'."\n"
                    .'                  </div>'."\n"
                    .'                </sc-if>'."\n"
                    .'                <sc-if value="{{ u.plain }}" hint-placeholder-val="{{ true }}">'."\n"
                    .'                  <div style="{{ u.rowStyle }}">'."\n"
                    .'                    <span style="{{ u.subDot }}"></span>'."\n"
                    .'                    <span style="{{ u.subLabel }}">{{ u.label }}</span>'."\n"
                    .'                    <span style="{{ u.subNote }}">{{ u.note }}</span>'."\n"
                    .'                  </div>'."\n"
                    .'                </sc-if>'."\n"
                    .'              </sc-for>'."\n"
                    .'            </sc-for>', 1],
                'reg-approve' => ["onApprove: () => this.setState({regs: s.regs.map(x => x.id === r.id ? {...x, status: 'Approved'} : x)})", "onApprove: () => { window.TakeOneFed.approveReg(r); this.setState({regs: s.regs.map(x => x.id === r.id ? {...x, status: 'Approved'} : x)}); }", 1],
                // PLATFORM: the Athletes page lists real athletes, not its sample.
                // PLATFORM: the Athletes page shows a member's face where they made
                // one public (FederationDesk::photo), painted as the tile's
                // background the way the Registrations rows do; initials otherwise.
                'ath-photo-row' => ["takeoneId: a.tk || '\\u2014', initials: inits(a.name),",
                    "takeoneId: a.tk || '\\u2014', initials: a.photo ? '' : inits(a.name), avStyle: Object.assign(this.cssToObj('width:36px;height:48px;border-radius:8px;background:#eef1f6;background-size:cover;background-position:center;display:grid;place-items:center;font-size:.72rem;font-weight:600;color:#1e2c4f'), a.photo ? {backgroundImage: 'url(\"' + a.photo + '\")'} : {}),", 1],
                'ath-photo-sel' => ["const athSel = selA ? {...selA, takeoneId: selA.tk || '\\u2014', initials: inits(selA.name),",
                    "const athSel = selA ? {...selA, takeoneId: selA.tk || '\\u2014', initials: selA.photo ? '' : inits(selA.name), avStyle: Object.assign(this.cssToObj('width:72px;height:96px;border-radius:12px;background:#eef1f6;background-size:cover;background-position:center;display:grid;place-items:center;font-size:1.1rem;font-weight:700;color:#1e2c4f;flex:none'), selA.photo ? {backgroundImage: 'url(\"' + selA.photo + '\")'} : {}), natName: selA.natName || selA.nat, hasNatFlag: !!selA.natFlag, natFlagStyle: selA.natFlag ? Object.assign(this.cssToObj('width:20px;height:15px;flex:none;border-radius:3px;box-shadow:0 0 0 1px #d5dae3;background-size:cover;background-position:center'), {backgroundImage: 'url(\"' + selA.natFlag + '\")'}) : {}, eventList: (selA.eventList || []).map(ev => ({...ev, go: !!(ev.fed || ev.url), goIcon: ev.fed ? '\u203a' : '\u2197', rowStyle: this.cssToObj('display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;background:#f7f8fb;border:1px solid #eef1f6;width:100%;box-sizing:border-box;font-family:inherit;text-align:start;color:#1e2c4f;' + ((ev.fed || ev.url) ? 'cursor:pointer' : 'cursor:default')), onOpen: () => { if (ev.fed) { this.setState({athProfile: null, nav: 'events', view: 'detail', selectedId: ev.fed, tab: 'overview'}, () => this.loadClubRegs()); } else if (ev.url) { window.open(ev.url, '_blank', 'noopener'); } }})), hasEvents: !!(selA.eventList && selA.eventList.length), clubGo: !!(selA.clubUrl || (selA.club && selA.club !== '\u2014')), clubNoGo: !(selA.clubUrl || (selA.club && selA.club !== '\u2014')), clubGoIcon: selA.clubUrl ? '\u2197' : '\u203a', onClub: () => { if (selA.clubUrl) { window.open(selA.clubUrl, '_blank', 'noopener'); } else if (selA.club && selA.club !== '\u2014') { this.setState({athProfile: null, nav: 'clubs', clubTab: 'All', clubQuery: selA.club}); } }, hasClubLogo: !!selA.clubLogo, clubLogoStyle: selA.clubLogo ? Object.assign(this.cssToObj('width:20px;height:20px;flex:none;background-size:contain;background-repeat:no-repeat;background-position:center'), {backgroundImage: 'url(\"' + selA.clubLogo + '\")'}) : {},", 1],
                'ath-photo-row-tile' => ['<div style="width:36px;height:48px;border-radius:8px;background:#eef1f6;display:grid;place-items:center;font-size:.72rem;font-weight:600;color:#1e2c4f">{{ a.initials }}</div>',
                    '<div style="{{ a.avStyle }}">{{ a.initials }}</div>', 1],
                'ath-photo-sel-tile' => ['<div style="width:72px;height:96px;border-radius:12px;background:#eef1f6;display:grid;place-items:center;font-size:1.1rem;font-weight:700;color:#1e2c4f;flex:none">{{ athSel.initials }}</div>',
                    '<div style="{{ athSel.avStyle }}">{{ athSel.initials }}</div>', 1],
                // The athlete card: nationality as a flag and the full country
                // name, and the events they took part in, listed.
                'ath-nat-cell' => ['<div style="font-size:.84rem;font-weight:600">{{ athSel.nat }}</div>',
                    '<div style="font-size:.84rem;font-weight:600;display:flex;align-items:center;gap:7px"><sc-if value="{{ athSel.hasNatFlag }}" hint-placeholder-val="{{ false }}"><span style="{{ athSel.natFlagStyle }}"></span></sc-if><span>{{ athSel.natName }}</span></div>', 1],
                'ath-events-list' => ['<sc-if value="{{ athSel.hasHistory }}" hint-placeholder-val="{{ false }}">',
                    '<sc-if value="{{ athSel.hasEvents }}" hint-placeholder-val="{{ false }}">'
                    .'<div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4;margin:20px 0 10px">Events</div>'
                    .'<div style="display:flex;flex-direction:column;gap:8px">'
                    .'<sc-for list="{{ athSel.eventList }}" as="ev" hint-placeholder-count="2">'
                    .'<button type="button" onClick="{{ ev.onOpen }}" style="{{ ev.rowStyle }}" style-hover="border-color:#e7cf94;background:#fdf8ec">'
                    .'<span style="width:3px;align-self:stretch;border-radius:2px;background:#d8b25f;flex:none"></span>'
                    .'<span style="flex:1;min-width:0;font-size:.84rem;font-weight:600">{{ ev.name }}</span>'
                    .'<span style="font-size:.72rem;color:#6b7689;white-space:nowrap">{{ ev.date }}</span>'
                    .'<sc-if value="{{ ev.go }}" hint-placeholder-val="{{ false }}"><span aria-hidden="true" style="font-size:.95rem;color:#8a6d20;flex:none">{{ ev.goIcon }}</span></sc-if>'
                    .'</button></sc-for></div></sc-if>'
                    .'<sc-if value="{{ athSel.hasHistory }}" hint-placeholder-val="{{ false }}">', 1],
                // The club's logo before its name on the athlete card — bare, on
                // no tile (CLAUDE.md Design Rule #5).
                'ath-club-logo' => ['<div style="font-size:.8rem;color:#6b7689">{{ athSel.club }}</div>',
                    '<sc-if value="{{ athSel.clubGo }}" hint-placeholder-val="{{ false }}"><button type="button" onClick="{{ athSel.onClub }}" title="{{ athSel.club }}" style="display:inline-flex;align-items:center;gap:6px;margin:2px 0 0 -6px;padding:3px 8px 3px 6px;border:1px solid transparent;border-radius:999px;background:none;font-family:inherit;font-size:.8rem;font-weight:600;color:#8a6d20;cursor:pointer;text-align:start;transition:background .15s ease,border-color .15s ease" style-hover="background:#fdf8ec;border-color:#e7cf94"><sc-if value="{{ athSel.hasClubLogo }}" hint-placeholder-val="{{ false }}"><span role="img" aria-label="{{ athSel.club }}" style="{{ athSel.clubLogoStyle }}"></span></sc-if><span>{{ athSel.club }}</span><span aria-hidden="true" style="font-size:.72rem;opacity:.7">{{ athSel.clubGoIcon }}</span></button></sc-if><sc-if value="{{ athSel.clubNoGo }}" hint-placeholder-val="{{ false }}"><div style="font-size:.8rem;color:#6b7689;display:flex;align-items:center;gap:6px;margin-top:2px"><sc-if value="{{ athSel.hasClubLogo }}" hint-placeholder-val="{{ false }}"><span role="img" aria-label="{{ athSel.club }}" style="{{ athSel.clubLogoStyle }}"></span></sc-if><span>{{ athSel.club }}</span></div></sc-if>', 1],
                // The athlete row's sub-line in full words — "Male · 14 years" — and
                // the club as its logo and name (Design Rule #5: bare logo).
                // The athlete row's sub-lines: gender in its colour (blue / pink),
                // age in black, nationality with its flag; then the club (bare
                // logo, Design Rule #5) and the martial arts practised there.
                'ath-row-meta' => ["meta: a.gender + ' · ' + a.age + ' · ' + a.club,",
                    "meta: '', metaGender: /^f/i.test(a.gender || '') ? 'Female' : (/^m/i.test(a.gender || '') ? 'Male' : ''), metaGenderStyle: this.cssToObj('font-weight:600;color:' + (/^f/i.test(a.gender || '') ? '#db2777' : '#2563eb')), hasMetaGender: /^[fm]/i.test(a.gender || ''), metaAge: (a.age !== '' && a.age != null && a.age !== '\\u2014') ? (a.age + (+a.age === 1 ? ' year' : ' years')) : '', hasMetaAge: (a.age !== '' && a.age != null && a.age !== '\\u2014'), metaNat: a.natName && a.natName !== '\\u2014' ? a.natName : '', hasMetaNat: !!(a.natName && a.natName !== '\\u2014'), hasMetaFlag: !!a.natFlag, metaFlagStyle: a.natFlag ? Object.assign(this.cssToObj('width:16px;height:12px;flex:none;border-radius:2px;box-shadow:0 0 0 1px #d5dae3;background-size:cover;background-position:center'), {backgroundImage: 'url(\"' + a.natFlag + '\")'}) : {}, metaClub: a.club && a.club !== '\\u2014' ? a.club : '', hasMetaClub: !!(a.club && a.club !== '\\u2014'), hasRowClubLogo: !!a.clubLogo, rowClubLogoStyle: a.clubLogo ? Object.assign(this.cssToObj('width:16px;height:16px;flex:none;background-size:contain;background-repeat:no-repeat;background-position:center'), {backgroundImage: 'url(\"' + a.clubLogo + '\")'}) : {}, metaArts: (a.clubArts || []).join(' · '), hasMetaArts: !!(a.clubArts && a.clubArts.length),", 1],
                'ath-row-meta-tile' => ['<div style="font-size:.72rem;color:#6b7689">{{ a.meta }}</div>',
                    '<div style="font-size:.72rem;display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:1px"><sc-if value="{{ a.hasMetaGender }}" hint-placeholder-val="{{ false }}"><span style="{{ a.metaGenderStyle }}">{{ a.metaGender }}</span></sc-if><sc-if value="{{ a.hasMetaAge }}" hint-placeholder-val="{{ false }}"><span aria-hidden="true" style="color:#97a1b4">·</span><span style="color:#111827">{{ a.metaAge }}</span></sc-if><sc-if value="{{ a.hasMetaNat }}" hint-placeholder-val="{{ false }}"><span aria-hidden="true" style="color:#97a1b4">·</span><span style="display:inline-flex;align-items:center;gap:5px;color:#1e2c4f"><span>{{ a.metaNat }}</span><sc-if value="{{ a.hasMetaFlag }}" hint-placeholder-val="{{ false }}"><span role="img" aria-label="{{ a.metaNat }}" style="{{ a.metaFlagStyle }}"></span></sc-if></span></sc-if></div><sc-if value="{{ a.hasMetaClub }}" hint-placeholder-val="{{ false }}"><div style="font-size:.72rem;color:#6b7689;display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:3px"><sc-if value="{{ a.hasRowClubLogo }}" hint-placeholder-val="{{ false }}"><span role="img" aria-label="{{ a.metaClub }}" style="{{ a.rowClubLogoStyle }}"></span></sc-if><span style="font-weight:600;color:#1e2c4f">{{ a.metaClub }}</span><sc-if value="{{ a.hasMetaArts }}" hint-placeholder-val="{{ false }}"><span aria-hidden="true" style="color:#97a1b4">·</span><span style="color:#8a6d20;font-weight:600">{{ a.metaArts }}</span></sc-if></div></sc-if>', 1],
                'athletes-data' => [["    const athData = [\n      {name:'Yusuf Al Doseri'", "\n    ];\n    const q = s.athQuery"], "    // The Athletes list is the people the FEDERATION brought in (a TakeOne ID) —\n    // not every member of a club that does karate (asked for 2026-09-26). Club\n    // members stay findable where the federation brings someone in: Enroll\n    // athlete and \"Same person as\u2026\" read the whole directory (athDataAll).\n    const athDataAll = window.TakeOneFed.athleteDirectory();\n    const athData = athDataAll.filter(a => a.tk);\n    const q = s.athQuery", 1],
                'athletes-dedupe' => ["    (s.regs || []).filter(r => String(r.id).indexOf('ext-') === 0).forEach(r => {\n      athData.push({", "    (s.regs || []).filter(r => String(r.id).indexOf('ext-') === 0).forEach(r => {\n      if (!athData.some(x => x.tk === r.takeoneId)) athData.push({", 1],
                // PLATFORM: the officials clubs actually named, not the sample list.
                'officials-data' => [["const offData = [\n      ['Hassan Al Khalifa'", "\n    ];\n    // Officials, drawn"], "const offData = window.TakeOneFed.officials();\n    // Officials, drawn", 1],
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
                // The Athletes list carries Gender, Age and Weight as columns of their
                // own, between National ID and Division (after 'athed-cols').
                'regcol-grid' => ['140px 120px 100px 136px;gap:14px;align-items:center;min-width:1116px', '140px 72px 64px 84px 120px 100px 136px;gap:14px;align-items:center;min-width:1378px', 2],
                // The Weigh-in column, between Status and the actions ('weigh-list-btn').
                'weigh-cols' => ['120px 100px 136px;gap:14px;align-items:center;min-width:1378px', '120px 100px 118px 136px;gap:14px;align-items:center;min-width:1510px', 2],
                // Spread the columns across the card (owner, 2026-10-15): each keeps
                // its old width as a minimum and shares the spare room, instead of
                // the Athlete column taking all of it.
                'regcol-spread' => ['24px 118px 36px minmax(200px,1fr) 175px 140px 72px 64px 84px 120px 100px 118px 136px;gap:14px;',
                    '24px minmax(118px,.9fr) 36px minmax(200px,1.8fr) minmax(175px,1.3fr) minmax(140px,1fr) minmax(72px,.55fr) minmax(64px,.5fr) minmax(84px,.6fr) minmax(120px,1.1fr) minmax(100px,.75fr) minmax(118px,.85fr) minmax(136px,.9fr);gap:16px;', 2],
                'regcol-head' => ['uppercase;color:#97a1b4">National ID</div>'."\n".'              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Division</div>',
                    'uppercase;color:#97a1b4">National ID</div>'."\n"
                    .'              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Gender</div>'."\n"
                    .'              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Age</div>'."\n"
                    .'              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Weight</div>'."\n"
                    .'              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Division</div>', 1],
                'regcol-cell' => ['<div style="font-size:.76rem;color:#1e2c4f">{{ r.natId }}</div>',
                    '<div style="font-size:.76rem;color:#1e2c4f">{{ r.natId }}</div>'."\n"
                    .'                <div style="{{ r.genderColStyle }}">{{ r.genderCol }}</div>'."\n"
                    .'                <div style="font-size:.76rem;color:#1e2c4f;font-variant-numeric:tabular-nums">{{ r.ageCol }}</div>'."\n"
                    .'                <div style="font-size:.76rem;color:#1e2c4f;white-space:nowrap;font-variant-numeric:tabular-nums">{{ r.weightLabel }}</div>', 1],
                'regcol-vals' => ["weightLabel: r.weight ? r.weight + ' kg' : '\\u2014',", "weightLabel: r.weight ? r.weight + ' kg' : '\\u2014', ageCol: (r.age === 0 || (r.age && r.age !== '\\u2014')) ? String(r.age) : '\\u2014', genderCol: /^f/i.test(r.gender || '') ? 'Female' : /^m/i.test(r.gender || '') ? 'Male' : '\\u2014', genderColStyle: this.cssToObj('font-size:.76rem;font-weight:600;color:' + (/^f/i.test(r.gender || '') ? '#db2777' : /^m/i.test(r.gender || '') ? '#2563eb' : '#6b7689')),", 1],
                // Sort by any column (click a header; again to reverse) and
                // narrow by gender / status with chips. Blanks always sort last.
                'regsort-filter' => ["    const filteredRegs = s.regs.filter(r => !rq || [r.name, r.mobile, r.email, r.club, r.natId, r.nat, r.takeoneId].some(v => v.toLowerCase().includes(rq)));", <<<'JS'
    const rfG = s.regFGender || 'all', rfS = s.regFStatus || 'all', rsBy = s.regSort || '', rsDir = s.regSortDir || 1;
    // Club filter (owner, 2026-10-16): one dropdown, "All" first and chosen by default.
    const rfC = s.regFClub || 'all';
    // "No picture" (owner, 2026-10-16): only the athletes with no photo on file.
    const rfP = !!s.regFNoPhoto;
    // Age / weight ranges: typed text, read as numbers; an empty box is no limit.
    const rfR = {ageMin: s.regFAgeMin || '', ageMax: s.regFAgeMax || '', wMin: s.regFWMin || '', wMax: s.regFWMax || ''};
    const rfNum = v => { const n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? null : n; };
    const rfIn = (v, lo, hi) => { lo = rfNum(lo); hi = rfNum(hi); if (lo === null && hi === null) return true; const n = rfNum(v == null ? '' : String(v).replace(/[^0-9.,]/g, '')); return n !== null && (lo === null || n >= lo) && (hi === null || n <= hi); };
    const rIsF = r => /^f/i.test(r.gender || ''), rIsM = r => /^m/i.test(r.gender || '');
    const rNum = v => { const n = parseFloat(String(v == null ? '' : v).replace(/[^0-9.]/g, '')); return isNaN(n) ? null : n; };
    const regKeys = {
      id: r => r.takeoneId === 'Pending' ? '' : String(r.takeoneId || ''), name: r => String(r.name || ''), contact: r => String(r.mobile || ''),
      natId: r => String(r.natId || ''), gender: r => rIsF(r) ? 'Female' : rIsM(r) ? 'Male' : '',
      age: r => rNum(r.age), weight: r => rNum(r.weight), division: r => String(r.divisionName || r.division || ''), status: r => String(r.status || '')
    };
    const rKey = regKeys[rsBy];
    const rBlank = v => v == null || v === '' || v === '—';
    const filteredRegs = s.regs.filter(r => !rq || [r.name, r.mobile, r.email, r.club, r.natId, r.nat, r.takeoneId].some(v => String(v || '').toLowerCase().includes(rq)))
      .filter(r => rfG === 'all' || (rfG === 'F' ? rIsF(r) : rIsM(r)))
      .filter(r => rfS === 'all' || (rfS === 'Approved' ? r.status === 'Approved' : r.status !== 'Approved'))
      .filter(r => rfIn(r.age, rfR.ageMin, rfR.ageMax) && rfIn(r.weight, rfR.wMin, rfR.wMax))
      .filter(r => rfC === 'all' || String(r.club || '') === rfC)
      .filter(r => !rfP || !r.photo)
      .sort((a, b) => {
        if (!rKey) return 0;
        const x = rKey(a), y = rKey(b), bx = rBlank(x), by = rBlank(y);
        if (bx || by) return (bx ? 1 : 0) - (by ? 1 : 0);
        return rsDir * (typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y), undefined, {numeric: true, sensitivity: 'base'}));
      });
    const regSortVals = {};
    ['id', 'name', 'contact', 'natId', 'gender', 'age', 'weight', 'division', 'status'].forEach(k => {
      const on = rsBy === k;
      regSortVals[k] = {
        arrow: on ? (rsDir > 0 ? '▲' : '▼') : '',
        ariaSort: on ? (rsDir > 0 ? 'ascending' : 'descending') : 'none',
        onClick: () => this.setState({regSort: k, regSortDir: on ? -rsDir : 1}),
        style: this.cssToObj('display:inline-flex;align-items:center;gap:4px;padding:0;border:0;background:none;font-family:inherit;font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;text-align:start;color:' + (on ? '#1e2c4f;font-weight:700' : '#97a1b4'))
      };
    });
    const rChip = (on, color) => this.cssToObj('height:32px;padding:0 14px;border-radius:999px;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .15s,border-color .15s;'
      + (on ? 'background:#fdf6e6;border:1px solid #caa14f;box-shadow:0 2px 8px rgba(202,161,79,.22);' : 'background:#fff;border:1px solid #d5dae3;') + 'color:' + (color || (on ? '#8a6d20' : '#5d6880')));
    const regFilterVals = {
      gender: [['all', 'All', ''], ['M', 'Male', '#2563eb'], ['F', 'Female', '#db2777']].map(([id, label, c]) => ({label, on: rfG === id, style: rChip(rfG === id, c), onClick: () => this.setState({regFGender: id})})),
      status: [['all', 'All'], ['Approved', 'Approved'], ['Pending', 'Pending']].map(([id, label]) => ({label, on: rfS === id, style: rChip(rfS === id), onClick: () => this.setState({regFStatus: id})})),
      ...(() => {
        const box = v => this.cssToObj('height:32px;width:52px;min-width:0;max-width:52px;box-sizing:border-box;padding:0 6px;border-radius:999px;font-family:inherit;font-size:.74rem;font-weight:600;text-align:center;color:#1e2c4f;outline:none;font-variant-numeric:tabular-nums;'
          + (v !== '' ? 'background:#fdf6e6;border:1px solid #caa14f' : 'background:#fff;border:1px solid #d5dae3'));
        const clean = v => String(v).replace(/[^0-9.,]/g, '').slice(0, 5);
        const f = (k, key) => ({value: rfR[k], style: box(rfR[k]), onChange: e => this.setState({[key]: clean(e.target.value)})});
        return {ageMin: f('ageMin', 'regFAgeMin'), ageMax: f('ageMax', 'regFAgeMax'), wMin: f('wMin', 'regFWMin'), wMax: f('wMax', 'regFWMax')};
      })(),
      ...(() => {
        // Every club with an athlete on this event, A–Z, each with its own logo.
        const seen = {};
        s.regs.forEach(r => { const n = String(r.club || ''); if (n && n !== '\u2014' && !seen[n]) seen[n] = r.logo || null; });
        const names = Object.keys(seen).sort((a, b) => a.localeCompare(b, undefined, {sensitivity: 'base'}));
        const logo = url => url ? Object.assign(this.cssToObj('width:22px;height:22px;flex:none;background-size:contain;background-repeat:no-repeat;background-position:center'), {backgroundImage: 'url(' + JSON.stringify(url) + ')'}) : {};
        const mark = this.cssToObj('width:22px;height:22px;flex:none;border-radius:50%;background:#eef1f6;color:#8a93a6;display:grid;place-items:center;font-size:.62rem;font-weight:800');
        const item = (key, label, url, all) => ({key, label, on: rfC === key, check: rfC === key ? '\u2713' : '',
          hasLogo: !!url, noLogo: !url && !all, isAll: all, logoStyle: logo(url), markStyle: mark, initial: String(label).trim().charAt(0).toUpperCase(),
          style: this.cssToObj('width:100%;display:flex;align-items:center;gap:10px;padding:8px 10px;border:0;border-radius:10px;font-family:inherit;font-size:.78rem;cursor:pointer;text-align:start;'
            + (rfC === key ? 'background:#fdf6e6;color:#8a6d20;font-weight:700' : 'background:transparent;color:#1e2c4f;font-weight:600')),
          onClick: () => this.setState({regFClub: key, regFClubOpen: false})});
        const open = !!s.regFClubOpen, cur = rfC === 'all' ? null : rfC;
        return {club: {
          open, caret: open ? '\u25B4' : '\u25BE', label: cur || 'All', hasLogo: !!(cur && seen[cur]), logoStyle: logo(cur ? seen[cur] : null),
          style: this.cssToObj('display:inline-flex;align-items:center;gap:8px;height:32px;max-width:240px;padding:0 12px;border-radius:999px;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .15s,border-color .15s;'
            + (cur ? 'background:#fdf6e6;border:1px solid #caa14f;box-shadow:0 2px 8px rgba(202,161,79,.22);color:#8a6d20' : 'background:#fff;border:1px solid #d5dae3;color:#5d6880')),
          items: [item('all', 'All', null, true)].concat(names.map(n => item(n, n, seen[n], false))),
          onToggle: e => { if (e && e.stopPropagation) e.stopPropagation(); this.setState({regFClubOpen: !open}); },
          onClose: () => this.setState({regFClubOpen: false}),
          stop: e => { if (e && e.stopPropagation) e.stopPropagation(); }
        }};
      })(),
      noPhoto: {on: rfP, checked: String(rfP), tick: rfP ? '\u2713' : '',
        style: this.cssToObj('display:inline-flex;align-items:center;gap:8px;height:32px;padding:0 14px 0 10px;border-radius:999px;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .15s,border-color .15s;'
          + (rfP ? 'background:#fdf6e6;border:1px solid #caa14f;box-shadow:0 2px 8px rgba(202,161,79,.22);color:#8a6d20' : 'background:#fff;border:1px solid #d5dae3;color:#5d6880')),
        boxStyle: this.cssToObj('width:18px;height:18px;flex:none;box-sizing:border-box;border-radius:5px;display:grid;place-items:center;font-size:.7rem;font-weight:800;line-height:1;'
          + (rfP ? 'background:#caa14f;border:1px solid #caa14f;color:#fff' : 'background:#fff;border:1.5px solid #c3cad6;color:transparent')),
        onClick: () => this.setState({regFNoPhoto: !rfP})},
      any: rfP || rfG !== 'all' || rfS !== 'all' || rfC !== 'all' || !!rsBy || Object.values(rfR).some(v => v !== ''),
      onClear: () => this.setState({regFGender: 'all', regFStatus: 'all', regFClub: 'all', regFClubOpen: false, regFNoPhoto: false, regSort: '', regSortDir: 1, regFAgeMin: '', regFAgeMax: '', regFWMin: '', regFWMax: ''}),
      count: filteredRegs.length + ' of ' + s.regs.length
    };
JS, 1],
                'regsort-vals' => ['regsEmpty: regs.length === 0,', 'regsEmpty: regs.length === 0, rs: regSortVals, rf: regFilterVals,', 1],
                'regsort-head' => [<<<'HTML'
              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Takeone ID</div>
              <div></div>
              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Athlete</div>
              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Contact</div>
              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">National ID</div>
              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Gender</div>
              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Age</div>
              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Weight</div>
              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Division</div>
              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Status</div>
HTML, <<<'HTML'
              <div aria-sort="{{ rs.id.ariaSort }}"><button type="button" onClick="{{ rs.id.onClick }}" style="{{ rs.id.style }}"><span>Takeone ID</span><span>{{ rs.id.arrow }}</span></button></div>
              <div></div>
              <div aria-sort="{{ rs.name.ariaSort }}"><button type="button" onClick="{{ rs.name.onClick }}" style="{{ rs.name.style }}"><span>Athlete</span><span>{{ rs.name.arrow }}</span></button></div>
              <div aria-sort="{{ rs.contact.ariaSort }}"><button type="button" onClick="{{ rs.contact.onClick }}" style="{{ rs.contact.style }}"><span>Contact</span><span>{{ rs.contact.arrow }}</span></button></div>
              <div aria-sort="{{ rs.natId.ariaSort }}"><button type="button" onClick="{{ rs.natId.onClick }}" style="{{ rs.natId.style }}"><span>National ID</span><span>{{ rs.natId.arrow }}</span></button></div>
              <div aria-sort="{{ rs.gender.ariaSort }}"><button type="button" onClick="{{ rs.gender.onClick }}" style="{{ rs.gender.style }}"><span>Gender</span><span>{{ rs.gender.arrow }}</span></button></div>
              <div aria-sort="{{ rs.age.ariaSort }}"><button type="button" onClick="{{ rs.age.onClick }}" style="{{ rs.age.style }}"><span>Age</span><span>{{ rs.age.arrow }}</span></button></div>
              <div aria-sort="{{ rs.weight.ariaSort }}"><button type="button" onClick="{{ rs.weight.onClick }}" style="{{ rs.weight.style }}"><span>Weight</span><span>{{ rs.weight.arrow }}</span></button></div>
              <div aria-sort="{{ rs.division.ariaSort }}"><button type="button" onClick="{{ rs.division.onClick }}" style="{{ rs.division.style }}"><span>Division</span><span>{{ rs.division.arrow }}</span></button></div>
              <div aria-sort="{{ rs.status.ariaSort }}"><button type="button" onClick="{{ rs.status.onClick }}" style="{{ rs.status.style }}"><span>Status</span><span>{{ rs.status.arrow }}</span></button></div>
HTML, 1],
                'weigh-head' => ['<span>Status</span><span>{{ rs.status.arrow }}</span></button></div>'."\n".'              <div></div>',
                    '<span>Status</span><span>{{ rs.status.arrow }}</span></button></div>'."\n".'              <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#97a1b4">Weigh-in</div>'."\n".'              <div></div>', 1],
                'regfilter-chips' => ['          <sc-if value="{{ regsEmpty }}" hint-placeholder-val="{{ false }}">', <<<'HTML'
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0 0 14px;padding:10px 12px;border-radius:13px;background:#f7f8fb;border:1px solid #eef1f6">
            <span style="font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#8a93a6;margin-inline-end:2px">Gender</span>
            <sc-for list="{{ rf.gender }}" as="c" hint-placeholder-count="3"><button type="button" onClick="{{ c.onClick }}" aria-pressed="{{ c.on }}" style="{{ c.style }}">{{ c.label }}</button></sc-for>
            <span aria-hidden="true" style="width:1px;height:22px;background:#d5dae3;margin:0 6px"></span>
            <span style="font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#8a93a6;margin-inline-end:2px">Status</span>
            <sc-for list="{{ rf.status }}" as="c" hint-placeholder-count="3"><button type="button" onClick="{{ c.onClick }}" aria-pressed="{{ c.on }}" style="{{ c.style }}">{{ c.label }}</button></sc-for>
            <span aria-hidden="true" style="width:1px;height:22px;background:#d5dae3;margin:0 6px"></span>
            <span style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap">
            <span style="font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#8a93a6;margin-inline-end:2px">Age</span>
            <input inputmode="decimal" aria-label="Minimum age" placeholder="Min" value="{{ rf.ageMin.value }}" onChange="{{ rf.ageMin.onChange }}" style="{{ rf.ageMin.style }}">
            <span aria-hidden="true" style="color:#97a1b4;font-size:.74rem">&ndash;</span>
            <input inputmode="decimal" aria-label="Maximum age" placeholder="Max" value="{{ rf.ageMax.value }}" onChange="{{ rf.ageMax.onChange }}" style="{{ rf.ageMax.style }}">
            </span>
            <span aria-hidden="true" style="width:1px;height:22px;background:#d5dae3;margin:0 6px"></span>
            <span style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap">
            <span style="font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#8a93a6;margin-inline-end:2px">Weight</span>
            <input inputmode="decimal" aria-label="Minimum weight in kg" placeholder="Min" value="{{ rf.wMin.value }}" onChange="{{ rf.wMin.onChange }}" style="{{ rf.wMin.style }}">
            <span aria-hidden="true" style="color:#97a1b4;font-size:.74rem">&ndash;</span>
            <input inputmode="decimal" aria-label="Maximum weight in kg" placeholder="Max" value="{{ rf.wMax.value }}" onChange="{{ rf.wMax.onChange }}" style="{{ rf.wMax.style }}">
            <span style="font-size:.7rem;color:#97a1b4">kg</span>
            </span>
            <span aria-hidden="true" style="width:1px;height:22px;background:#d5dae3;margin:0 6px"></span>
            <span style="font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#8a93a6;margin-inline-end:2px">Club</span>
            <div style="position:relative">
              <button type="button" onClick="{{ rf.club.onToggle }}" aria-haspopup="menu" aria-expanded="{{ rf.club.open }}" aria-label="Filter by club" style="{{ rf.club.style }}"><sc-if value="{{ rf.club.hasLogo }}" hint-placeholder-val="{{ false }}"><span aria-hidden="true" style="{{ rf.club.logoStyle }}"></span></sc-if><span style="overflow:hidden;text-overflow:ellipsis">{{ rf.club.label }}</span><span aria-hidden="true" style="font-size:.7rem">{{ rf.club.caret }}</span></button>
              <sc-if value="{{ rf.club.open }}" hint-placeholder-val="{{ false }}">
                <div onClick="{{ rf.club.onClose }}" style="position:fixed;inset:0;z-index:45"></div>
                <div role="menu" onClick="{{ rf.club.stop }}" style="position:absolute;top:38px;left:0;z-index:46;min-width:240px;max-width:min(320px,calc(100vw - 2rem));max-height:340px;overflow-y:auto;padding:6px;background:#fff;border:1px solid #eef1f6;border-radius:14px;box-shadow:0 18px 40px rgba(30,44,79,.16);animation:rise .18s cubic-bezier(.22,.68,.35,1) both">
                  <sc-for list="{{ rf.club.items }}" as="c" hint-placeholder-count="4"><button type="button" role="menuitemradio" aria-checked="{{ c.on }}" onClick="{{ c.onClick }}" style="{{ c.style }}" style-hover="background:#fdf6e6"><sc-if value="{{ c.hasLogo }}" hint-placeholder-val="{{ false }}"><span aria-hidden="true" style="{{ c.logoStyle }}"></span></sc-if><sc-if value="{{ c.noLogo }}" hint-placeholder-val="{{ false }}"><span aria-hidden="true" style="{{ c.markStyle }}">{{ c.initial }}</span></sc-if><span style="flex:1;min-width:0;overflow-wrap:anywhere">{{ c.label }}</span><span aria-hidden="true">{{ c.check }}</span></button></sc-for>
                </div>
              </sc-if>
            </div>
            <span aria-hidden="true" style="width:1px;height:22px;background:#d5dae3;margin:0 6px"></span>
            <button type="button" role="checkbox" aria-checked="{{ rf.noPhoto.checked }}" onClick="{{ rf.noPhoto.onClick }}" style="{{ rf.noPhoto.style }}"><span aria-hidden="true" style="{{ rf.noPhoto.boxStyle }}">{{ rf.noPhoto.tick }}</span>No picture</button>
            <span style="margin-inline-start:auto;display:inline-flex;align-items:center;gap:10px">
              <span style="font-size:.74rem;color:#6b7689;font-variant-numeric:tabular-nums">{{ rf.count }}</span>
              <sc-if value="{{ rf.any }}" hint-placeholder-val="{{ false }}"><button type="button" onClick="{{ rf.onClear }}" style="height:32px;padding:0 12px;border:0;border-radius:999px;background:none;font-family:inherit;font-size:.74rem;font-weight:700;color:#8a6d20;cursor:pointer;text-decoration:underline;text-underline-offset:3px">Reset</button></sc-if>
            </span>
          </div>
          <sc-if value="{{ regsEmpty }}" hint-placeholder-val="{{ false }}">
HTML, 1],
                // The Participants table drops its Contact and Status columns
                // (owner, 2026-10-16). Header and cell go together, and the grid
                // loses the same two tracks, so every column stays under its head.
                // Contact stays searchable; the Status filter chips stay.
                'regcol-drop-grid' => ['24px minmax(118px,.9fr) 36px minmax(200px,1.8fr) minmax(175px,1.3fr) minmax(140px,1fr) minmax(72px,.55fr) minmax(64px,.5fr) minmax(84px,.6fr) minmax(120px,1.1fr) minmax(100px,.75fr) minmax(118px,.85fr) minmax(136px,.9fr);gap:16px;align-items:center;min-width:1510px',
                    '24px minmax(118px,.9fr) 36px minmax(200px,1.8fr) minmax(140px,1fr) minmax(72px,.55fr) minmax(64px,.5fr) minmax(84px,.6fr) minmax(120px,1.1fr) minmax(118px,.85fr) minmax(136px,.9fr);gap:16px;align-items:center;min-width:1210px', 2],
                'regcol-drop-contact-head' => ['              <div aria-sort="{{ rs.contact.ariaSort }}"><button type="button" onClick="{{ rs.contact.onClick }}" style="{{ rs.contact.style }}"><span>Contact</span><span>{{ rs.contact.arrow }}</span></button></div>'."\n", '', 1],
                'regcol-drop-status-head' => ['              <div aria-sort="{{ rs.status.ariaSort }}"><button type="button" onClick="{{ rs.status.onClick }}" style="{{ rs.status.style }}"><span>Status</span><span>{{ rs.status.arrow }}</span></button></div>'."\n", '', 1],
                'regcol-drop-contact-cell' => ['                <div style="min-width:0">'."\n".'                  <div style="font-size:.76rem;color:#1e2c4f">{{ r.mobile }}</div>'."\n".'                  <div style="font-size:.72rem;color:#6b7689;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.email }}</div>'."\n".'                </div>'."\n", '', 1],
                'regcol-drop-status-cell' => ['                <div><span style="{{ r.pillStyle }}">{{ r.status }}</span></div>'."\n", '', 1],
            ],
            'Karate Statistics.dc.html' => $this->statisticsRewrites() + $this->statisticsLayoutRewrites() + [
                'font' => ['https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap', './vendor/fonts/poppins.css', 1],
                'leaflet-css' => ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', './vendor/leaflet/leaflet.css', 1],
                'leaflet-js' => ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', './vendor/leaflet/leaflet.js', 1],
            ],
            'Club Invitation.dc.html' => $flags + [
                'preconnect' => ['<link rel="preconnect" href="https://fonts.googleapis.com">', '', 1],
                // PLATFORM: the store is the server's (same function names).
                'store' => ['<script src="./federation-store.js"></script>', '<script src="./federation-store.platform.js?v='.$this->version('federation-store.platform.js').'"></script>', 1],
                // PLATFORM: a real code goes by email; nothing on screen gives it away,
                // and whether a contact belongs to a club is never revealed. (The
                // club step itself — find by name, send, verify — and the one-shot
                // submit are now written into the page, not rewritten into it.)
                'code-sent-en' => ["idSentTo: 'Code sent to {c}.'", "idSentTo: 'If a club is registered with {c}, a code is on its way to the club’s email.'", 1],
                'code-sent-ar' => ["idSentTo: 'تم إرسال الرمز إلى {c}.'", "idSentTo: 'إذا كان هناك نادٍ مسجّل بـ {c} فقد أُرسل الرمز إلى بريد النادي.'", 1],
                // PLATFORM: a personal link was proved by the server; so was a
                // session the coach already opened — both come back signed in.
                'club-link' => ['const c = id && window.FedStore.club(id); if (c) this.loadClub(c, true);', 'const vk = window.FedStore.verifiedClub(); const c = vk && window.FedStore.club(vk); if (c) this.loadClub(c, !!id);', 1],
                // PLATFORM: no gateway — entry fees are paid by bank transfer,
                // to the federation that runs the event.
                'club-pay-default' => ["payMethod: 'card'", "payMethod: 'bank'", 1],
                'club-pay-opts' => ["v.payOpts = [['card', t.payCardL, t.payCardS], ['bank', t.payBankL, t.payBankS]]", "v.payOpts = [['bank', t.payBankL, t.payBankS]]", 1],
                // PLATFORM: the event's own words and artwork, not the sample event's.
                'event-texts' => ["    return this.state.lang === 'ar' ? ar : en;\n  }", "    const __tx = window.TakeOneFed && window.TakeOneFed.eventTexts && window.TakeOneFed.eventTexts(); if (__tx) { Object.assign(en, __tx.en); Object.assign(ar, __tx.ar); }\n    return this.state.lang === 'ar' ? ar : en;\n  }", 1],
                'cover-art' => ['<image-slot id="invite-cover-art" shape="rect" placeholder="Drop the competition artwork here"', '<image-slot id="invite-cover-art" shape="rect" placeholder=" " src="{{ coverSrc }}"', 1],
                'cover-art-side' => ['<image-slot id="invite-cover-art" shape="rect" placeholder="Competition artwork"', '<image-slot id="invite-cover-art" shape="rect" placeholder=" " src="{{ coverSrc }}"', 1],
                // PLATFORM: no gateway and no account on screen — the organiser
                // confirms each club's payment themselves (FederationDesk::
                // confirmEntryPayment). The club sees what it owes, the reference
                // the organiser will match it against, and who confirms it.
                'club-pay-panel' => [['<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">', '{{ t.bankNote }}</div>'."\n".'              </sc-if>'], '<div style="display:flex;gap:12px;align-items:flex-start;padding:14px;border-radius:12px;background:#fdf6e6;border:1px solid #f0dfb4;margin-bottom:12px"><span style="width:36px;height:36px;flex:0 0 auto;border-radius:10px;background:#1e2c4f;color:#fff;display:grid;place-items:center;font-size:1rem;font-weight:700">✓</span><div style="min-width:0"><div style="font-size:.84rem;font-weight:700;color:#1e2c4f">{{ t.payBankL }}</div><div style="font-size:.74rem;color:#5d6880;margin-top:2px;line-height:1.5">{{ t.bankNote }}</div></div></div><div style="padding:14px;border-radius:12px;background:#f7f8fb;display:grid;grid-template-columns:auto 1fr;gap:6px 14px;font-size:.8rem"><span style="color:#6b7689">{{ t.reference }}</span><b dir="ltr" style="color:#8a6d20;text-align:start">{{ invRef }}</b><span style="color:#6b7689">{{ t.dueNow }}</span><b dir="ltr" style="text-align:start">{{ dueAmount }}</b></div>', null],
                // PLATFORM: the reference is issued by the server on submit
                // (FederationInvitation::nextRef). Before that there is no number,
                // so say so rather than show a truncated one a club might pay against.
                // PLATFORM: the confirmation sits on the page itself, not in a
                // floating card — no white panel, shadow or gold top edge.
                // PLATFORM: the Location card draws a map on the organiser's pin —
                // Leaflet served from here, OSM tiles as on the dashboard, sending
                // only the origin as Referer. No pin, no map (the link stays).
                'club-leaflet' => ['<script src="./image-slot.js"></script>', '<script src="./image-slot.js"></script>'."\n".'<link rel="stylesheet" href="./vendor/leaflet/leaflet.css">'."\n".'<script src="./vendor/leaflet/leaflet.js"></script>', 1],
                'club-map-el' => ['{{ t.secWhere }}</span></div>', '{{ t.secWhere }}</span></div><sc-if value="{{ hasPin }}" hint-placeholder-val="{{ false }}"><div ref="{{ pinMapRef }}" role="img" aria-label="{{ whereTitle }}" style="height:260px;background:#eef1f6;position:relative;z-index:0"></div></sc-if>', 1],
                // PLATFORM: with a map, the venue's name and the Open-in-maps button
                // ride on the map's lower edge instead of sitting under it.
                'club-where-section' => ['<section id="inv-where" style="background:#fff;border-radius:18px;border:1px solid #e3e7ee;box-shadow:0 10px 30px rgba(30,44,79,.06);overflow:hidden;margin-bottom:14px">', '<section id="inv-where" style="background:#fff;border-radius:18px;border:1px solid #e3e7ee;box-shadow:0 10px 30px rgba(30,44,79,.06);overflow:hidden;margin-bottom:14px;position:relative">', 1],
                'club-where-row' => ['<div style="padding:14px 18px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">', '<div style="{{ whereRowStyle }}">', 1],
                'club-where-title' => ['<span style="flex:1;min-width:180px"><span style="display:block;font-size:.9rem;font-weight:700;color:#1e2c4f">{{ whereTitle }}</span>', '<span style="{{ whereNameStyle }}"><span style="{{ whereTitleStyle }}">{{ whereTitle }}</span>', 1],
                'club-where-sub' => ['<span style="display:block;font-size:.76rem;color:#6b7689;margin-top:2px">{{ whereSub }}</span>', '<span style="{{ whereSubStyle }}">{{ whereSub }}</span>', 1],
                'club-where-btn' => ['<a href="{{ mapHref }}" target="_blank" rel="noopener" style="display:inline-flex;', '<a href="{{ mapHref }}" target="_blank" rel="noopener" style="pointer-events:auto;display:inline-flex;', 1],
                'club-map-vals' => ["v.hasMap = /^https?:\\/\\//i.test(t.mapUrl || ''); v.mapHref = v.hasMap ? t.mapUrl : '#';", "v.hasMap = /^https?:\\/\\//i.test(t.mapUrl || ''); v.mapHref = v.hasMap ? t.mapUrl : '#'; const __pl = parseFloat(t.mapLat), __pg = parseFloat(t.mapLng); v.hasPin = isFinite(__pl) && isFinite(__pg) && Math.abs(__pl) <= 90 && Math.abs(__pg) <= 180; v.whereRowStyle = css(v.hasPin ? 'position:absolute;left:0;right:0;bottom:0;z-index:500;padding:56px 16px 14px;display:flex;align-items:center;gap:12px;background:linear-gradient(to top,rgba(11,19,43,.92),rgba(11,19,43,.55) 55%,rgba(11,19,43,0));pointer-events:none' : 'padding:14px 18px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap'); v.whereNameStyle = css(v.hasPin ? 'flex:1;min-width:0;overflow-wrap:anywhere' : 'flex:1;min-width:180px'); v.whereTitleStyle = css('display:block;font-size:.9rem;font-weight:700;color:' + (v.hasPin ? '#fff;text-shadow:0 1px 3px rgba(0,0,0,.35)' : '#1e2c4f')); v.whereSubStyle = css('display:block;font-size:.76rem;margin-top:2px;color:' + (v.hasPin ? 'rgba(255,255,255,.8)' : '#6b7689')); v.pinMapRef = el => { if (!el || el._leaflet_id || !v.hasPin) return; const init = n => { if (typeof L === 'undefined') { if (n < 40) setTimeout(() => init(n + 1), 150); return; } if (el._leaflet_id || !el.isConnected) return; L.Icon.Default.imagePath = new URL('./vendor/leaflet/images/', location.href).href; const m = L.map(el, {scrollWheelZoom: false, dragging: !L.Browser.mobile, tap: false, zoomControl: false, attributionControl: false}).setView([__pl, __pg], 15); L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; <a href=\"https://www.openstreetmap.org/copyright\" target=\"_blank\" rel=\"noopener\">OpenStreetMap</a> contributors', referrerPolicy: 'strict-origin-when-cross-origin', maxZoom: 19}).addTo(m); L.marker([__pl, __pg], {keyboard: false}).addTo(m); setTimeout(() => m.invalidateSize(), 250); }; init(0); };", 1],
                // PLATFORM: on a phone the page body rides 22px up over the cover,
                // which swallowed the gap under the date line — keep it clear.
                'club-cover-gap' => ['<div style="position:absolute;left:0;right:0;bottom:0;padding:clamp(20px,4vw,40px);color:#fff;pointer-events:none">', '<div style="position:absolute;left:0;right:0;bottom:0;padding:clamp(20px,4vw,40px);padding-bottom:max(clamp(20px,4vw,40px),46px);color:#fff;pointer-events:none">', 1],
                // PLATFORM: the opening page doesn't list the requirements — they
                // are read (and accepted) on the Rules step, which still shows them.
                'club-no-landing-reqs' => ["v.hasReqs = (t.reqList || []).length > 0;", "v.hasReqs = false;", 1],
                // PLATFORM: the club's own entries page. What is still to settle is
                // one figure (not "0.000 · to settle 30.000"), a zero is not green,
                // and the history no longer says "Paid" when fees were only raised:
                // the server logs both as logPaid, told apart by their method.
                'club-manage-stats' => ['      v.mgStats = [[t.stAthletes, String(act.length), \'#1e2c4f\'], [t.stTotal, this.money(inv0.total), \'#1e2c4f\'], [t.stPaid, this.money(inv0.paid), \'#2f7a4f\'],
        [inv0.balance < 0 ? t.credit : t.stBalance, this.money(Math.abs(inv0.balance - 0)) + (inv0.pending ? \' · \' + t.awaiting + \' \' + this.money(inv0.pending) : \'\'), inv0.balance > 0 ? \'#b4483c\' : \'#2f7a4f\']].map(([label, value, c]) => ({label, value, style: statStyle(c)}));', '      const __owe = Math.max(0, inv0.balance) + (inv0.pending || 0);
      v.mgStats = [[t.stAthletes, String(act.length), \'#1e2c4f\'], [t.stTotal, this.money(inv0.total), \'#1e2c4f\'], [t.stPaid, this.money(inv0.paid), inv0.paid > 0 ? \'#2f7a4f\' : \'#1e2c4f\'],
        inv0.balance < -0.0005 ? [t.credit, this.money(-inv0.balance), \'#2f7a4f\'] : __owe > 0.0005 ? [t.awaiting, this.money(__owe), \'#a8620f\'] : [t.stBalance, this.money(0), \'#2f7a4f\']].map(([label, value, c]) => ({label, value, style: statStyle(c)}));', 1],
                'club-manage-log' => ['text: (t[g.key] || g.key).replace(\'{n}\', g.n || \'\')', 'text: (t[g.key === \'logPaid\' ? (g.m === \'Bank transfer\' ? \'logDue\' : \'logReceived\') : g.key] || g.key).replace(\'{n}\', g.n || \'\')', 1],
                'club-log-en' => ["logPaid: 'Paid {a} ({m})'", "logPaid: 'Paid {a} ({m})', logDue: 'Fees of {a} to settle with the organiser', logReceived: 'Organiser recorded payment of {a}'", 1],
                'club-log-ar' => ["logPaid: 'دفع {a} ({m})'", "logPaid: 'دفع {a} ({m})', logDue: 'رسوم بقيمة {a} تُسدَّد للمنظّم', logReceived: 'سجّل المنظّم استلام {a}'", 1],
                // PLATFORM: the entries page on a phone — the reference on a line of
                // its own (never broken mid-code), no "Male" twice, Withdraw beside the athlete.
                'club-manage-row' => ['<div style="flex:1;min-width:180px"><div style="font-size:.88rem;font-weight:600">{{ a.name }}', '<div style="flex:1;min-width:0"><div style="font-size:.88rem;font-weight:600">{{ a.name }}', 1],
                'club-manage-meta' => ['meta: (a.gender === \'Female\' ? t.female : t.male) + \' · \' + a.weight + \' kg · \' + (this.classify(a) || \'\'),', 'meta: (this.classify(a) ? a.weight + \' kg · \' + this.classify(a) : (a.gender === \'Female\' ? t.female : t.male) + \' · \' + a.weight + \' kg\'),', 1],
                'club-manage-tk' => ['<span style="font-size:.7rem;color:#6b7689;font-weight:500" dir="ltr">{{ a.tk }}</span>', '<span style="font-size:.7rem;color:#6b7689;font-weight:500;white-space:nowrap;display:inline-block" dir="ltr">{{ a.tk }}</span>', 1],
                // PLATFORM: the entries page opens with the SAME bar every step has —
                // back, eyebrow, title, language — sticky on a phone, not a new header.
                'club-manage-head' => [['<sc-if value="{{ isManage }}" hint-placeholder-val="{{ false }}">', '      <div style="{{ phaseStyle }}">{{ phaseMsg }}</div>'], '<sc-if value="{{ isManage }}" hint-placeholder-val="{{ false }}">
  <div style="min-height:100vh;box-sizing:border-box;display:flex;flex-direction:column;align-items:center">
    <div style="{{ mgTopBar }}">
      <div style="display:flex;align-items:center;gap:10px">
        <button onClick="{{ mgBack }}" aria-label="{{ t.back }}" style="width:40px;height:40px;flex:none;border-radius:12px;background:#fff;border:1px solid #d5dae3;color:#1e2c4f;font-weight:600;cursor:pointer;display:grid;place-items:center;font-size:1rem">{{ backArrow }}</button>
        <div style="flex:1;min-width:0">
          <div style="font-size:.66rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:#8a6d20">{{ t.yourEntries }}</div>
          <div style="font-size:1.08rem;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ clubName }}</div>
        </div>
        <div style="display:flex;gap:2px;padding:3px;border-radius:999px;background:#eef1f6;box-shadow:none;pointer-events:auto">
          <sc-for list="{{ langs }}" as="l" hint-placeholder-count="2"><button onClick="{{ l.onClick }}" aria-pressed="{{ l.on }}" style="{{ l.style }}">{{ l.label }}</button></sc-for>
        </div>
      </div>
    </div>
    <div style="{{ mgBody }}">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
        <div style="{{ logoSmallStyle }}"></div>
        <div style="flex:1;min-width:0"><div style="font-size:.84rem;font-weight:600;color:#1e2c4f">{{ t.eventName }}</div><div style="font-size:.74rem;color:#8a6d20;font-weight:600;margin-top:2px;white-space:nowrap" dir="ltr">{{ invRef }}</div></div>
      </div>
      <div style="{{ phaseStyle }}">{{ phaseMsg }}</div>', null],
                'club-manage-back-val' => ['      v.invRef = baseEntry.ref;
      v.startAdd = ', '      v.invRef = baseEntry.ref;
      v.mgBack = () => this.go(0); v.mgTopBar = css(D ? \'width:100%;max-width:820px;padding:40px 40px 0;box-sizing:border-box\' : \'width:100%;box-sizing:border-box;position:sticky;top:0;z-index:30;background:rgba(238,241,246,.96);backdrop-filter:blur(10px);padding:12px 16px;border-bottom:1px solid #e3e7ee\'); v.mgBody = css(\'width:100%;max-width:820px;box-sizing:border-box;padding:\' + (D ? \'22px 40px 40px\' : \'18px 16px 40px\'));
      v.startAdd = ', 1],
                'club-done-flat' => ['<div style="width:100%;max-width:520px;background:#fff;border-radius:22px;border-top:4px solid #d8b25f;padding:clamp(26px,5vw,44px);box-shadow:0 22px 60px rgba(30,44,79,.13);text-align:center;animation:rise .4s cubic-bezier(.22,.68,.35,1) both">', '<div style="width:100%;max-width:520px;padding:clamp(8px,3vw,24px) 0;text-align:center;animation:rise .4s cubic-bezier(.22,.68,.35,1) both">', 1],
                'club-ref-pending' => ["v.invRef = (baseEntry && baseEntry.ref) || (EV.code ? EV.code + '-…' : '');", "v.invRef = (baseEntry && baseEntry.ref) || (rtl ? 'يصدر عند الإرسال' : 'Issued when you submit');", 1],
                'club-pay-label-en' => ["payBankL: 'Bank transfer', payBankS: 'Before the deadline'", "payBankL: 'Confirmed by the organiser', payBankS: ''", 1],
                'club-pay-label-ar' => ["payBankL: 'تحويل بنكي', payBankS: 'قبل الموعد النهائي'", "payBankL: 'يؤكّده المنظّم', payBankS: ''", 1],
                'club-pay-note-en' => ["bankNote: 'Entries are confirmed once the transfer arrives. Use the reference exactly as shown.'", "bankNote: 'Settle the fees with the organiser as they direct. The organiser records the payment on their side — there is nothing more to do here.'", 1],
                'club-pay-note-ar' => ["bankNote: 'تُعتمد التسجيلات عند وصول التحويل. استخدم الرقم المرجعي كما هو.'", "bankNote: 'سدّد الرسوم للمنظّم بالطريقة التي يحدّدها. يسجّل المنظّم الدفعة من جهته — لا شيء آخر مطلوب منك هنا.'", 1],
                'club-pay-done-en' => ["transferMsg: 'Awaiting bank transfer of {a}.'", "transferMsg: '{a} to settle with the organiser.'", 1],
                'club-pay-done-ar' => ["transferMsg: 'بانتظار تحويل بنكي بقيمة {a}.'", "transferMsg: 'يُسدَّد {a} للمنظّم.'", 1],
                'club-pay-awaiting-en' => ["awaiting: 'Awaiting transfer'", "awaiting: 'To settle with the organiser'", 1],
                'club-pay-awaiting-ar' => ["awaiting: 'بانتظار التحويل'", "awaiting: 'يُسدَّد للمنظّم'", 1],
                'font' => ['https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap', './vendor/fonts/poppins-tajawal.css', 1],
            ],
            default => [],
        };
    }
}
