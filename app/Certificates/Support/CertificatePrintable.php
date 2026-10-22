<?php

namespace App\Certificates\Support;

use App\Certificates\Contracts\CertificateContext;
use App\Members\Models\User;
use App\Printables\Contracts\PrintableKind;
use Illuminate\Database\Eloquent\Model;

/**
 * Certificates and badges as a printable KIND (App\Printables).
 *
 * The owner is a certificate CONTEXT (an event, an event's officials, its
 * participants) and the slot is the certificate type (participation, podium,
 * official_badge …). Who may design it is exactly who may manage that context's
 * certificates — the provider decides, as it does for the old design room.
 *
 * The live fields are the context's own {tokens}, plus the recipient's name and
 * three pictures: their photograph, the verification QR and the host's logo.
 */
class CertificatePrintable implements PrintableKind
{
    public function __construct(private readonly Contexts $contexts) {}

    public function key(): string
    {
        return 'certificate';
    }

    public function label(): string
    {
        return __('printables::messages.kind_certificate');
    }

    public function canDesign(Model $owner, User $user): bool
    {
        return (bool) $this->contexts->forModel($owner)?->canManage($owner, $user);
    }

    public function formats(): array
    {
        return [];
    }

    public function startingSize(?string $slot): array
    {
        return $this->isBadge(self::baseType($slot))
            ? ['format' => 'badge_a6', 'orientation' => 'portrait']
            : ['format' => 'a4', 'orientation' => 'landscape'];
    }

    /**
     * Every certificate and badge type for this owner. An event's officials' and
     * participants' badges are providers of their own (they own no certificate
     * records, so forModel() never names them) — they extend the event's provider
     * and are listed beside its types.
     */
    public function slots(Model $owner): array
    {
        $base = $this->contexts->forModel($owner);
        if (! $base) {
            return [];
        }
        $types = [];
        foreach ($this->contexts->all() as $provider) {
            if (is_a($provider, $base::class)) {
                array_push($types, ...$provider->types());
            }
        }
        $types = array_values(array_unique($types));

        // Each job at an event may have its own officials' badge and certificate
        // (owner, 2026-10-20): `official_badge.<job>`, `official_thanks.<job>`.
        // A job without one prints from the general design.
        if ($owner instanceof \App\Events\Models\ClubEvent) {
            foreach (array_keys(app(\App\Events\Support\OfficialRoles::class)->jobs($owner)) as $job) {
                foreach (self::JOB_TYPES as $type) {
                    if (in_array($type, $types, true)) {
                        $types[] = $type.'.'.$job;
                    }
                }
            }
        }

        return $types;
    }

    /** The certificate types a job may have its own design of. */
    public const JOB_TYPES = ['official_badge', 'official_thanks'];

    /** `official_badge.referee` → `official_badge`; a plain type is itself. */
    public static function baseType(?string $slot): ?string
    {
        return $slot === null ? null : explode('.', $slot, 2)[0];
    }

    /** The job a slot is for (`referee`), or null for a type's general design. */
    public static function jobOf(?string $slot): ?string
    {
        return $slot !== null && str_contains($slot, '.') ? explode('.', $slot, 2)[1] : null;
    }

    /**
     * May the design for $to start as a copy of $from? Only within one KIND of
     * page — a certificate from any certificate (the club's or the referees'
     * certificate is a fine start for the judges'), a badge from any badge —
     * never across, because their page sizes and fields differ.
     */
    public function mayCopy(Model $owner, string $from, string $to): bool
    {
        return $this->isBadge(self::baseType($from)) === $this->isBadge(self::baseType($to));
    }

    /** "Official's badge — Referee", for the designer's list of designs. */
    public function slotLabel(Model $owner, string $slot): string
    {
        $type = __('cert::messages.types.'.self::baseType($slot));
        $job = self::jobOf($slot);
        $name = $job && $owner instanceof \App\Events\Models\ClubEvent ? (app(\App\Events\Support\OfficialRoles::class)->jobs($owner)[$job] ?? null) : null;

        return $name ? $type.' — '.$name : $type;
    }

