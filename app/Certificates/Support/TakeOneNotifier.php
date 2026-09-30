<?php

namespace App\Certificates\Support;

use Ahl\Certificates\Contracts\CertificateNotifier;
use Ahl\Certificates\Models\Certificate;
use App\Members\Models\User;
use App\Members\Models\UserNotification;

/**
 * Tells a person their certificate is ready: a platform notification, which
 * also reaches them live over MQTT and on the phone (UserNotification::notifyUser).
 * Written in THEIR language, and deep-linked to their own certificates page —
 * never to a file URL, which would outlive the moment and skip authorisation.
 */
class TakeOneNotifier implements CertificateNotifier
{
    public function __construct(private Contexts $contexts) {}

    public function enabled(): bool
    {
        return (bool) config('certificates.enabled');
    }

    public function send(Certificate $certificate, string $verifyUrl): int
    {
        $user = $certificate->recipient;
        if (! $user instanceof User) {
            return 0;
        }

        $locale = in_array($user->locale, ['en', 'ar'], true) ? $user->locale : 'en';
        $context = $certificate->context_id ? $certificate->context : null;
        $provider = $this->contexts->forModel($context);

        $sent = UserNotification::notifyUser($user->id, 'certificate', __('cert::messages.notify_title', [], $locale), [
            'body' => $provider && $context
                ? __('cert::messages.notify_body', ['what' => $provider->describe($certificate, $locale), 'event' => $provider->headline($context)], $locale)
                : (string) $certificate->title,
            'action_url' => route('me.certificates.index'),
            'icon' => 'bi-patch-check-fill',
            'actor_id' => $certificate->approved_by,
        ]);

        return $sent ? 1 : 0;
    }
}
