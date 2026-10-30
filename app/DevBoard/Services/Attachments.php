<?php

namespace App\DevBoard\Services;

use App\DevBoard\Models\Attachment;
use App\DevBoard\Models\Card;
use App\Members\Models\User;
use App\Support\StoragePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A card's attachments: FILES and LINKS (Trello's "Attach").
 *
 * A file is accepted by what its BYTES are, never by its name or the type the
 * browser claims: the extension must be on the list and the sniffed type must
 * be one that extension can really have (documents, spreadsheets, slides,
 * text, pictures, sound, video, archives). It is stored on the private disk
 * under the card's folder with a server-made name; the person's file name is
 * kept only as the display name. Programs, scripts, SVG and HTML never get in.
 *
 * A link is an http or https address only, kept as typed and shown with its
 * host; nothing is fetched from it.
 */
class Attachments
{
    /** extension → the types its bytes may sniff as */
    public const TYPES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'zip' => ['application/zip'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        // more of what people keep (owner, 28 Oct: "a random file type") — still by their bytes, still download-only
        'heic' => ['image/heic', 'image/heif'],
        'rtf' => ['text/rtf', 'application/rtf'],
        'odt' => ['application/vnd.oasis.opendocument.text'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
        'odp' => ['application/vnd.oasis.opendocument.presentation'],
        'md' => ['text/plain', 'text/markdown'],
        'json' => ['application/json', 'text/plain'],
        'psd' => ['image/vnd.adobe.photoshop', 'application/octet-stream'],
        'ai' => ['application/pdf', 'application/postscript'],
        'eps' => ['application/postscript'],
        'mp3' => ['audio/mpeg'],
        'm4a' => ['audio/x-m4a', 'audio/mp4', 'video/mp4'],
        'wav' => ['audio/x-wav', 'audio/wav', 'audio/vnd.wave'],
        'ogg' => ['audio/ogg', 'video/ogg', 'application/ogg'],
        'mp4' => ['video/mp4'],
        'mov' => ['video/quicktime'],
        'webm' => ['video/webm', 'audio/webm'],
        'rar' => ['application/x-rar', 'application/vnd.rar', 'application/x-rar-compressed'],
        '7z' => ['application/x-7z-compressed'],
        'gz' => ['application/gzip', 'application/x-gzip'],
        'tar' => ['application/x-tar'],
    ];

    public const IMAGES = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    public function __construct(private Board $board) {}

    /** Null when the file is not one we take (type, bytes or size). */
    public function storeFile(Card $card, User $by, UploadedFile $file): ?Attachment
    {
        if (! $file->isValid() || $file->getSize() < 1 || $file->getSize() > (int) config('devboard.limits.file_mb', 10) * 1024 * 1024) {
            return null;
        }
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $sniffed = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! isset(self::TYPES[$ext]) || ! in_array($sniffed, self::TYPES[$ext], true)) {
            return null;
        }
        // Office's new formats are zips: one is only taken when it really is the document it claims.
        if (in_array($ext, ['docx', 'xlsx', 'pptx'], true) && ! $this->isOfficeZip($file->getRealPath(), $ext)) {
            return null;
        }
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $uuid = (string) Str::uuid();
        $path = StoragePath::file(StoragePath::platform('devboard/cards/'.$card->uuid.'/files'), $uuid, $ext);
        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

        $name = $this->displayName($file->getClientOriginalName(), $ext);
        $a = Attachment::create([
            'uuid' => $uuid, 'card_id' => $card->id, 'kind' => 'file', 'name' => $name,
            'path' => $path, 'ext' => $ext, 'mime' => $sniffed, 'size' => $file->getSize(), 'created_by' => $by->id,
        ]);
        $this->board->log($card, $by, 'attached', ['name' => $name]);

        return $a;
    }

    /** Null when the address is not an http(s) one. */
    public function storeLink(Card $card, User $by, string $url, ?string $name): ?Attachment
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])
            || ! filter_var($url, FILTER_VALIDATE_URL) || mb_strlen($url) > 1000) {
            return null;
        }
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $name));
        $a = Attachment::create([
            'uuid' => (string) Str::uuid(), 'card_id' => $card->id, 'kind' => 'link',
            'name' => mb_substr($name !== '' ? $name : $parts['host'], 0, 200), 'url' => $url, 'created_by' => $by->id,
        ]);
        $this->board->log($card, $by, 'linked', ['name' => $a->name]);

        return $a;
    }

    public function payload(Attachment $a): array
    {
        $a->loadMissing('author:id,full_name');

        return [
            'uuid' => $a->uuid,
            'kind' => $a->kind,
            'name' => $a->name,
            'url' => $a->kind === 'link' ? $a->url : null,
            'host' => $a->kind === 'link' ? parse_url((string) $a->url, PHP_URL_HOST) : null,
            'ext' => $a->ext,
            'image' => $a->kind === 'file' && in_array($a->ext, self::IMAGES, true),
            'size' => $a->size,
            'by' => $this->board->person($a->author),
            'at' => $a->created_at?->toIso8601String(),
        ];
    }

    /** The person's file name, cleaned, as a label only (never a path). */
    private function displayName(string $original, string $ext): string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $base = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $base));

        return mb_substr(($base !== '' ? $base : 'file'), 0, 180).'.'.$ext;
    }

    private function isOfficeZip(string $path, string $ext): bool
    {
        if (! class_exists(\ZipArchive::class)) {
            return false;
        }
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }
        $part = ['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml'][$ext];
        $ok = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($part) !== false;
        $zip->close();

        return $ok;
    }
}
