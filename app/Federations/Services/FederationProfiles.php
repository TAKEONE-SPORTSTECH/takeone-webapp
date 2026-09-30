<?php

namespace App\Federations\Services;

use App\Federations\Models\Federation;
use App\Federations\Models\FederationDocument;
use App\Federations\Models\FederationProfile;
use App\Members\Models\User;
use App\Support\Countries;
use App\Traits\StoresBase64Images;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A federation's record, as the super-admin creates and keeps it.
 *
 * Three stages, each a set of facts, answered by readiness():
 *   open      — enough to run the dashboard: name, short name, country,
 *               sports, owner, official email, phone and HQ address.
 *               Only name, country, sports and owner are needed to SAVE
 *               (the quick create); the rest is shown as missing, not forced.
 *   payments  — enough for clubs to pay it: the bank account (name, bank,
 *               a valid IBAN) and its registration with the sports authority,
 *               certificate attached.
 *   verified  — enough to vouch for it: president and secretary general, its
 *               international federation, statutes and that federation's
 *               recognition letter.
 *
 * Nothing past the four essentials blocks a save; the stages are shown, so a
 * federation can be set up in seconds and completed as its details arrive.
 */
class FederationProfiles
{
    use StoresBase64Images;

    public const LANGUAGES = ['en' => 'English', 'ar' => 'العربية', 'fr' => 'Français'];

    private const DOC_MIMES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /** Validation for the create / edit form. */
    public function rules(?Federation $federation): array
    {
        $officer = fn ($r) => ["officers.$r.name" => ['nullable', 'string', 'max:150'], "officers.$r.email" => ['nullable', 'email:rfc', 'max:191'], "officers.$r.phone" => ['nullable', 'string', 'max:40']];

        return array_merge([
            // Identity — required
            'name' => ['required', 'string', 'max:150', Rule::unique('federations', 'name')->ignore($federation?->id)->whereNull('deleted_at')],
            // Worked out from the name when left blank (acronym()).
            'short_name' => ['nullable', 'string', 'max:20', 'regex:/^[\pL\pN .&\-]+$/u'],
            'country' => ['required', 'string', 'size:2', 'alpha', Rule::in(collect(Countries::all())->pluck('code')->map(fn ($c) => strtoupper($c))->all())],
            'sports' => ['required', 'array', 'min:1'],
            'sports.*' => ['string', Rule::in(FederationDirectory::SPORTS)],
            'timezone' => ['nullable', 'timezone:all'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'founded_year' => ['nullable', 'integer', 'min:1850', 'max:'.now()->year],
            'languages' => ['nullable', 'array'],
            'languages.*' => [Rule::in(array_keys(self::LANGUAGES))],

            // Owner — an existing platform account
            'owner_email' => ['required', 'email:rfc', 'max:191', Rule::exists('users', 'email')],

            // Contact & HQ — asked for, never demanded: a federation can be made
            // with a name, sport, country and owner, and filled in as it goes.
            'email' => ['nullable', 'email:rfc', 'max:191'],
            'phone_code' => ['nullable', 'string', 'max:6', 'regex:/^\+?\d{1,4}$/'],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^[\d\s\-]{4,20}$/'],
            'office_hours' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'address' => ['nullable', 'string', 'max:300'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            // The HQ's Google Maps link — only a real Google Maps address (App\Support\MapLink).
            'maps_url' => ['nullable', 'string', 'max:500', function ($attr, $value, $fail) {
                if (! \App\Support\MapLink::isGoogle((string) $value)) {
                    $fail(__('federations::messages.f_maps_url_invalid'));
                }
            }],
            'social_links' => ['nullable', 'array', 'max:12'],
            'social_links.*.platform' => ['nullable', 'string', 'max:30'],
            'social_links.*.url' => ['nullable', 'url:http,https', 'max:255'],

            // Legal, tax, bank
            'registration_number' => ['nullable', 'string', 'max:60'],
            'registration_authority' => ['nullable', 'string', 'max:150'],
            'tax_number' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-\s]+$/'],
            'invoice_name' => ['nullable', 'string', 'max:150'],
            'invoice_address' => ['nullable', 'string', 'max:300'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'iban' => ['nullable', 'string', 'max:42', function ($attr, $value, $fail) {
                if ($value !== null && $value !== '' && ! self::validIban($value)) {
                    $fail(__('federations::messages.admin_iban_invalid'));
                }
            }],
            'swift' => ['nullable', 'string', 'regex:/^[A-Za-z]{6}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/'],

            // Governance & affiliations
            'board' => ['nullable', 'array', 'max:12'],
            'board.*.name' => ['nullable', 'string', 'max:150'],
            'board.*.role' => ['nullable', 'string', 'max:80'],
            'international_federation' => ['nullable', 'string', 'max:150'],
            'olympic_recognised' => ['nullable', 'boolean'],

            // Branding
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'file', 'max:2048'],

            // Documents
            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'file', 'max:10240'],
        ], ...array_map($officer, FederationProfile::OFFICER_ROLES));
    }

