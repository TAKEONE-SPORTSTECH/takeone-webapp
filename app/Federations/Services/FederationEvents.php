<?php

namespace App\Federations\Services;

use App\Events\Support\DocumentUpload;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationEventDocument;
use App\Federations\Models\FederationUpload;
use App\Members\Models\User;
use App\Traits\StoresBase64Images;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Federations\Support\FederationTestMode;
use Illuminate\Support\Str;

/**
 * A federation's events, in the exact object shape the designed dashboard
 * keeps in its `events` list — and the one door that saves them.
 *
 * Scope: the events of every body in the viewer's tree; a sport-limited
 * viewer sees only their sports (the page filters too, but the page is not
 * the boundary). Creating and editing need the event's sport to be one the
 * viewer may see, and the body it belongs to must be in their tree.
 */
class FederationEvents
{
    use StoresBase64Images;

    /** The form fields kept in `details`, exactly as the page names them. */
    private const DETAIL_KEYS = [
        'dates', 'venue', 'city', 'tickets', 'revenue', 'enrollStart', 'enrollEnd', 'weighIn', 'drawDate',
        'dayStart', 'dayEnd', 'breakOn', 'breakFrom', 'breakTo', 'mats', 'location', 'about', 'free', 'fees',
        'lateFee', 'lateFrom', 'specOn', 'specs', 'lat', 'lng', 'mapUrl', 'types', 'reqs',
    ];

    public function __construct(private FederationDirectory $directory) {}

    /** @return array<int, array> newest first, as the page lists them */
    public function payload(array $access): array
    {
        return $this->query($access)->with('documents')->latest('id')->get()
            ->map(fn (FederationEvent $e) => $this->shape($e))->all();
    }

    private function query(array $access)
    {
        $ids = collect($this->directory->subtree($access['root']))->pluck('id');
        $q = FederationEvent::whereIn('federation_id', $ids);
        if ($access['sports'] !== []) {
            $q->whereIn('sport', $access['sports']);
        }

        return $q;
    }

    public function find(array $access, string $uuid): ?FederationEvent
    {
        return Str::isUuid($uuid) ? $this->query($access)->where('uuid', $uuid)->first() : null;
    }

    public function shape(FederationEvent $e): array
    {
        $d = (array) $e->details;
        $media = fn (?string $path, string $kind) => $path
            ? route('federations.event-media', ['event' => $e->uuid, 'kind' => $kind, 'v' => substr(md5($path), 0, 8)]) : null;

        return [
            'id' => $e->uuid,
            'orgId' => $e->federation?->uuid,
            'name' => $e->name,
            'sport' => $e->sport,
            // Derived from what has happened (FederationEvent::lifecycleStatus);
            // the typed one is kept beside it for the edit form.
            'status' => $e->lifecycleStatus(),
            'statusTyped' => $e->status,
            'cap' => $e->capacity,
            'startDate' => $e->starts_on,
            'endDate' => $e->ends_on,
            'dates' => $d['dates'] ?? 'TBC',
            'venue' => $d['venue'] ?? 'TBC',
            'city' => $d['city'] ?? '',
            // Derived, never typed: athletes, divisions, check-ins and the
            // readiness checklist, read from the event itself (FederationEventFigures).
            ...app(FederationEventFigures::class)->of($e, $d),
            'tickets' => $d['tickets'] ?? '—',
            'revenue' => $d['revenue'] ?? '—',
            'logo' => $media($e->logo, 'logo'),
            'logoShape' => $e->logo_shape ?: 'circle',
            'artP' => $media($e->art_portrait, 'art-p'),
            'artL' => $media($e->art_landscape, 'art-l'),
            'docs' => $e->documents->map(fn (FederationEventDocument $doc) => [
                'uuid' => $doc->uuid,
                'name' => $doc->title,
                'file' => $doc->filename,
                'size' => $this->sizeLabel((int) $doc->size),
                'url' => route('federations.event-doc', $doc->uuid),
                'data' => route('federations.event-doc', $doc->uuid),
            ])->all(),
        ] + array_intersect_key($d, array_flip(self::DETAIL_KEYS));
    }

