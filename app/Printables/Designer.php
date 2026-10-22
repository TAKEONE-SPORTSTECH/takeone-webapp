<?php

namespace App\Printables;

use App\Members\Models\User;
use App\Printables\Contracts\PrintableKind;
use App\Printables\Models\PrintableDesign;
use App\Printables\Support\Kinds;
use Illuminate\Database\Eloquent\Model;

/**
 * The designer's PUBLIC door — how any module opens, finds or links to a design.
 *
 *   Designer::url($event, 'certificate', 'podium', back: '/me/events/…/manage')
 *       the address of the editor for that owner's design (made on first save)
 *   Designer::find($event, 'certificate', 'podium')
 *       the saved design, or null — what a printer reads
 *
 * Nothing outside app/Printables reaches its controllers or support classes.
 */
final class Designer
{
    public static function enabled(): bool
    {
        return (bool) config('printables.enabled');
    }

    public static function kind(string $key): ?PrintableKind
    {
        return app(Kinds::class)->get($key);
    }

    public static function find(Model $owner, string $kind, ?string $slot = null): ?PrintableDesign
    {
        return PrintableDesign::query()
            ->where('kind', $kind)
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->where('slot', (string) $slot)
            ->first();
    }

    /**
     * The design that prints for $owner — the saved one, or, when none has been saved
     * yet, the kind's starting page (an unsaved model: exactly what the designer shows
     * on first open). So what the organiser sees in the designer is always what prints.
     * Null only when the kind is unknown.
     */
    public static function resolve(Model $owner, string $kind, ?string $slot = null): ?PrintableDesign
    {
        if ($saved = self::find($owner, $kind, $slot)) {
            return $saved;
        }
        $k = self::kind($kind);
        if (! $k) {
            return null;
        }
        $start = $k->startingSize($slot);
        [$wMm, $hMm] = \App\Printables\Support\Formats::resolve($start['format'], $start['orientation'], null, null, []) ?? [210, 297];
        $imageFields = array_values(array_column(array_filter($k->fields($owner, $slot), fn ($f) => $f['type'] === 'image'), 'key'));
        $px = [\App\Printables\Support\Formats::px($wMm), \App\Printables\Support\Formats::px($hMm)];

        $design = new PrintableDesign([
            'kind' => $kind,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'slot' => (string) $slot,
            'format' => $start['format'],
            'orientation' => $start['orientation'],
            'width_mm' => $wMm,
            'height_mm' => $hMm,
            'document' => (new \App\Printables\Support\DocumentSanitizer([], $imageFields, \App\Printables\Support\Fonts::families()))
                ->clean(['pages' => [['elements' => $k->starter($owner, $slot, $px)]]]),
            'revision' => 0,
        ]);
        $design->setRelation('owner', $owner);
        $design->setRelation('assets', collect());

        return $design;
    }

    /** The editor's address for $owner's design of $kind. Null when the kind is unknown. */
    public static function url(Model $owner, string $kind, ?string $slot = null, ?string $back = null): ?string
    {
        $k = self::kind($kind);
        if (! $k) {
            return null;
        }
        $query = array_filter(['slot' => $slot, 'back' => self::safeBack($back)]);

        return route('me.printables.design', ['kind' => $kind, 'ref' => $k->ownerRef($owner)] + $query);
    }

    public static function canDesign(Model $owner, string $kind, ?User $user): bool
    {
        $k = self::kind($kind);

        return $k !== null && $user !== null && $k->canDesign($owner, $user);
    }

    /** Only a path on this site, never another host — the back address is user-supplied. */
    public static function safeBack(?string $back): ?string
    {
        return is_string($back) && str_starts_with($back, '/') && ! str_starts_with($back, '//') && ! str_contains($back, '\\')
            ? $back : null;
    }
}
