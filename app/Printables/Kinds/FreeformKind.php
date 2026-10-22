<?php

namespace App\Printables\Kinds;

use App\Members\Models\User;
use App\Printables\Contracts\PrintableKind;
use Illuminate\Database\Eloquent\Model;

/**
 * A member's own free design — any size, no live fields. The owner is the
 * member themselves, so the only person who may open it is them; its address
 * says "me", never an id.
 */
class FreeformKind implements PrintableKind
{
    public function key(): string
    {
        return 'freeform';
    }

    public function label(): string
    {
        return __('printables::messages.kind_freeform');
    }

    public function canDesign(Model $owner, User $user): bool
    {
        return $owner instanceof User && $owner->is($user);
    }

    public function formats(): array
    {
        return [];
    }

    public function startingSize(?string $slot): array
    {
        return ['format' => 'a4', 'orientation' => 'portrait'];
    }

    public function fields(Model $owner, ?string $slot): array
    {
        return [];
    }

    public function title(Model $owner, ?string $slot): string
    {
        return __('printables::messages.kind_freeform');
    }

    public function slots(Model $owner): array
    {
        return [];
    }

    public function ownerRef(Model $owner): string
    {
        return 'me';
    }

    public function findOwner(string $ref, User $user): ?Model
    {
        return $ref === 'me' ? $user : null;
    }

    public function starter(Model $owner, ?string $slot, array $size): array
    {
        return [];
    }
}
