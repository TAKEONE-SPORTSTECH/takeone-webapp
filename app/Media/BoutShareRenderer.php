<?php

namespace App\Media;

use App\Printables\Support\ChromeRenderer;
use App\Support\Flag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Draws a bout as a file somebody can send (see App\Media\BoutShare).
 *
 * Pure on purpose: a video file and the score bar's own state go in, an MP4
 * comes out. It reads no database and decides nothing about who may have the
 * result — that is the caller's — so it can be exercised against any footage.
 *
 * ── How the score bar gets into the picture ───────────────────────────────
 *
 * The bar only CHANGES at a handful of instants: the start, each round, each
 * point. Each of those states is drawn once by headless Chromium from the bout
 * page's own markup (media::share.scoreboard), with a transparent background,
 * and ffmpeg lays the sequence over the footage for exactly as long as each
 * state held. The clock is the one thing that moves every second, so ffmpeg
 * draws it itself, per round, in the box the bar leaves empty for it.
 *
 * The whole bar is laid down BEFORE any cutting, against the footage's own
 * timeline, so the highlights reel shows the score as it stood at that moment
 * of the bout rather than at that moment of the reel.
 */
class BoutShareRenderer
{
    /** The overlay is drawn at this size and scaled to whatever the output is. */
    private const OVERLAY_W = 1920;
    private const OVERLAY_H = 1080;

    /** The bout page's design canvas is 1150 px wide (see the scoreboard view). */
    private const DESIGN_W = 1150;

    /** More states than this is a log gone wrong, not a bout; cap the screenshots. */
    private const MAX_STATES = 120;

    /** Where the clock sits, as fractions of the frame (measured off the design). */
    private const CLOCK_Y = 0.8541;
    private const CLOCK_SIZE = 0.0526;

    public function __construct(
        private Ffmpeg $ffmpeg,
        private ChromeRenderer $chrome,
    ) {}

    /**
     * Render one kind of bout file.
     *
     * @param  array<string, mixed>  $msb  the bout page's scoreboard payload
     * @return array{ok: bool, error: ?string, bytes: int, duration: int}
     */
    public function render(string $source, array $msb, string $kind, string $outAbs, string $title = ''): array
    {
        $fail = fn (string $why) => ['ok' => false, 'error' => $why, 'bytes' => 0, 'duration' => 0];

        if (! $this->ffmpeg->available()) {
            return $fail('This server has no ffmpeg.');
        }

        if (! is_file($source)) {
            return $fail('The footage could not be read.');
        }

        $probe = $this->ffmpeg->probe($source);
        $duration = (float) ($probe['duration'] ?? 0);

        if ($duration <= 0) {
            return $fail('The footage has no length.');
        }

        $points = $this->points($msb, $duration);

        $windows = $kind === BoutShare::HIGHLIGHTS ? $this->windows($points, $duration) : [];

        if ($kind === BoutShare::HIGHLIGHTS && $windows === []) {
            return $fail('This bout has no scoring moments to cut.');
        }

        [$width, $height] = $this->outputSize((int) ($probe['height'] ?? 0));

        $work = storage_path('app/private/bout-share-work/'.Str::random(24));
        @mkdir($work, 0770, true);

        try {
            $concat = $this->drawStates($msb, $points, $duration, $work);

            if ($concat === null) {
                return $fail('The score bar could not be drawn.');
            }

            $chapters = $work.'/chapters.txt';
            file_put_contents($chapters, $this->chapterFile(
                $kind === BoutShare::HIGHLIGHTS
                    ? $this->highlightChapters($windows, $points, $msb)
                    : $this->fullChapters($points, $msb, $duration),
                $title,
            ));

            @mkdir(dirname($outAbs), 0775, true);
            $partial = $outAbs.'.part.mp4';

            $result = $this->encode(
                $source, $concat, $chapters, $partial,
                $msb, $windows, $duration, $width, $height,
                $this->ffmpeg->hasAudio($source),
                $this->ffmpeg->gpuUsable(),
            );

            if (! $result['ok']) {
                @unlink($partial);

                return $fail($result['error']);
            }

            rename($partial, $outAbs);

            $length = $kind === BoutShare::HIGHLIGHTS
                ? array_sum(array_map(fn ($w) => $w[1] - $w[0], $windows))
                : $duration;

            return ['ok' => true, 'error' => null, 'bytes' => (int) filesize($outAbs), 'duration' => (int) round($length)];
        } finally {
            $this->remove($work);
        }
    }

