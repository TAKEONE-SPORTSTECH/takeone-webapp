<?php

namespace App\Events\Support\ClubEntry;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventEntryConsent;
use Illuminate\Http\Request;

/**
 * Who accepted an event's rules, and which rules (owner, 2026-10-04).
 *
 * The rules screen (/me/events/{uuid}/rules) records an acceptance HERE before
 * it lets the reader on; the row's uuid is remembered in the reader's session
 * for that event, so later steps — and the finished registration, which stamps
 * the club on it — find the same row. Session-held on purpose: an invited club
 * has no account, and the session already carries its proof of identity.
 *
 * A fingerprint of what was shown (about text, numbered requirements, the
 * documents attached) is kept with it. When the organiser changes any of
 * those, the fingerprint no longer matches and the reader is asked again.
 *
 * The statements are taken from the HOST's own words (the desk's strings), in
 * the reader's language — never from the request.
 */
final class EntryConsent
{
    private const KEY = 'evconsent';

    /** The fingerprint of the rules as the page shows them (its `$e` payload). */
    public static function rulesHash(array $e): string
    {
        $docs = collect($e['documents'] ?? [])->map(fn ($d) => (string) ($d['uuid'] ?? ''))->sort()->values()->all();

        return hash('sha256', json_encode([
            'about' => trim((string) ($e['about'] ?? '')),
            'requirements' => array_values(array_map('strval', (array) ($e['requirements'] ?? []))),
            'documents' => $docs,
        ], JSON_UNESCAPED_UNICODE));
    }

    /** The statements a reader accepts, from the host's strings. */
    public static function statements(array $strings): array
    {
        return array_values(array_filter([(string) ($strings['accept1'] ?? ''), (string) ($strings['accept2'] ?? '')]));
    }

    public static function record(Request $request, ClubEvent $event, string $channel, array $statements, string $hash): EventEntryConsent
    {
        $consent = new EventEntryConsent;
        $consent->forceFill([
            'event_id' => $event->id,
            'channel' => $channel,
            'user_id' => $request->user()?->id,
            'statements' => $statements,
            'rules_hash' => $hash,
            'locale' => substr((string) app()->getLocale(), 0, 12),
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'accepted_at' => now(),
        ])->save();

        $held = (array) $request->session()->get(self::KEY, []);
        $held[(string) $event->uuid] = $consent->uuid;
        $request->session()->put(self::KEY, array_slice($held, -20, null, true));

        return $consent;
    }

    /** This reader's acceptance of these exact rules, or null. */
    public static function current(Request $request, ClubEvent $event, string $hash): ?EventEntryConsent
    {
        if (! $request->hasSession()) {
            return null;
        }
        $uuid = ((array) $request->session()->get(self::KEY, []))[(string) $event->uuid] ?? null;

        return is_string($uuid)
            ? EventEntryConsent::where('uuid', $uuid)->where('event_id', $event->id)->where('rules_hash', $hash)->first()
            : null;
    }

    /** Stamp the club this reader registered on their acceptance. */
    public static function attach(Request $request, ClubEvent $event, array $subject): ?EventEntryConsent
    {
        if (! $request->hasSession()) {
            return null;
        }
        $uuid = ((array) $request->session()->get(self::KEY, []))[(string) $event->uuid] ?? null;
        $consent = is_string($uuid) ? EventEntryConsent::where('uuid', $uuid)->where('event_id', $event->id)->first() : null;
        if ($consent && ! empty($subject['key'])) {
            $consent->forceFill([
                'subject_key' => mb_substr((string) $subject['key'], 0, 64),
                'subject_name' => mb_substr((string) ($subject['name'] ?? ''), 0, 190) ?: null,
                'subject_at' => now(),
            ])->save();
        }

        return $consent;
    }
}
