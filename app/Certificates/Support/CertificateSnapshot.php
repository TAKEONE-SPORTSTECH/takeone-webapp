<?php

namespace App\Certificates\Support;

use Ahl\Certificates\Models\Certificate;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Support\EntryPhoto;
use Carbon\Carbon;

/**
 * Freezes, on the certificate, who the holder WAS at the event — their age that
 * day, the weight recorded at the weigh-in, their height, and the photograph that
 * showed them — the moment it is issued. People grow up and change their pictures;
 * a certificate says what was true then, so the check page and every reprint read
 * this snapshot, never today's profile.
 *
 * The photograph is not copied (CLAUDE.md → A Person's Photo Has One Home): the
 * snapshot names the same file, and EntryPhoto counts a certificate as a claim on
 * it, so replacing a profile picture can no longer delete the face a certificate
 * still shows.
 *
 * Stored in `certificates.details.snapshot`:
 *   { at, age, weight, weighed, height, photo: {path, kind: entry|profile} }
 */
final class CertificateSnapshot
{
    /** Take (or keep) the snapshot. An existing one is never overwritten. */
    public static function take(Certificate $certificate): void
    {
        $details = (array) ($certificate->details ?? []);
        if (! empty($details['snapshot'])) {
            return;
        }
        $event = $certificate->context_id ? $certificate->context : null;
        $regId = (int) ($details['registration_id'] ?? 0);
        $reg = $event instanceof ClubEvent && $regId
            ? ClubEventRegistration::query()->where('event_id', $event->id)->with('user')->find($regId)
            : null;
        $holder = $certificate->recipient;

        $on = $event instanceof ClubEvent && $event->date
            ? Carbon::parse($event->getRawOriginal('date') ?? $event->date)
            : ($certificate->issued_on ? Carbon::parse($certificate->issued_on) : now());
        $birth = $holder?->birthdate ?? null;

        $photo = null;
        if ($reg && EntryPhoto::showable($reg->photo)) {
            $photo = ['path' => $reg->photo, 'kind' => 'entry'];
        } elseif ($holder?->profile_picture) {
            $photo = ['path' => $holder->profile_picture, 'kind' => 'profile'];
        }

        $details['snapshot'] = array_filter([
            'at' => now()->toIso8601String(),
            'age' => $birth ? (int) Carbon::parse($birth)->diffInYears($on) : ($reg?->declared_age ?: null),
            'weight' => $reg?->weight !== null ? (float) $reg->weight : null,
            'weighed' => $reg?->weighed_in_at !== null,
            'height' => $holder?->height_cm ? (int) $holder->height_cm : null,
            'photo' => $photo,
        ], fn ($v) => $v !== null);

        $certificate->forceFill(['details' => $details])->saveQuietly();
    }

    /** @return array<string, mixed> the snapshot, or [] before the certificate is issued */
    public static function of(Certificate $certificate): array
    {
        return (array) (((array) ($certificate->details ?? []))['snapshot'] ?? []);
    }

    /**
     * The frozen photograph as a URL for the check page, under the rule its kind obeys
     * TODAY: an event photograph if it may still be shown, a profile picture only
     * while its owner keeps their picture public.
     */
    public static function photoUrl(Certificate $certificate): ?string
    {
        $p = self::of($certificate)['photo'] ?? null;
        if (! $p || empty($p['path'])) {
            return null;
        }
        if (($p['kind'] ?? '') === 'entry') {
            return EntryPhoto::showable($p['path']) ? file_url($p['path']) : null;
        }

        return $certificate->recipient?->profile_picture_is_public ? file_url($p['path']) : null;
    }

    /** The same photograph as a data: URI, for printing. */
    public static function photoDataUri(Certificate $certificate): ?string
    {
        if (! self::photoUrl($certificate)) {
            return null;
        }
        $path = self::of($certificate)['photo']['path'];
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $path = preg_replace('#^(storage/|public/)#', '', ltrim($path, '/'));
        if (! $disk->exists($path)) {
            return null;
        }
        $bytes = (string) $disk->get($path);

        return 'data:'.((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/jpeg').';base64,'.base64_encode($bytes);
    }
}
