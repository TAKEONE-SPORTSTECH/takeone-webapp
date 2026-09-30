<?php

namespace App\Federations\Services;

use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationTransfer;
use App\Members\Models\User;
use App\Members\Models\UserPhoto;
use App\Members\Support\ProfilePhoto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An athlete, as ONE file — and back again.
 *
 * Deleting an athlete is permanent (FederationAthleteMerge::remove), so a
 * federation can first take everything about them away in a single
 * `.tkathlete` file named "<Full name> - <CPR>.tkathlete", and later import it
 * to put them back exactly as they were: the same TakeOne ID, the same national
 * ID, their club history, their entries, their transfers, and every photograph
 * they ever had.
 *
 * ── The file ────────────────────────────────────────────────────────────────
 * A zip (manifest.json + athlete.json + photos/…), then ENCRYPTED and sealed
 * with the platform's key (Laravel's encrypter: AES-256 + MAC). It carries a
 * national ID and photographs, so it must be unreadable to anybody who finds
 * it, and it must be impossible to hand-edit: a changed byte fails the MAC and
 * the import refuses it. Only this platform can read it back.
 *
 * ── The import ──────────────────────────────────────────────────────────────
 * Nothing in the file is trusted beyond the seal: every club, entry and event
 * is looked up again and must still exist inside the importer's federation;
 * photographs are re-stored through ProfilePhoto (real bytes checked, names
 * generated). Refused when the TakeOne ID or the national ID now belongs to
 * somebody else, or when the athlete belongs to another federation.
 */
class FederationAthleteArchive
{
    public const EXT = 'tkathlete';

    public const MAGIC = "TAKEONE-ATHLETE\n";

    public const VERSION = 1;

    private const MAX_PHOTO_BYTES = 12 * 1024 * 1024;

    public function __construct(private FederationDirectory $directory, private FederationRegistry $registry) {}

    // ------------------------------------------------------------- export

