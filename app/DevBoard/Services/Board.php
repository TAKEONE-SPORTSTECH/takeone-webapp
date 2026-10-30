<?php

namespace App\DevBoard\Services;

use App\DevBoard\Models\Activity;
use App\DevBoard\Models\BoardMember;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Lane;
use App\DevBoard\Models\Notebook;
use App\DevBoard\Models\Step;
use App\DevBoard\Support\Notebooks;
use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The board's domain logic: lanes, cards, ordering and the activity line.
 *
 * Ordering is a fractional key. A card dropped between two others takes the
 * midpoint of their positions, so a move writes ONE row; when two neighbours
 * get too close to split again, that lane is renumbered once.
 */
class Board
{
    private const GAP = 1024.0;

    private const MIN_GAP = 1e-6;

    /** @var array<int,string>|null user id → ink */
    private ?array $inks = null;

    /** The lanes, in order — creating the default set the first time. */
    public function lanes(): Collection
    {
        if (! Lane::query()->exists()) {
            DB::transaction(function () {
                if (Lane::query()->lockForUpdate()->exists()) {
                    return;
                }
                foreach (array_values(config('devboard.default_lanes', [])) as $i => $lane) {
                    Lane::create(['name' => $lane['name'], 'color' => $lane['color'], 'position' => ($i + 1) * self::GAP]);
                }
            });
        }

        return Lane::query()->orderBy('position')->get();
    }

    /** Everything the board screen draws, scoped to what a board member may see. */
    public function payload(User $viewer): array
    {
        $lanes = $this->lanes();
        $cards = $this->cardQuery()->orderBy('position')->get();
        $steps = $this->stepCounts($cards->pluck('id')->all());

        return [
            'me' => ['id' => $viewer->id, 'name' => $this->firstName($viewer), 'ink' => $this->inkFor($viewer->id)],
            'people' => $this->people(),
            'lanes' => $lanes->map(fn (Lane $l) => $this->lanePayload($l))->values(),
            'cards' => $cards->map(fn (Card $c) => $this->cardPayload($c, $steps[$c->id] ?? null))->values(),
            'bin' => Card::onlyTrashed()->count(),
        ];
    }

    public function lanePayload(Lane $lane): array
    {
        return ['uuid' => $lane->uuid, 'name' => $lane->name, 'color' => $lane->color, 'position' => $lane->position];
    }

    public function cardPayload(Card $card, ?array $steps = null): array
    {
        $card->loadMissing(['lane:id,uuid', 'author:id,full_name', 'assignee:id,full_name']);
        $steps ??= $this->stepCounts([$card->id])[$card->id] ?? null;

        return [
            'uuid' => $card->uuid,
            'lane' => $card->lane?->uuid,
            'title' => $card->title,
            'priority' => $card->priority,
            'position' => $card->position,
            'version' => $card->version,
            'due_on' => $card->due_on?->toDateString(),
            'due_label' => $card->due_on?->format('j M'),
            'source_url' => $card->source_url,
            'author' => $card->author ? ['id' => $card->author->id, 'name' => $this->firstName($card->author), 'ink' => $this->inkFor($card->author->id)] : null,
            'assignee' => $card->assignee ? ['id' => $card->assignee->id, 'name' => $this->firstName($card->assignee), 'ink' => $this->inkFor($card->assignee->id)] : null,
            'counts' => [
                'notes' => (int) ($card->notes_count ?? 0),
                'images' => (int) ($card->images_count ?? 0),
                'sketches' => (int) ($card->sketches_count ?? 0),
                'comments' => (int) ($card->comments_count ?? 0),
                'steps' => (int) ($steps['total'] ?? 0),
                'steps_done' => (int) ($steps['done'] ?? 0),
            ],
            'created_at' => $card->created_at?->toIso8601String(),
            'deleted_at' => $card->deleted_at?->toIso8601String(),
        ];
    }

    /** A card re-read with the counts the board needs. */
    public function fresh(Card $card): Card
    {
        return $this->cardQuery()->withTrashed()->findOrFail($card->id);
    }

    public function create(User $by, Lane $lane, string $title): Card
    {
        $first = Card::where('lane_id', $lane->id)->min('position');

        $card = Card::create([
            'lane_id' => $lane->id,
            'title' => $title,
            'priority' => 'normal',
            'position' => $first === null ? self::GAP : $first - self::GAP,
            'created_by' => $by->id,
        ]);

        $this->log($card, $by, 'created', ['lane' => $lane->name]);

        return $this->fresh($card);
    }

    /**
     * Put a card in $lane between $above and $below (either may be null —
     * the top or the bottom of the lane). Both neighbours must already be in
     * that lane; the controller checks it.
     */
    public function move(User $by, Card $card, Lane $lane, ?Card $above, ?Card $below): Card
    {
        return DB::transaction(function () use ($by, $card, $lane, $above, $below) {
            $position = $this->between($lane, $card, $above, $below);
            $from = $card->lane_id;

            $card->forceFill(['lane_id' => $lane->id, 'position' => $position])->save();

            if ($from !== $lane->id) {
                $this->log($card, $by, 'moved', [
                    'from' => Lane::whereKey($from)->value('name'),
                    'to' => $lane->name,
                ]);
            }

            return $this->fresh($card);
        });
    }