    private function sizeLabel(int $bytes): string
    {
        return $bytes < 1048576 ? max(1, (int) round($bytes / 1024)).' KB' : number_format($bytes / 1048576, 1).' MB';
    }

    /**
     * Save the event the page just applied. `$orgUuid` is the body it
     * belongs to (the one the viewer has open); an existing event stays where
     * it is.
     */
    public function save(User $user, array $access, array $posted, ?string $orgUuid): ?array
    {
        $uuid = (string) ($posted['id'] ?? '');
        if (! Str::isUuid($uuid)) {
            return null;
        }

        $sport = mb_substr(trim((string) ($posted['sport'] ?? '')), 0, 40);
        $mySports = $access['sports'];
        $treeSports = collect($this->directory->subtree($access['root']))->flatMap(fn ($f) => (array) $f->sports)->unique()->values()->all();
        $allowedSports = $mySports !== [] ? $mySports : ($treeSports !== [] ? $treeSports : FederationDirectory::SPORTS);
        if (! in_array($sport, $allowedSports, true)) {
            return null;
        }

        $event = FederationEvent::withTrashed()->where('uuid', $uuid)->first();
        if ($event) {
            // Existing: only if it is one the viewer can see.
            if ($event->trashed() || ! $this->find($access, $uuid)) {
                return null;
            }
        } else {
            $body = collect($this->directory->subtree($access['root']))->first(fn (Federation $f) => $f->uuid === $orgUuid) ?? $access['root'];
            if ($body->isSuspended()) {
                return null;
            }
            $event = new FederationEvent(['federation_id' => $body->id, 'created_by' => $user->id]);
            $event->uuid = $uuid;
        }

        $name = mb_substr(trim(strip_tags((string) ($posted['name'] ?? ''))), 0, 150);
        if ($name === '') {
            return null;
        }

        DB::transaction(function () use ($event, $posted, $name, $sport, $user, $access) {
            $event->name = $name;
            $event->sport = $sport;
            $event->status = in_array($posted['status'] ?? null, FederationEvent::STATUSES, true) ? $posted['status'] : ($event->status ?: 'Planning');
            $cap = $posted['cap'] ?? null;
            $event->capacity = is_numeric($cap) && (int) $cap > 0 ? min((int) $cap, 100000) : null;
            $event->starts_on = $this->dateish($posted['startDate'] ?? null);
            $event->ends_on = $this->dateish($posted['endDate'] ?? null);
            // The rule book is chosen on its own (setRuleBook), never by this form.
            $event->details = $this->details($posted)
                + array_filter(array_intersect_key((array) $event->details, array_flip(['categorySystem', 'beltSystem'])), fn ($v) => $v !== null);
            $event->logo_shape = in_array($posted['logoShape'] ?? null, ['circle', 'rect'], true) ? $posted['logoShape'] : 'circle';
            $event->save();

            $folder = 'federations/'.$event->federation->uuid.'/events/'.$event->uuid.'/branding';
            foreach (['logo' => 'logo', 'artP' => 'art_portrait', 'artL' => 'art_landscape'] as $key => $column) {
                $this->media($event, $column, $posted[$key] ?? null, array_key_exists($key, $posted), $folder);
            }
            $event->save();

            $this->documents($event, is_array($posted['docs'] ?? null) ? $posted['docs'] : [], $user, $access);
        });

        // Its platform event follows (federations.platform_events). A failure
        // there must never lose the federation's own save.
        rescue(fn () => app(FederationPlatformEvent::class)->sync($event->fresh('federation')), null, report: true);

        return $this->shape($event->fresh(['documents', 'federation']));
    }

    private function dateish(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/', $v) ? $v : null;
    }

    private function feeRows(array $p, \Closure $str): array
    {
        $types = collect(is_array($p['types'] ?? null) ? $p['types'] : [])->map(fn ($t) => $str($t, 60))->filter()->all();

        return collect(is_array($p['fees'] ?? null) ? $p['fees'] : [])->take(50)
            ->filter(fn ($r) => is_array($r) && $str($r['label'] ?? '') !== '')
            ->map(function ($r) use ($str, $types) {
                $row = ['label' => $str($r['label']), 'amount' => $str($r['amount'] ?? '', 20)];
                $type = $str($r['type'] ?? '', 60);
                if ($type !== '' && in_array($type, $types, true)) {
                    $row['type'] = $type;
                }

                return $row;
            })->values()->all();
    }

