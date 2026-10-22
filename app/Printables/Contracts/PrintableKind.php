<?php

namespace App\Printables\Contracts;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One kind of thing the platform prints — a certificate, a badge, a poster.
 *
 * The designer draws any kind; the kind decides everything the designer must
 * not: who may edit a design for a given owner, which page sizes it may take,
 * where it starts, and which LIVE FIELDS it can print. A field is written in a
 * text as {key} (e.g. "Awarded to {name}") or placed as a picture field (a
 * photograph, the verification QR) and is filled in when the page is printed
 * for one recipient.
 *
 * Register an implementation in config('printables.kinds').
 */
interface PrintableKind
{
    /** Stable key, stored on every design of this kind. */
    public function key(): string;

    /** Human name, in the current locale. */
    public function label(): string;

    /** Whether $user may open and change designs of this kind belonging to $owner. */
    public function canDesign(Model $owner, User $user): bool;

    /**
     * Page sizes this kind may take — keys of config('printables.formats'), or
     * an empty list for "any, including custom".
     *
     * @return list<string>
     */
    public function formats(): array;

    /**
     * Where a NEW design starts.
     *
     * @return array{format: string, orientation: 'portrait'|'landscape'}
     */
    public function startingSize(?string $slot): array;

    /**
     * The live fields a design for $owner (and $slot) may print, in display order.
     * `sample` is what the editor shows in preview mode.
     *
     * @return list<array{key: string, label: string, type: 'text'|'image', sample: string}>
     */
    public function fields(Model $owner, ?string $slot): array;

    /** A short line naming what is being designed ("Participation certificate · Bahrain Open"). */
    public function title(Model $owner, ?string $slot): string;

    /** The slots this kind recognises for $owner (e.g. a certificate's types), or [] for one design per owner. */
    public function slots(Model $owner): array;

    /**
     * The owner's PUBLIC reference in a designer address — never an auto-increment
     * id (CLAUDE.md → Unpredictable Resource Identifiers).
     */
    public function ownerRef(Model $owner): string;

    /** The owner a reference names, or null. Authorisation is canDesign()'s job, not this. */
    public function findOwner(string $ref, User $user): ?Model;

    /**
     * The elements a brand-new design starts with (a certificate's wording, a
     * badge's name and photo), in document form, or [] for an empty page.
     * $size is [width px, height px].
     *
     * @param  array{0: float, 1: float}  $size
     * @return list<array<string, mixed>>
     */
    public function starter(Model $owner, ?string $slot, array $size): array;
}
