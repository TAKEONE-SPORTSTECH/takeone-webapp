<?php

namespace App\Certificates\Contracts;

use Ahl\Certificates\Models\Certificate;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * What a certificate is FOR — an event, later a belt test or a course — and
 * everything the certificates module needs to know about it.
 *
 * The certificates module owns the workflow, the files, the screens and the
 * rules about who may see what. It knows nothing about brackets, gradings or
 * attendance: a context supplies who has earned what, what goes on the page,
 * and who runs it. Adding a new kind of certificate is one class implementing
 * this, and one line in config/certificates.php → `providers`.
 *
 * A context is addressed from the outside by its PUBLIC key (an event's uuid),
 * never by its numeric id.
 */
interface CertificateContext
{
    /** Stable alias, used in URLs and as the certificates.context_type morph ('club_event'). */
    public function alias(): string;

    /** The template owner types this context's chain can name ('club_event', 'club', 'federation'). */
    public function ownerTypes(): array;

    /** The context by its public key, or null. Never throws. */
    public function find(string $key): ?Model;

    public function key(Model $context): string;

    public function handles(Model $context): bool;

    /** May this user issue, approve, revoke and design certificates here? */
    public function canManage(Model $context, User $user): bool;

    /**
     * May this user change the design owned by $owner (one link of templateOwners())?
     * A design shared by many contexts needs more than running one of them.
     */
    public function canManageOwner(Model $context, array $owner, User $user): bool;

    /** @return list<int> user ids who run this context — told live when its certificates change */
    public function audience(Model $context): array;

    /** The name printed at the top of the certificate. In the organiser's own words. */
    public function headline(Model $context): string;

    /** Who issued it — printed on the verification page. */
    public function issuer(Model $context, string $locale): string;

    /** @return list<array{0:string,1:int}> template owners, most specific first */
    public function templateOwners(Model $context): array;

    /** A human name for one owner in the chain ("This event only", "Bahrain Karate Federation"). */
    public function ownerLabel(Model $context, array $owner, string $locale): string;

    /** Folder a template owned by $owner keeps its signatures and artwork in, or null if unknown. */
    public function ownerStorageRoot(array $owner): ?string;

    /** Folder this context's certificate PDFs are stored in. */
    public function storageRoot(Model $context): string;

    /**
     * @return array{accent:?string, watermark:?string, logos:list<string>, seal:?string}
     *   watermark: raw image bytes; logos: data: URIs
     */
    public function brand(Model $context): array;

    /** Placeholder values for one certificate. Keys without braces. */
    public function placeholders(Certificate $certificate, string $locale): array;

    /** @return list<string> the placeholder tokens this context adds */
    public function placeholderKeys(): array;

    /** `details` for a preview of $type in this context. */
    public function previewDetails(string $type, Model $context): array;

    /** @return list<string> certificate types this context issues */
    public function types(): array;

    /** The organiser's settings for this context, as the console reads them. */
    public function settings(Model $context): array;

    /** Save the organiser's settings; returns settings() after the save. */
    public function saveSettings(Model $context, array $input): array;

    /** @return array<string, int> how many certificates of each type this context would issue now */
    public function eligible(Model $context): array;

    /**
     * Draft every certificate this context has earned and not yet been given.
     * Idempotent. Returns ['created' => n, 'pending' => m] (m: parts not finished yet).
     */
    public function generate(Model $context, ?User $by): array;

    /** One line saying what a certificate was for, for lists ("Kumite −67 kg · Gold"). */
    public function describe(Certificate $certificate, string $locale): string;
}
