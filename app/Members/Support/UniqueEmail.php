<?php

namespace App\Members\Support;

use App\Members\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * "Is this email address already somebody's?" — compared case-insensitively.
 *
 * `Rule::unique('users', 'email')` compares the column exactly, so
 * `Ali@x.com` opened a second account beside `ali@x.com`. Addresses are not
 * case-sensitive to a person, so neither is this check. `lower(email)` works
 * the same on SQLite and PostgreSQL. Soft-deleted accounts are not counted,
 * matching the `whereNull('deleted_at')` the old rules carried.
 *
 * Stored emails are never rewritten; doors using this store new addresses
 * lower-cased and trimmed (see `normalise()`).
 */
class UniqueEmail implements ValidationRule
{
    public function __construct(private ?int $ignoreId = null) {}

    public static function normalise(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    public static function taken(?string $email, ?int $ignoreId = null): bool
    {
        $email = self::normalise($email);

        if ($email === null) {
            return false;
        }

        return User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::taken($value, $this->ignoreId)) {
            $fail('validation.unique')->translate();
        }
    }
}