    /** "Bahrain Judo Federation" → "BJF": the short name when none is given. */
    public static function acronym(string $name): string
    {
        $letters = collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->reject(fn ($w) => $w === '' || in_array(mb_strtolower($w), ['of', 'the', 'and', 'for', '&', 'de', 'du', 'des', 'la', 'le', 'al'], true))
            ->map(fn ($w) => mb_strtoupper(mb_substr(preg_replace('/[^\pL\pN]/u', '', $w), 0, 1)))
            ->implode('');

        return mb_substr($letters !== '' ? $letters : mb_strtoupper(mb_substr(preg_replace('/[^\pL\pN]/u', '', $name), 0, 4)), 0, 20);
    }

    /** ISO 13616 mod-97 check. */
    public static function validIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            return false;
        }
        $moved = substr($iban, 4).substr($iban, 0, 4);
        $digits = preg_replace_callback('/[A-Z]/', fn ($m) => (string) (ord($m[0]) - 55), $moved);
        $rem = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $rem = (int) (($rem.$chunk) % 97);
        }

        return $rem === 1;
    }

    /**
     * Create or update from validated data. Files are validated by their real
     * bytes here; a file that fails is reported, never stored.
     *
     * @return array{federation: Federation, rejected: array<string, string>}
     */
    public function save(User $by, ?Federation $federation, array $data, ?UploadedFile $logo, array $documents): array
    {
        $owner = User::where('email', $data['owner_email'])->firstOrFail();
        $rejected = [];

        $federation = DB::transaction(function () use ($by, $federation, $data, $owner) {
            $core = [
                'owner_user_id' => $owner->id,
                'name' => trim($data['name']),
                'short_name' => trim((string) ($data['short_name'] ?? '')) ?: self::acronym($data['name']),
                'country' => strtoupper($data['country']),
                'sports' => array_values(array_unique($data['sports'])),
                'timezone' => $data['timezone'] ?? null,
                'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null,
                'color' => $data['color'] ?? null,
            ];
            if ($federation) {
                $federation->update($core);
            } else {
                $federation = Federation::create($core + [
                    'status' => Federation::STATUS_APPROVED,
                    'approved_at' => now(),
                    'approved_by' => $by->id,
                ]);
            }

            // A code with no number is not a phone.
            $phone = trim((string) ($data['phone_number'] ?? '')) !== '' ? trim(($data['phone_code'] ?? '').' '.$data['phone_number']) : '';
            $officers = [];
            foreach (FederationProfile::OFFICER_ROLES as $role) {
                $o = array_map(fn ($v) => is_string($v) ? trim($v) : null, (array) ($data['officers'][$role] ?? []));
                if (array_filter($o)) {
                    $officers[$role] = ['name' => $o['name'] ?? null, 'email' => $o['email'] ?? null, 'phone' => $o['phone'] ?? null];
                }
            }
            $socials = collect($data['social_links'] ?? [])
                ->filter(fn ($l) => ! empty($l['url']))
                ->map(fn ($l) => ['platform' => mb_substr((string) ($l['platform'] ?? ''), 0, 30), 'url' => $l['url']])->values()->all();
            $board = collect($data['board'] ?? [])->filter(fn ($b) => ! empty($b['name']))
                ->map(fn ($b) => ['name' => trim($b['name']), 'role' => trim((string) ($b['role'] ?? ''))])->values()->all();

            FederationProfile::updateOrCreate(['federation_id' => $federation->id], [
                'email' => trim((string) ($data['email'] ?? '')) !== '' ? strtolower(trim($data['email'])) : null,
                'phone' => $phone !== '' ? $phone : null,
                'office_hours' => $data['office_hours'] ?? null,
                'website' => $data['website'] ?? null,
                'socials' => $socials,
                'founded_year' => $data['founded_year'] ?? null,
                'languages' => array_values(array_unique($data['languages'] ?? [])),
                'address' => trim((string) ($data['address'] ?? '')) ?: null,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                // Absent is not blank: a caller that never sent the link leaves it alone.
                ...(array_key_exists('maps_url', $data) ? ['maps_url' => trim((string) $data['maps_url']) ?: null] : []),
                'registration_number' => $data['registration_number'] ?? null,
                'registration_authority' => $data['registration_authority'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'invoice_name' => $data['invoice_name'] ?? null,
                'invoice_address' => $data['invoice_address'] ?? null,
                'bank_account_name' => $data['bank_account_name'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'iban' => isset($data['iban']) && $data['iban'] !== '' ? strtoupper(preg_replace('/\s+/', '', $data['iban'])) : null,
                'swift' => isset($data['swift']) ? strtoupper($data['swift']) : null,
                'officers' => $officers,
                'board' => $board,
                'international_federation' => $data['international_federation'] ?? null,
                'olympic_recognised' => (bool) ($data['olympic_recognised'] ?? false),
                'updated_by' => $by->id,
            ]);

            return $federation;
        });

        if ($logo && ! $this->storeLogo($federation, $logo)) {
            $rejected['logo'] = __('federations::messages.admin_logo_invalid');
        }
        foreach ($documents as $kind => $file) {
            if ($file instanceof UploadedFile && in_array($kind, FederationDocument::KINDS, true) && ! $this->storeDocument($by, $federation, $kind, $file)) {
                $rejected["documents.$kind"] = __('federations::messages.admin_document_invalid');
            }
        }

        return ['federation' => $federation->fresh(['profile', 'documents', 'owner']), 'rejected' => $rejected];
    }

    /** A logo: the image's real bytes decide; SVG never. Old file goes only after the new one is safe. */
    private function storeLogo(Federation $f, UploadedFile $file): bool
    {
        $bytes = @file_get_contents($file->getRealPath());
        if (! $bytes) {
            return false;
        }
        $path = $this->storeBase64Image('data:image/png;base64,'.base64_encode($bytes), 'federations/'.$f->uuid.'/branding', (string) Str::uuid(), 'public');
        if ($path === null) {
            return false;
        }
        $old = $f->logo;
        $f->update(['logo' => $path]);
        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        return true;
    }

    /** A PDF or image, sniffed from its bytes, stored privately under a generated name; replaces that kind. */
    private function storeDocument(User $by, Federation $f, string $kind, UploadedFile $file): bool
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: '';
        $ext = self::DOC_MIMES[$mime] ?? null;
        if ($ext === null) {
            return false;
        }
        $path = 'federations/'.$f->uuid.'/documents/'.Str::uuid().'.'.$ext;
        if (! Storage::disk('local')->put($path, file_get_contents($file->getRealPath()))) {
            return false;
        }
        // Files before rows: the model's DeletesUploadedFiles removes the old file.
        FederationDocument::where('federation_id', $f->id)->where('kind', $kind)->get()->each->delete();
        FederationDocument::create([
            'federation_id' => $f->id, 'kind' => $kind, 'path' => $path,
            'original_name' => mb_substr(preg_replace('/[^\pL\pN ._\-()]/u', '', $file->getClientOriginalName()), 0, 190),
            'mime' => $mime, 'size' => (int) $file->getSize(), 'uploaded_by' => $by->id,
        ]);

        return true;
    }

    /**
     * The three stages, with what is missing from each, and a done/total per
     * form section for the hub's status dots.
     */
    public function readiness(?Federation $f): array
    {
        $p = $f?->profile;
        $docs = $f ? $f->documents->pluck('kind')->all() : [];
        $has = fn ($v) => $v !== null && $v !== '' && $v !== [];
        $officer = fn ($r) => $has($p?->officers[$r]['name'] ?? null);

        $stages = [
            'open' => [
                'name' => $has($f?->name), 'short_name' => $has($f?->short_name), 'country' => $has($f?->country),
                'sports' => $has($f?->sports), 'owner' => $has($f?->owner_user_id), 'email' => $has($p?->email),
                'phone' => $has($p?->phone), 'address' => $has($p?->address),
            ],
            'payments' => [
                'bank_account_name' => $has($p?->bank_account_name), 'bank_name' => $has($p?->bank_name),
                'iban' => $has($p?->iban), 'registration_number' => $has($p?->registration_number),
                'registration_certificate' => in_array('registration_certificate', $docs, true),
            ],
            'verified' => [
                'president' => $officer('president'), 'secretary_general' => $officer('secretary_general'),
                'international_federation' => $has($p?->international_federation),
                'statutes' => in_array('statutes', $docs, true),
                'international_letter' => in_array('international_letter', $docs, true),
            ],
        ];

        $out = [];
        foreach ($stages as $key => $checks) {
            $done = count(array_filter($checks));
            $out[$key] = ['done' => $done, 'total' => count($checks), 'complete' => $done === count($checks),
                'missing' => array_keys(array_filter($checks, fn ($ok) => ! $ok))];
        }

        return $out;
    }

    /** What the list page shows per federation. */
    public function card(Federation $f): array
    {
        $r = $this->readiness($f);
        $stage = $r['verified']['complete'] && $r['payments']['complete'] ? 'verified' : ($r['payments']['complete'] ? 'payments' : ($r['open']['complete'] ? 'open' : 'draft'));

        return [
            'uuid' => $f->uuid,
            'name' => $f->name,
            'short' => $f->short_name,
            'country' => $f->country,
            'sports' => (array) $f->sports,
            'color' => $f->color ?: '#1e2c4f',
            'logo' => $f->logo ? route('federations.logo', $f->uuid) : null,
            'owner' => $f->owner?->full_name ?: $f->owner?->name,
            'stage' => $stage,
            'readiness' => $r,
            'suspended' => (bool) $f->suspended_at,
            'parent' => $f->parent?->name,
        ];
    }
}
