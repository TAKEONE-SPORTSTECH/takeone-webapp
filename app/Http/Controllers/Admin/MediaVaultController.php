<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\MigrateMediaToVault;
use App\Media\Ffmpeg;
use App\Media\MediaVaults;
use App\Models\MediaFile;
use App\Models\MediaVault;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Attach and detach the places media is kept.
 *
 * Super-admin only — the route group enforces that. Credentials are write-only:
 * stored encrypted, never returned to the browser, and replaced only when a new
 * value is actually supplied.
 *
 * The page this serves has one job beyond CRUD: to make the CURRENT arrangement
 * obvious. Nothing attached is a legitimate state, so the page says so plainly
 * rather than looking broken, and every vault shows whether video streams off it
 * directly or has to be copied down first — because that is the difference
 * between owning the storage problem and pretending to.
 */
class MediaVaultController extends Controller
{
    public function __construct(
        private MediaVaults $vaults,
        private Ffmpeg $ffmpeg,
    ) {}

    public function index(Request $request)
    {
        $vaults = MediaVault::query()
            ->orderByDesc('priority')->orderBy('id')->get()
            ->map(fn (MediaVault $v) => $this->payload($v));

        $mobile = $request->attributes->get('is_mobile') && view()->exists('admin.storage.mobile');

        return view($mobile ? 'admin.storage.mobile' : 'admin.storage.index', [
            'vaults' => $vaults,
            'drivers' => MediaVault::DRIVERS,
            'local' => $this->localSummary(),
            'writeTarget' => $this->vaults->writeVault()?->uuid,
            'pipeline' => $this->pipelineSummary(),
        ]);
    }

    /** Browser-safe projection. Never carries the credential. */
    private function payload(MediaVault $vault): array
    {
        return [
            'uuid' => $vault->uuid,
            'name' => $vault->name,
            'driver' => $vault->driver,
            'location' => $vault->location,
            'host' => $vault->host,
            'port' => $vault->port,
            'share' => $vault->share,
            'username' => $vault->username,
            'domain' => $vault->domain,
            'mount_path' => $vault->mount_path,
            'root_path' => $vault->root_path,
            'priority' => $vault->priority,
            'enabled' => $vault->enabled,
            'read_only' => $vault->read_only,
            'serves_in_place' => $vault->servesInPlace(),
            'has_password' => filled($vault->getAttributes()['password'] ?? null),
            'last_status' => $vault->last_status,
            'last_error' => $vault->last_error,
            'last_checked_at' => $vault->last_checked_at?->diffForHumans(),
            'free_bytes' => $vault->free_bytes,
            'total_bytes' => $vault->total_bytes,
            'file_count' => $vault->files()->count(),
            'stored_bytes' => (int) $vault->files()->sum('bytes'),
            // What the automatic migration is doing, when one is running.
            'migration' => MigrateMediaToVault::progressFor($vault),
        ];
    }

    /** What is on this server's own disk, whether or not a vault is attached. */
    private function localSummary(): array
    {
        $usage = $this->vaults->localDriver()->usage();

        return [
            'root' => config('media.local_root'),
            'free_bytes' => $usage['free'],
            'total_bytes' => $usage['total'],
            'file_count' => MediaFile::whereNull('vault_id')->count(),
            'stored_bytes' => (int) MediaFile::whereNull('vault_id')->sum('bytes'),
        ];
    }

    /**
     * The state of the machinery, not just the storage.
     *
     * Worth showing on this page because the two answers together decide what
     * actually happens to a bout: where it is kept, and whether this server can
     * make it watchable. A missing encoder is not an error state — the video is
     * stored and downloadable either way — but it is something an operator
     * should not have to discover from a log.
     */
    private function pipelineSummary(): array
    {
        return [
            'ffmpeg' => $this->ffmpeg->ffmpeg(),
            'has_ffmpeg' => $this->ffmpeg->available(),
            'gpu' => $this->ffmpeg->gpuUsable(),
            'gpu_encoder' => (string) config('media.gpu.encoder'),
            'ready' => MediaFile::where('status', MediaFile::STATUS_READY)->count(),
            'processing' => MediaFile::whereIn('status', [MediaFile::STATUS_STORED, MediaFile::STATUS_PROCESSING])->count(),
            'failed' => MediaFile::whereIn('status', [MediaFile::STATUS_FAILED, MediaFile::STATUS_MISSING])->count(),
        ];
    }

    /**
     * Attach a new place to keep media.
     *
     * The probe runs before we answer so the page can say whether the storage
     * an operator just described is actually reachable — "saved" on its own is
     * not useful when the whole point is that a NAS is on the other end.
     */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $vault = MediaVault::create($data);