    /**
     * The score bar as DATA, for a device that draws it itself (boutcam, on the
     * phone's own video chip, App\Events\Support\Cameras\CameraController::plan).
     *
     * Exactly the states drawStates() would screenshot and the windows the
     * highlights would cut — computed by the same methods, so the bar a phone
     * burns in and the bar this server burns in cannot disagree about a score,
     * a round or a cut. Only the drawing differs.
     *
     * @return array<string, mixed>
     */
    public function plan(array $msb, float $duration): array
    {
        $points = $this->points($msb, $duration);
        $rounds = $this->rounds($msb, $duration);

        $instants = array_merge([0.0], array_column($rounds, 'start'), array_column($points, 't'));
        $instants = array_values(array_unique(array_map(fn ($t) => round((float) $t, 2), $instants)));
        sort($instants);
        $instants = array_slice(array_values(array_filter($instants, fn ($t) => $t < $duration)), 0, self::MAX_STATES);

        $states = [];
        $first = null;

        foreach ($instants as $at) {
            $view = $this->stateAt($msb, $rounds, $points, $at);
            $first ??= $view;

            $states[] = [
                'at' => $at,
                'score_red' => (int) $view['scoreRed'],
                'score_blue' => (int) $view['scoreBlue'],
                'round' => (string) $view['roundText'],
                'feed' => $view['feed'],
                'segments' => $view['segments'],
            ];
        }

        $first ??= $this->stateAt($msb, $rounds, $points, 0.0);

        return [
            'duration' => $duration,
            'disc' => $first['disc'],
            'sub' => $first['sub'],
            'feed_title' => $first['feedTitle'],
            'red' => ['name' => $first['red']['name'], 'club' => $first['red']['club'], 'label' => $first['redLabel'], 'flag' => (string) ($msb['red']['flag'] ?? '')],
            'blue' => ['name' => $first['blue']['name'], 'club' => $first['blue']['club'], 'label' => $first['blueLabel'], 'flag' => (string) ($msb['blue']['flag'] ?? '')],
            'rounds' => $rounds,
            'states' => $states,
            'points' => count($points),
            'windows' => $this->windows($points, $duration),
        ];
    }

    /* ──────────────────────────────────────────────────────────────────────
     | What happened, and when
     ────────────────────────────────────────────────────────────────────── */

    /**
     * The scoring points inside the footage, in order.
     *
     * @return list<array{t: float, action: string, pts: int, side: string, sr: int, sb: int}>
     */
    public function points(array $msb, float $duration): array
    {
        $out = [];

        foreach ((array) ($msb['points'] ?? []) as $p) {
            $t = (float) ($p['t'] ?? -1);

            if ($t < 0 || $t > $duration) {
                continue;
            }

            $out[] = [
                't' => round($t, 2),
                'action' => (string) ($p['action'] ?? ''),
                'pts' => (int) ($p['pts'] ?? 0),
                'side' => ($p['side'] ?? '') === 'blue' ? 'blue' : 'red',
                'sr' => (int) ($p['sr'] ?? 0),
                'sb' => (int) ($p['sb'] ?? 0),
            ];
        }

        usort($out, fn ($a, $b) => $a['t'] <=> $b['t']);

        return $out;
    }