    /**
     * @return array{name: string, bytes: string}|null  null when the athlete is not this federation's
     */
    public function export(User $by, array $access, string $tk): ?array
    {
        $ids = $this->ids($access);
        $a = FederationAthlete::with(['user' => fn ($q) => $q->withTrashed()])->where('takeone_id', $tk)->first();
        if (! $a || ! $this->inScope($a, $ids)) {
            return null;
        }
        $u = $a->user;

        $photos = [];
        $files = [];
        if ($u) {
            $rows = UserPhoto::where('user_id', $u->id)->orderBy('sort_order')->orderBy('id')->get();
            $paths = $rows->pluck('path')->all();
            if ($u->profile_picture && ! in_array($u->profile_picture, $paths, true)) {
                array_unshift($paths, $u->profile_picture);
            }
            foreach (array_values(array_unique(array_filter($paths))) as $i => $path) {
                $bytes = $this->readPhoto($path);
                if ($bytes === null) {
                    continue;
                }
                $name = 'photos/'.($i + 1).'.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
                $files[$name] = $bytes;
                $row = $rows->firstWhere('path', $path);
                $photos[] = ['file' => $name, 'current' => $path === $u->profile_picture,
                    'sort_order' => $row?->sort_order, 'added_at' => optional($row?->created_at)->toIso8601String()];
            }
        }

        $clubKey = fn ($fcId, $appId) => $fcId ? ($c = FederationClub::find($fcId)) ? $this->registry->clubKey($c) : null
            : (($c = $appId ? FederationClubApplication::find($appId) : null) ? $this->registry->clubKey($c) : null);
        $clubName = fn ($fcId, $appId) => $fcId ? FederationClub::with('club')->find($fcId)?->club?->club_name : FederationClubApplication::find($appId)?->name;

        $data = [
            'person' => $u ? [
                'uuid' => $u->uuid,
                'full_name' => $u->full_name, 'name' => $u->name,
                'gender' => $u->gender, 'birthdate' => optional($u->birthdate)->toDateString(),
                'nationality' => $u->nationality, 'mobile' => $u->mobile, 'email' => $u->email,
                'documents' => $u->documents, 'is_unclaimed' => (bool) $u->is_unclaimed,
                'deleted' => $u->trashed(),
            ] : null,
            'athlete' => [
                'takeone_id' => $a->takeone_id, 'national_id' => $a->national_id, 'nationality' => $a->nationality,
                'created_at' => optional($a->created_at)->toIso8601String(),
            ],
            'clubs' => FederationAthleteClub::where('federation_athlete_id', $a->id)->orderBy('id')->get()->map(fn ($r) => [
                'federation' => $r->federation_id, 'club' => $clubKey($r->federation_club_id, $r->club_application_id),
                'club_name' => $clubName($r->federation_club_id, $r->club_application_id),
                'from_ym' => $r->from_ym, 'to_ym' => $r->to_ym,
            ])->values()->all(),
            'entries' => FederationEntryAthlete::with('entry.event')->where('federation_athlete_id', $a->id)->orderBy('id')->get()->map(fn ($r) => [
                'entry' => $r->entry?->uuid, 'entry_ref' => $r->entry?->ref,
                'event' => $r->entry?->event?->uuid, 'event_name' => $r->entry?->event?->name,
                'weight' => $r->weight, 'types' => $r->types, 'status' => $r->status, 'approval' => $r->approval,
                'late' => (bool) $r->late, 'chargeable' => (bool) $r->chargeable,
                'withdrawn_at' => optional($r->withdrawn_at)->toIso8601String(),
            ])->values()->all(),
            'transfers' => FederationTransfer::where('federation_athlete_id', $a->id)->orderBy('id')->get()->map(fn ($t) => [
                'uuid' => $t->uuid, 'federation' => $t->federation_id,
                'from' => $clubKey($t->from_club_id, $t->from_application_id), 'to' => $clubKey($t->to_club_id, $t->to_application_id),
                'requested_by' => $t->requested_by, 'status' => $t->status, 'old_club' => $t->old_club,
                'due_at' => optional($t->due_at)->toIso8601String(), 'decided_at' => optional($t->decided_at)->toIso8601String(),
                'created_at' => optional($t->created_at)->toIso8601String(),
            ])->values()->all(),
            'photos' => $photos,
        ];
        $manifest = ['format' => 'takeone-athlete', 'version' => self::VERSION, 'exported_at' => now()->toIso8601String(),
            'exported_by' => $by->uuid, 'federations' => $ids->all(), 'takeone_id' => $a->takeone_id,
            'name' => $u?->full_name ?: $u?->name];

        $zip = $this->zip(['manifest.json' => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'athlete.json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)] + $files);

        activity()->causedBy($by)->withProperties(['takeone_id' => $a->takeone_id, 'photos' => count($photos),
            'federation' => $access['root']->uuid ?? $access['root']->id])->log('federation.athlete_exported');

        $cpr = $a->national_id ?: $a->takeone_id;
        $file = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', ($u?->full_name ?: $a->takeone_id).' - '.$cpr));

        return ['name' => mb_substr($file, 0, 120).'.'.self::EXT, 'bytes' => self::MAGIC.Crypt::encryptString(base64_encode($zip))];
    }

    // ------------------------------------------------------------- import

    /** @return array{ok: bool, message: string, takeoneId?: string, notes?: array<int, string>} */
    public function import(User $by, array $access, string $raw): array
    {
        if (! str_starts_with($raw, self::MAGIC)) {
            return ['ok' => false, 'message' => 'This is not a TakeOne athlete file (.'.self::EXT.').'];
        }
        try {
            $zipBytes = base64_decode(Crypt::decryptString(substr($raw, strlen(self::MAGIC))), true);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'This file cannot be opened — it was changed, damaged, or made by another system.'];
        }
        $parts = $zipBytes === false ? null : $this->unzip($zipBytes);
        $manifest = json_decode($parts['manifest.json'] ?? '', true);
        $data = json_decode($parts['athlete.json'] ?? '', true);
        if (! is_array($manifest) || ($manifest['format'] ?? '') !== 'takeone-athlete' || ! is_array($data)) {
            return ['ok' => false, 'message' => 'This file is damaged.'];
        }
        if ((int) ($manifest['version'] ?? 0) > self::VERSION) {
            return ['ok' => false, 'message' => 'This file was made by a newer version of TakeOne.'];
        }

        $ids = $this->ids($access);
        if (collect((array) ($manifest['federations'] ?? []))->intersect($ids)->isEmpty()) {
            return ['ok' => false, 'message' => 'This athlete belongs to another federation.'];
        }

        $ath = (array) ($data['athlete'] ?? []);
        $tk = strtoupper((string) ($ath['takeone_id'] ?? ''));
        if (! preg_match('/^[A-Z]{3}-[0-9A-Z]{1,10}$/', $tk)) {
            return ['ok' => false, 'message' => 'This file is damaged.'];
        }
        $p = (array) ($data['person'] ?? []);
        $name = (string) ($p['full_name'] ?? $tk);
        $notes = [];

        // Conflicts: the ID or the national ID now belongs to somebody else.
        $existing = FederationAthlete::where('takeone_id', $tk)->first();
        $user = ! empty($p['uuid']) ? User::withTrashed()->where('uuid', (string) $p['uuid'])->first() : null;
        // A record kept in a draw as "Deleted athlete" is THIS person's to take back.
        $reclaim = $existing && $existing->anonymised_at;
        if ($existing && ! $reclaim && (! $user || $existing->user_id !== $user->id)) {
            return ['ok' => false, 'message' => $tk.' now belongs to another athlete — nothing was imported.'];
        }
        $natId = isset($ath['national_id']) && $ath['national_id'] !== '' ? (string) $ath['national_id'] : null;
        $hash = FederationAthlete::hashNationalId($natId);
        if ($hash && FederationAthlete::where('national_id_hash', $hash)->where('takeone_id', '!=', $tk)->exists()) {
            return ['ok' => false, 'message' => 'The national ID in this file now belongs to another athlete — nothing was imported.'];
        }

        try {
            $result = DB::transaction(function () use ($p, $ath, $tk, $natId, $hash, $existing, $user, $data, $parts, $ids, &$notes) {
                // 1. The person: back from the bin, as they were, or made again.
                if ($user && $user->trashed()) {
                    $user->restore();
                    $notes[] = 'Their account was restored.';
                } elseif (! $user) {
                    $user = User::create(array_filter([
                        'uuid' => (string) ($p['uuid'] ?? Str::uuid()),
                        'full_name' => (string) ($p['full_name'] ?? $tk), 'name' => (string) ($p['name'] ?? $p['full_name'] ?? $tk),
                        'gender' => in_array($p['gender'] ?? null, ['Male', 'Female'], true) ? $p['gender'] : null,
                        'birthdate' => $p['birthdate'] ?? null, 'nationality' => $p['nationality'] ?? null,
                        'mobile' => $p['mobile'] ?? null,
                        'email' => ! empty($p['email']) && ! User::where('email', $p['email'])->exists() ? $p['email'] : null,
                        'documents' => $p['documents'] ?? null,
                        'is_unclaimed' => true, 'is_discoverable' => false, 'password' => null,
                    ], fn ($v) => $v !== null));
                    $notes[] = 'Their account was created again.';
                }

                // 2. The athlete record — the same TakeOne ID and national ID.
                if ($existing && $existing->anonymised_at) {
                    $stand = $existing->user;
                    $existing->forceFill(['user_id' => $user->id, 'national_id' => $natId, 'national_id_hash' => $hash,
                        'nationality' => $ath['nationality'] ?? null, 'anonymised_at' => null])->save();
                    if ($stand && $stand->id !== $user->id && $stand->full_name === 'Deleted athlete' && $stand->is_unclaimed) {
                        $stand->forceDelete();   // the nameless stand-in, no longer needed
                    }
                    $notes[] = 'Their place in the draw has their name again.';
                }
                $a = $existing ?? FederationAthlete::create([
                    'user_id' => $user->id, 'takeone_id' => $tk, 'national_id' => $natId,
                    'national_id_hash' => $hash, 'nationality' => $ath['nationality'] ?? null,
                ]);

                // 3. Club history, where the club is still this federation's.
                $restored = ['clubs' => 0, 'entries' => 0, 'transfers' => 0, 'photos' => 0];
                foreach ((array) ($data['clubs'] ?? []) as $c) {
                    $club = $this->registry->clubFromKey((string) ($c['club'] ?? ''), $ids);
                    if (! $club) {
                        $notes[] = 'Club history at '.($c['club_name'] ?? 'a club').' was not restored — that club is no longer in the federation.';

                        continue;
                    }
                    FederationAthleteClub::firstOrCreate([
                        'federation_athlete_id' => $a->id,
                        'federation_club_id' => $club instanceof FederationClub ? $club->id : null,
                        'club_application_id' => $club instanceof FederationClubApplication ? $club->id : null,
                        'from_ym' => $c['from_ym'] ?? null,
                    ], ['federation_id' => $club->federation_id, 'to_ym' => $c['to_ym'] ?? null]);
                    $restored['clubs']++;
                }

                // 4. Entries, where the entry still exists in this federation's events.
                foreach ((array) ($data['entries'] ?? []) as $en) {
                    $entry = ! empty($en['entry']) ? FederationEntry::where('uuid', (string) $en['entry'])
                        ->whereHas('event', fn ($q) => $q->whereIn('federation_id', $ids))->first() : null;
                    if (! $entry) {
                        $notes[] = 'The entry in '.($en['event_name'] ?? 'an event').' was not restored — that entry no longer exists.';

                        continue;
                    }
                    FederationEntryAthlete::updateOrCreate(['federation_entry_id' => $entry->id, 'federation_athlete_id' => $a->id], [
                        'weight' => is_numeric($en['weight'] ?? null) ? $en['weight'] : null, 'types' => (array) ($en['types'] ?? []),
                        'status' => in_array($en['status'] ?? null, ['Active', 'Withdrawn'], true) ? $en['status'] : 'Active',
                        'approval' => in_array($en['approval'] ?? null, ['Pending', 'Approved', 'Rejected'], true) ? $en['approval'] : 'Pending',
                        'late' => (bool) ($en['late'] ?? false), 'chargeable' => (bool) ($en['chargeable'] ?? false),
                        'withdrawn_at' => $en['withdrawn_at'] ?? null,
                    ]);
                    $restored['entries']++;
                }

                // 5. Transfer requests, where both clubs are still known.
                foreach ((array) ($data['transfers'] ?? []) as $t) {
                    if (empty($t['uuid']) || FederationTransfer::where('uuid', $t['uuid'])->exists()) {
                        continue;
                    }
                    $from = $this->registry->clubFromKey((string) ($t['from'] ?? ''), $ids);
                    $to = $this->registry->clubFromKey((string) ($t['to'] ?? ''), $ids);
                    if (! $to) {
                        continue;
                    }
                    $row = new FederationTransfer;
                    $row->forceFill([
                        'uuid' => $t['uuid'], 'federation_id' => $to->federation_id, 'federation_athlete_id' => $a->id,
                        'from_club_id' => $from instanceof FederationClub ? $from->id : null,
                        'from_application_id' => $from instanceof FederationClubApplication ? $from->id : null,
                        'to_club_id' => $to instanceof FederationClub ? $to->id : null,
                        'to_application_id' => $to instanceof FederationClubApplication ? $to->id : null,
                        'requested_by' => $t['requested_by'] ?? null, 'status' => $t['status'] ?? 'Pending', 'old_club' => $t['old_club'] ?? null,
                        'due_at' => $t['due_at'] ?? null, 'decided_at' => $t['decided_at'] ?? null,
                    ])->save();
                    $restored['transfers']++;
                }

                // 6. Every photograph, re-stored (bytes checked, names generated).
                $have = UserPhoto::where('user_id', $user->id)->pluck('path')->filter(fn ($x) => $this->readPhoto($x) !== null)->count();
                if ($have === 0) {
                    $current = null;
                    foreach ((array) ($data['photos'] ?? []) as $ph) {
                        $bytes = $parts[(string) ($ph['file'] ?? '')] ?? null;
                        if (! is_string($bytes) || strlen($bytes) > self::MAX_PHOTO_BYTES) {
                            continue;
                        }
                        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/jpeg';
                        $path = ProfilePhoto::store($user, 'data:'.$mime.';base64,'.base64_encode($bytes));
                        if ($path) {
                            $restored['photos']++;
                            if (! empty($ph['current'])) {
                                $current = $path;
                            }
                        }
                    }
                    if ($current) {
                        $user->forceFill(['profile_picture' => $current])->save();
                    }
                } else {
                    $notes[] = 'Their photographs were already there and were left as they are.';
                }

                return [$a, $restored];
            });
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => 'The import of '.$name.' did not go through — nothing was changed.'];
        }

        [$a, $restored] = $result;
        activity()->causedBy($by)->withProperties(['takeone_id' => $tk, 'restored' => $restored,
            'federation' => $access['root']->uuid ?? $access['root']->id])->log('federation.athlete_imported');

        $parts2 = array_filter([
            $restored['clubs'] ? $restored['clubs'].' club record'.($restored['clubs'] > 1 ? 's' : '') : null,
            $restored['entries'] ? $restored['entries'].' entr'.($restored['entries'] > 1 ? 'ies' : 'y') : null,
            $restored['transfers'] ? $restored['transfers'].' transfer'.($restored['transfers'] > 1 ? 's' : '') : null,
            $restored['photos'] ? $restored['photos'].' photo'.($restored['photos'] > 1 ? 's' : '') : null,
        ]);

        return ['ok' => true, 'takeoneId' => $tk, 'notes' => $notes,
            'message' => $name.' ('.$tk.') is back'.($parts2 ? ' — '.implode(', ', $parts2).'.' : '.')];
    }