    private function details(array $p): array
    {
        $str = fn ($v, $n = 150) => is_scalar($v) ? mb_substr(trim(strip_tags((string) $v)), 0, $n) : '';
        $rows = fn ($v) => collect(is_array($v) ? $v : [])->take(50)
            ->filter(fn ($r) => is_array($r) && $str($r['label'] ?? '') !== '')
            ->map(fn ($r) => ['label' => $str($r['label']), 'amount' => $str($r['amount'] ?? '', 20)])->values()->all();
        $url = $str($p['mapUrl'] ?? '', 500);

        return [
            'dates' => $str($p['dates'] ?? 'TBC', 40) ?: 'TBC',
            'venue' => $str($p['venue'] ?? 'TBC') ?: 'TBC',
            'city' => $str($p['city'] ?? ''),
            'tickets' => $str($p['tickets'] ?? '—', 30) ?: '—',
            'revenue' => $str($p['revenue'] ?? '—', 30) ?: '—',
            'enrollStart' => $this->dateish($p['enrollStart'] ?? null) ?? '',
            'enrollEnd' => $this->dateish($p['enrollEnd'] ?? null) ?? '',
            'weighIn' => $this->dateish($p['weighIn'] ?? null) ?? '',
            'drawDate' => $this->dateish($p['drawDate'] ?? null) ?? '',
            'dayStart' => preg_match('/^\d{2}:\d{2}$/', (string) ($p['dayStart'] ?? '')) ? $p['dayStart'] : '',
            'dayEnd' => preg_match('/^\d{2}:\d{2}$/', (string) ($p['dayEnd'] ?? '')) ? $p['dayEnd'] : '',
            'breakOn' => (bool) ($p['breakOn'] ?? false),
            'breakFrom' => preg_match('/^\d{2}:\d{2}$/', (string) ($p['breakFrom'] ?? '')) ? $p['breakFrom'] : '',
            'breakTo' => preg_match('/^\d{2}:\d{2}$/', (string) ($p['breakTo'] ?? '')) ? $p['breakTo'] : '',
            'mats' => is_numeric($p['mats'] ?? null) ? max(1, min(50, (int) $p['mats'])) : null,
            'location' => $str($p['location'] ?? '', 300),
            'about' => FederationHtml::clean(is_string($p['about'] ?? null) ? $p['about'] : ''),
            'free' => ($p['free'] ?? true) !== false,
            // A fee line may name the competition type it prices (`type`) —
            // the invitation joins on that before it tries the label.
            'fees' => $this->feeRows($p, $str),
            'lateFee' => $str($p['lateFee'] ?? '', 20),
            'lateFrom' => $this->dateish($p['lateFrom'] ?? null) ?? '',
            'specOn' => (bool) ($p['specOn'] ?? false),
            'specs' => $rows($p['specs'] ?? []),
            'lat' => is_numeric($p['lat'] ?? null) ? (string) round((float) $p['lat'], 5) : '',
            'lng' => is_numeric($p['lng'] ?? null) ? (string) round((float) $p['lng'], 5) : '',
            // Only a real Google Maps link (App\Support\MapLink) — the pin follows it.
            'mapUrl' => \App\Support\MapLink::isGoogle($url) ? $url : '',
            'types' => collect(is_array($p['types'] ?? null) ? $p['types'] : [])->map(fn ($t) => $str($t, 60))->filter()->unique()->take(20)->values()->all(),
            'reqs' => collect(is_array($p['reqs'] ?? null) ? $p['reqs'] : [])->map(fn ($r) => $str($r, 300))->filter()->take(50)->values()->all(),
        ];
    }

