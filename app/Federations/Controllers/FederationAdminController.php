<?php

namespace App\Federations\Controllers;

use App\Federations\Models\Federation;
use App\Federations\Models\FederationDocument;
use App\Federations\Models\FederationProfile;
use App\Federations\Services\FederationDirectory;
use App\Federations\Services\FederationProfiles;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use App\Members\Support\MemberLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The super-admin's Federations screens: the list, and the one form that
 * creates and keeps a federation's record (FederationProfiles decides what it
 * needs). Behind the platform admin stack (auth, verified, two-factor,
 * super-admin) and the module flag.
 *
 * Writes are AJAX and answer with the record and its readiness, so the form
 * patches itself in place; a create navigates to the new record's edit page.
 */
class FederationAdminController extends Controller
{
    public function __construct(private FederationProfiles $profiles) {}

    public function index(Request $request): View
    {
        $this->ensureEnabled();
        $federations = Federation::with('profile', 'documents', 'owner', 'parent')->orderBy('name')->get();
        $cards = $federations->map(fn ($f) => $this->profiles->card($f))->values();

        // Deleted ones stay restorable; the most recent few are listed under the cards.
        $deleted = Federation::onlyTrashed()->orderByDesc('deleted_at')->limit(20)->get(['id', 'uuid', 'name', 'short_name', 'deleted_at']);

        return view($this->pick($request, 'index'), ['cards' => $cards, 'deleted' => $deleted]);
    }

    public function create(Request $request): View
    {
        $this->ensureEnabled();

        return view($this->pick($request, 'form'), $this->formData(null));
    }

    public function edit(Request $request, Federation $federation): View
    {
        $this->ensureEnabled();
        $federation->load('profile', 'documents', 'owner');

        return view($this->pick($request, 'form'), $this->formData($federation));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->persist($request, null);
    }

    public function update(Request $request, Federation $federation): JsonResponse
    {
        return $this->persist($request, $federation);
    }

