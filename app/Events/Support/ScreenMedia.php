<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A sound an event's screens play, stored by slot.
 *
 * Shared rather than package-owned on purpose: an introduction, a celebration
 * and a noise for a scoring action are what EVERY combat scoreboard in this
 * product has, and Karate and Taekwondo would otherwise grow two identical
 * tables. The packages decide WHEN each slot plays; this only decides where the
 * file lives and who may fetch it.
 */
class ScreenMedia extends Model
{
    protected $table = 'event_screen_media';

    protected $fillable = ['event_id', 'slot', 'disk', 'path', 'original_name', 'mime', 'bytes', 'uploaded_by'];

    /**
     * The slots a screen knows how to play.
     *
     * A closed list because it is half of a filename and all of a route
     * parameter: anything not in here never reaches the filesystem.
     */
    public const SLOTS = [
        'vs_music',      // under the introduction, while the two names are shown
        'winner_music',  // over the celebration
        'point_1',       // yuko
        'point_2',       // waza-ari
        'point_3',       // ippon
        'foul',          // a penalty went up the ladder
        'match_start',   // hajime — the bout is under way
        'match_end',     // the bout is decided, before the celebration
        'time_up',       // the buzzer at 0:00
        'atoshi',        // the last-seconds alarm (WKF atoshi baraku)
    ];

    /** What a browser on a wall screen can actually play. */
    public const MIMES = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
        'audio/webm' => 'weba',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'audio/x-m4a' => 'm4a',
    ];

    /**
     * Ten megabytes. A celebration sting is tens of kilobytes and a three-minute
     * introduction track is a few megabytes; past this somebody is uploading a
     * set, and a hall screen has to fetch it over the venue's wifi.
     */
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public static function slot(ClubEvent $event, string $slot): ?self
    {
        if (! in_array($slot, self::SLOTS, true)) {
            return null;
        }

        return static::where('event_id', $event->id)->where('slot', $slot)->first();
    }

    /** Every slot this event has a file for, as slot => row. */
    public static function forEvent(ClubEvent $event): array
    {
        return static::where('event_id', $event->id)->get()->keyBy('slot')->all();
    }

    /**
     * Store an upload against a slot, replacing whatever was there.
     *
     * Validates the REAL bytes, not the name or the client's content-type: the
     * extension is assigned here from a whitelist, and the stored filename is
     * generated. Returns null when the file is not something a screen can play,
     * so the caller answers 422 rather than storing a surprise.
     */
    public static function put(ClubEvent $event, string $slot, UploadedFile $file, ?int $by): ?self
    {
        if (! in_array($slot, self::SLOTS, true)) {
            return null;
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return null;
        }

        // getMimeType() sniffs the file itself (finfo), unlike getClientMimeType.
        $mime = (string) $file->getMimeType();
        $ext = self::MIMES[$mime] ?? null;

        if ($ext === null) {
            return null;
        }

        // Application-generated path and filename, per the upload rules: the
        // event's public id groups it, and nothing user-supplied is in either.
        $dir = 'events/'.$event->uuid.'/screen-audio';
        $name = $slot.'-'.Str::random(24).'.'.$ext;

        $path = $file->storeAs($dir, $name, 'local');

        if ($path === false) {
            return null;
        }

        $existing = static::slot($event, $slot);

        // The old file goes AFTER the new one is safely stored, and only then —
        // a failed upload must never leave the slot empty.
        $row = static::updateOrCreate(
            ['event_id' => $event->id, 'slot' => $slot],
            [
                'disk' => 'local',
                'path' => $path,
                'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 120),
                'mime' => $mime,
                'bytes' => (int) $file->getSize(),
                'uploaded_by' => $by,
            ],
        );

        if ($existing && $existing->path !== $path) {
            Storage::disk($existing->disk)->delete($existing->path);
        }

        return $row;
    }

    /** Remove the file, then the row — never the other way round. */
    public function purge(): void
    {
        Storage::disk($this->disk)->delete($this->path);
        $this->delete();
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }
}
