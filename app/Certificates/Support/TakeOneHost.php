<?php

namespace App\Certificates\Support;

use Ahl\Certificates\Certificates as Engine;
use Ahl\Certificates\Contracts\BrandsCertificates;
use Ahl\Certificates\Contracts\OwnsTemplates;
use Ahl\Certificates\Models\Certificate;
use Ahl\Certificates\Models\CertificateTemplate;
use Ahl\Certificates\Support\DefaultHost;
use App\Members\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The package's view of TakeOne. Everything that depends on WHAT a certificate
 * is for is asked of that context's provider (App\Certificates\Contracts\
 * CertificateContext); what is left here is platform-wide.
 */
class TakeOneHost extends DefaultHost implements BrandsCertificates, OwnsTemplates
{
    public function __construct(private Contexts $contexts) {}

    /** The package's own list endpoints are not mounted; this is a second line anyway. */
    public function scopeVisible(Builder $query, Authenticatable $user): void
    {
        if ($user instanceof User && $user->isSuperAdmin()) {
            return;
        }
        $query->where('recipient_type', (new User)->getMorphClass())->where('recipient_id', $user->getAuthIdentifier());
    }

    public function searchRecipients(Builder $query, string $like): void
    {
        $query->orWhereHasMorph('recipient', [User::class], fn (Builder $q) => $q->where('full_name', 'like', $like));
    }

    public function presentRecipient(Model $recipient): array
    {
        return [
            'id' => $recipient instanceof User ? $recipient->uuid : $recipient->getKey(),
            'type' => Engine::recipientAlias($recipient),
            'name' => $recipient instanceof User ? $recipient->certificateName() : (string) $recipient->getKey(),
        ];
    }

    public function placeholders(Certificate $certificate, string $locale): array
    {
        $context = $certificate->context_id ? $certificate->context : null;

        return $this->contexts->forModel($context)?->placeholders($certificate, $locale) ?? [];
    }

    public function placeholderKeys(): array
    {
        return collect($this->contexts->all())->flatMap(fn ($p) => $p->placeholderKeys())->unique()->values()->all();
    }

    public function issuer(string $locale): string
    {
        return (string) config('certificates.issuer', 'TakeOne');
    }

    public function displayTime(?CarbonInterface $time): ?CarbonInterface
    {
        return $time;
    }

    public function verifyUrl(Certificate $certificate): string
    {
        return route('certificates.verify', ['token' => (string) $certificate->verify_token]);
    }

    public function viewData(array $data, ?Certificate $certificate, string $locale): array
    {
        $context = $data['context'] ?? ($certificate && $certificate->context_id ? $certificate->context : null);
        $provider = $this->contexts->forModel($context);

        if ($provider && $context) {
            $data['headline'] = $provider->headline($context);
            $data['issuer'] = $provider->issuer($context, $locale);
        }

        return $data;
    }

    public function templateOwners(?Model $context): array
    {
        return $this->contexts->forModel($context)?->templateOwners($context) ?? [];
    }

    public function brand(?Certificate $certificate, CertificateTemplate $template, ?Model $context, string $locale): array
    {
        $context ??= $certificate && $certificate->context_id ? $certificate->context : null;

        return $this->contexts->forModel($context)?->brand($context) ?? [];
    }

    public function previewDetails(string $type, ?Model $context): array
    {
        return $context ? ($this->contexts->forModel($context)?->previewDetails($type, $context) ?? []) : [];
    }
}
