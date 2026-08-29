<?php

namespace App\Media;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The encoder, and the questions worth asking before using it.
 *
 * Two GPUs are passed through to this container, so NVENC is the default here —
 * a bout re-encodes in a fraction of the time and costs the CPU almost nothing,
 * which matters when four mats are filing clips at once. But a card can be
 * missing, busy, or unavailable to this process, and a competition's footage must
 * not be lost to that: the encoder is PROBED once and the answer cached, and
 * libx264 takes over when the probe fails.
 *
 * Everything shells out. There is no PHP binding worth the dependency here, and
 * every argument is escaped — the only values that reach a command line are
 * paths this application built and numbers from config.
 */
class Ffmpeg
{
    /** How long a successful GPU probe is trusted. */
    private const PROBE_TTL = 900;

    /* ──────────────────────────────────────────────────────────────────────
     | Binaries
     ────────────────────────────────────────────────────────────────────── */

    /**
     * The ffmpeg binary, or null when this box has none.
     *
     * jellyfin's build first (it ships the codecs and NVENC), then the
     * distribution's. Null is answered honestly so a caller can say "this server
     * cannot transcode" instead of failing inside an encode.
     */
    public function ffmpeg(): ?string
    {
        return $this->resolve(config('media.ffmpeg'), config('media.ffmpeg_fallbacks', []), 'ffmpeg');
    }

    public function ffprobe(): ?string
    {
        return $this->resolve(config('media.ffprobe'), config('media.ffprobe_fallbacks', []), 'ffprobe');
    }

    public function available(): bool
    {
        return $this->ffmpeg() !== null && $this->ffprobe() !== null;
    }

