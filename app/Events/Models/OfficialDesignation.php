<?php

namespace App\Events\Models;

use App\Events\Support\EventHost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A designation a host added to its officials' list — offered on every event
 * of that host beside the standard jobs (App\Events\Support\OfficialRoles).
 *
 * An appointment to one is stored as the role KEY `cd_<id>`: a job of its own,
 * not typed-in text, so a person may hold several and every surface names it.
 * The key can never spell one of the four access roles (jury, weigh_in,
 * payments, organiser), so a designation grants nothing — like a mat role.
 */
class OfficialDesignation extends Model
{
    public const KEY_PREFIX = 'cd_';

    /** How many one host may add — a list, not a dumping ground. */
    public const MAX_PER_HOST = 100;

    protected $table = 'event_official_designations';

    protected $fillable = ['host_type', 'host_id', 'label', 'section', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (self $d) {
            $d->uuid ??= (string) Str::uuid();
        });
    }

    /** The role key an appointment to it is stored under. */
    public function key(): string
    {
        return self::KEY_PREFIX.$this->id;
    }

    /** The designations offered on this event: its host's list. */
    public static function forEvent(ClubEvent $event): Collection
    {
        $host = EventHost::of($event);
        if (! $host->id || $host->kind === 'none') {
            return new Collection;
        }

        return static::where('host_type', $host->kind)->where('host_id', $host->id)->orderBy('label')->get();
    }
}
