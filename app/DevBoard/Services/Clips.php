<?php

namespace App\DevBoard\Services;

use App\DevBoard\Jobs\PrepareClip;
use App\DevBoard\Models\Asset;
use App\DevBoard\Models\Card;
use App\Media\Ffmpeg;
use App\Members\Models\User;
use App\Support\StoragePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Voice notes and short videos placed in a note.
 *
 * Nothing is trusted from the phone: the file's real contents are probed with
 * ffprobe (container on an allow-list, the right kind of stream present), the
 * name and the folder are the server's, and the file is RE-ENCODED before it
 * is ever served — a voice note to AAC in .m4a, a video to H.264/AAC in .mp4 —
 * which both strips whatever else the upload carried and makes it play on
 * every phone (an Android recording is WebM, which an iPhone will not play).
 * Encoding is also where the length limit is enforced (`-t`), so a recording
 * can never come out longer than allowed whatever its header claims.
 *
 * A voice note is encoded during the upload (seconds); a video on the media
 * queue (PrepareClip), with the asset `processing` until it is ready.
 */
class Clips
{
    /** Containers a phone's recorder or camera produces. */
    private const FORMATS = ['matroska', 'webm', 'mov', 'mp4', 'm4a', '3gp', '3g2', 'mj2', 'ogg', 'wav', 'mp3', 'aac'];

    public function __construct(private Ffmpeg $ffmpeg) {}

    public function store(Card $card, User $by, UploadedFile $file, string $kind): ?Asset
    {
        if (! in_array($kind, ['audio', 'video'], true) || ! $this->ffmpeg->available() || ! $this->ffmpeg->ffprobe()) {
            return null;
        }

        $probe = $this->probe($file->getRealPath());
        if (! $probe || ! $this->acceptable($probe, $kind)) {
            return null;
        }

        $uuid = (string) Str::uuid();
        $dir = StoragePath::platform('devboard/cards/'.$card->uuid);
        $disk = Storage::disk('local');
        $source = StoragePath::file($dir, $uuid.'-source', 'bin'); // a neutral extension: never served, never executed
        $disk->putFileAs(dirname($source), $file, basename($source));

        $asset = Asset::create([
            'uuid' => $uuid,
            'card_id' => $card->id,
            'kind' => $kind,
            'status' => 'processing',
            'mime' => $kind === 'audio' ? 'audio/mp4' : 'video/mp4',
            'path' => StoragePath::file($dir, $uuid, $kind === 'audio' ? 'm4a' : 'mp4'),
            'preview_path' => $kind === 'video' ? StoragePath::file($dir, $uuid.'-poster', 'webp') : null,
            'source_path' => $source,
            'width' => 0,
            'height' => 0,
            'bytes' => (int) $file->getSize(),
            'created_by' => $by->id,
        ]);

        if ($kind === 'audio') {
            $this->prepare($asset);
        } else {
            PrepareClip::dispatch($asset->id)->onQueue('media');
        }

        return $asset->fresh();
    }

    /**
     * Encode the upload into the file that is served, then drop the upload.
     * Idempotent: a ready or failed asset is left as it is.
     */
    public function prepare(Asset $asset): void
    {
        if ($asset->status !== 'processing' || ! $asset->source_path) {
            return;
        }

        $disk = Storage::disk('local');
        $in = $disk->path($asset->source_path);
        $out = $disk->path($asset->path);
        @mkdir(dirname($out), 0775, true);

        $ok = $asset->kind === 'audio' ? $this->encodeAudio($in, $out) : $this->encodeVideo($in, $out);

        $probe = $ok ? $this->probe($out) : null;
        $duration = (int) round(((float) ($probe['format']['duration'] ?? 0)) * 1000);

        if (! $ok || ! $probe || $duration <= 0) {
            @unlink($out);
            $disk->delete($asset->source_path);
            $asset->forceFill(['status' => 'failed', 'source_path' => null])->save();
            Log::warning('devboard: a clip could not be prepared', ['asset' => $asset->uuid, 'kind' => $asset->kind]);

            return;
        }

        $fields = ['status' => 'ready', 'duration_ms' => $duration, 'bytes' => (int) filesize($out), 'source_path' => null];

        if ($asset->kind === 'audio') {
            $fields['meta'] = ['peaks' => $this->peaks($out)];
        } else {
            $v = collect($probe['streams'] ?? [])->firstWhere('codec_type', 'video');
            $fields['width'] = (int) ($v['width'] ?? 0);
            $fields['height'] = (int) ($v['height'] ?? 0);
            if (! $this->poster($out, $disk->path($asset->preview_path))) {
                $fields['preview_path'] = null;
            }
        }

        $disk->delete($asset->source_path);
        $asset->forceFill($fields)->save();
    }