    /** A data: URL is a new crop; our own link is "unchanged"; null is "removed". */
    private function media(FederationEvent $event, string $column, mixed $value, bool $sent, string $folder): void
    {
        if (is_string($value) && str_starts_with($value, 'data:image/')) {
            $path = $this->storeBase64Image($value, $folder, (string) Str::uuid(), 'public');
            if ($path !== null) {
                $old = $event->{$column};
                $event->{$column} = $path;
                if ($old && $old !== $path) {
                    Storage::disk('public')->delete($old);
                }
            }
        } elseif ($sent && $value === null && $event->{$column}) {
            Storage::disk('public')->delete($event->{$column});
            $event->{$column} = null;
        }
    }

    /**
     * The document list the page holds: existing ones by uuid, new ones by
     * the staging token the bridge swapped in. Anything no longer listed is
     * deleted — file first, then row.
     */
    private function documents(FederationEvent $event, array $docs, User $user, array $access): void
    {
        $keep = [];
        $position = 0;

        foreach (array_slice($docs, 0, 30) as $d) {
            if (! is_array($d)) {
                continue;
            }
            $title = mb_substr(trim(strip_tags((string) ($d['name'] ?? ''))), 0, 150) ?: 'Document';

            if (Str::isUuid((string) ($d['uuid'] ?? ''))) {
                $doc = $event->documents()->where('uuid', $d['uuid'])->first();
                if ($doc) {
                    $doc->update(['title' => $title, 'position' => $position++]);
                    $keep[] = $doc->id;
                }

                continue;
            }

            $token = (string) ($d['token'] ?? '');
            if (! Str::isUuid($token)) {
                continue;
            }
            $staged = FederationUpload::where('token', $token)->where('user_id', $user->id)
                ->whereIn('federation_id', collect($this->directory->subtree($access['root']))->pluck('id'))->first();
            if (! $staged || ! Storage::disk('local')->exists($staged->path)) {
                continue;
            }

            $target = 'federations/'.$event->federation->uuid.'/events/'.$event->uuid.'/documents/'.basename($staged->path);
            Storage::disk('local')->move($staged->path, $target);
            $doc = $event->documents()->create([
                'title' => $title,
                'filename' => $staged->filename,
                'path' => $target,
                'mime' => $staged->mime,
                'size' => $staged->size,
                'position' => $position++,
            ]);
            $staged->delete();   // the file moved; the row's own path is gone already
            $keep[] = $doc->id;
        }

        $event->documents()->whereNotIn('id', $keep)->get()->each->delete();
    }

    /**
     * Delete an event: a SOFT delete, so it leaves every list and screen but
     * its entries, draws, results and files are all kept and it can be
     * restored. Only a manager (the federation's owner, unrestricted staff, a
     * platform administrator) may, and only an event in their own tree —
     * anything else answers exactly as a missing event does.
     */
    public function delete(User $user, array $access, string $uuid): bool
    {
        if (! ($access['manager'] ?? false)) {
            return false;
        }
        $event = $this->find($access, $uuid);
        if (! $event) {
            return false;
        }

        $event->delete();
        // Its platform event is archived with it — entries and bouts are kept.
        rescue(fn () => app(FederationPlatformEvent::class)->archive($event), null, report: true);
        activity()->causedBy($user)
            ->withProperties(['event' => $event->uuid, 'name' => $event->name, 'federation' => $event->federation?->uuid])
            ->log('federation.event_deleted');

        return true;
    }

    /** The uuids of the events in scope that are in test mode. */
    /**
     * Paid featuring on the members' Events page (App\Events\Support\EventFeaturing),
     * for every event this viewer can see: uuid → the organiser's state.
     */
    public function featureStates(array $access): array
    {
        if (! class_exists(\App\Events\Support\EventFeaturing::class)) {
            return [];
        }

        return $this->query($access)->with('federation')->get()
            ->mapWithKeys(fn (FederationEvent $e) => [$e->uuid => \App\Events\Support\EventFeaturing::state($e)])->all();
    }