        $result = $this->refreshStatus($vault);
        $migrating = $this->beginMigration($vault, $result['ok']);

        return response()->json([
            'success' => true,
            'message' => ($result['ok']
                ? "Attached {$vault->name}."
                : "Attached {$vault->name}, but it could not be reached: {$result['message']}")
                .($migrating ? ' Moving existing video onto it now.' : ''),
            'vault' => $this->payload($vault->fresh()),
        ], 201);
    }

    /**
     * Edit an attached vault.
     *
     * A vault that was not a usable destination and now is gets the same
     * migration a fresh attach would — re-enabling storage, or clearing its
     * read-only flag, is the same act as attaching it as far as pending media
     * is concerned. One that was already usable is left alone, so saving a
     * rename does not queue the library again.
     */
    public function update(Request $request, MediaVault $vault)
    {
        $wasDestination = $vault->enabled && ! $vault->read_only && $vault->last_status === 'online';

        $data = $this->validated($request, $vault);

        $vault->fill($data)->save();

        // The connection details may have changed under us; the cached verdict
        // for the old ones must not decide anything about the new ones.
        $this->vaults->forgetReachability($vault);

        $result = $this->refreshStatus($vault);
        $migrating = ! $wasDestination && $this->beginMigration($vault, $result['ok']);

        return response()->json([
            'success' => true,
            'message' => ($result['ok']
                ? "Saved {$vault->name}."
                : "Saved {$vault->name}, but it could not be reached: {$result['message']}")
                .($migrating ? ' Moving existing video onto it now.' : ''),
            'vault' => $this->payload($vault->fresh()),
        ]);
    }

    /**
     * Detach.
     *
     * A vault holding files is refused unless it is explicitly forced, because
     * detaching one is how you lose a competition's footage. The rows survive
     * either way (`nullOnDelete`) and are marked missing, so there is a record
     * that the video existed and where it was — which is the only thing that
     * makes a re-attach recoverable.
     */
    public function destroy(Request $request, MediaVault $vault)
    {
        $count = $vault->files()->count();

        if ($count > 0 && ! $request->boolean('force')) {
            return response()->json([
                'success' => false,
                'needs_confirmation' => true,
                'file_count' => $count,
                'message' => "{$count} file(s) are stored on this vault. Drain it first, or confirm to detach anyway — those videos will stop playing.",
            ], 409);
        }

        if ($count > 0) {
            // Mark before the delete: once the vault row is gone these rows read
            // as local-disk files, and a missing status is the only thing that
            // stops something later treating them as present.
            $vault->files()->update(['status' => MediaFile::STATUS_MISSING]);
        }

        $name = $vault->name;
        $this->vaults->forgetReachability($vault);
        $vault->delete();

        return response()->json([
            'success' => true,
            'message' => "Detached {$name}.".($count > 0 ? " {$count} file(s) marked as missing." : ''),
        ]);
    }

    /**
     * Start filling a newly usable vault, if there is anything to fill it with.
     *
     * Deliberately conditional: a vault that is disabled, read-only or offline is
     * not a destination, and queueing a migration onto one would just fail in a
     * loop. Nothing to move is also a perfectly ordinary answer — a fresh install
     * attaching storage before its first competition.
     */
    private function beginMigration(MediaVault $vault, bool $online): bool
    {
        if (! $online || ! $vault->enabled || $vault->read_only) {
            return false;
        }

        $pending = MediaFile::whereNull('vault_id')
            ->whereNotIn('status', [MediaFile::STATUS_UPLOADING, MediaFile::STATUS_PROCESSING, MediaFile::STATUS_MISSING])
            ->exists();

        if (! $pending) {
            return false;
        }

        MigrateMediaToVault::dispatch($vault->uuid)->onQueue('media');

        return true;
    }

    /** A live connection check, and the moment the cached status is refreshed. */
    public function test(MediaVault $vault)
    {
        $this->vaults->forgetReachability($vault);

        $result = $this->refreshStatus($vault);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'vault' => $this->payload($vault->fresh()),
        ]);
    }

    /**
     * Move media OFF this vault, so it can be retired without losing anything.
     *
     * Batched rather than "all of it": this copies whole videos over a network
     * and a request is the wrong place to do a terabyte. The endpoint reports
     * what is left, and the page calls it again — or `php artisan media:drain`
     * runs the whole thing from a terminal.
     */
    public function drain(Request $request, MediaVault $vault)
    {
        $vault->forceFill(['read_only' => true])->save();

        $limit = (int) $request->integer('limit', 5);
        $limit = max(1, min($limit, 25));

        $target = MediaVault::query()->writable()->where('id', '!=', $vault->id)
            ->orderByDesc('priority')->first();

        $moved = 0;
        $failed = 0;

        foreach ($vault->files()->limit($limit)->get() as $file) {
            $this->vaults->moveTo($file, $target) ? $moved++ : $failed++;
        }

        $remaining = $vault->files()->count();
        $destination = $target?->name ?? 'local disk';

        return response()->json([
            'success' => true,
            'moved' => $moved,
            'failed' => $failed,
            'remaining' => $remaining,
            'destination' => $destination,
            'message' => $remaining === 0
                ? "Drained — everything moved to {$destination}."
                : "Moved {$moved}, {$remaining} left.",
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────── */

    /** Probe the vault and cache what we saw on the row. */
    private function refreshStatus(MediaVault $vault): array
    {
        try {
            $driver = $vault->driver();
            $result = $driver->probe();
            $usage = $result['ok'] ? $driver->usage() : ['free' => null, 'total' => null];
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => 'Configuration error: '.mb_substr($e->getMessage(), 0, 140)];
            $usage = ['free' => null, 'total' => null];
        }

        $vault->forceFill([
            'last_status' => $result['ok'] ? 'online' : 'offline',
            'last_error' => $result['ok'] ? null : mb_substr($result['message'], 0, 250),
            'last_checked_at' => now(),
            'free_bytes' => $usage['free'],
            'total_bytes' => $usage['total'],
        ])->save();

        return $result;
    }

    /**
     * Validation, with the rules the chosen driver actually needs.
     *
     * A mount vault needs a path on this box; an SMB vault needs a host and a
     * share. Both are constrained to shapes we build paths out of — no traversal,
     * no shell metacharacters, nothing that reaches a command line unexamined.
     */
    private function validated(Request $request, ?MediaVault $existing = null): array
    {
        $driver = $request->input('driver');

        $rules = [
            'name' => 'required|string|max:80',
            'driver' => ['required', Rule::in(MediaVault::DRIVERS)],
            'root_path' => 'nullable|string|max:255|regex:/^[A-Za-z0-9 _\-\/\.]+$/',
            'priority' => 'nullable|integer|min:0|max:1000',
            'enabled' => 'boolean',
            'read_only' => 'boolean',
        ];

        if ($driver === MediaVault::DRIVER_MOUNT) {
            // An absolute path on this server. Leading slash required so it can
            // never be read as relative to the app directory.
            $rules['mount_path'] = ['required', 'string', 'max:255', 'regex:/^\/[A-Za-z0-9 _\-\/\.]+$/'];
        } else {
            $rules['host'] = ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9\.\-]+$/'];
            $rules['port'] = 'nullable|integer|min:1|max:65535';
            $rules['share'] = ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9 _\-\.\$]+$/'];
            $rules['username'] = 'nullable|string|max:120';
            $rules['password'] = 'nullable|string|max:255';
            $rules['domain'] = ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9\.\-]*$/'];
        }

        $data = $request->validate($rules, [
            'mount_path.regex' => 'Give an absolute path on this server, e.g. /mnt/nas/takeone.',
            'host.regex' => 'Use a hostname or IP address only.',
        ]);

        // Normalise so the stored shape is ours, not the form's.
        $data['root_path'] = filled($data['root_path'] ?? null) ? trim($data['root_path'], '/ ') : null;
        $data['priority'] = (int) ($data['priority'] ?? 0);
        $data['enabled'] = (bool) ($data['enabled'] ?? true);
        $data['read_only'] = (bool) ($data['read_only'] ?? false);

        // Write-only credential. On an EDIT a blank or absent password means
        // "leave the one on file alone" — the form never receives the stored
        // value, so it cannot resend it, and writing null here would silently
        // wipe the credential of a working vault every time somebody renamed
        // it. On a CREATE there is nothing to preserve, so a blank stays blank.
        if ($existing !== null && blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($driver === MediaVault::DRIVER_MOUNT) {
            $data['mount_path'] = rtrim($data['mount_path'], '/');
            // Anything left over from a previous driver choice is cleared, so a
            // vault never carries stale credentials it no longer uses.
            $data += ['host' => null, 'port' => null, 'share' => null, 'username' => null, 'domain' => null];
        } else {
            $data['port'] = (int) ($data['port'] ?? 445);
            $data['mount_path'] = null;
        }

        return $data;
    }
}
