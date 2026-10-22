<?php

namespace App\Certificates\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * A CLUB as the holder of a certificate (Ahl\Certificates\Contracts\Recipient)
 * — the thank-you an event gives each club that took part (2026-10-20).
 *
 * Two models are a club at an event: a club on the platform (Tenant) and one
 * the organiser wrote down for the day (EventClub). Both hold one through this,
 * so the name and crest on the paper are read by ONE rule. The using class
 * says where its name and crest are (certificateClubName / certificateLogoPath).
 *
 * The "photo" of a club is its crest, as a data: URI — never a remote fetch.
 */
trait ClubRecipient
{
    abstract protected function certificateClubName(): string;

    abstract protected function certificateLogoPath(): ?string;

    public function certificateName(): string
    {
        return trim($this->certificateClubName());
    }

    /** A club has no language of its own: the certificate follows the event's. */
    public function certificateLocale(): ?string
    {
        return null;
    }

    /**
     * What a printed copy depends on besides the certificate itself: the club's
     * record and the crest file it prints. A new crest is a new file name, so
     * a PDF drawn with the old one is known to be stale (CertificateBundles).
     */
    public function certificateStamp(): string
    {
        return $this->updated_at.'|'.$this->certificateLogoPath();
    }

    public function certificatePhoto(): ?string
    {
        $path = preg_replace('#^(storage/|public/)#', '', ltrim((string) $this->certificateLogoPath(), '/'));
        if ($path === '' || str_contains($path, '://') || str_contains($path, '..')) {
            return null;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }
        $bytes = (string) $disk->get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? 'data:'.$mime.';base64,'.base64_encode($bytes) : null;
    }
}