    /**
     * Ask for featuring: the same gate as test mode and deleting (a manager),
     * then EventFeaturing's own rules — fee set, event still to come, proof by its bytes.
     *
     * @return array{ok:bool, message:string, state?:array}
     */
    public function requestFeature(User $user, array $access, string $uuid, \Illuminate\Http\UploadedFile $proof): array
    {
        if (! ($access['manager'] ?? false) || ! class_exists(\App\Events\Support\EventFeaturing::class)) {
            return ['ok' => false, 'message' => 'Only a manager can ask for featuring.'];
        }
        $event = $this->find($access, $uuid);
        if (! $event || ! $event->federation) {
            return ['ok' => false, 'message' => 'This event could not be found.'];
        }

        $r = \App\Events\Support\EventFeaturing::submit($user, $event, $proof);

        return $r + ['state' => \App\Events\Support\EventFeaturing::state($event)];
    }

    /**
     * The rule book each event in scope runs under — its category system (age
     * groups × weight classes) and belt system — and what it may choose from:
     * the sport's built-in systems plus the federation's own (made in the
     * event console's rule book, saved for the federation).
     *
     * @return array<string, array{category: ?string, belt: ?string, categories: array, belts: array}>
     */
    public function ruleBooks(array $access): array
    {
        $systems = app(\App\Events\Support\CategorySystems::class);

        return $this->query($access)->get()->mapWithKeys(function (FederationEvent $e) use ($systems) {
            $sport = strtolower(trim((string) $e->sport));
            $d = (array) $e->details;
            $list = fn (array $all) => array_values(array_map(fn ($x) => ['key' => $x->key, 'label' => $x->label, 'custom' => (bool) $x->custom], $all));

            return [$e->uuid => [
                'category' => $d['categorySystem'] ?? null,
                'belt' => $d['beltSystem'] ?? null,
                'categories' => $sport !== '' ? $list($systems->weights($sport, 'federation', (int) $e->federation_id)) : [],
                'belts' => $sport !== '' ? $list($systems->belts($sport, 'federation', (int) $e->federation_id)) : [],
            ]];
        })->all();
    }

    /**
     * Choose an event's rule book (a manager, as for test mode and featuring).
     * Kept on the event; when it already runs as a platform event the choice
     * goes through CategorySystems::change — every entrant re-sorted, refused
     * once a draw is cut — so the dashboard and the console can never differ.
     *
     * @return array{ok: bool, message: string, rulebook?: array}
     */
    public function setRuleBook(User $user, array $access, string $uuid, ?string $category, ?string $belt, ?string $mode = null): array
    {
        if (! ($access['manager'] ?? false)) {
            return ['ok' => false, 'message' => 'Only a manager can change the rule book.'];
        }
        $event = $this->find($access, $uuid);
        if (! $event) {
            return ['ok' => false, 'message' => 'This event could not be found.'];
        }
        $book = $this->ruleBooks($access)[$event->uuid] ?? null;
        $category = $category ?: null;
        $belt = $belt ?: null;
        if (! $book
            || ($category !== null && ! in_array($category, array_column($book['categories'], 'key'), true))
            || ($belt !== null && ! in_array($belt, array_column($book['belts'], 'key'), true))) {
            return ['ok' => false, 'message' => __('events::events.rulebook_invalid')];
        }

        $moved = 0;
        if ($ev = app(FederationPlatformEvent::class)->of($event)) {
            $r = app(\App\Events\Support\CategorySystems::class)->change($ev, $category, $belt, $mode);
            if (! $r['ok']) {
                return ['ok' => false, 'message' => $r['message'] ?? __('events::events.rulebook_invalid'), 'can_keep' => (bool) ($r['can_keep'] ?? false)];
            }
            $moved = (int) ($r['moved'] ?? 0);
            $kept = (bool) ($r['kept'] ?? false);
        }

        $d = (array) $event->details;
        $d['categorySystem'] = $category;
        $d['beltSystem'] = $belt;
        $event->details = $d;
        $event->saveQuietly();
        activity()->causedBy($user)->withProperties(['event' => $event->uuid, 'category' => $category, 'belt' => $belt])
            ->log('federation.event_rulebook');

        return ['ok' => true, 'message' => $moved ? __('events::events.rulebook_changed', ['moved' => $moved, 'created' => 0])
                : (($kept ?? false) ? 'Rule book saved — your divisions and the draw are unchanged.' : 'Rule book saved.'),
            'rulebook' => ['category' => $category, 'belt' => $belt]];
    }

