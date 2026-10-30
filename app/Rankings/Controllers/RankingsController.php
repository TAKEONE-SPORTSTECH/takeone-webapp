<?php

namespace App\Rankings\Controllers;

use App\Federations\Models\Federation;
use App\Http\Controllers\Controller;
use App\Rankings\Support\Standings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The national rankings, for any signed-in member.
 *
 * What a ranking shows is what a podium already shows — names, clubs, medals —
 * and a face only where the member published it (CompetitorFace). Read-only:
 * nothing here writes.
 */
class RankingsController extends Controller
{
    public function __construct(private Standings $standings) {}

    public function index(Request $request)
    {
        abort_unless(config('rankings.enabled'), 404);

        $federations = $this->standings->federations();

        // One federation ranking anything: its page IS the rankings page.
        if ($federations->count() === 1) {
            return redirect()->route('me.rankings.federation', $federations->first()['federation']);
        }

        return view($this->device($request).'index', compact('federations'))
            ->with('shellTitle', __('rankings::messages.title'));
    }

    public function federation(Request $request, Federation $federation)
    {
        abort_unless(config('rankings.enabled'), 404);

        $categories = $this->standings->categories($federation);
        $mine = $this->standings->forAthlete($federation, (int) Auth::id());
        $athletes = $this->standings->federations()->firstWhere('federation.id', $federation->id)['athletes'] ?? 0;

        return view($this->device($request).'federation', [
            'federation' => $federation,
            'groups' => $categories->groupBy('group'),
            'categoryCount' => $categories->count(),
            'athletes' => $athletes,
            'mine' => $mine,
            'factor' => (int) config('rankings.default_factor', 6),
        ])->with('shellTitle', __('rankings::messages.title'));
    }

    public function category(Request $request, Federation $federation, string $category)
    {
        abort_unless(config('rankings.enabled'), 404);

        $table = $this->standings->table($federation, $category);
        abort_if($table->isEmpty(), 404);

        $meta = $this->standings->categories($federation)->firstWhere('key', $category);

        return view($this->device($request).'category', [
            'federation' => $federation,
            'category' => $meta,
            'table' => $table,
            'me' => (int) Auth::id(),
            'factor' => (int) config('rankings.default_factor', 6),
        ])->with('shellTitle', __('rankings::messages.title'));
    }

    private function device(Request $request): string
    {
        return (bool) $request->attributes->get('is_mobile') ? 'rankings::mobile.' : 'rankings::desktop.';
    }
}
