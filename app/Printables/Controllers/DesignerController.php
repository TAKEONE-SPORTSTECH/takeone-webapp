<?php

namespace App\Printables\Controllers;

use App\Http\Controllers\Controller;
use App\Members\Models\User;
use App\Printables\Contracts\PrintableKind;
use App\Printables\Designer;
use App\Printables\Models\PrintableAsset;
use App\Printables\Models\PrintableDesign;
use App\Printables\Support\AssetStore;
use App\Printables\Support\DocumentSanitizer;
use App\Printables\Support\Fonts;
use App\Printables\Support\Formats;
use App\Printables\Support\Kinds;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The designer's endpoints. Every one resolves the design's KIND and OWNER and
 * asks the kind whether this user may design it; a missing design and one that
 * is not theirs get the same 404 (CLAUDE.md → Anti-Enumeration).
 *
 * A design is not written on GET: the editor opens on a starter page, and the
 * first save creates the row (create()).
 */
class DesignerController extends Controller
{
    public function __construct(private readonly Kinds $kinds) {}

    /* ── The editor ─────────────────────────────────────────────────────── */

    public function page(Request $request, string $kind, string $ref)
    {
        [$k, $owner, $slot] = $this->target($request, $kind, $ref);
        $design = Designer::find($owner, $kind, $slot);
        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'printables::mobile' : 'printables::desktop', [
            'boot' => $this->boot($request, $k, $owner, $slot, $design),
            'title' => $k->title($owner, $slot),
        ]);
    }

    public function create(Request $request, string $kind, string $ref): JsonResponse
    {
        [$k, $owner, $slot] = $this->target($request, $kind, $ref);
        $data = $this->validated($request);
        $size = $this->size($k, $data);

        if ($existing = Designer::find($owner, $kind, $slot)) {
            return $this->conflict($existing);
        }

        $clean = $this->sanitizer($k, $owner, $slot, [])->clean($data['document']);
        try {
            $design = PrintableDesign::create([
                'kind' => $kind,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'slot' => (string) $slot,
                'title' => $data['title'] ?? null,
                'format' => $data['format'],
                'orientation' => $data['orientation'],
                'width_mm' => $size[0],
                'height_mm' => $size[1],
                'document' => $clean,
                'revision' => 1,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        } catch (QueryException) {
            // Two organisers pressed the first save at the same moment: one made it.
            return $this->conflict(Designer::find($owner, $kind, $slot));
        }

        return response()->json(['success' => true, 'design' => ['uuid' => $design->uuid, 'revision' => $design->revision]]);
    }

    /**
     * Start this slot's design as a COPY of another saved design of the same
     * owner (owner, 2026-10-20: a job's certificate starts from the Referee's
     * rather than a blank page). Only a slot with no design yet, only from a
     * design the kind says it may copy (a certificate from a certificate, never
     * a badge), and the pictures are copied with it so each design owns its own.
     */
    public function copy(Request $request, string $kind, string $ref, AssetStore $store): JsonResponse
    {
        [$k, $owner, $slot] = $this->target($request, $kind, $ref);
        $from = $request->validate(['from' => ['required', 'string', 'max:120']])['from'];
        $source = in_array($from, $k->slots($owner), true) && $from !== $slot ? Designer::find($owner, $kind, $from) : null;
        if (! $source || ! $this->mayCopy($k, $owner, $from, $slot)) {
            return response()->json(['success' => false, 'message' => __('printables::messages.copy_unavailable')], 422);
        }
        if ($existing = Designer::find($owner, $kind, $slot)) {
            return $this->conflict($existing);
        }

        try {
            $design = \Illuminate\Support\Facades\DB::transaction(function () use ($k, $owner, $kind, $slot, $source, $store, $request) {
                $design = PrintableDesign::create([
                    'kind' => $kind, 'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'slot' => (string) $slot,
                    'title' => null, 'format' => $source->format, 'orientation' => $source->orientation,
                    'width_mm' => $source->width_mm, 'height_mm' => $source->height_mm,
                    'document' => ['pages' => []], 'revision' => 1,
                    'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
                ]);
                $map = [];
                foreach ($source->assets as $asset) {
                    if ($copy = $store->copy($asset, $design, $request->user())) {
                        $map[$asset->uuid] = $copy->uuid;
                    }
                }
                $document = $this->remapAssets((array) $source->document, $map);
                $design->forceFill(['document' => $this->sanitizer($k, $owner, $slot, array_values($map))->clean($document)])->save();

                return $design;
            });
        } catch (QueryException) {
            return $this->conflict(Designer::find($owner, $kind, $slot));
        }

        return response()->json(['success' => true, 'design' => ['uuid' => $design->uuid, 'revision' => $design->revision]], 201);
    }

    /** Which designs a slot may start as a copy of — the kind decides; none when it says nothing. */
    private function mayCopy(PrintableKind $k, Model $owner, string $from, ?string $to): bool
    {
        return $to !== null && method_exists($k, 'mayCopy') && $k->mayCopy($owner, $from, $to);
    }

    /** Every picture reference in a document, pointed at its copy (elements, and inside groups). */
    private function remapAssets(array $node, array $map): array
    {
        foreach ($node as $key => $value) {
            if ($key === 'asset' && is_string($value)) {
                $node[$key] = $map[$value] ?? $value;
            } elseif (is_array($value)) {
                $node[$key] = $this->remapAssets($value, $map);
            }
        }

        return $node;
    }

    public function save(Request $request, string $uuid): JsonResponse
    {
        [$design, $k, $owner] = $this->design($request, $uuid);
        $data = $this->validated($request);
        $size = $this->size($k, $data);
        $clean = $this->sanitizer($k, $owner, $design->slot, $design->assets()->pluck('uuid')->all())->clean($data['document']);

        // Only the revision this editor last saw may be replaced — a second
        // editor's save is refused, not silently overwritten.
        $saved = PrintableDesign::query()
            ->whereKey($design->id)
            ->where('revision', (int) ($data['revision'] ?? 0))
            ->update([
                'title' => $data['title'] ?? $design->title,
                'format' => $data['format'],
                'orientation' => $data['orientation'],
                'width_mm' => $size[0],
                'height_mm' => $size[1],
                'document' => json_encode($clean),
                'revision' => (int) ($data['revision'] ?? 0) + 1,
                'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ]);

        if (! $saved) {
            return $this->conflict($design->fresh());
        }

        return response()->json(['success' => true, 'design' => ['uuid' => $design->uuid, 'revision' => (int) ($data['revision'] ?? 0) + 1]]);
    }

    /* ── Pictures ───────────────────────────────────────────────────────── */

    public function upload(Request $request, string $uuid, AssetStore $store): JsonResponse
    {
        [$design] = $this->design($request, $uuid);
        $request->validate(['file' => ['required', 'file', 'max:'.(int) ceil(config('printables.limits.asset_bytes', 12 * 1024 * 1024) / 1024)]]);

        if ($design->assets()->count() >= (int) config('printables.limits.assets_per_design', 200)) {
            return response()->json(['success' => false, 'message' => __('printables::messages.too_many_pictures')], 422);
        }
        $asset = $store->store($design, $request->file('file'), $request->user());
        if (! $asset) {
            return response()->json(['success' => false, 'message' => __('printables::messages.bad_picture')], 422);
        }

        return response()->json(['success' => true, 'asset' => $this->assetPayload($asset)]);
    }

    public function asset(Request $request, string $uuid)
    {
        $asset = PrintableAsset::query()->where('uuid', $uuid)->first();
        abort_unless($asset && $asset->design, 404);
        $this->design($request, $asset->design->uuid);
        abort_unless(Storage::disk('local')->exists($asset->path), 404);

        return Storage::disk('local')->response($asset->path, null, [
            'Content-Type' => $asset->mime,
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroyAsset(Request $request, string $uuid): JsonResponse
    {
        $asset = PrintableAsset::query()->where('uuid', $uuid)->first();
        abort_unless($asset && $asset->design, 404);
        $this->design($request, $asset->design->uuid);
        $asset->delete();

        return response()->json(['success' => true, 'uuid' => $uuid]);
    }

    /* ── Real-person preview ─────────────────────────────────────────── */

    /**
     * The real people a design can be previewed with — those the kind will name
     * (a certificate: the holders of this owner's certificates of this type).
     * A kind with no real data answers an empty list.
     */
    public function people(Request $request, string $kind, string $ref): JsonResponse
    {
        [$k, $owner, $slot] = $this->target($request, $kind, $ref);
        $q = (string) ($request->validate(['q' => ['nullable', 'string', 'max:80']])['q'] ?? '');

        return response()->json([
            'success' => true,
            'people' => method_exists($k, 'realRecipients') ? $k->realRecipients($owner, $slot, $q) : [],
        ]);
    }

    /** Every live field filled for one of those people, exactly as it prints. */
    public function person(Request $request, string $kind, string $ref, string $id): JsonResponse
    {
        [$k, $owner, $slot] = $this->target($request, $kind, $ref);
        $values = method_exists($k, 'realValues') ? $k->realValues($owner, $slot, $id) : null;
        abort_unless($values !== null, 404);

        return response()->json(['success' => true, 'values' => $values]);
    }

    /* ── Print preview ─────────────────────────────────────────────────── */

    public function print(Request $request, string $uuid)
    {
        [$design, $k, $owner] = $this->design($request, $uuid);

        return view('printables::print', [
            'boot' => [
                'document' => $design->document,
                'size' => ['w' => Formats::px($design->width_mm), 'h' => Formats::px($design->height_mm)],
                'fonts' => Fonts::forClient(),
                'fields' => $k->fields($owner, $design->slot ?: null),
                'assets' => $design->assets()->get()->map(fn ($a) => $this->assetPayload($a))->values(),
            ],
            'title' => $design->title ?: $k->title($owner, $design->slot ?: null),
            'widthMm' => $design->width_mm,
            'heightMm' => $design->height_mm,
        ]);
    }

    /* ── Internals ─────────────────────────────────────────────────────── */

    /**
     * The largest picture that can actually arrive: the designer's own limit, or
     * PHP's upload/post limits when those are lower — so the page can refuse a
     * too-big file at once instead of after a long upload the server then drops.
     */
    private function uploadBytes(): int
    {
        $bytes = static function (string|false $v): int {
            $v = trim((string) $v);
            if ($v === '' || $v === '0' || $v === '-1') {
                return PHP_INT_MAX;
            }
            $n = (int) $v;

            return match (strtolower(substr($v, -1))) {
                'g' => $n * 1024 ** 3,
                'm' => $n * 1024 ** 2,
                'k' => $n * 1024,
                default => $n,
            };
        };

        return min(
            (int) config('printables.limits.asset_bytes', 12 * 1024 * 1024),
            $bytes(ini_get('upload_max_filesize')),
            $bytes(ini_get('post_max_size')),
        );
    }

    /** @return array{0: PrintableKind, 1: Model, 2: ?string} */
    private function target(Request $request, string $kind, string $ref): array
    {
        $user = $request->user();
        $k = $this->kinds->get($kind);
        $owner = $k && $user instanceof User ? $k->findOwner($ref, $user) : null;
        abort_unless($k && $owner && $k->canDesign($owner, $user), 404);

        $slots = $k->slots($owner);
        $slot = $request->query('slot', $request->input('slot'));
        if ($slots === []) {
            $slot = null;
        } elseif (! is_string($slot) || ! in_array($slot, $slots, true)) {
            $slot = $slots[0];
        }

        return [$k, $owner, $slot];
    }

    /** @return array{0: PrintableDesign, 1: PrintableKind, 2: Model} */
    private function design(Request $request, string $uuid): array
    {
        $design = PrintableDesign::query()->where('uuid', $uuid)->first();
        $user = $request->user();
        $k = $design ? $this->kinds->get($design->kind) : null;
        $owner = $design?->owner;
        abort_unless($design && $k && $owner && $user instanceof User && $k->canDesign($owner, $user), 404);

        return [$design, $k, $owner];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'revision' => ['nullable', 'integer', 'min:0'],
            'title' => ['nullable', 'string', 'max:160'],
            'format' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/'],
            'orientation' => ['required', 'in:portrait,landscape'],
            'width_mm' => ['nullable', 'numeric'],
            'height_mm' => ['nullable', 'numeric'],
            'document' => ['required', 'array'],
        ]);
    }

    /** @return array{0: float, 1: float} */
    private function size(PrintableKind $k, array $data): array
    {
        $size = Formats::resolve(
            $data['format'],
            $data['orientation'],
            isset($data['width_mm']) ? (float) $data['width_mm'] : null,
            isset($data['height_mm']) ? (float) $data['height_mm'] : null,
            $k->formats(),
        );
        if (! $size) {
            abort(response()->json(['success' => false, 'message' => __('printables::messages.bad_size')], 422));
        }

        return $size;
    }

    /** @return list<string> */
    private function imageFields(PrintableKind $k, Model $owner, ?string $slot): array
    {
        return array_values(array_column(array_filter($k->fields($owner, $slot ?: null), fn ($f) => $f['type'] === 'image'), 'key'));
    }

    private function sanitizer(PrintableKind $k, Model $owner, ?string $slot, array $assets): DocumentSanitizer
    {
        return new DocumentSanitizer($assets, $this->imageFields($k, $owner, $slot), Fonts::families());
    }

    private function conflict(?PrintableDesign $design): JsonResponse
    {
        return response()->json([
            'success' => false,
            'conflict' => true,
            'message' => __('printables::messages.conflict'),
            'design' => $design ? $this->designPayload($design) : null,
        ], 409);
    }

    private function designPayload(PrintableDesign $d): array
    {
        return [
            'uuid' => $d->uuid,
            'title' => $d->title,
            'format' => $d->format,
            'orientation' => $d->orientation,
            'widthMm' => $d->width_mm,
            'heightMm' => $d->height_mm,
            'revision' => $d->revision,
            'document' => $d->document,
            'updatedAt' => $d->updated_at?->toIso8601String(),
        ];
    }

    private function assetPayload(PrintableAsset $a): array
    {
        return ['uuid' => $a->uuid, 'url' => route('me.printables.asset', $a->uuid), 'width' => $a->width, 'height' => $a->height];
    }

    private function slotLabel(string $slot): string
    {
        $key = 'cert::messages.types.'.$slot;

        return __($key) !== $key ? __($key) : ucfirst(str_replace('_', ' ', $slot));
    }

    private function boot(Request $request, PrintableKind $k, Model $owner, ?string $slot, ?PrintableDesign $design): array
    {
        if ($design) {
            $payload = $this->designPayload($design);
        } else {
            $start = $k->startingSize($slot);
            [$wMm, $hMm] = Formats::resolve($start['format'], $start['orientation'], null, null, []) ?? [210, 297];
            $payload = [
                'uuid' => null,
                'title' => $k->title($owner, $slot),
                'format' => $start['format'],
                'orientation' => $start['orientation'],
                'widthMm' => $wMm,
                'heightMm' => $hMm,
                'revision' => 0,
                'document' => $this->sanitizer($k, $owner, $slot, [])
                    ->clean(['pages' => [['elements' => $k->starter($owner, $slot, [Formats::px($wMm), Formats::px($hMm)])]]]),
                'updatedAt' => null,
            ];
        }

        $base = ['kind' => $k->key(), 'ref' => $k->ownerRef($owner)];
        // Where "back" goes: the page that opened the designer, else the kind's own home for this owner.
        $back = Designer::safeBack($request->query('back'))
            ?? (method_exists($k, 'homeUrl') ? Designer::safeBack($k->homeUrl($owner)) : null);

        // A slot with no design yet may start as a copy of one already made.
        $copies = [];
        if (! $design && $slot !== null) {
            foreach ($k->slots($owner) as $s) {
                if ($s !== $slot && $this->mayCopy($k, $owner, $s, $slot) && Designer::find($owner, $k->key(), $s)) {
                    $copies[] = ['slot' => $s, 'label' => method_exists($k, 'slotLabel') ? $k->slotLabel($owner, $s) : $this->slotLabel($s)];
                }
            }
        }

        return [
            'design' => $payload,
            'copies' => $copies,
            'copyUrl' => $copies ? route('me.printables.copy', $base + ['slot' => $slot]) : null,
            'kind' => ['key' => $k->key(), 'label' => $k->label(), 'title' => $k->title($owner, $slot)],
            'slot' => $slot,
            'slots' => array_map(fn ($s) => [
                'key' => $s,
                'label' => method_exists($k, 'slotLabel') ? $k->slotLabel($owner, $s) : $this->slotLabel($s),
                'url' => route('me.printables.design', $base + array_filter(['slot' => $s, 'back' => $back])),
            ], $k->slots($owner)),
            'formats' => Formats::offered($k->formats()),
            'fonts' => Fonts::forClient(),
            'fields' => $k->fields($owner, $slot),
            'assets' => $design ? $design->assets()->latest()->get()->map(fn ($a) => $this->assetPayload($a))->values() : [],
            'limits' => (array) config('printables.limits', []) + ['upload_bytes' => $this->uploadBytes()],
            'endpoints' => [
                'create' => route('me.printables.create', $base + array_filter(['slot' => $slot])),
                'save' => route('me.printables.save', '__UUID__'),
                'upload' => route('me.printables.upload', '__UUID__'),
                'print' => route('me.printables.print', '__UUID__'),
                'assetDelete' => route('me.printables.asset.destroy', '__UUID__'),
                // Real-person preview, only for a kind that has real people to show.
                'people' => method_exists($k, 'realRecipients') ? route('me.printables.people', $base + array_filter(['slot' => $slot])) : null,
                'person' => method_exists($k, 'realValues') ? route('me.printables.person', $base + ['id' => '__ID__'] + array_filter(['slot' => $slot])) : null,
            ],
            'back' => $back,
            'locale' => app()->getLocale(),
            'dir' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'qrStyle' => (bool) config('printables.qr_style'),
        ];
    }
}