    /**
     * Rounds as the clock reads them: where each starts, in footage time.
     *
     * @return list<array{n: int, name: string, start: float}>
     */
    private function rounds(array $msb, float $duration): array
    {
        $rounds = [];

        foreach ((array) ($msb['rounds'] ?? []) as $r) {
            $start = max(0.0, (float) ($r['start'] ?? 0));

            if ($start >= $duration) {
                continue;
            }

            $rounds[] = ['n' => (int) ($r['n'] ?? 1), 'name' => (string) ($r['name'] ?? ''), 'start' => $start];
        }

        usort($rounds, fn ($a, $b) => $a['start'] <=> $b['start']);

        return $rounds;
    }

    /**
     * The highlights: each scoring moment with some lead-in and some follow-
     * through, overlapping windows merged so a flurry is one clip, not three.
     *
     * @return list<array{0: float, 1: float}>
     */
    public function windows(array $points, float $duration): array
    {
        $before = (float) config('media.share_export.highlight_before', 6);
        $after = (float) config('media.share_export.highlight_after', 3);

        $windows = [];

        foreach (array_values(array_unique(array_column($points, 't'))) as $t) {
            $from = max(0.0, $t - $before);
            $to = min($duration, $t + $after);

            $last = count($windows) - 1;

            // Touching (within a second) counts as overlapping: two cuts a
            // second apart read as a stutter, not as two moments.
            if ($last >= 0 && $from <= $windows[$last][1] + 1.0) {
                $windows[$last][1] = max($windows[$last][1], $to);
            } else {
                $windows[] = [$from, $to];
            }
        }

        return $windows;
    }

    /* ──────────────────────────────────────────────────────────────────────
     | The score bar
     ────────────────────────────────────────────────────────────────────── */

    /**
     * Screenshot every state the bar passes through, and write the ffmpeg
     * concat list that plays them for as long as each held.
     */
    private function drawStates(array $msb, array $points, float $duration, string $work): ?string
    {
        $rounds = $this->rounds($msb, $duration);

        $instants = array_merge([0.0], array_column($rounds, 'start'), array_column($points, 't'));
        $instants = array_values(array_unique(array_map(fn ($t) => round((float) $t, 2), $instants)));
        sort($instants);
        $instants = array_slice(array_values(array_filter($instants, fn ($t) => $t < $duration)), 0, self::MAX_STATES);

        $drawn = [];
        $lines = ['ffconcat version 1.0'];
        $last = null;

        foreach ($instants as $i => $at) {
            $html = view('media::share.scoreboard', $this->stateAt($msb, $rounds, $points, $at))->render();
            $hash = md5($html);

            // An instant that changes nothing visible (a round starting with no
            // new label, say) reuses the picture it already has.
            if (! isset($drawn[$hash])) {
                try {
                    // 1152×648 CSS px at 160 dpi is exactly 1920×1080.
                    $png = $this->chrome->png($html, self::OVERLAY_W * 0.6, self::OVERLAY_H * 0.6, 160, true);
                } catch (\Throwable $e) {
                    Log::warning('bout-share: score bar could not be drawn', ['error' => Str::limit($e->getMessage(), 200)]);

                    return null;
                }

                $path = $work.'/state-'.count($drawn).'.png';
                file_put_contents($path, $png);
                $drawn[$hash] = $path;
            }

            $until = $instants[$i + 1] ?? $duration;
            $lines[] = "file '".$drawn[$hash]."'";
            $lines[] = 'duration '.$this->num(max(0.04, $until - $at));
            $last = $drawn[$hash];
        }

        // The concat demuxer ignores the last entry's duration unless the file
        // is named once more after it.
        $lines[] = "file '".$last."'";

        $list = $work.'/states.ffconcat';
        file_put_contents($list, implode("\n", $lines)."\n");

        return $list;
    }

