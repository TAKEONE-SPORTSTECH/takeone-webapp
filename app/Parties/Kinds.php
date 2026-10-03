<?php

namespace App\Parties;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The kinds of party, and the model each one is (PLAN.md §3.1).
 *
 * Its own vocabulary on purpose, not the Eloquent morph map: the keys stored in
 * party_relations must never change when a class moves folder, and adding
 * morph-map aliases for Business/Federation would orphan activity-log rows
 * already stored under their class names.
 *
 * A new kind of tenant (clinic, trainer practice, venue…) is one line here.
 */
final class Kinds
{
    public const PERSON = 'person';
    public const CLUB = 'club';
    public const BUSINESS = 'business';
    public const FEDERATION = 'federation';
    public const EVENT = 'event';

    /** @return array<string, class-string<Model>> */
    public static function map(): array
    {
        return [
            self::PERSON => \App\Members\Models\User::class,
            self::CLUB => \App\Clubs\Models\Tenant::class,
            self::BUSINESS => \App\Clubs\Models\Business::class,
            self::FEDERATION => \App\Federations\Models\Federation::class,
            self::EVENT => \App\Events\Models\ClubEvent::class,
        ];
    }

    public static function keyOf(Model $model): string
    {
        foreach (self::map() as $key => $class) {
            if ($model instanceof $class) {
                return $key;
            }
        }

        throw new \InvalidArgumentException(get_class($model).' is not a party kind (App\Parties\Kinds).');
    }

    public static function classOf(string $key): string
    {
        return self::map()[$key] ?? throw new \InvalidArgumentException("Unknown party kind '{$key}'.");
    }

    /** Resolve a party, soft-deleted included. */
    public static function find(string $key, int $id): ?Model
    {
        $class = self::classOf($key);
        $query = in_array(SoftDeletes::class, class_uses_recursive($class), true)
            ? $class::withTrashed()
            : $class::query();

        return $query->find($id);
    }
}
