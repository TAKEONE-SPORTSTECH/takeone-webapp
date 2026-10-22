<?php

namespace App\Duplicates\Controllers;

use App\Duplicates\Models\DuplicateFinding;
use App\Duplicates\Support\DuplicateFixer;
use App\Duplicates\Support\FindingPresenter;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The super-admin's view of the duplicate watch: what it found, what it fixed
 * by itself, and the decisions left to a person. Super-admin only — a finding
 * names people and clubs across every tenant. Every write re-checks what the
 * page offers, patches the page in place, and nudges any other open copy of
 * the page with a refresh signal (never the data itself).
 */
class DuplicatesController extends Controller
{
    /** Resolved findings shown beside the open ones. */
    private const RECENT = 150;

    public function __construct(private FindingPresenter $presenter, private DuplicateFixer $fixer) {}

    public function index(Request $request)
    {
        $this->guard($request);
        $mobile = $request->attributes->get('is_mobile') && view()->exists('duplicates::mobile.index');

        return view($mobile ? 'duplicates::mobile.index' : 'duplicates::desktop.index', $this->payload());
    }

    public function list(Request $request): JsonResponse
    {
        $this->guard($request);

        return response()->json(['success' => true] + $this->payload());
    }

    public function dismiss(Request $request, DuplicateFinding $finding): JsonResponse
    {
        $by = $this->guard($request);
        if (! $finding->isOpen()) {
            return $this->answer(false, __('duplicates::messages.not_open'), $finding, 422);
        }
        $this->fixer->dismiss($finding, $by);
        $this->nudge($by);

        return $this->answer(true, __('duplicates::messages.dismissed'), $finding->fresh());
    }

    public function fix(Request $request, DuplicateFinding $finding): JsonResponse
    {
        $by = $this->guard($request);
        $r = $this->fixer->fix($finding, $by, confirmedByPerson: true);
        if (! $r['ok']) {
            return $this->answer(false, __('duplicates::messages.not_fixed', ['why' => $r['message']]), $finding->fresh(), 422);
        }
        $this->nudge($by);

        return $this->answer(true, __('duplicates::messages.fixed'), $finding->fresh());
    }

    private function payload(): array
    {
        $open = DuplicateFinding::where('status', DuplicateFinding::OPEN)
            ->orderByRaw("case strength when 'certain' then 0 else 1 end")->orderByDesc('id')->limit(500)->get();
        $recent = DuplicateFinding::where('status', '!=', DuplicateFinding::OPEN)
            ->orderByDesc('resolved_at')->orderByDesc('id')->limit(self::RECENT)->get();

        return [
            'findings' => $open->concat($recent)->map(fn ($f) => $this->presenter->present($f))->values()->all(),
            'watch' => (bool) config('duplicates.watch'),
            'autofix' => (bool) config('duplicates.autofix'),
            'lastRun' => DuplicateFinding::max('last_seen_at'),
        ];
    }

    private function answer(bool $ok, string $message, DuplicateFinding $f, int $status = 200): JsonResponse
    {
        return response()->json(['success' => $ok, 'message' => $message, 'finding' => $this->presenter->present($f)], $ok ? 200 : $status);
    }

    /** Deny by default: the route carries role:super-admin, and this says it again. */
    private function guard(Request $request): User
    {
        $u = $request->user();
        abort_unless($u && $u->isSuperAdmin(), 403);

        return $u;
    }

    /** Every OTHER super-admin's open page re-reads (a refresh signal — no data travels). */
    private function nudge(User $by): void
    {
        rescue(function () use ($by) {
            if (! function_exists('Realtime') || ! Realtime()->enabled()) {
                return;
            }
            User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->where('id', '!=', $by->id)->pluck('id')
                ->each(fn ($id) => Realtime()->publishToUser((int) $id, 'duplicates', ['action' => 'refresh']));
        }, null, report: false);
    }
}