    /**
     * The view data for the bar as it stood at one instant — the same rules the
     * bout page's script applies on every tick (see bout-video's msb RUN()).
     */
    private function stateAt(array $msb, array $rounds, array $points, float $at): array
    {
        $labels = $this->cornerLabels($msb);
        $sport = strtolower((string) ($msb['sport'] ?? ''));

        $round = ['n' => 1, 'name' => '', 'start' => 0.0];
        foreach ($rounds as $i => $r) {
            if ($i === 0 || $at >= $r['start']) {
                $round = $r;
            }
        }

        $roundText = $round['name'];
        if (str_starts_with($sport, 'taekwondo')) {
            $roundText = __('events::events.bout_video_round', ['n' => $round['n']]).' / '.max(1, count($rounds));
        } elseif (str_starts_with($sport, 'karate')) {
            $roundText = $roundText ?: 'Kumite';
        }

        $seen = array_values(array_filter($points, fn ($p) => $p['t'] <= $at + 0.001));
        $latest = $seen === [] ? null : $seen[count($seen) - 1];

        $segments = [];
        for ($i = 0, $n = max(14, count($points)); $i < $n; $i++) {
            $segments[] = isset($seen[$i]) ? $this->colour($seen[$i]['side']) : '#2a2724';
        }

        $feed = array_map(fn ($p) => [
            'ts' => $this->clock($p['t']),
            'name' => $this->pointLabel($p, $sport),
            'pts' => $p['pts'],
            'colour' => $this->colour($p['side']),
        ], array_reverse(array_slice($seen, -4)));

        $corner = fn (string $side, string $fallback) => [
            'name' => (string) (($msb[$side]['name'] ?? '') ?: $fallback),
            'club' => (string) ($msb[$side]['club'] ?? ''),
            'flag' => $this->flagFile($msb[$side]['flag'] ?? null),
        ];

        return [
            'fonts' => public_path('fonts/designer'),
            'scale' => round(1152 / self::DESIGN_W, 6),
            'canvasHeight' => 647,
            'disc' => (string) ($labels['disc'] ?: ($msb['discipline'] ?? '')),
            'sub' => (string) ($msb['subtitle'] ?? ''),
            'feedTitle' => 'Live scoring',
            'feed' => $feed,
            'red' => $corner('red', 'RED'),
            'blue' => $corner('blue', 'BLUE'),
            'redLabel' => mb_strtoupper($labels['red']),
            'blueLabel' => mb_strtoupper($labels['blue']),
            'scoreRed' => $latest['sr'] ?? 0,
            'scoreBlue' => $latest['sb'] ?? 0,
            'roundText' => $roundText,
            'segments' => $segments,
        ];
    }

    /** The bout page's corner words, per sport. */
    private function cornerLabels(array $msb): array
    {
        $sport = strtolower((string) ($msb['sport'] ?? 'karate'));

        return match (true) {
            str_starts_with($sport, 'taekwondo') => ['red' => 'Hong', 'blue' => 'Chung', 'disc' => 'Taekwondo Kyorugi'],
            str_starts_with($sport, 'karate') => ['red' => 'Aka', 'blue' => 'Ao', 'disc' => 'Karate Kumite'],
            default => ['red' => 'Red', 'blue' => 'Blue', 'disc' => ''],
        };
    }

    /** What the ticker calls a point — the bout page's own words. */
    private function pointLabel(array $p, string $sport): string
    {
        if (str_starts_with($sport, 'taekwondo')) {
            return match (true) {
                $p['pts'] >= 4 => 'Turning body',
                $p['pts'] === 3 => 'Head kick',
                $p['pts'] === 2 => 'Body kick',
                $p['pts'] === 1 => 'Punch',
                default => $p['action'] ?: 'Point',
            };
        }

        return match ($p['pts']) {
            3 => 'Ippon',
            2 => 'Waza-ari',
            1 => 'Yuko',
            default => $p['action'] ?: 'Point',
        };
    }

    private function colour(string $side): string
    {
        return $side === 'blue' ? '#6aa6ff' : '#ff6a5e';
    }

    /** The local flag file, or null — never a guessed path. */
    private function flagFile(?string $code): ?string
    {
        $iso = Flag::iso2($code);

        if (! $iso || ! Flag::exists($iso)) {
            return null;
        }

        $path = public_path('images/flags/'.strtolower($iso).'.svg');

        return is_file($path) ? $path : null;
    }