    /** A private document, to the super-admin only (this route's stack). */
    public function document(Federation $federation, FederationDocument $document): Response
    {
        $this->ensureEnabled();
        abort_unless($document->federation_id === $federation->id && Storage::disk('local')->exists($document->path), 404);
        $name = Str::slug($federation->short_name ?: $federation->name).'-'.str_replace('_', '-', $document->kind).'.'.pathinfo($document->path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download($document->path, $name, [
            'Content-Type' => $document->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Delete a federation: a SOFT delete. It disappears from the list and its
     * owner's dashboard; its profile, documents, clubs, events and files are
     * all kept, so restore() brings it back exactly as it was.
     */
    public function destroy(Request $request, Federation $federation): JsonResponse
    {
        $this->ensureEnabled();
        $federation->delete();
        activity('federations')->causedBy($request->user())->performedOn($federation)->log('Federation deleted');

        return response()->json(['success' => true, 'message' => __('federations::messages.admin_deleted', ['name' => $federation->name]),
            'redirect' => route('admin.federations.index')]);
    }

    public function restore(Request $request, string $uuid): JsonResponse
    {
        $this->ensureEnabled();
        $federation = Federation::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
        // Names are unique among live federations: one made since may have taken it.
        if (Federation::where('name', $federation->name)->exists()) {
            return response()->json(['success' => false, 'message' => __('federations::messages.admin_restore_name_taken', ['name' => $federation->name])], 422);
        }
        $federation->restore();
        activity('federations')->causedBy($request->user())->performedOn($federation)->log('Federation restored');

        return response()->json(['success' => true, 'message' => __('federations::messages.admin_restored', ['name' => $federation->name]),
            'redirect' => route('admin.federations.edit', $federation->uuid)]);
    }

    public function destroyDocument(Federation $federation, FederationDocument $document): JsonResponse
    {
        $this->ensureEnabled();
        abort_unless($document->federation_id === $federation->id, 404);
        $document->delete();   // file first (DeletesUploadedFiles), then the row
        $federation->load('profile', 'documents', 'owner');

        return response()->json(['success' => true, 'message' => __('federations::messages.admin_document_removed'),
            'documents' => $this->documents($federation), 'readiness' => $this->profiles->readiness($federation)]);
    }

    private function persist(Request $request, ?Federation $federation): JsonResponse
    {
        $this->ensureEnabled();
        if ($request->filled('country')) {
            $request->merge(['country' => strtoupper((string) $request->input('country'))]);
        }
        $data = $request->validate($this->profiles->rules($federation));

        $result = $this->profiles->save($request->user(), $federation, $data, $request->file('logo'), (array) $request->file('documents', []));
        $saved = $result['federation'];

        activity('federations')->causedBy($request->user())->performedOn($saved)
            ->log($federation ? 'Federation record updated' : 'Federation created');

        return response()->json([
            'success' => true,
            'message' => $result['rejected'] ? __('federations::messages.admin_saved_with_rejections') : __('federations::messages.admin_saved'),
            'rejected' => (object) $result['rejected'],
            'redirect' => $federation ? null : route('admin.federations.edit', $saved->uuid),
            'federation' => ['uuid' => $saved->uuid, 'name' => $saved->name, 'logo' => $saved->logo ? route('federations.logo', $saved->uuid).'?v='.substr(md5($saved->logo), 0, 8) : null],
            'documents' => $this->documents($saved),
            'readiness' => $this->profiles->readiness($saved),
        ]);
    }

    private function formData(?Federation $f): array
    {
        $p = $f?->profile ?? new FederationProfile;
        $phone = (string) $p->phone;
        [$code, $number] = preg_match('/^(\+\d{1,4})\s+(.*)$/', $phone, $m) ? [$m[1], $m[2]] : ['+973', $phone];

        return [
            'federation' => $f,
            'values' => [
                'name' => $f?->name, 'short_name' => $f?->short_name, 'country' => $f?->country ?: 'BH',
                'sports' => (array) ($f?->sports ?? []), 'timezone' => $f?->timezone ?: 'Asia/Bahrain',
                'currency' => $f?->currency ?: 'BHD', 'color' => $f?->color ?: '#1e2c4f',
                'owner_email' => $f?->owner?->email, 'owner_name' => $f?->owner?->full_name ?: $f?->owner?->name,
                'email' => $p->email, 'phone_code' => $code, 'phone_number' => $number, 'office_hours' => $p->office_hours,
                'website' => $p->website, 'founded_year' => $p->founded_year, 'languages' => (array) ($p->languages ?? ['en', 'ar']),
                'address' => $p->address, 'lat' => $p->lat, 'lng' => $p->lng, 'maps_url' => $p->maps_url, 'social_links' => (array) ($p->socials ?? []),
                'registration_number' => $p->registration_number, 'registration_authority' => $p->registration_authority ?: ($f ? null : 'General Sports Authority'),
                'tax_number' => $p->tax_number, 'invoice_name' => $p->invoice_name, 'invoice_address' => $p->invoice_address,
                'bank_account_name' => $p->bank_account_name, 'bank_name' => $p->bank_name, 'iban' => $p->iban, 'swift' => $p->swift,
                'officers' => (array) ($p->officers ?? []), 'board' => (array) ($p->board ?? []),
                'international_federation' => $p->international_federation, 'olympic_recognised' => (bool) $p->olympic_recognised,
                'logo' => $f?->logo ? route('federations.logo', $f->uuid) : null,
            ],
            'documents' => $f ? $this->documents($f) : (object) [],
            'readiness' => $this->profiles->readiness($f),
            'sports' => FederationDirectory::SPORTS,
            'languages' => FederationProfiles::LANGUAGES,
            'officerRoles' => FederationProfile::OFFICER_ROLES,
            'documentKinds' => FederationDocument::KINDS,
            'action' => $f ? route('admin.federations.update', $f->uuid) : route('admin.federations.store'),
            'method' => $f ? 'PUT' : 'POST',
            'sections' => self::SECTIONS,
        ];
    }

    /** The form's chapters, in order: key => [icon, required?]. */
    public const SECTIONS = [
        'identity' => ['bi-flag-fill', true], 'owner' => ['bi-person-badge-fill', true], 'contact' => ['bi-geo-alt-fill', false],
        'legal' => ['bi-bank2', false], 'tax' => ['bi-receipt', false], 'bank' => ['bi-credit-card-2-front-fill', false],
        'governance' => ['bi-people-fill', false], 'documents' => ['bi-patch-check-fill', false], 'branding' => ['bi-palette-fill', false],
    ];

    /** Everything the shared Alpine form needs, identical for both layouts. */
    public static function jsConfig(array $d, bool $mobile): array
    {
        $t = fn ($k) => __('federations::messages.'.$k);

        return [
            'values' => $d['values'], 'readiness' => $d['readiness'], 'documents' => $d['documents'],
            'action' => $d['action'], 'method' => $d['method'], 'mobile' => $mobile, 'uuid' => $d['federation']?->uuid,
            'sections' => array_keys(self::SECTIONS),
            'sectionChecks' => [
                'identity' => ['name', 'short_name', 'country', 'sports'], 'owner' => ['owner'], 'contact' => ['email', 'phone', 'address'],
                'legal' => ['registration_number', 'registration_certificate'], 'bank' => ['bank_account_name', 'bank_name', 'iban'],
                'governance' => ['president', 'secretary_general'], 'documents' => ['international_federation', 'statutes', 'international_letter'],
            ],
            'labels' => [
                'name' => $t('f_name'), 'short_name' => $t('f_short'), 'country' => $t('f_country'), 'sports' => $t('f_sports'),
                'owner' => $t('sec_owner'), 'email' => $t('f_email'), 'phone' => $t('f_phone'), 'address' => $t('f_address'),
                'bank_account_name' => $t('f_bank_account_name'), 'bank_name' => $t('f_bank_name'), 'iban' => $t('f_iban'),
                'registration_number' => $t('f_reg_number'), 'registration_certificate' => $t('doc_registration_certificate'),
                'president' => $t('officer_president'), 'secretary_general' => $t('officer_secretary_general'),
                'international_federation' => $t('f_intl'), 'statutes' => $t('doc_statutes'), 'international_letter' => $t('doc_international_letter'),
                '__fix' => $t('admin_fix_errors'), '__logo_invalid' => $t('admin_logo_invalid'),
                '__remove' => $t('doc_remove'), '__remove_title' => $t('doc_remove'),
            ],
        ];
    }

    /** kind => {uuid, name, size, url, remove} — never a storage path. */
    private function documents(Federation $f): object
    {
        return (object) $f->documents->mapWithKeys(fn (FederationDocument $d) => [$d->kind => [
            'uuid' => $d->uuid, 'name' => $d->original_name ?: str_replace('_', ' ', $d->kind), 'size' => $d->size,
            'url' => route('admin.federations.document', [$f->uuid, $d->uuid]),
            'remove' => route('admin.federations.document.destroy', [$f->uuid, $d->uuid]),
        ]])->all();
    }

    private function pick(Request $request, string $view): string
    {
        $mobile = (bool) ($request->attributes->get('is_mobile') ?? view()->shared('isMobile', false));

        return 'federations::admin.'.($mobile ? 'mobile' : 'desktop').'.'.$view;
    }

    /**
     * Find a member to be a federation's owner or officer: name or email, for
     * the form's "Find a member" field. Super-admin only (the route's stack).
     * Returns the few fields the picker draws, and only members with an email,
     * since the email is what the form keeps. The photo honours the member's
     * own choice; otherwise it is the drawn avatar.
     */
    /**
     * Find a member to be a federation's owner or officer — by name, phone,
     * email or TakeOne ID (App\Members\Support\MemberLookup, the one search
     * every "Find a member" uses). Super-admin only (the route's stack).
     * Returns only what the picker draws; the email is what the form keeps.
     */
    public function people(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        return response()->json(['people' => MemberLookup::search((string) $request->query('q', ''))
            ->map(fn (User $u) => [
                'name' => $u->full_name ?: $u->name,
                'email' => $u->email,
                'photo' => MemberLookup::photo($u),
                // For "use this member's contact details" on the Contact section.
                'mobile' => $u->mobile_formatted,
                'phone_code' => is_array($u->mobile) ? (string) ($u->mobile['code'] ?? '') : '',
                'phone_number' => is_array($u->mobile) ? (string) ($u->mobile['number'] ?? '') : '',
            ])
            ->values()]);
    }

    private function ensureEnabled(): void
    {
        abort_unless(config('federations.enabled'), 404);
    }
}
