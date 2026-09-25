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
            // Versioned: served with a cache lifetime, so a changed bridge
            // must arrive under a new address or browsers keep the old one.
            .'<script src="./takeone-bridge.js?v='.$this->version('takeone-bridge.js').'"></script>'."\n".$head;

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
            'es-ring-note' => ['Schedule assumes 4-minute slots from 09:00. Referee assignments are not tracked yet.', '{{ es.ringNote }}', 1],
            'es-targets' => ["<sc-if value=\"{{ es.m.isAth }}\" hint-placeholder-val=\"{{ false }}\">\n                <div", "<sc-if value=\"{{ es.m.hasTargets }}\" hint-placeholder-val=\"{{ false }}\">\n                <div", 1],
        ], $file);

        return [
            'es-tab' => ["['overview','Overview'],['regs','Registrations']", "['overview','Overview'],['stats','Statistics'],['regs','Registrations']", 1],
            // Computed only while the tab is open — the simulation is not free.
            'es-vals' => ["tabDraw: s.tab === 'draw', tabFin: s.tab === 'fin',", "tabDraw: s.tab === 'draw', tabFin: s.tab === 'fin', tabStats: s.tab === 'stats', es: s.tab === 'stats' ? this.evStatsVals() : {$blank},", 1],
            'es-methods' => ['  cssToObj(css) {', $methods.'  cssToObj(css) {', 1],
            'es-markup' => ['      <sc-if value="{{ tabFin }}" hint-placeholder-val="{{ false }}">',
                '      <sc-if value="{{ tabStats }}" hint-placeholder-val="{{ false }}">'."\n".$markup."\n      </sc-if>\n".'      <sc-if value="{{ tabFin }}" hint-placeholder-val="{{ false }}">', 1],
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
            'Events Dashboard.dc.html' => $flags + $this->eventStatisticsRewrites() + [
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
                    .'<a href="{{ inviteQrSvg }}" style="display:inline-flex;align-items:center;height:32px;padding:0 14px;border-radius:9px;border:1px solid #d5dae3;background:#fff;color:#1e2c4f;font-size:.74rem;font-weight:600;text-decoration:none">SVG</a></div></div></div></sc-if>', 1],
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
