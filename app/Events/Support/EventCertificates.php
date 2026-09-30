<?php

namespace App\Events\Support;

use Ahl\Certificates\CertificateService;
use Ahl\Certificates\Certificates as Engine;
use Ahl\Certificates\Models\Certificate;
use App\Certificates\Contracts\CertificateContext;
use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventCertificateSetting;
use App\Federations\Models\Federation;
use App\Members\Models\User;
use App\Support\StoragePath;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What a competition gives the people who took part in it (2026-09-29).
 *
 *   participation — everyone who stepped on the mat, one per division entered
 *   podium        — gold, silver and bronze, read off the division's stored podium
 *
 * COMMON to every bracketed sport (CLAUDE.md → Shared Stays Shared): it reads
 * `event_categories.podium` — the one record Advancement writes, whatever the
 * sport or its bronze rule — and the bouts in `event_matches`, never a sport's
 * own engine. A division counts only once its podium is stored; until then it
 * is "still running" and nothing is drafted for it.
 *
 * Nothing reaches an athlete from here: this makes DRAFTS. The organiser
 * approves them in the console, and only then is the PDF stored and the
 * athlete told (App\Certificates).
 */
class EventCertificates implements CertificateContext
{
    public const ALIAS = 'club_event';

    public const SOURCE_ENTRY = 'event_entry';

    public const SOURCE_PODIUM = 'event_podium';

    /** @var array<int, array> brand per event, for one request */
    private array $brands = [];

    public function __construct(private EventAccess $access) {}

    public function alias(): string
    {
        return self::ALIAS;
    }

    public function ownerTypes(): array
    {
        return [self::ALIAS, 'club', 'federation'];
    }