    public function moveLane(Lane $lane, ?Lane $before, ?Lane $after): Lane
    {
        $a = $before?->position;
        $b = $after?->position;

        $lane->position = match (true) {
            $a === null && $b === null => self::GAP,
            $a === null => $b - self::GAP,
            $b === null => $a + self::GAP,
            default => ($a + $b) / 2,
        };
        $lane->save();

        return $lane;
    }

    public function log(Card $card, ?User $by, string $action, array $meta = []): void
    {
        Activity::create([
            'card_id' => $card->id,
            'user_id' => $by?->id,
            'action' => $action,
            'meta' => $meta ?: null,
        ]);
    }

    public function inkFor(int $userId): string
    {
        $this->inks ??= BoardMember::pluck('ink', 'user_id')->all();

        return $this->inks[$userId] ?? config('devboard.inks.0', '#8b7cf6');
    }

    /**
     * The people of the notebook in use: on the main one, listed members plus every super-admin (as
     * always); on a friend's, that friend alone — nobody can be added to, assigned or told about a
     * card in a notebook they cannot open.
     */
    public function people(): array
    {
        $book = Notebooks::currentId() ? Notebook::find(Notebooks::currentId()) : null;
        $ids = $book && ! $book->is_main
            ? collect([$book->owner_id])->filter()
            : BoardMember::pluck('user_id')
                ->merge(User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->pluck('id'))
                ->merge(array_filter([Notebooks::main()?->owner_id]))
                ->unique();

        // whoever this notebook shares a folder with works in it too: their names on what they write, mentions,
        // card members — inside a shared folder, only that folder's people (Support\Notebooks)
        if ($book && Notebooks::sharingOn()) {
            $share = Notebooks::share();
            $ids = collect($ids)->merge(
                \App\DevBoard\Models\FolderShare::live()->where('notebook_id', $book->id)->whereNotNull('user_id')
                    ->when($share, fn ($q) => $q->where('folder_id', $share->folder_id))->pluck('user_id')
            )->merge($share ? [$book->owner_id] : [])->filter()->unique();
        }

        return User::whereIn('id', $ids)->get(['id', 'full_name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $this->firstName($u), 'ink' => $this->inkFor($u->id)])
            ->values()->all();
    }

    private function between(Lane $lane, Card $card, ?Card $above, ?Card $below): float
    {
        $a = $above?->position;
        $b = $below?->position;

        if ($a !== null && $b !== null && ($b - $a) < self::MIN_GAP) {
            $this->rebalance($lane, $card);
            $a = $above->fresh()->position;
            $b = $below->fresh()->position;
        }

        return match (true) {
            $a === null && $b === null => self::GAP,
            $a === null => $b - self::GAP,
            $b === null => $a + self::GAP,
            default => ($a + $b) / 2,
        };
    }

    /** Renumber a lane at even gaps — only when two neighbours can no longer be split. */
    private function rebalance(Lane $lane, Card $except): void
    {
        $i = 1;
        Card::withTrashed()->where('lane_id', $lane->id)->whereKeyNot($except->id)
            ->orderBy('position')->get(['id'])
            ->each(function (Card $c) use (&$i) {
                Card::withTrashed()->whereKey($c->id)->update(['position' => $i++ * self::GAP]);
            });
    }

    private function cardQuery()
    {
        return Card::query()->cards()->withCount([ // the board is cards; notes stay in the notebook
            'items as notes_count' => fn ($q) => $q->where('type', 'note'),
            'items as images_count' => fn ($q) => $q->where('type', 'image'),
            'items as sketches_count' => fn ($q) => $q->where('type', 'sketch'),
            'comments',
        ]);
    }

    /** @return array<int,array{total:int,done:int}> card id → step counts */
    private function stepCounts(array $cardIds): array
    {
        if (! $cardIds) {
            return [];
        }

        return Step::query()
            ->join('devboard_items', 'devboard_items.id', '=', 'devboard_steps.item_id')
            ->whereIn('devboard_items.card_id', $cardIds)
            ->whereNull('devboard_items.deleted_at')
            ->groupBy('devboard_items.card_id')
            ->selectRaw('devboard_items.card_id as card_id, count(*) as total, sum(case when devboard_steps.done_at is not null then 1 else 0 end) as done')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->card_id => ['total' => (int) $r->total, 'done' => (int) $r->done]])
            ->all();
    }

    /** A person as every Margin screen draws them: first name and ink. */
    public function person(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $this->firstName($user), 'ink' => $this->inkFor($user->id)] : null;
    }

    private function firstName(User $user): string
    {
        return strtok((string) $user->full_name, ' ') ?: (string) $user->full_name;
    }
}
