<?php

namespace App\Events\Support\Clubs;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventClub;
use App\Support\StoragePath;
use App\Traits\StoresBase64Images;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Changing a club that was WRITTEN DOWN for an event — the one place it is done.
 *
 * Moved unchanged out of the event's Clubs page controller on 2026-10-09 so the
 * Karate scoring console's Clubs tab saves through the very same rules:
 *
 *   · only a written-down club. A club with an account owns its own name, crest
 *     and country on its `tenants` row; an organiser editing those from inside
 *     one competition would be editing somebody else's record by a side door.
 *   · no two spellings of one club in one event (itself excepted).
 *   · an Instagram page is reduced to one canonical https URL or refused —
 *     the value ends up in an href.
 *   · a new crest is checked by its real bytes and stored under the event; the
 *     old one goes only once the new one is safely stored.
 *
 * Authorisation is the caller's (EventAccess::canManage), and so is validating
 * the shape of the input.
 */
class EventClubEditor
{
    use StoresBase64Images;

    /**
     * @param  array{name: string, logo?: ?string, instagram?: ?string, country?: ?string}  $data
     * @return array{ok: bool, message: string, field?: string, club?: EventClub}
     */
    public function update(ClubEvent $event, EventClub $eventClub, array $data): array
    {
        if ((int) $eventClub->event_id !== (int) $event->id) {
            return ['ok' => false, 'message' => __('events::events.club_not_editable')];
        }

        if ($eventClub->isOnPlatform()) {
            return ['ok' => false, 'message' => __('events::events.club_not_editable')];
        }

        $name = trim(preg_replace('/\s+/u', ' ', $data['name']));

        // Same rule as creating one: two spellings of one club is a start list
        // that adds up wrong. Itself excepted, or renaming nothing would fail.
        $clash = EventClub::where('event_id', $event->id)
            ->where('id', '!=', $eventClub->id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($clash) {
            return ['ok' => false, 'field' => 'name', 'message' => __('events::events.club_exists')];
        }

        $instagram = self::instagramUrl($data['instagram'] ?? null);

        if (($data['instagram'] ?? '') !== '' && $instagram === null) {
            return ['ok' => false, 'field' => 'instagram', 'message' => __('events::events.club_instagram_invalid')];
        }

        $previousLogo = $eventClub->logo;
        $logo = $previousLogo;

        if (! empty($data['logo'])) {
            $stored = $this->storeBase64Image(
                $data['logo'],
                StoragePath::event($event, 'clubs'),
                (string) Str::uuid()
            );

            if (! $stored) {
                return ['ok' => false, 'field' => 'logo', 'message' => __('events::events.club_logo_rejected')];
            }

            $logo = $stored;
        }

        $eventClub->forceFill([
            'name' => $name,
            'logo' => $logo,
            'instagram' => $instagram,
            'country' => isset($data['country']) ? strtoupper($data['country']) : $eventClub->country,
        ])->save();

        // The old crest goes only once the new one is safely stored, and only
        // if it actually moved (CLAUDE.md, "Delete Files Before Records" — the
        // replace case).
        if ($previousLogo && $previousLogo !== $logo) {
            rescue(fn () => Storage::disk('public')->delete($previousLogo), null, false);
        }

        return ['ok' => true, 'message' => __('events::events.club_updated', ['name' => $name]), 'club' => $eventClub];
    }

    /**
     * A pasted Instagram page, reduced to one canonical URL.
     *
     * Accepts what people actually paste — `@club`, `club`,
     * `instagram.com/club`, the full https URL with a query string — and emits
     * one shape. Anything else is refused rather than stored, because this
     * value ends up in an `href`: a `javascript:` scheme in a link an organiser
     * typed is a stored XSS on every screen that shows the club.
     */
    public static function instagramUrl(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        // A bare handle, with or without the @.
        if (preg_match('/^@?([A-Za-z0-9._]{1,30})$/', $raw, $m)) {
            return 'https://instagram.com/'.$m[1];
        }

        // A URL — and it must be Instagram's, over http(s), or it is not an
        // Instagram page whatever it claims.
        $url = preg_match('#^https?://#i', $raw) ? $raw : 'https://'.$raw;
        $parts = parse_url($url);

        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');

        if (! in_array($host, ['instagram.com', 'www.instagram.com', 'm.instagram.com'], true)) {
            return null;
        }

        $handle = trim((string) ($parts['path'] ?? ''), '/');

        if (! preg_match('/^([A-Za-z0-9._]{1,30})$/', $handle, $m)) {
            return null;
        }

        return 'https://instagram.com/'.$m[1];
    }
}
