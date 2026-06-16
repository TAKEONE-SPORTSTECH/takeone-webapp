<?php

namespace Takeone\Realtime;

use Illuminate\Contracts\Foundation\Application;
use Takeone\Realtime\Contracts\Publisher;
use Takeone\Realtime\Models\RealtimeSetting;
use Takeone\Realtime\Mqtt\TokenIssuer;
use Takeone\Realtime\Support\Topics;

/**
 * Public entry point for the plugin. Resolves effective configuration
 * (DB overrides win over config/realtime.php) and exposes the two things the
 * rest of the app cares about: publishing to a user, and minting a browser
 * token. Reach it via the Realtime() helper or the Realtime facade.
 */
class RealtimeManager
{
    /** Keys editable from the admin UI, persisted in realtime_settings. */
    public const OVERRIDABLE = [
        'enabled', 'broker.host', 'broker.port', 'broker.username',
        'broker.password', 'broker.ws_url', 'jwt.secret', 'jwt.ttl',
    ];

    public function __construct(private Application $app) {}

    /**
     * Effective config value. DB override (flat dotted key) takes precedence
     * over the static config file so the admin page can retune live.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $override = RealtimeSetting::get($key);
        if ($override !== null && in_array($key, self::OVERRIDABLE, true)) {
            return $this->cast($key, $override);
        }

        return config("realtime.$key", $default);
    }

    public function enabled(): bool
    {
        return (bool) $this->config('enabled', false);
    }

    public function publisher(): Publisher
    {
        return $this->app->make(Publisher::class);
    }

    /** Publish a payload to one user's channel (e.g. 'notifications'|'messages'). */
    public function publishToUser(int $userId, string $channel, array $payload): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return $this->publisher()->publish(Topics::user($userId, $channel), $payload);
    }

    /** Fully-qualified leaf topic for a user/channel — handy when batching. */
    public function userTopic(int $userId, string $channel): string
    {
        return Topics::user($userId, $channel);
    }

    /**
     * Publish many pre-built messages over one connection (efficient fan-out).
     *
     * @param array<int, array{topic:string, payload:array}> $messages
     */
    public function publishMany(array $messages): bool
    {
        if (! $this->enabled() || empty($messages)) {
            return false;
        }

        return $this->publisher()->publishMany($messages);
    }

    /** @return array{token:string, username:string, ws_url:string, topic:string, expires_at:int} */
    public function tokenFor(int $userId): array
    {
        return $this->app->make(TokenIssuer::class)->issue($userId);
    }

    private function cast(string $key, mixed $value): mixed
    {
        return match ($key) {
            'enabled'    => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'broker.port', 'jwt.ttl' => (int) $value,
            default      => $value,
        };
    }
}
