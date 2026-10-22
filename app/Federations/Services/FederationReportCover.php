<?php

namespace App\Federations\Services;

use App\Events\Models\ClubEvent;
use App\Federations\Models\FederationEvent;
use App\Support\StoragePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * The cover page of an event's downloadable results report (owner,
 * 2026-10-17: "a button to upload cover page … for the pdf download report").
 * One per event: a picture or a PDF (its first page), stretched to fill the
 * report's first A4 page. FederationResultsPdf puts it first.
 *
 * Stored privately at events/{event-uuid}/branding/report-cover-{uuid}.{ext}
 * — the folder is the record (one file, found by its prefix), the name is
 * generated, the extension decided from the real bytes. Never served by URL:
 * it only ever travels inside a PDF the server prints.
 */
class FederationReportCover
{
    /** Real type => extension. SVG is never taken (it can carry script). */
    private const TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private const PREFIX = 'report-cover-';

    public function __construct(private FederationDirectory $directory) {}

    /**
     * The platform event behind a federation event the viewer may reach —
     * the same scope as the dashboard's own data. NULL for anything else.
     */
    public function event(array $access, string $feUuid): ?ClubEvent
    {
        $q = FederationEvent::where('uuid', $feUuid)->whereNotNull('club_event_id')
            ->whereIn('federation_id', collect($this->directory->subtree($access['root']))->pluck('id'));
        if (($access['sports'] ?? []) !== []) {
            $q->whereIn('sport', $access['sports']);
        }
        $fe = $q->first();

        return $fe ? ClubEvent::whereKey($fe->club_event_id)->where('is_archived', false)->first() : null;
    }

    /** The stored cover's path on the local disk, or null. */
    public function path(ClubEvent $event): ?string
    {
        return collect(Storage::disk('local')->files($this->folder($event)))
            ->first(fn ($f) => str_starts_with(basename($f), self::PREFIX));
    }

    /** 'pdf', 'image' or null — what the dashboard shows. */
    public function kind(ClubEvent $event): ?string
    {
        $p = $this->path($event);

        return $p ? (str_ends_with($p, '.pdf') ? 'pdf' : 'image') : null;
    }

    /** Keep a new cover, replacing the old one. An error message, or null when stored. */
    public function store(ClubEvent $event, UploadedFile $file): ?string
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $ext = self::TYPES[$mime] ?? null;
        if (! $ext) {
            return 'The cover must be a PDF or a JPG, PNG or WebP picture.';
        }
        if ($ext === 'pdf' && ! $this->readablePdf($file->getRealPath())) {
            return 'That PDF could not be read. Save it again (not password-protected) and try once more.';
        }
        if ($ext !== 'pdf' && ! @getimagesizefromstring($bytes)) {
            return 'That picture could not be read.';
        }

        $old = $this->path($event);
        $new = $this->folder($event).'/'.self::PREFIX.Str::uuid().'.'.$ext;
        if (! Storage::disk('local')->put($new, $bytes)) {
            return 'The cover could not be saved. Try again.';
        }
        // The old file goes only once the new one is safely in place.
        if ($old && $old !== $new) {
            Storage::disk('local')->delete($old);
        }

        return null;
    }

    public function remove(ClubEvent $event): void
    {
        if ($p = $this->path($event)) {
            Storage::disk('local')->delete($p);
        }
    }

    /**
     * The cover as a picture for the report's first page, stretched to fill
     * it (owner, 2026-10-17: "stretch it to fit the page"). A PDF cover's
     * first page is drawn as a 200 dpi picture first, so a cover of any page
     * size fills the A4 sheet the same way a picture does.
     */
    public function imageDataUri(ClubEvent $event): ?string
    {
        $p = $this->path($event);
        if (! $p) {
            return null;
        }
        if (! str_ends_with($p, '.pdf')) {
            $bytes = Storage::disk('local')->get($p);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

            return isset(self::TYPES[$mime]) ? 'data:'.$mime.';base64,'.base64_encode($bytes) : null;
        }

        $dir = storage_path('app/private/tmp/report-cover/'.Str::uuid());
        File::ensureDirectoryExists($dir, 0750);
        try {
            $draw = new Process(['pdftoppm', '-f', '1', '-l', '1', '-r', '200', '-jpeg', '-jpegopt', 'quality=92', '-singlefile',
                Storage::disk('local')->path($p), $dir.'/cover']);
            $draw->setTimeout(60)->run();

            // A cover that will not draw never costs the report itself.
            return $draw->isSuccessful() && is_file($dir.'/cover.jpg')
                ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($dir.'/cover.jpg'))
                : null;
        } finally {
            File::deleteDirectory($dir);
        }
    }

    private function folder(ClubEvent $event): string
    {
        return StoragePath::event($event, 'branding');
    }

    private function readablePdf(string $file): bool
    {
        $p = new Process(['pdfinfo', $file]);
        $p->setTimeout(15)->run();

        return $p->isSuccessful() && preg_match('/^Pages:\s+([1-9]\d*)/m', $p->getOutput())
            && ! preg_match('/^Encrypted:\s+yes/m', $p->getOutput());
    }
}