    /**
     * A country's flag as a small PNG data URI, for a device that cannot draw
     * the vendored SVG (boutcam's score bar). Drawn once per country by the
     * same Chromium that draws this server's bar, from the same file, and kept
     * under the derived-files root — so it is ours, offline, and never a CDN.
     */
    public function flagPng(?string $code): ?string
    {
        $svg = $this->flagFile($code);

        if ($svg === null) {
            return null;
        }

        $iso = strtolower((string) Flag::iso2($code));
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $cached = 'cache/flag-png/'.$iso.'.png';

        if (! $disk->exists($cached)) {
            try {
                $html = '<!doctype html><html><body style="margin:0;background:transparent">'
                    .'<img src="file://'.e($svg).'" style="display:block;width:78px;height:52px;object-fit:cover"></body></html>';
                $disk->put($cached, $this->chrome->png($html, 78, 52, 192, true));
            } catch (\Throwable $e) {
                Log::warning('bout-share: flag could not be drawn', ['iso' => $iso, 'error' => Str::limit($e->getMessage(), 200)]);

                return null;
            }
        }

        return 'data:image/png;base64,'.base64_encode($disk->get($cached));
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Chapters, and the words that go with the file
     ────────────────────────────────────────────────────────────────────── */

    /**
     * One chapter per scoring moment, starting a few seconds early so a tap
     * lands on the exchange rather than after it.
     *
     * @return list<array{start: float, end: float, title: string}>
     */
    private function fullChapters(array $points, array $msb, float $duration): array
    {
        $lead = 3.0;
        $marks = [['start' => 0.0, 'title' => $this->openingTitle($msb)]];

        foreach ($this->moments($points) as $t => $group) {
            $start = max(0.0, (float) $t - $lead);
            $previous = $marks[count($marks) - 1];

            // A moment too close to the last mark folds into it instead of
            // making a chapter one second long.
            if ($start - $previous['start'] < 2.0) {
                $marks[count($marks) - 1]['title'] .= ' · '.$this->momentTitle((float) $t, $group, $msb);

                continue;
            }

            $marks[] = ['start' => $start, 'title' => $this->momentTitle((float) $t, $group, $msb)];
        }

        return $this->closeChapters($marks, $duration);
    }

    /**
     * One chapter per clip in the reel, named for what happens in it.
     *
     * @return list<array{start: float, end: float, title: string}>
     */
    private function highlightChapters(array $windows, array $points, array $msb): array
    {
        $marks = [];
        $offset = 0.0;
        $moments = $this->moments($points);

        foreach ($windows as [$from, $to]) {
            $titles = [];

            foreach ($moments as $t => $group) {
                if ((float) $t >= $from && (float) $t <= $to) {
                    $titles[] = $this->momentTitle((float) $t, $group, $msb);
                }
            }

            $marks[] = ['start' => $offset, 'title' => implode(' · ', $titles)];
            $offset += $to - $from;
        }

        return $this->closeChapters($marks, $offset);
    }

    /**
     * The scoring moments as lines a message can carry — the chapters, for an
     * app whose player will never show chapters.
     *
     * @return list<string>
     */
    public function momentLines(array $msb, float $duration): array
    {
        $lines = [];

        foreach ($this->moments($this->points($msb, $duration)) as $t => $group) {
            $lines[] = $this->momentTitle((float) $t, $group, $msb);
        }

        return $lines;
    }

    /** @return array<string, list<array>> points grouped by the instant they share */
    private function moments(array $points): array
    {
        $out = [];

        foreach ($points as $p) {
            $out[(string) $p['t']][] = $p;
        }

        return $out;
    }

    /** "0:42 Aka Waza-ari +2 (2–0)" — what a chapter list and a message both say. */
    private function momentTitle(float $t, array $group, array $msb): string
    {
        $labels = $this->cornerLabels($msb);
        $sport = strtolower((string) ($msb['sport'] ?? ''));

        $parts = array_map(fn ($p) => $labels[$p['side']].' '.$this->pointLabel($p, $sport).' +'.$p['pts'], $group);
        $last = $group[count($group) - 1];

        return $this->clock($t).' '.implode(' / ', $parts).' ('.$last['sr'].'–'.$last['sb'].')';
    }

    private function openingTitle(array $msb): string
    {
        $red = ($msb['red']['name'] ?? '') ?: 'Red';
        $blue = ($msb['blue']['name'] ?? '') ?: 'Blue';

        return $red.' vs '.$blue;
    }

    /** @return list<array{start: float, end: float, title: string}> */
    private function closeChapters(array $marks, float $end): array
    {
        $out = [];

        foreach (array_values($marks) as $i => $mark) {
            $out[] = [
                'start' => $mark['start'],
                'end' => $marks[$i + 1]['start'] ?? $end,
                'title' => $mark['title'],
            ];
        }

        return array_values(array_filter($out, fn ($c) => $c['end'] > $c['start']));
    }

    /** ffmpeg's metadata file: the title, then every chapter in milliseconds. */
    private function chapterFile(array $chapters, string $title): string
    {
        $esc = fn (string $s) => preg_replace('/([=;#\\\\\n])/', '\\\\$1', $s);

        $out = ";FFMETADATA1\n";

        if ($title !== '') {
            $out .= 'title='.$esc($title)."\n";
        }

        foreach ($chapters as $c) {
            $out .= "\n[CHAPTER]\nTIMEBASE=1/1000\n"
                .'START='.(int) round($c['start'] * 1000)."\n"
                .'END='.(int) round($c['end'] * 1000)."\n"
                .'title='.$esc($c['title'])."\n";
        }

        return $out;
    }

    /* ──────────────────────────────────────────────────────────────────────
     | The encode
     ────────────────────────────────────────────────────────────────────── */

    /** @return array{0: int, 1: int} 1080p when the footage has it, otherwise 720p. */
    private function outputSize(int $sourceHeight): array
    {
        $max = (int) config('media.share_export.max_height', 1080);

        return $sourceHeight >= 1000 && $max >= 1080 ? [1920, 1080] : [1280, 720];
    }

    /** @return array{ok: bool, error: ?string} */
    private function encode(
        string $source, string $concat, string $chapters, string $out,
        array $msb, array $windows, float $duration,
        int $width, int $height, bool $hasAudio, bool $gpu,
    ): array {
        $fps = (int) config('media.share_export.fps', 30);
        $font = public_path('fonts/designer/barlow-condensed/BarlowCondensed-Black.ttf');

        $graph = [
            "[0:v]fps={$fps},scale={$width}:{$height}:force_original_aspect_ratio=decrease,"
                ."pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2,setsar=1[base]",
            "[1:v]format=rgba,scale={$width}:{$height}[bar]",
            '[base][bar]overlay=0:0:eof_action=repeat:format=auto'.$this->clockFilters($msb, $duration, $font).'[scored]',
        ];

        $select = $windows === [] ? null : implode('+', array_map(
            fn ($w) => 'between(t,'.$this->num($w[0]).','.$this->num($w[1]).')',
            $windows,
        ));

        if ($select !== null) {
            $graph[] = "[scored]select='{$select}',setpts=N/FRAME_RATE/TB[v]";

            if ($hasAudio) {
                $graph[] = "[0:a]aselect='{$select}',asetpts=N/SR/TB[a]";
            }
        } else {
            $graph[] = '[scored]null[v]';

            if ($hasAudio) {
                $graph[] = '[0:a]anull[a]';
            }
        }

        $script = dirname($concat).'/graph.txt';
        file_put_contents($script, implode(";\n", $graph));

        $crf = (string) (int) config('media.share_export.crf', 25);
        $bitrate = (string) config('media.share_export.bitrate', '3500k');
        $maxrate = (string) config('media.share_export.maxrate', '4500k');

        $video = $gpu
            ? ['-c:v', (string) config('media.gpu.encoder'), '-preset', (string) config('media.gpu.preset'),
                '-rc', 'vbr', '-cq', $crf, '-b:v', $bitrate, '-maxrate', $maxrate, '-bufsize', $maxrate,
                '-gpu', (string) (int) config('media.gpu.device')]
            : ['-c:v', 'libx264', '-preset', 'veryfast', '-crf', $crf, '-maxrate', $maxrate, '-bufsize', $maxrate];

        $cmd = [
            ...(is_executable('/usr/bin/nice') ? ['/usr/bin/nice', '-n', '10'] : []),
            (string) $this->ffmpeg->ffmpeg(),
            '-hide_banner', '-loglevel', 'error', '-y',
            '-i', $source,
            '-f', 'concat', '-safe', '0', '-i', $concat,
            '-f', 'ffmetadata', '-i', $chapters,
            '-filter_complex_script', $script,
            '-map', '[v]',
            ...($hasAudio ? ['-map', '[a]', '-c:a', 'aac', '-b:a', '128k', '-ar', '48000'] : []),
            '-map_metadata', '2', '-map_chapters', '2',
            ...$video,
            '-profile:v', 'high', '-pix_fmt', 'yuv420p',
            '-g', (string) ($fps * 2),
            '-movflags', '+faststart',
            $out,
        ];

        $process = new Process($cmd, null, null, null, 3600);
        $process->run();

        if ($process->isSuccessful() && is_file($out) && filesize($out) > 0) {
            return ['ok' => true, 'error' => null];
        }

        $tail = Str::limit(trim($process->getErrorOutput()), 400);
        Log::error('bout-share: encode failed', ['gpu' => $gpu, 'tail' => $tail]);

        // A GPU that passed the probe but refused this file: once more on the
        // CPU rather than failing the person waiting for it.
        if ($gpu) {
            Cache::put('media_gpu_usable', false, 900);

            return $this->encode($source, $concat, $chapters, $out, $msb, $windows, $duration, $width, $height, $hasAudio, false);
        }

        return ['ok' => false, 'error' => 'The video could not be encoded.'];
    }

    /**
     * The running clock, one drawtext per round, counting from that round's
     * start exactly as the page's clock does (0:00 before the first round).
     */
    private function clockFilters(array $msb, float $duration, string $font): string
    {
        $rounds = $this->rounds($msb, $duration);

        if ($rounds === []) {
            $rounds = [['n' => 1, 'name' => '', 'start' => 0.0]];
        }

        $style = 'fontfile='.$this->filterValue($font)
            .':fontcolor=0xefe9e0:fontsize=h*'.self::CLOCK_SIZE
            .':x=(w-text_w)/2:y=h*'.self::CLOCK_Y.'-text_h/2';

        $filters = [];

        if ($rounds[0]['start'] > 0) {
            $filters[] = "drawtext={$style}:text='0\\:00':enable='lt(t,".$this->num($rounds[0]['start']).")'";
        }

        foreach ($rounds as $i => $r) {
            $from = $this->num($r['start']);
            $to = $this->num($rounds[$i + 1]['start'] ?? $duration + 1);

            $text = "%{eif\\:floor((t-{$from})/60)\\:d}\\:%{eif\\:mod(floor(t-{$from})\\,60)\\:d\\:2}";
            $filters[] = "drawtext={$style}:text='{$text}':enable='between(t,{$from},{$to})'";
        }

        return ','.implode(',', $filters);
    }

    private function num(float $n): string
    {
        return number_format($n, 3, '.', '');
    }

    /** A path inside a filter graph: its colons and quotes escaped. */
    private function filterValue(string $path): string
    {
        return "'".str_replace(['\\', "'", ':'], ['\\\\', "\\'", '\\:'], $path)."'";
    }

    private function clock(float $seconds): string
    {
        $whole = max(0, (int) floor($seconds));

        return intdiv($whole, 60).':'.str_pad((string) ($whole % 60), 2, '0', STR_PAD_LEFT);
    }

    private function remove(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }
}
