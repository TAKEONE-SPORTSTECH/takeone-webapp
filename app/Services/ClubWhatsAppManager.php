<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;

/**
 * Per-club WhatsApp session assignment. The gateway connection itself (base
 * URL + API key) stays platform-wide, managed via WhatsAppManager — clubs only
 * pick which OpenWA session (i.e. which paired phone number) is theirs, stored
 * on the tenant's existing `settings` JSON column under the `whatsapp` key.
 */
class ClubWhatsAppManager
{
    public function __construct(private WhatsAppManager $platform) {}

    public function enabled(Tenant $club): bool
    {
        return (bool) data_get($club->settings, 'whatsapp.enabled', false);
    }

    public function sessionName(Tenant $club): ?string
    {
        return data_get($club->settings, 'whatsapp.session_name');
    }

    public function save(Tenant $club, array $data): void
    {
        $settings = $club->settings ?? [];
        $settings['whatsapp'] = [
            'enabled'      => (bool) ($data['enabled'] ?? false),
            'session_name' => (string) ($data['session_name'] ?? ''),
        ];
        $club->update(['settings' => $settings]);
    }

    public function adminSettings(Tenant $club): array
    {
        return [
            'enabled'            => $this->enabled($club),
            'session_name'       => $this->sessionName($club),
            'gateway_configured' => filled($this->platform->baseUrl()) && filled($this->platform->apiKey()),
        ];
    }

    /** Confirms the club's assigned session actually exists on the shared gateway. */
    public function probe(Tenant $club): array
    {
        $baseUrl = $this->platform->baseUrl();
        $apiKey = $this->platform->apiKey();

        if (blank($baseUrl) || blank($apiKey)) {
            return ['success' => false, 'message' => 'The WhatsApp gateway is not configured platform-wide yet — ask your platform administrator to set it up first.'];
        }

        $sessionName = $this->sessionName($club);
        if (blank($sessionName)) {
            return ['success' => false, 'message' => 'No session ID set for this club yet.'];
        }

        // Defense in depth: the session ID is club-admin-controlled input interpolated into a
        // request made with the platform-wide (privileged) API key. Re-validate the charset here
        // even though the save endpoint already restricts it, so stale/pre-existing stored values
        // can never redirect this privileged request to a different gateway path.
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $sessionName)) {
            return ['success' => false, 'message' => 'Stored session ID contains invalid characters — re-save it.'];
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-API-Key' => $apiKey])
                ->get(rtrim($baseUrl, '/').'/api/sessions/'.rawurlencode($sessionName));
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach the gateway: '.$e->getMessage()];
        }

        if ($response->status() === 401) {
            return ['success' => false, 'message' => 'The gateway rejected the platform API key — ask your platform administrator.'];
        }

        if ($response->status() === 404) {
            return ['success' => false, 'message' => 'No session with that ID exists on the gateway. Check the session ID with your platform administrator.'];
        }

        if (! $response->successful()) {
            return ['success' => false, 'message' => 'Gateway error (HTTP '.$response->status().').'];
        }

        $status = $response->json('status');
        $phone = $response->json('phone');

        return [
            'success' => true,
            'message' => 'Session found — status: '.$status.($phone ? ", phone: {$phone}" : ' (not yet paired to a phone)'),
        ];
    }

    public function sendTestMessage(Tenant $club, string $phone): array
    {
        $text = 'This is a test message from '.$club->club_name.' via TAKEONE.';

        return $this->platform->sendTestMessage($this->sessionName($club), $phone, $text);
    }
}
