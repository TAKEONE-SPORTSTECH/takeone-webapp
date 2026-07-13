<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;

/**
 * Connection layer for a self-hosted OpenWA (github.com/rmyndharis/OpenWA) instance.
 * Settings are persisted via PlatformSetting so a super-admin can point the app at a
 * different gateway or flip the master switch from the admin UI without redeploying.
 */
class WhatsAppManager
{
    private const PREFIX = 'whatsapp_';

    public function enabled(): bool
    {
        return PlatformSetting::getBool(self::PREFIX.'enabled', false);
    }

    public function baseUrl(): ?string
    {
        return PlatformSetting::get(self::PREFIX.'base_url');
    }

    public function apiKey(): ?string
    {
        return PlatformSetting::get(self::PREFIX.'api_key');
    }

    public function sessionName(): ?string
    {
        return PlatformSetting::get(self::PREFIX.'session_name');
    }

    /** Persist the admin-editable fields. An empty api_key leaves the stored key unchanged. */
    public function save(array $data): void
    {
        PlatformSetting::set(self::PREFIX.'enabled', (bool) ($data['enabled'] ?? false));
        PlatformSetting::set(self::PREFIX.'base_url', rtrim((string) ($data['base_url'] ?? ''), '/'));
        PlatformSetting::set(self::PREFIX.'session_name', (string) ($data['session_name'] ?? ''));

        if (filled($data['api_key'] ?? null)) {
            PlatformSetting::set(self::PREFIX.'api_key', $data['api_key']);
        }
    }

    /** Current settings shaped for the admin form. The API key is never sent back to the browser. */
    public function adminSettings(): array
    {
        return [
            'enabled'        => $this->enabled(),
            'base_url'       => $this->baseUrl(),
            'session_name'   => $this->sessionName(),
            'api_key_set'    => filled($this->apiKey()),
        ];
    }

    /**
     * Reachability + auth probe: hit the public health endpoint, then (if an API key is
     * configured) confirm the key is accepted by listing sessions.
     */
    public function probe(): array
    {
        $baseUrl = $this->baseUrl();

        if (! $baseUrl) {
            return ['success' => false, 'message' => 'No base URL configured.'];
        }

        try {
            $health = Http::timeout(5)->get(rtrim($baseUrl, '/').'/api/health');
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach the gateway: '.$e->getMessage()];
        }

        if (! $health->successful()) {
            return ['success' => false, 'message' => 'Gateway responded with HTTP '.$health->status().'.'];
        }

        $apiKey = $this->apiKey();
        if (blank($apiKey)) {
            return ['success' => true, 'message' => 'Gateway reachable (v'.($health->json('version') ?? '?').'). No API key set — add one to verify authentication.'];
        }

        try {
            $sessions = Http::timeout(5)
                ->withHeaders(['X-API-Key' => $apiKey])
                ->get(rtrim($baseUrl, '/').'/api/sessions');
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Gateway reachable, but the auth check failed: '.$e->getMessage()];
        }

        if ($sessions->status() === 401) {
            return ['success' => false, 'message' => 'Gateway reachable, but the API key was rejected (401).'];
        }

        if (! $sessions->successful()) {
            return ['success' => false, 'message' => 'Gateway reachable, but listing sessions failed (HTTP '.$sessions->status().').'];
        }

        return ['success' => true, 'message' => 'Gateway reachable (v'.($health->json('version') ?? '?').') and API key accepted.'];
    }

    /**
     * Send a one-off text message through a given session (used by both the platform-wide
     * card and each club's card — the gateway/API key are shared, only the session differs).
     */
    public function sendTestMessage(?string $sessionName, string $phone, string $text): array
    {
        $baseUrl = $this->baseUrl();
        $apiKey = $this->apiKey();

        if (blank($baseUrl) || blank($apiKey)) {
            return ['success' => false, 'message' => 'The WhatsApp gateway is not configured yet.'];
        }

        if (blank($sessionName)) {
            return ['success' => false, 'message' => 'No session ID set.'];
        }

        // Same charset lock as the connection probe — this value may come from club-admin
        // input, and it's interpolated into a request made with the shared, privileged API key.
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $sessionName)) {
            return ['success' => false, 'message' => 'Stored session ID contains invalid characters — re-save it.'];
        }

        $chatId = $this->normalizeChatId($phone);
        if ($chatId === null) {
            return ['success' => false, 'message' => 'Enter a valid phone number with country code, digits only (e.g. 97333626491).'];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-API-Key' => $apiKey])
                ->post(rtrim($baseUrl, '/').'/api/sessions/'.rawurlencode($sessionName).'/messages/send-text', [
                    'chatId' => $chatId,
                    'text'   => $text,
                ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach the gateway: '.$e->getMessage()];
        }

        if ($response->status() === 401) {
            return ['success' => false, 'message' => 'The gateway rejected the API key.'];
        }

        if ($response->status() === 400) {
            return ['success' => false, 'message' => 'This session is not started/ready — pair it first via the OpenWA dashboard.'];
        }

        if (! $response->successful()) {
            return ['success' => false, 'message' => 'Send failed (HTTP '.$response->status().').'];
        }

        return ['success' => true, 'message' => 'Test message sent to '.$chatId.'.'];
    }

    /** Digits-only phone number -> OpenWA's `<number>@c.us` chat id. Rejects anything else. */
    private function normalizeChatId(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === null || strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return $digits.'@c.us';
    }
}