    /** What a note needs to draw a clip (or a picture). */
    public static function describe(Asset $a): array
    {
        return array_filter([
            'uuid' => $a->uuid,
            'kind' => $a->kind,
            'status' => $a->status,
            'duration_ms' => $a->duration_ms,
            'peaks' => $a->kind === 'audio' ? ($a->meta['peaks'] ?? null) : null,
            'poster' => $a->kind === 'video' && $a->preview_path ? true : null,
            'width' => $a->width ?: null,
            'height' => $a->height ?: null,
        ], fn ($v) => $v !== null);
    }

    private function probe(string $path): ?array
    {
        $p = new Process([$this->ffmpeg->ffprobe(), '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $path]);
        $p->setTimeout(30);
        $p->run();

        return $p->isSuccessful() ? json_decode($p->getOutput(), true) : null;
    }

    private function acceptable(array $probe, string $kind): bool
    {
        $formats = explode(',', (string) ($probe['format']['format_name'] ?? ''));
        if (! array_intersect($formats, self::FORMATS)) {
            return false;
        }

        $streams = collect($probe['streams'] ?? []);
        $audio = $streams->contains(fn ($s) => ($s['codec_type'] ?? '') === 'audio');
        $video = $streams->contains(fn ($s) => ($s['codec_type'] ?? '') === 'video' && empty($s['disposition']['attached_pic']));

        return $kind === 'audio' ? $audio : $video;
    }

    private function encodeAudio(string $in, string $out): bool
    {
        return $this->run([
            $this->ffmpeg->ffmpeg(), '-hide_banner', '-loglevel', 'error', '-y', '-i', $in,
            '-t', (string) config('devboard.limits.voice_seconds', 900),
            '-vn', '-sn', '-dn', '-map_metadata', '-1',
            '-ac', '1', '-c:a', 'aac', '-b:a', '64k', '-movflags', '+faststart', '-f', 'mp4', $out,
        ], 300);
    }

    private function encodeVideo(string $in, string $out): bool
    {
        // Long side at most 1280, even sides, the phone's rotation applied.
        $fit = "scale='if(gte(iw,ih),min(1280,iw),-2)':'if(gte(iw,ih),-2,min(1280,ih))',format=yuv420p";

        return $this->run([
            $this->ffmpeg->ffmpeg(), '-hide_banner', '-loglevel', 'error', '-y', '-i', $in,
            '-t', (string) config('devboard.limits.video_seconds', 60),
            '-map', '0:v:0', '-map', '0:a:0?', '-sn', '-dn', '-map_metadata', '-1',
            '-vf', $fit, '-r', '30', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '26', '-profile:v', 'high',
            '-c:a', 'aac', '-b:a', '96k', '-ac', '2', '-movflags', '+faststart', '-f', 'mp4', $out,
        ], 900);
    }

    private function poster(string $video, string $out): bool
    {
        return $this->run([
            $this->ffmpeg->ffmpeg(), '-hide_banner', '-loglevel', 'error', '-y', '-ss', '0.4', '-i', $video,
            '-frames:v', '1', '-vf', "scale='min(720,iw)':-2", '-c:v', 'libwebp', '-quality', '78', '-f', 'webp', $out,
        ], 60) && is_file($out);
    }

    /** 48 bars, 0–1, for the waveform a voice note is drawn with. */
    private function peaks(string $audio, int $bars = 48): array
    {
        $p = new Process([$this->ffmpeg->ffmpeg(), '-hide_banner', '-loglevel', 'error', '-i', $audio, '-ac', '1', '-ar', '1000', '-f', 's16le', 'pipe:1']);
        $p->setTimeout(120);
        $p->run();
        $raw = $p->isSuccessful() ? $p->getOutput() : '';
        $n = intdiv(strlen($raw), 2);
        if ($n < $bars) {
            return array_fill(0, $bars, 0.15);
        }

        $samples = unpack('s*', substr($raw, 0, $n * 2));
        $per = intdiv($n, $bars);
        $out = [];
        for ($b = 0; $b < $bars; $b++) {
            $max = 0;
            for ($i = $b * $per + 1, $end = ($b + 1) * $per; $i <= $end; $i++) {
                $v = abs($samples[$i]);
                if ($v > $max) {
                    $max = $v;
                }
            }
            $out[] = $max;
        }
        $top = max($out) ?: 1;

        return array_map(fn ($v) => round(max(0.08, $v / $top), 2), $out);
    }

    private function run(array $cmd, int $timeout): bool
    {
        $p = new Process($cmd);
        $p->setTimeout($timeout);
        $p->run();

        return $p->isSuccessful();
    }
}
