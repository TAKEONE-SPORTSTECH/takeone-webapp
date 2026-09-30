<?php

namespace App\Certificates\Support;

use App\Certificates\Contracts\CertificateContext;
use Illuminate\Database\Eloquent\Model;

/**
 * The certificate contexts the platform knows, from config/certificates.php →
 * `providers` (alias => class). One lookup for the three questions every part
 * of the module asks: which provider answers for this alias, this model, or
 * this template owner type.
 */
class Contexts
{
    /** @var array<string, CertificateContext>|null */
    private ?array $providers = null;

    /** @return array<string, CertificateContext> */
    public function all(): array
    {
        if ($this->providers === null) {
            $this->providers = [];
            foreach ((array) config('certificates.providers', []) as $alias => $class) {
                if (is_string($class) && class_exists($class)) {
                    $provider = app($class);
                    if ($provider instanceof CertificateContext) {
                        $this->providers[$provider->alias()] = $provider;
                    }
                }
            }
        }

        return $this->providers;
    }

    public function forAlias(?string $alias): ?CertificateContext
    {
        return $alias !== null ? ($this->all()[$alias] ?? null) : null;
    }

    public function forModel(?Model $context): ?CertificateContext
    {
        if (! $context) {
            return null;
        }
        foreach ($this->all() as $provider) {
            if ($provider->handles($context)) {
                return $provider;
            }
        }

        return null;
    }

    public function forOwnerType(string $type): ?CertificateContext
    {
        foreach ($this->all() as $provider) {
            if (in_array($type, $provider->ownerTypes(), true)) {
                return $provider;
            }
        }

        return null;
    }

    /** The context by alias + public key, and its provider — or null for anything unknown. */
    public function resolve(string $alias, string $key): ?array
    {
        $provider = $this->forAlias($alias);
        $context = $provider?->find($key);

        return $provider && $context ? [$provider, $context] : null;
    }
}
