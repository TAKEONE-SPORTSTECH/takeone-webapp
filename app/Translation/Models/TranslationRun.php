<?php

namespace App\Translation\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How one record's translation into one language is going.
 *
 * Exists because somebody is WATCHING. The first visitor to ask for a language
 * waits a few seconds while the agent writes it, and a spinner with nothing
 * behind it is how a page hangs forever. This row is what the picker polls, and
 * it is a row rather than a cache entry so that a queue restart, a deploy or a
 * failed job still leaves an honest answer on the other end of that poll.
 *
 * @property string $status 'queued' | 'running' | 'ready' | 'failed'
 */
class TranslationRun extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const READY = 'ready';

    public const FAILED = 'failed';

    protected $table = 'content_translation_runs';

    protected $fillable = [
        'translatable_type', 'translatable_id', 'locale',
        'status', 'fields', 'error', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'fields' => 'integer',
    ];

    public function isFinished(): bool
    {
        return in_array($this->status, [self::READY, self::FAILED], true);
    }

    /**
     * A run that is still marked running long after anything could plausibly
     * still be running.
     *
     * A worker killed mid-job leaves `running` behind forever, and without this
     * the module would refuse to ever try that language again — the one failure
     * mode that turns a temporary outage into a permanent missing language.
     */
    public function isStuck(): bool
    {
        return in_array($this->status, [self::QUEUED, self::RUNNING], true)
            && $this->updated_at !== null
            && $this->updated_at->lt(now()->subMinutes(10));
    }
}
