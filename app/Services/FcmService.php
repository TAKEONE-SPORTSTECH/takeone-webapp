<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends native push notifications to a user's registered devices via
 * Firebase Cloud Messaging (FCM HTTP v1). Zero external SDK: the service-account
 * JWT is signed with openssl and exchanged for a short-lived OAuth token.
 *
 * Best-effort: every failure is caught + logged; the DB stays the source of truth.
 * Invalid/expired device tokens are pruned automatically.
 */
class FcmService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const OAUTH_URL = 'https://oauth2.googleapis.com/token';

    /** Whether FCM is configured and enabled. */
    public function enabled(): bool
    {
        return (bool) config('services.fcm.enabled', true)
            && is_string(config('services.fcm.credentials'))
            && is_file(config('services.fcm.credentials'));
    }

    /**
     * Send a push to every device registered to a user.
     *
     * @param  array<string,mixed>  $data  Extra key/value data (e.g. action_url, type) — cast to strings.
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        $tokens = PushToken::where('user_id', $userId)->pluck('token', 'id');
        if ($tokens->isEmpty()) {
            return;
        }

        $accessToken = $this->accessToken();
        $projectId = $this->serviceAccount()['project_id'] ?? null;
        if (! $accessToken || ! $projectId) {
            return;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $stringData = array_map(static fn ($v) => (string) $v, $data);

        foreach ($tokens as $id => $token) {
            try {
                $resp = Http::withToken($accessToken)
                    ->timeout(10)
                    ->post($url, [
                        'message' => [
                            'token' => $token,
                            'notification' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'data' => $stringData,
                            'android' => [
                                'priority' => 'HIGH',
                                'notification' => [
                                    'default_sound' => true,
                                    'notification_priority' => 'PRIORITY_HIGH',
                                ],
                            ],
                        ],
                    ]);

                // 404 UNREGISTERED / 400 INVALID_ARGUMENT => token is dead, remove it.
                if ($resp->status() === 404 || $resp->status() === 400) {
                    PushToken::where('id', $id)->delete();
                } elseif ($resp->failed()) {
                    Log::warning('FCM send failed', ['user' => $userId, 'status' => $resp->status(), 'body' => $resp->body()]);
                }
            } catch (\Throwable $e) {
                Log::warning('FCM send exception', ['user' => $userId, 'error' => $e->getMessage()]);
            }
        }
    }

    /** Cached OAuth access token (valid ~1h; cached 55m). */
    private function accessToken(): ?string
    {
        try {
            return Cache::remember('fcm.access_token', 3300, function () {
                $sa = $this->serviceAccount();
                $now = time();

                $jwt = $this->encodeJwt([
                    'iss' => $sa['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => self::OAUTH_URL,
                    'iat' => $now,
                    'exp' => $now + 3600,
                ], $sa['private_key']);

                $resp = Http::asForm()->timeout(10)->post(self::OAUTH_URL, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                if ($resp->failed()) {
                    Log::warning('FCM OAuth failed', ['status' => $resp->status(), 'body' => $resp->body()]);

                    return null;
                }

                return $resp->json('access_token');
            });
        } catch (\Throwable $e) {
            Log::warning('FCM OAuth exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Sign a JWT with the service-account RSA private key (RS256). */
    private function encodeJwt(array $claims, string $privateKey): string
    {
        $segments = [
            $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->b64(json_encode($claims)),
        ];
        $signingInput = implode('.', $segments);

        $signature = '';
        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $segments[] = $this->b64($signature);

        return implode('.', $segments);
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** @return array<string,mixed> */
    private function serviceAccount(): array
    {
        $path = config('services.fcm.credentials');
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : [];
    }
}
