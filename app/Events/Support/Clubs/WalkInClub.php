<?php

namespace App\Events\Support\Clubs;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventClub;
use App\Support\StoragePath;
use App\Traits\StoresBase64Images;
use Illuminate\Support\Str;

/**
 * A club named at the desk for a late entry — "the club this walk-in fights
 * for", typed rather than picked (owner, 2026-10-07).
 *
 * The same club twice under two spellings is a start list that adds up wrong,
 * so a name the event already has (case-insensitive) is REUSED, never
 * duplicated — the rule EventClubsController::store applies to the clubs page.
 * The logo is optional here and checked by its real bytes when given.
 */
final class WalkInClub
{
    use StoresBase64Images;

    /** @return array{club: ?EventClub, logo_rejected: bool} */
    public function findOrCreate(ClubEvent $event, string $name, ?string $logo, int $actorId): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if (mb_strlen($name) < 2) {
            return ['club' => null, 'logo_rejected' => false];
        }

        $existing = EventClub::where('event_id', $event->id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();
        if ($existing) {
            return ['club' => $existing, 'logo_rejected' => false];
        }

        $path = null;
        $rejected = false;
        if ($logo) {
            $path = $this->storeBase64Image($logo, StoragePath::event($event, 'clubs'), (string) Str::uuid());
            $rejected = $path === null;
        }

        return ['club' => EventClub::create([
            'event_id' => $event->id,
            'name' => mb_substr($name, 0, 120),
            'logo' => $path,
            'state' => 'listed',
            'created_by' => $actorId,
        ]), 'logo_rejected' => $rejected];
    }
}
