<?php

namespace Takeone\Realtime\Facades;

use Illuminate\Support\Facades\Facade;
use Takeone\Realtime\RealtimeManager;

/**
 * @method static mixed config(string $key, mixed $default = null)
 * @method static bool enabled()
 * @method static bool publishToUser(int $userId, string $channel, array $payload)
 * @method static array tokenFor(int $userId)
 *
 * @see RealtimeManager
 */
class Realtime extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RealtimeManager::class;
    }
}