    public function find(string $key): ?Model
    {
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $key)) {
            return null;
        }

        return ClubEvent::where('uuid', $key)->where('is_archived', false)->first();
    }

    public function key(Model $context): string
    {
        return (string) $context->uuid;
    }

    public function handles(Model $context): bool
    {
        return $context instanceof ClubEvent;
    }

    public function canManage(Model $context, User $user): bool
    {
        return $context instanceof ClubEvent && ! $context->is_archived && $this->access->canManage($context, $user);
    }

    /**
     * May this user change a design that belongs to $owner? The event's own
     * design: anyone who runs the event. The host's default, which every other
     * event of that host inherits: only the host's own people — an official
     * appointed to one event does not get to redesign a federation's paper.
     */
    public function canManageOwner(Model $context, array $owner, User $user): bool
    {
        if (! $this->canManage($context, $user)) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        [$type, $id] = [$owner[0] ?? '', (int) ($owner[1] ?? 0)];

        return match ($type) {
            self::ALIAS => $id === (int) $context->id,
            'club' => $id === (int) $context->tenant_id
                && (Tenant::whereKey($id)->where('owner_user_id', $user->id)->exists() || $user->isClubAdmin($id)),
            default => ! $context->tenant_id && $type === (string) $context->host_type && $id === (int) $context->host_id
                && $context->host()->manages($context, $user),
        };
    }

    public function audience(Model $context): array
    {
        return app(AudienceResolver::class)->managers($context);
    }

    public function headline(Model $context): string
    {
        return (string) ($context->getRawOriginal('title') ?: $context->title);
    }

    public function issuer(Model $context, string $locale): string
    {
        return EventHost::of($context)->name ?: $this->headline($context);
    }

    public function templateOwners(Model $context): array
    {
        $chain = [[self::ALIAS, (int) $context->id]];
        $host = EventHost::of($context);
        if ($host->id && $host->kind !== 'none') {
            $chain[] = [$host->kind, (int) $host->id];
        }

        return $chain;
    }

    public function ownerLabel(Model $context, array $owner, string $locale): string
    {
        return ($owner[0] ?? '') === self::ALIAS
            ? __('cert::messages.owner_event', [], $locale)
            : __('cert::messages.owner_host', ['host' => EventHost::of($context)->name], $locale);
    }

    public function ownerStorageRoot(array $owner): ?string
    {
        [$type, $id] = [$owner[0] ?? '', (int) ($owner[1] ?? 0)];

        return match ($type) {
            self::ALIAS => ($e = ClubEvent::find($id)) ? StoragePath::event($e, 'certificates') : null,
            'club' => ($c = Tenant::find($id)) ? StoragePath::club($c, 'certificates') : null,
            'federation' => ($f = Federation::find($id)) ? 'federations/'.$f->uuid.'/certificates' : null,
            default => null,
        };
    }

    public function storageRoot(Model $context): string
    {
        return StoragePath::event($context, 'certificates');
    }

    public function brand(Model $context): array
    {
        return $this->brands[$context->id] ??= $this->readBrand($context);
    }

    public function placeholderKeys(): array
    {
        return ['division', 'event', 'place', 'medal', 'date', 'venue', 'club', 'representing'];
    }

    public function placeholders(Certificate $certificate, string $locale): array
    {
        $event = $certificate->context;
        $d = (array) ($certificate->details ?? []);
        $place = isset($d['place']) ? (int) $d['place'] : null;
        $club = trim((string) ($d['club'] ?? ''));

        return [
            'division' => (string) ($d['division'] ?? $certificate->achievement ?? ''),
            'event' => $event instanceof ClubEvent ? $this->headline($event) : (string) ($d['event'] ?? ''),
            'place' => $place ? __("cert::messages.place_word.{$place}", [], $locale) : '',
            'medal' => $place && $place <= 3 ? __("cert::messages.medal_word.{$place}", [], $locale) : '',
            'date' => $event instanceof ClubEvent ? $this->dates($event, $locale) : '',
            'venue' => $event instanceof ClubEvent ? (string) ($event->getRawOriginal('location') ?? '') : '',
            'club' => $club,
            'representing' => $club !== '' ? __('cert::messages.representing', ['club' => $club], $locale) : '',
        ];
    }

    public function previewDetails(string $type, Model $context): array
    {
        $division = EventCategory::where('event_id', $context->id)->competing()->orderBy('sort_order')->value('name');

        return [
            'division' => $division ?: __('cert::messages.sample_division'),
            'club' => EventHost::of($context)->name ?: null,
            'place' => $type === 'podium' ? 1 : null,
        ];
    }

    public function types(): array
    {
        return ['participation', 'podium'];
    }

    public function settings(Model $context): array
    {
        $s = EventCertificateSetting::for($context);

        return [
            'participation' => (bool) $s->participation,
            'podium' => (bool) $s->podium,
            'trigger' => in_array($s->trigger, EventCertificateSetting::TRIGGERS, true) ? $s->trigger : 'division',
        ];
    }

    public function saveSettings(Model $context, array $input): array
    {
        $s = EventCertificateSetting::for($context);
        foreach (['participation', 'podium'] as $flag) {
            if (array_key_exists($flag, $input)) {
                $s->{$flag} = (bool) $input[$flag];
            }
        }
        if (isset($input['trigger']) && in_array($input['trigger'], EventCertificateSetting::TRIGGERS, true)) {
            $s->trigger = $input['trigger'];
        }
        $s->save();

        return $this->settings($context);
    }

    public function eligible(Model $context): array
    {
        $plan = $this->plan($context);

        return [
            'participation' => $plan['participation']->count(),
            'podium' => $plan['podium']->count(),
            'decided' => $plan['decided'],
            'pending' => $plan['pending'],
        ];
    }

    /**
     * Draft what has been earned and not yet given. Idempotent: an entry that
     * already has a certificate of that kind (a draft, an approved one, or one
     * the organiser revoked) is never given another. A podium DRAFT whose place
     * changed since — a result corrected at the table — follows the correction;
     * an approved one is left alone for the organiser to revoke.
     */
    public function generate(Model $context, ?User $by): array
    {
        $settings = $this->settings($context);
        $plan = $this->plan($context);
        $service = app(CertificateService::class);
        $created = 0;

        return DB::transaction(function () use ($context, $by, $settings, $plan, $service, &$created) {
            if ($settings['podium']) {
                $this->reconcilePodiumDrafts($context, $plan['podium'], $service);
            }

            foreach (['participation' => self::SOURCE_ENTRY, 'podium' => self::SOURCE_PODIUM] as $type => $source) {
                if (! $settings[$type]) {
                    continue;
                }
                foreach ($plan[$type] as $row) {
                    if ($service->exists($row['user'], $source, $row['registration_id'])) {
                        continue;
                    }
                    $service->createDraft($row['user'], $type, [
                        'achievement' => $row['division'],
                        'context' => $context,
                        'source' => $source,
                        'source_id' => $row['registration_id'],
                        'details' => array_filter([
                            'division' => $row['division'],
                            'category_id' => $row['category_id'],
                            'registration_id' => $row['registration_id'],
                            'club' => $row['club'],
                            'place' => $row['place'] ?? null,
                        ], fn ($v) => $v !== null && $v !== ''),
                    ], $by);
                    $created++;
                }
            }

            return ['created' => $created, 'pending' => $plan['pending']];
        });
    }

    public function describe(Certificate $certificate, string $locale): string
    {
        $d = (array) ($certificate->details ?? []);
        $division = (string) ($d['division'] ?? $certificate->achievement ?? '');
        $place = isset($d['place']) ? (int) $d['place'] : null;

        return $place && $place <= 3
            ? $division.' · '.__("cert::messages.medal_short.{$place}", [], $locale)
            : $division;
    }

    /* ─────────────────────────── the plan ─────────────────────────── */

    /**
     * Who has earned what, right now.
     *
     * @return array{participation: Collection, podium: Collection, decided: int, pending: int}
     */
    public function plan(ClubEvent $event): array
    {
        $divisions = EventCategory::where('event_id', $event->id)->competing()->orderBy('sort_order')->get(['id', 'name', 'podium']);
        $decided = $divisions->filter(fn ($c) => is_array($c->podium) && $c->podium !== []);

        $fought = $decided->isEmpty() ? collect() : DB::table('event_matches')
            ->where('event_id', $event->id)
            ->whereIn('category_id', $decided->pluck('id'))
            ->whereNotNull('winner')
            ->get(['category_id', 'a_competitor_id', 'b_competitor_id'])
            ->flatMap(fn ($m) => [[$m->category_id, $m->a_competitor_id], [$m->category_id, $m->b_competitor_id]])
            ->filter(fn ($pair) => $pair[1] !== null)
            ->map(fn ($pair) => $pair[0].':'.$pair[1])
            ->unique()
            ->flip();

        $medals = $decided->flatMap(fn ($c) => collect($c->podium)
            ->filter(fn ($m) => ! empty($m['competitor_id']) && (int) ($m['place'] ?? 0) >= 1 && (int) ($m['place'] ?? 0) <= 3)
            ->map(fn ($m) => ['category_id' => $c->id, 'registration_id' => (int) $m['competitor_id'], 'place' => (int) $m['place']]));

        $ids = $fought->keys()->map(fn ($k) => (int) explode(':', $k)[1])->merge($medals->pluck('registration_id'))->unique()->values();

        $entries = $ids->isEmpty() ? collect() : ClubEventRegistration::with(['user', 'representingTenant:id,club_name'])
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->where('role', 'participant')
            ->get()
            ->filter(fn ($r) => $r->user !== null)
            ->keyBy('id');

        $clubs = EntryClub::forMany($event, $entries->keys());
        $names = $decided->mapWithKeys(fn ($c) => [$c->id => (string) ($c->getRawOriginal('name') ?: $c->name)]);

        $row = function (ClubEventRegistration $r, int $categoryId, ?int $place = null) use ($names, $clubs) {
            $club = $r->competingClub()?->club_name ?: ($clubs[$r->id]['name'] ?? null);

            return [
                'registration_id' => $r->id,
                'category_id' => $categoryId,
                'division' => $names[$categoryId] ?? '',
                'user' => $r->user,
                'club' => $club ?: null,
                'place' => $place,
            ];
        };

        $participation = $entries
            ->filter(fn ($r) => $r->category_id && $decided->contains('id', $r->category_id)
                && ($fought->has($r->category_id.':'.$r->id) || $medals->contains('registration_id', $r->id)))
            ->map(fn ($r) => $row($r, (int) $r->category_id))
            ->values();

        $podium = $medals
            ->filter(fn ($m) => $entries->has($m['registration_id']))
            ->map(fn ($m) => $row($entries[$m['registration_id']], $m['category_id'], $m['place']))
            ->values();

        return [
            'participation' => $participation,
            'podium' => $podium,
            'decided' => $decided->count(),
            'pending' => $divisions->count() - $decided->count(),
        ];
    }

    /** A podium draft follows its division's result until it is approved. */
    private function reconcilePodiumDrafts(ClubEvent $event, Collection $podium, CertificateService $service): void
    {
        $current = $podium->keyBy('registration_id');

        Engine::model()::query()->forContext($event)
            ->where('source', self::SOURCE_PODIUM)
            ->where('status', 'draft')
            ->get()
            ->each(function (Certificate $c) use ($current, $service) {
                $now = $current[$c->source_id] ?? null;
                if (! $now) {
                    $service->delete($c);   // no longer on the podium: the draft goes (files first)

                    return;
                }
                $details = (array) $c->details;
                if ((int) ($details['place'] ?? 0) !== (int) $now['place']) {
                    $details['place'] = $now['place'];
                    $c->forceFill(['details' => $details])->save();
                }
            });
    }

    /* ─────────────────────────── branding ─────────────────────────── */

    private function readBrand(ClubEvent $event): array
    {
        $host = EventHost::of($event);
        $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $event->color) ? strtolower($event->color)
            : (preg_match('/^#[0-9a-fA-F]{6}$/', (string) $host->color) ? strtolower($host->color) : '#6d5bd0');

        $logoPath = null;
        if ($host->club) {
            $logoPath = $host->club->logo;
        } elseif ($event->host_type === 'federation' && $event->host_id) {
            $f = Federation::find($event->host_id);
            $logoPath = $f && $f->isApproved() ? $f->logo : null;
        }
        $logo = $this->logoPng($logoPath);

        return [
            'accent' => $accent,
            'watermark' => $logo,
            'logos' => $logo ? ['data:image/png;base64,'.base64_encode($logo)] : [],
            'seal' => $this->initials($this->headline($event)),
        ];
    }

    /** The host's logo as a small transparent PNG, or null (never a remote fetch). */
    private function logoPng(?string $path): ?string
    {
        $path = preg_replace('#^(storage/|public/)#', '', ltrim((string) $path, '/'));
        if ($path === '' || str_contains($path, '://') || str_contains($path, '..') || ! Storage::disk('public')->exists($path)) {
            return null;
        }
        $bytes = (string) Storage::disk('public')->get($path);
        $im = @imagecreatefromstring($bytes);
        if (! $im instanceof \GdImage) {
            return null;
        }
        [$w, $h] = [imagesx($im), imagesy($im)];
        $scale = min(1, 400 / max($w, $h));
        [$tw, $th] = [max(1, (int) ($w * $scale)), max(1, (int) ($h * $scale))];
        $out = imagecreatetruecolor($tw, $th);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $im, 0, 0, 0, 0, $tw, $th, $w, $h);
        ob_start();
        imagepng($out);
        $png = (string) ob_get_clean();
        imagedestroy($im);
        imagedestroy($out);

        return $png;
    }

    /** "Bahrain Open Karate Championship 2026" → "BOKC". Latin titles only; otherwise no seal word. */
    private function initials(string $title): ?string
    {
        $words = preg_split('/\s+/u', trim($title)) ?: [];
        $letters = collect($words)
            ->filter(fn ($w) => preg_match('/^[A-Za-z]/', $w) && ! in_array(strtolower($w), ['of', 'the', 'and', 'for', 'in', 'at'], true))
            ->map(fn ($w) => strtoupper($w[0]))
            ->take(5)
            ->implode('');

        return strlen($letters) >= 2 ? $letters : null;
    }

    private function dates(ClubEvent $event, string $locale): string
    {
        if (! $event->date) {
            return '';
        }
        $start = Carbon::parse($event->getRawOriginal('date') ?? $event->date)->locale($locale);
        $end = $event->end_date ? Carbon::parse($event->getRawOriginal('end_date') ?? $event->end_date)->locale($locale) : null;

        if (! $end || $end->isSameDay($start)) {
            return $start->translatedFormat('j F Y');
        }
        if ($end->isSameMonth($start)) {
            return $start->translatedFormat('j').'–'.$end->translatedFormat('j F Y');
        }

        return $start->translatedFormat('j F').' – '.$end->translatedFormat('j F Y');
    }
}