    private function resolve(?string $preferred, array $fallbacks, string $name): ?string
    {
        foreach (array_filter(array_merge([$preferred], $fallbacks)) as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        // Last resort: whatever is on PATH.
        $found = trim((string) @shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null'));

        return $found !== '' ? $found : null;
    }

    /* ──────────────────────────────────────────────────────────────────────
     | What this machine can encode with
     ────────────────────────────────────────────────────────────────────── */

    /**
     * Can we actually hand work to the GPU right now?
     *
     * Not "is it configured" — a real one-frame encode, because a configured
     * device that cannot encode produces a job that fails after uploading a
     * competition's worth of video to it. Cached, because the answer costs
     * about a second.
     */
    public function gpuUsable(): bool
    {
        if (! config('media.gpu.enabled') || ! $this->available()) {
            return false;
        }

        return Cache::remember('media_gpu_usable', self::PROBE_TTL, function () {
            $encoder = (string) config('media.gpu.encoder');
            $device = (int) config('media.gpu.device');

            $cmd = implode(' ', [
                escapeshellcmd((string) $this->ffmpeg()),
                '-hide_banner -loglevel error',
                '-f lavfi -i '.escapeshellarg('color=c=black:s=256x144:d=0.1'),
                '-c:v '.escapeshellarg($encoder),
                '-gpu '.$device,
                '-frames:v 1 -f null -',
                '2>&1',
            ]);

            $output = [];
            $code = 0;
            exec($cmd, $output, $code);

            if ($code !== 0) {
                Log::warning('media: GPU encode probe failed, falling back to CPU', [
                    'encoder' => $encoder,
                    'output' => implode(' ', array_slice($output, -3)),
                ]);
            }

            return $code === 0;
        });
    }

    public function forgetGpuProbe(): void
    {
        Cache::forget('media_gpu_usable');
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Reading a file
     ────────────────────────────────────────────────────────────────────── */

    /**
     * Duration, dimensions and orientation of a video.
     *
     * @return array{duration: ?int, width: ?int, height: ?int, orientation: ?string}
     */
    public function probe(string $absPath): array
    {
        $blank = ['duration' => null, 'width' => null, 'height' => null, 'orientation' => null];

        if (! is_file($absPath) || $this->ffprobe() === null) {
            return $blank;
        }

        $cmd = implode(' ', [
            escapeshellcmd((string) $this->ffprobe()),
            '-v error',
            '-select_streams v:0',
            '-show_entries stream=width,height:format=duration',
            '-of json',
            escapeshellarg($absPath),
            '2>/dev/null',
        ]);

        $json = @shell_exec($cmd);
        $data = json_decode((string) $json, true);

        if (! is_array($data)) {
            return $blank;
        }

        $stream = $data['streams'][0] ?? [];
        $width = isset($stream['width']) ? (int) $stream['width'] : null;
        $height = isset($stream['height']) ? (int) $stream['height'] : null;
        $duration = isset($data['format']['duration']) ? (int) round((float) $data['format']['duration']) : null;

        return [
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'orientation' => ($width && $height)
                ? ($height > $width ? 'portrait' : ($width > $height ? 'landscape' : 'square'))
                : null,
        ];
    }

    /**
     * Does this file carry an audio stream at all?
     *
     * It has to be asked. A mat camera often records silent — the phone's mic is
     * muted, or the app captures video only — and an HLS ladder that DECLARES an
     * audio stream for a file that has none fails outright with "Unable to map
     * stream at a:0". Not a corner case: it is most of the footage from a hall,
     * and it fails at the last step, after the upload has already succeeded.
     */
    public function hasAudio(string $absPath): bool
    {
        if (! is_file($absPath) || $this->ffprobe() === null) {
            return false;
        }

        $cmd = implode(' ', [
            escapeshellcmd((string) $this->ffprobe()),
            '-v error',
            '-select_streams a',
            '-show_entries stream=index',
            '-of csv=p=0',
            escapeshellarg($absPath),
            '2>/dev/null',
        ]);

        return trim((string) @shell_exec($cmd)) !== '';
    }

    /** A single frame as a poster image. Returns false rather than throwing. */
    public function poster(string $absPath, string $outAbsPath, int $atSecond = 1): bool
    {
        if (! is_file($absPath) || $this->ffmpeg() === null) {
            return false;
        }

        if (! is_dir(dirname($outAbsPath))) {
            @mkdir(dirname($outAbsPath), 0775, true);
        }

        $cmd = implode(' ', [
            escapeshellcmd((string) $this->ffmpeg()),
            '-hide_banner -loglevel error -y',
            '-ss '.max(0, $atSecond),
            '-i '.escapeshellarg($absPath),
            '-frames:v 1 -q:v 3',
            escapeshellarg($outAbsPath),
            '2>&1',
        ]);

        exec($cmd, $out, $code);

        return $code === 0 && is_file($outAbsPath);
    }

    /* ──────────────────────────────────────────────────────────────────────
     | The ladder
     ────────────────────────────────────────────────────────────────────── */

    /**
     * Build the HLS ladder for one video into $outDir.
     *
     * One ffmpeg invocation for every rung, which is the whole reason this is
     * fast: the source is decoded once (on the GPU when it is available) and fed
     * to three encoders, instead of being read three times.
     *
     * Rungs taller than the source are dropped — upscaling a mat camera's 720p
     * to 1080p spends GPU time making the file bigger and nothing else.
     *
     * @return array{ok: bool, master: ?string, variants: array<int,string>, error: ?string}
     */
    public function hls(string $sourceAbs, string $outDir, ?int $sourceHeight = null): array
    {
        if (! $this->available()) {
            return ['ok' => false, 'master' => null, 'variants' => [], 'error' => 'No ffmpeg on this server.'];
        }

        if (! is_file($sourceAbs)) {
            return ['ok' => false, 'master' => null, 'variants' => [], 'error' => 'The source file is not readable.'];
        }

        $ladder = collect(config('media.hls.ladder'))
            ->filter(fn ($rung) => $sourceHeight === null || $rung['height'] <= $sourceHeight * 1.05)
            ->values()
            ->all();

        // A source shorter than the lowest rung still gets one rendition, so
        // everything is streamable by the same player and the same URL shape.
        if ($ladder === []) {
            $ladder = [collect(config('media.hls.ladder'))->first()];
        }

        if (! is_dir($outDir) && ! @mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
            return ['ok' => false, 'master' => null, 'variants' => [], 'error' => 'Could not create the output directory.'];
        }

        foreach ($ladder as $rung) {
            @mkdir($outDir.'/'.$rung['name'], 0775, true);
        }

        // Silent footage is normal, and it changes the command: the audio stream
        // may be neither mapped nor declared, or the muxer refuses the whole job.
        $hasAudio = $this->hasAudio($sourceAbs);

        $gpu = $this->gpuUsable();
        $encoder = $gpu ? (string) config('media.gpu.encoder') : (string) config('media.gpu.cpu_encoder');
        $preset = $gpu ? (string) config('media.gpu.preset') : (string) config('media.gpu.cpu_preset');
        $device = (int) config('media.gpu.device');
        $segment = (int) config('media.hls.segment_seconds', 6);

        $cmd = [escapeshellcmd((string) $this->ffmpeg()), '-hide_banner', '-loglevel error', '-y'];

        if ($gpu && config('media.gpu.hwaccel') !== 'none') {
            $cmd[] = '-hwaccel '.escapeshellarg((string) config('media.gpu.hwaccel'));
            $cmd[] = '-hwaccel_device '.$device;
        }

        $cmd[] = '-i '.escapeshellarg($sourceAbs);

        // One video (plus audio, when there is any) per rung.
        foreach ($ladder as $i => $rung) {
            $cmd[] = '-map 0:v:0';

            if ($hasAudio) {
                $cmd[] = '-map 0:a:0';
            }
        }

        $cmd[] = '-c:v '.escapeshellarg($encoder);
        $cmd[] = '-preset '.escapeshellarg($preset);

        if ($gpu) {
            $cmd[] = '-rc vbr';
            $cmd[] = '-cq 23';
            $cmd[] = '-gpu '.$device;
        } else {
            $cmd[] = '-crf 23';
        }

        $cmd[] = '-pix_fmt yuv420p';

        if ($hasAudio) {
            $cmd[] = '-c:a aac -b:a 128k -ar 48000';
        }

        foreach ($ladder as $i => $rung) {
            // -2 keeps width even, which every H.264 profile requires.
            $cmd[] = "-filter:v:{$i} ".escapeshellarg('scale=-2:'.$rung['height']);
            $cmd[] = "-b:v:{$i} ".escapeshellarg($rung['bitrate']);
        }

        // A fixed GOP with no scene-cut keyframes is what makes segments
        // independent, and independent segments are what let a phone change rung
        // mid-bout without stalling.
        $cmd[] = '-g '.($segment * 8);
        $cmd[] = '-sc_threshold 0';
        $cmd[] = '-f hls';
        $cmd[] = '-hls_time '.$segment;
        $cmd[] = '-hls_list_size 0';
        $cmd[] = '-hls_flags independent_segments';
        $cmd[] = '-hls_segment_filename '.escapeshellarg($outDir.'/%v/%03d.ts');
        $cmd[] = '-master_pl_name playlist.m3u8';
        // The declaration must match what was actually mapped, rung for rung.
        $cmd[] = '-var_stream_map '.escapeshellarg(implode(' ', array_map(
            fn ($i, $rung) => $hasAudio
                ? "v:{$i},a:{$i},name:{$rung['name']}"
                : "v:{$i},name:{$rung['name']}",
            array_keys($ladder),
            $ladder
        )));
        $cmd[] = escapeshellarg($outDir.'/%v/index.m3u8');
        $cmd[] = '2>&1';

        $output = [];
        $code = 0;
        exec(implode(' ', $cmd), $output, $code);

        if ($code !== 0) {
            $tail = implode("\n", array_slice($output, -8));

            Log::error('media: hls encode failed', ['dir' => $outDir, 'gpu' => $gpu, 'tail' => $tail]);

            // A GPU that accepted the probe but refused the real file: retry on
            // the CPU once rather than losing the footage.
            if ($gpu) {
                $this->forgetGpuProbe();
                Cache::put('media_gpu_usable', false, self::PROBE_TTL);

                Log::warning('media: retrying the encode on the CPU');

                return $this->hls($sourceAbs, $outDir, $sourceHeight);
            }

            return ['ok' => false, 'master' => null, 'variants' => [], 'error' => 'Encoding failed: '.mb_substr($tail, 0, 200)];
        }

        return [
            'ok' => true,
            'master' => 'playlist.m3u8',
            'variants' => array_column($ladder, 'name'),
            'error' => null,
        ];
    }
}