    /**
     * The event's ranking coefficient (App\Rankings — the WKF "event factor"):
     * a manager of the event's federation sets it; the event's ranking points
     * are recomputed at once. Managers only, in the viewer's scope.
     */
    public function setRankingFactor(User $user, array $access, string $uuid, int $factor): array
    {
        if (! ($access['manager'] ?? false) || ! config('rankings.enabled')) {
            return ['ok' => false, 'message' => 'Only a manager can set the ranking coefficient.'];
        }
        $event = $this->find($access, $uuid);
        $ev = $event ? app(FederationPlatformEvent::class)->of($event) : null;
        if (! $ev) {
            return ['ok' => false, 'message' => 'This event could not be found.'];
        }

        $before = \App\Rankings\Support\EventFactor::for($ev);
        $factor = \App\Rankings\Support\EventFactor::set($ev, $factor, $user);
        \Illuminate\Support\Facades\Cache::forget('fed-analytics:'.$ev->id);
        activity()->causedBy($user)->withProperties(['event' => $event->uuid, 'from' => $before, 'to' => $factor])
            ->log('federation.event_ranking_factor');

        return ['ok' => true, 'message' => 'Ranking coefficient set to ×'.$factor.' — points recalculated.', 'factor' => $factor];
    }

    public function testEventIds(array $access): array
    {
        return $this->query($access)->whereNotNull('test_mode_at')->pluck('uuid')->all();
    }

    /**
     * Test mode on or off (App\Federations\Support\FederationTestMode). The
     * same gate as deleting an event. Off takes a verified database backup
     * first (CLAUDE.md RULE #2) and then removes everything the test made.
     *
     * @return array{ok:bool, message:string, testMode?:bool, removed?:array}
     */
    public function setTestMode(User $user, array $access, string $uuid, bool $on): array
    {
        if (! ($access['manager'] ?? false)) {
            return ['ok' => false, 'message' => 'Only a manager can switch test mode.'];
        }
        $event = $this->find($access, $uuid);
        if (! $event) {
            return ['ok' => false, 'message' => 'This event could not be found.'];
        }

        if ($on) {
            if (! $event->test_mode_at) {
                $event->forceFill(['test_mode_at' => now()])->save();
                activity()->causedBy($user)->performedOn($event)->log('federation.test_mode_on');
            }

            return ['ok' => true, 'testMode' => true, 'message' => 'Test mode is on. Everything entered from now on is test data.'];
        }
        if (! $event->test_mode_at) {
            return ['ok' => true, 'testMode' => false, 'message' => 'Test mode is already off.'];
        }

        // One sweep at a time, and never without a backup behind it.
        $lock = \Illuminate\Support\Facades\Cache::lock('fedtest:purge:'.$event->id, 300);
        if (! $lock->get()) {
            return ['ok' => false, 'message' => 'Test data is already being removed.'];
        }
        try {
            if (\Illuminate\Support\Facades\Artisan::call('takeone:backup', ['--db-only' => true]) !== 0) {
                return ['ok' => false, 'message' => 'The backup failed, so nothing was deleted. Test mode is still on.'];
            }
            $removed = FederationTestMode::purge($event->fresh());
        } finally {
            $lock->release();
        }
        activity()->causedBy($user)->performedOn($event)->withProperties($removed)->log('federation.test_mode_off');

        return ['ok' => true, 'testMode' => false, 'removed' => $removed,
            'message' => 'Test mode is off. '.$removed['created'].' test records removed, '.$removed['restored'].' restored.'];
    }

    /** Stage a document picked in an open form. */
    public function stage(User $user, array $access, UploadedFile $file): ?string
    {
        $stored = app(DocumentUpload::class)->store($file, 'federations/'.$access['root']->uuid.'/staging', 'local');
        if (! $stored) {
            return null;
        }
        $token = (string) Str::uuid();
        FederationUpload::create([
            'token' => $token,
            'federation_id' => $access['root']->id,
            'user_id' => $user->id,
            'path' => $stored['path'],
            'mime' => $stored['mime'],
            'size' => $stored['size'],
            'filename' => mb_substr(basename(str_replace('\\', '/', (string) $file->getClientOriginalName())), 0, 150),
        ]);

        return $token;
    }
}