    /** The provider a type belongs to — a badge type's own, not the event's. */
    private function provider(Model $owner, ?string $slot): ?CertificateContext
    {
        $slot = self::baseType($slot);
        $base = $this->contexts->forModel($owner);
        if ($base && $slot) {
            foreach ($this->contexts->all() as $provider) {
                if (is_a($provider, $base::class) && in_array($slot, $provider->types(), true)) {
                    return $provider;
                }
            }
        }

        return $base;
    }

    /** Back from the designer: the event's own console, where its certificates are issued. */
    public function homeUrl(Model $owner): ?string
    {
        return $owner instanceof \App\Events\Models\ClubEvent ? route('me.events.manage', $owner->uuid, false) : null;
    }

    public function ownerRef(Model $owner): string
    {
        $provider = $this->contexts->forModel($owner);

        return $provider ? $provider->alias().'.'.$provider->key($owner) : '';
    }

    public function findOwner(string $ref, User $user): ?Model
    {
        if (! preg_match('/^([a-z_]{2,40})\.([0-9a-fA-F-]{36})$/', $ref, $m)) {
            return null;
        }
        $found = $this->contexts->resolve($m[1], $m[2]);

        return $found[1] ?? null;
    }

    public function title(Model $owner, ?string $slot): string
    {
        $provider = $this->provider($owner, $slot);
        $type = $slot ? $this->slotLabel($owner, $slot) : $this->label();

        return trim($type.' · '.($provider ? $provider->headline($owner) : ''), ' ·');
    }

    public function fields(Model $owner, ?string $slot): array
    {
        $provider = $this->provider($owner, $slot);
        $job = self::jobOf($slot);
        $slot = self::baseType($slot);
        if (! $provider) {
            return [];
        }
        $keys = method_exists($provider, 'typePlaceholders') && $slot
            ? $provider->typePlaceholders($slot)
            : array_merge(['name'], $provider->placeholderKeys());
        $sample = $this->samples($provider, $owner, (string) $slot);
        // A job's own design previews with that job's name.
        if ($job && $owner instanceof \App\Events\Models\ClubEvent && ($name = app(\App\Events\Support\OfficialRoles::class)->jobs($owner)[$job] ?? null)) {
            $sample['role'] = $name;
        }

        // A club's thank-you is made out to the CLUB: its name and its crest.
        $club = $slot === \App\Events\Support\EventCertificates::TYPE_CLUBS;
        $label = fn (string $key) => __('printables::messages.field.'.($club && $key === 'name' ? 'club_name' : $key));

        $fields = [];
        foreach (array_unique(array_merge(['name'], $keys)) as $key) {
            $fields[] = [
                'key' => $key,
                'label' => $label($key),
                'type' => 'text',
                'sample' => (string) ($sample[$key] ?? __('printables::messages.field.'.$key)),
            ];
        }
        /*
         * Pictures. A certificate also offers the crest of the club it is about
         * (EventCertificates::clubLogo); a club's own certificate has no person,
         * so no photo — its crest IS the club logo. Badges keep their three.
         */
        // Flags too: the person's nationality and their club's country (a club's own certificate: its country only).
        $images = $this->isBadge($slot) ? ['photo', 'club_logo', 'nationality_flag', 'club_flag', 'qr', 'logo']
            : ($club ? ['club_logo', 'club_flag', 'qr', 'logo'] : ['photo', 'club_logo', 'nationality_flag', 'club_flag', 'qr', 'logo']);
        foreach ($images as $key) {
            $fields[] = ['key' => $key, 'label' => $label($key), 'type' => 'image', 'sample' => ''];
        }

        return $fields;
    }

    public function starter(Model $owner, ?string $slot, array $size): array
    {
        $slot = self::baseType($slot);

        return $this->isBadge($slot)
            ? StarterDesigns::badge($size, $this->title($owner, null))
            : ($slot === \App\Events\Support\EventCertificates::TYPE_CLUBS
                ? StarterDesigns::clubThanks($size)
                : StarterDesigns::certificate($size, $slot === 'podium'));
    }

