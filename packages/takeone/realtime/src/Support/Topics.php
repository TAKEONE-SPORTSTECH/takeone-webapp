<?php

namespace Takeone\Realtime\Support;

/**
 * Single source of truth for MQTT topic strings. Keeping this in one place
 * means the publisher, the JWT ACL, and the JS subscriber can never drift.
 *
 *   {prefix}/user/{id}/notifications
 *   {prefix}/user/{id}/messages
 */
class Topics
{
    public static function prefix(): string
    {
        return trim((string) Realtime()->config('prefix', 'takeone'), '/');
    }

    /** Leaf topic for a specific user + channel (e.g. takeone/user/42/messages). */
    public static function user(int $userId, string $channel): string
    {
        $leaf = Realtime()->config("channels.$channel", $channel);

        return sprintf('%s/user/%d/%s', self::prefix(), $userId, $leaf);
    }

    /** Wildcard a user is allowed to subscribe to (takeone/user/42/#). */
    public static function userWildcard(int $userId): string
    {
        return sprintf('%s/user/%d/#', self::prefix(), $userId);
    }
}
