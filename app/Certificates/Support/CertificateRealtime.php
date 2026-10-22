<?php

namespace App\Certificates\Support;

/**
 * A refresh signal on the `certificates` channel (CLAUDE.md → Realtime).
 *
 * A signal, never the certificate: a holder and an organiser see the same
 * change differently, so each open screen re-fetches what IT may see.
 * Best-effort, as every push is — the database is the truth.
 */
class CertificateRealtime
{
    /**
     * The member to tell about one certificate — null when its holder is not a
     * person (a club's thank-you), whose id is no user's id.
     */
    public static function holder(\Ahl\Certificates\Models\Certificate $certificate): ?int
    {
        return $certificate->recipient_type === (new \App\Members\Models\User)->getMorphClass() ? (int) $certificate->recipient_id : null;
    }

    /** @param iterable<int|null> $userIds */
    public static function refresh(iterable $userIds, ?int $except = null): void
    {
        $ids = collect($userIds)->map(fn ($id) => (int) $id)->filter()->unique()->reject(fn ($id) => $id === $except)->values();
        if ($ids->isEmpty()) {
            return;
        }

        rescue(function () use ($ids) {
            if (function_exists('Realtime') && Realtime()->enabled()) {
                Realtime()->publishMany($ids->map(fn ($id) => [
                    'topic' => Realtime()->userTopic($id, 'certificates'),
                    'payload' => ['action' => 'refresh'],
                ])->all());
            }
        }, null, report: false);
    }
}