    /**
     * The real people this design can be previewed with: the holders of this
     * owner's certificates of this type (drafted or issued), matching $search by
     * name. A job's own design lists only the officials who print from it.
     *
     * @return list<array{id: string, label: string, sub: string}>
     */
    public function realRecipients(Model $owner, ?string $slot, string $search = ''): array
    {
        $needle = mb_strtolower(trim($search));
        $out = [];
        foreach ($this->previewable($owner, $slot) as $c) {
            $name = $this->holderName($c);
            if ($needle !== '' && ! str_contains(mb_strtolower($name), $needle)) {
                continue;
            }
            $d = (array) ($c->details ?? []);
            $out[] = [
                'id' => (string) $c->uuid,
                'label' => $name,
                'sub' => implode(' · ', array_filter([
                    $d['division'] ?? $d['role'] ?? null,
                    $d['club'] ?? null,
                    __('printables::messages.'.($c->isDraft() ? 'real_draft' : 'real_issued')),
                ])),
            ];
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    /**
     * Every live field filled for one of those certificates — exactly what it
     * prints with (DesignedCertificates::values()), or null when $id is not one
     * of this owner's certificates of this type.
     *
     * @return array<string, string>|null
     */
    public function realValues(Model $owner, ?string $slot, string $id): ?array
    {
        $c = $this->previewable($owner, $slot)->firstWhere('uuid', $id);
        if (! $c) {
            return null;
        }

        return app(DesignedCertificates::class)->values($c, app(\Ahl\Certificates\CertificateService::class)->designFill($c));
    }

    /** This owner's certificates a design for $slot prints — never revoked ones, at most 500. */
    private function previewable(Model $owner, ?string $slot): \Illuminate\Support\Collection
    {
        $type = self::baseType($slot);
        if (! $type || $this->isBadge($type)) {
            return collect();
        }
        $rows = \Ahl\Certificates\Certificates::model()::query()->forContext($owner)
            ->where('type', $type)->where('status', '!=', 'revoked')
            ->with(['recipient', 'context'])->orderBy('id')->limit(500)->get()
            ->filter(fn ($c) => $c->recipient !== null);
        // A job's own design: only the officials who print from it.
        if (self::jobOf($slot) !== null) {
            $provider = $this->contexts->forModel($owner);
            $rows = $rows->filter(fn ($c) => $provider && method_exists($provider, 'designSlots') && in_array($slot, $provider->designSlots($c), true));
        }

        return $rows->values();
    }

    private function holderName(\Ahl\Certificates\Models\Certificate $c): string
    {
        $r = $c->recipient;

        return $r instanceof \Ahl\Certificates\Contracts\Recipient ? $r->certificateName() : (string) ($r?->getAttribute('name') ?? '');
    }

    private function isBadge(?string $slot): bool
    {
        return is_string($slot) && str_ends_with($slot, '_badge');
    }

    /** What each field reads as in the editor's preview — the event's real details where known. */
    private function samples(CertificateContext $provider, Model $owner, string $slot): array
    {
        $details = $slot !== '' ? (array) $provider->previewDetails($slot, $owner) : [];
        $place = isset($details['place']) ? (int) $details['place'] : null;

        return array_filter([
            // A club's thank-you is made out to the club.
            'name' => $slot === \App\Events\Support\EventCertificates::TYPE_CLUBS
                ? ($details['club'] ?? __('cert::messages.types.club_thanks'))
                : __('printables::messages.sample_name'),
            'event' => $provider->headline($owner),
            'division' => $details['division'] ?? null,
            'club' => $details['club'] ?? null,
            'representing' => isset($details['club']) ? __('cert::messages.representing', ['club' => $details['club']]) : null,
            'role' => $details['role'] ?? null,
            'athletes' => isset($details['athletes']) ? trans_choice('cert::messages.athletes_count', (int) $details['athletes'], ['count' => (int) $details['athletes']]) : null,
            'place' => $place ? __("cert::messages.place_word.{$place}") : null,
            'medal' => $place && $place <= 3 ? __("cert::messages.medal_word.{$place}") : null,
            'date' => now()->format('j M Y'),
        ], fn ($v) => $v !== null && $v !== '');
    }
}
