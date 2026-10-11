<?php

namespace App\Certificates\Support;

use Ahl\Certificates\Models\Certificate;
use App\Members\Models\User;

/**
 * What a certificate looks like in JSON — two shapes, never more than the
 * viewer may see (CLAUDE.md → API abuse & data extraction):
 *
 *   mine()    the holder's own card: no ids, no who-approved, no revoke reason
 *             beyond the fact and date
 *   managed() the organiser's row: adds the holder's name and the revoke reason
 *
 * Neither carries a storage path or a numeric id; files are reached through
 * routes that authorise every request.
 */
class CertificatePresenter
{
    public function __construct(private Contexts $contexts) {}

    public function mine(Certificate $c, string $locale): array
    {
        return $this->base($c, $locale) + [
            'verify_url' => $c->isDraft() ? null : route('certificates.verify', ['token' => $c->verify_token]),
        ];
    }

    public function managed(Certificate $c, string $locale): array
    {
        $recipient = $c->recipient;

        return $this->base($c, $locale) + [
            'holder' => $recipient instanceof User ? $recipient->certificateName() : null,
            'revoke_reason' => $c->isRevoked() ? $c->revoke_reason : null,
            'print_url' => route('me.certificates.print', $c->uuid),
            'verify_url' => $c->isDraft() ? null : route('certificates.verify', ['token' => $c->verify_token]),
        ];
    }

    private function base(Certificate $c, string $locale): array
    {
        $context = $c->context_id ? $c->context : null;
        $provider = $this->contexts->forModel($context);
        $details = (array) ($c->details ?? []);

        return [
            'uuid' => $c->uuid,
            'number' => $c->certificate_no,
            'type' => $c->type,
            'type_label' => $c->typeLabel($locale),
            'status' => $c->status->value,
            'status_label' => $c->status->label($locale),
            'title' => $c->title,
            'what' => $provider ? $provider->describe($c, $locale) : (string) $c->achievement,
            'place' => isset($details['place']) ? (int) $details['place'] : null,
            'context' => $provider && $context ? ['title' => $provider->headline($context)] : null,
            'issued_on' => $c->issued_on?->toDateString(),
            'revoked_on' => $c->revoked_at?->toDateString(),
            'pdf_url' => route('me.certificates.pdf', $c->uuid),
            'download_url' => route('me.certificates.pdf', ['cert' => $c->uuid, 'download' => 1]),
            'image_url' => route('me.certificates.image', $c->uuid),
            // Which way up the picture is, so a list can give it the right shape (only when the design is loaded).
            'portrait' => $c->relationLoaded('template') && $c->template?->pageOrientation() === 'portrait',
        ];
    }
}
