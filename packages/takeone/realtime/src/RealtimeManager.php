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

    /** Current admin-editable settings, shaped for the admin form(s). */
    public function adminSettings(): array
    {
        return [
            'enabled'         => $this->enabled(),
            'broker_host'     => $this->config('broker.host'),
            'broker_port'     => $this->config('broker.port'),
            'broker_username' => $this->config('broker.username'),
            'broker_ws_url'   => $this->config('broker.ws_url'),
            'jwt_ttl'         => $this->config('jwt.ttl'),
            'jwt_secret_set'  => filled($this->config('jwt.secret')),
        ];
    }

    /** Lightweight reachability probe for the status pill (TCP connect only). */
    public function probe(): array
    {
        if (! $this->enabled()) {
            return ['state' => 'disabled', 'label' => 'Disabled'];
        }

        $host = $this->config('broker.host', '127.0.0.1');
        $port = (int) $this->config('broker.port', 1883);
        $conn = @fsockopen($host, $port, $errno, $errstr, 2);

        if ($conn) {
            fclose($conn);

            return ['state' => 'online', 'label' => 'Broker online'];
        }

        return ['state' => 'offline', 'label' => 'Broker offline (' . ($errstr ?: 'no route') . ')'];
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