    // ------------------------------------------------------------ helpers

    private function inScope(FederationAthlete $a, Collection $ids): bool
    {
        $events = \App\Federations\Models\FederationEvent::whereIn('federation_id', $ids)->pluck('id');

        return FederationAthleteClub::where('federation_athlete_id', $a->id)->whereIn('federation_id', $ids)->exists()
            || FederationEntryAthlete::where('federation_athlete_id', $a->id)
                ->whereHas('entry', fn ($q) => $q->whereIn('federation_event_id', $events))->exists()
            || FederationTransfer::where('federation_athlete_id', $a->id)->whereIn('federation_id', $ids)->exists();
    }

    private function readPhoto(?string $path): ?string
    {
        if (! $path || str_contains($path, '..')) {
            return null;
        }
        foreach (['public', 'local'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    $b = Storage::disk($disk)->get($path);

                    return is_string($b) && strlen($b) <= self::MAX_PHOTO_BYTES ? $b : null;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /** @param  array<string, string>  $files */
    private function zip(array $files): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tka');
        $z = new \ZipArchive;
        $z->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($files as $name => $bytes) {
            $z->addFromString($name, $bytes);
        }
        $z->close();
        $out = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $out;
    }

    /** @return array<string, string>|null  only the names this format uses */
    private function unzip(string $bytes): ?array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tka');
        file_put_contents($tmp, $bytes);
        $z = new \ZipArchive;
        if ($z->open($tmp) !== true) {
            @unlink($tmp);

            return null;
        }
        $out = [];
        for ($i = 0; $i < min($z->numFiles, 200); $i++) {
            $n = (string) $z->getNameIndex($i);
            if (preg_match('#^(manifest\.json|athlete\.json|photos/\d{1,3}\.(jpe?g|png|gif|webp))$#i', $n)) {
                $s = $z->statIndex($i);
                if (($s['size'] ?? 0) <= self::MAX_PHOTO_BYTES) {
                    $out[$n] = (string) $z->getFromIndex($i);
                }
            }
        }
        $z->close();
        @unlink($tmp);

        return $out;
    }

    private function ids(array $access): Collection
    {
        return collect($this->directory->subtree($access['root']))->pluck('id');
    }
}
