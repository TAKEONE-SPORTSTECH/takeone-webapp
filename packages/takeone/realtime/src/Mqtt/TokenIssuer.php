<?php

namespace Takeone\Realtime\Mqtt;

use Takeone\Realtime\RealtimeManager;
use Takeone\Realtime\Support\Topics;

/**
 * Mints the short-lived HS256 JWT the browser presents to EMQX as its MQTT
 * password. EMQX verifies the signature with the shared secret and enforces the
 * embedded "acl" claim, so the token both authenticates the user AND restricts
 * them to their own subtree (subscribe-only). Browsers never publish.
 *
 * HS256 is encoded inline (header.payload.signature, base64url) to keep the
 * package dependency-free — no third-party JWT library required.
 */
class TokenIssuer
{
    public function __construct(private RealtimeManager $manager) {}

    /** @return array{token:string, username:string, ws_url:string, topic:string, expires_at:int} */
    public function issue(int $userId): array
    {
        $secret = (string) $this->manager->config('jwt.secret');
        $ttl    = (int) $this->manager->config('jwt.ttl', 3600);
        $now    = time();
        $exp    = $now + $ttl;

        $username = 'user-' . $userId;
        $wildcard = Topics::userWildcard($userId);

        $claims = [
            'iss'      => 'takeone-realtime',
            'sub'      => (string) $userId,
            'username' => $username,
            'iat'      => $now,
            'exp'      => $exp,
            // EMQX reads this to authorise the connection's subscriptions.
            'acl'      => [
                ['permission' => 'allow', 'action' => 'subscribe', 'topic' => $wildcard],
                // Explicitly deny publishing — defence in depth atop EMQX default-deny.
                ['permission' => 'deny',  'action' => 'publish',   'topic' => '#'],
            ],
        ];

        return [
            'token'      => $this->encode($claims, $secret),
            'username'   => $username,
            'ws_url'     => $this->manager->config('broker.ws_url'),
            'topic'      => $wildcard,
            'expires_at' => $exp,
        ];
    }

    /**
     * Long-lived token for the PHP publisher (the backend). Allowed to publish
     * anywhere under the prefix; that's its whole job. Used as the MQTT password
     * so the broker can run a single JWT authenticator with no DB user accounts.
     *
     * @return array{token:string, username:string}
     */
    public function issueServer(): array
    {
        $secret = (string) $this->manager->config('jwt.secret');
        $prefix = trim((string) $this->manager->config('prefix', 'takeone'), '/');
        $now    = time();

        $claims = [
            'iss'      => 'takeone-realtime',
            'sub'      => 'server',
            'username' => $this->manager->config('broker.username', 'takeone-server'),
            'iat'      => $now,
            'exp'      => $now + 86400, // 24h; minted fresh per connection anyway
            'acl'      => [
                ['permission' => 'allow', 'action' => 'publish',   'topic' => $prefix . '/#'],
                ['permission' => 'allow', 'action' => 'subscribe', 'topic' => $prefix . '/#'],
            ],
        ];

        return [
            'token'    => $this->encode($claims, $secret),
            'username' => $claims['username'],
        ];
    }

    private function encode(array $claims, string $secret): string
    {
        $segments = [
            $this->b64(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])),
            $this->b64(json_encode($claims, JSON_UNESCAPED_SLASHES)),
        ];
        $signing   = implode('.', $segments);
        $signature = hash_hmac('sha256', $signing, $secret, true);

        return $signing . '.' . $this->b64($signature);
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
