<?php

namespace App\DevBoard\Services;

use App\DevBoard\Models\Asset;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Item;
use App\DevBoard\Models\Lane;
use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TakeNote: the notebook and the action-plan board. A row is a NOTE (kind
 * note, in the notebook) or a CARD (kind card, on the board); a note can be
 * turned into a card and back (convert). A note put on the board STAYS in the
 * notebook too (in_notebook) — one row in both places, so its words never
 * drift; a card made on the board is not a note. Either way its PAGE (the
 * text, for a card its description) is its first note item, and its title
 * follows the page's first line.
 *
 * Notes and cards share one table on purpose: converting keeps the words,
 * pictures, comments and history exactly where they are.
 */
class TakeNote
{
    /** How many notes the list carries (newest first). Two people's ideas; generous. */
    public const LIST_LIMIT = 300;

    public function __construct(private Board $board, private Notes $notes) {}

    /** Every live NOTE, newest first, with what the notebook draws — including notes that are also on the board. */
    public function list(): array
    {
        return $this->summaries(Card::query()->inNotebook()->latest('updated_at')->limit(self::LIST_LIMIT));
    }

    /** Every live CARD of the action-plan board, in board order, with what a card's front draws. */
    public function cards(): array
    {
        return $this->summaries(Card::query()->cards()->whereNull('archived_at')->orderBy('position')->limit(self::LIST_LIMIT * 2));
    }

    /** Archived cards (Trello's "Archived items"), most recently archived first. */
    public function archived(): array
    {
        return $this->summaries(Card::query()->cards()->whereNotNull('archived_at')->latest('archived_at')->limit(self::LIST_LIMIT));
    }

    private function summaries($query): array
    {
        $cards = $query
            ->with(['lane:id,uuid,name,color', 'folder:id,uuid', 'author:id,full_name', 'assignee:id,full_name', 'labels:id,uuid,name,color', 'members:id,full_name', 'watchers:users.id'])
            ->withCount(['comments', 'attachments'])
            ->get();

        $pages = $this->pages($cards->pluck('id')->all());
        $editors = User::whereIn('id', $pages->pluck('updated_by')->filter()->unique())->get(['id', 'full_name'])->keyBy('id');
        $clips = $this->clips($pages->flatMap(fn (Item $p) => NoteHtml::assets($p->body))->all());
        $steps = $this->steps($cards->where('kind', 'card')->pluck('id')->all());

        return $cards->map(fn (Card $c) => $this->summary($c, $pages->get($c->id), $editors, $clips, $steps))->values()->all();
    }

    /** The board's lanes, in order, with what the Board view draws. */
    public function lanes(): array
    {
        return $this->board->lanes()->map(fn (Lane $l) => $this->board->lanePayload($l))->values()->all();
    }

    /** One note as the list and the board draw it (no page body). */
    public function one(Card $card): array
    {
        $card->loadMissing(['lane:id,uuid,name,color', 'folder:id,uuid', 'author:id,full_name', 'assignee:id,full_name', 'labels:id,uuid,name,color', 'members:id,full_name', 'watchers:users.id'])->loadCount(['comments', 'attachments']);
        $page = $this->page($card);

        return $this->summary($card, $page, null, $this->clips(NoteHtml::assets($page?->body)), $this->steps([$card->id]));
    }

    /**
     * Put a note on the board: in a lane (between two of its notes, or at the
     * top), for somebody, by a date. Only what is given changes. The person
     * must be on the board; the neighbours must be live notes of that lane.
     *
     * @param  array{assignee?: ?int, due?: ?string}  $fields
     */
    public function place(Card $card, User $by, ?Lane $lane, ?Card $above, ?Card $below, array $fields): Card
    {
        return DB::transaction(function () use ($card, $by, $lane, $above, $below, $fields) {
            $sameSpot = $lane && $lane->id === $card->lane_id && ! $above && ! $below;
            if ($lane && ! $sameSpot) {
                if (! $above && ! $below) {
                    // a note sent to a lane goes to its top
                    $below = Card::where('lane_id', $lane->id)->whereKeyNot($card->id)->orderBy('position')->first();
                }
                $this->board->move($by, $card, $lane, $above, $below);
                $card->refresh();
            }

            $changes = [];
            $who = isset($fields['assignee']) ? (int) $fields['assignee'] : null;
            if (array_key_exists('assignee', $fields) && $who !== $card->assignee_id) {
                $changes['assignee_id'] = $who;
            }
            if (array_key_exists('due', $fields) && $fields['due'] !== $card->due_on?->toDateString()) {
                $changes['due_on'] = $fields['due'];
            }
            if (isset($fields['priority']) && $fields['priority'] !== $card->priority) {
                $changes['priority'] = $fields['priority'];
            }
            if (array_key_exists('assignee_id', $changes) && $card->kind === 'card') {
                // the one-person field and the card's members agree: the new person joins, the old one leaves
                if ($card->assignee_id) {
                    $card->members()->detach($card->assignee_id);
                }
                if ($who) {
                    $card->members()->syncWithoutDetaching([$who => ['created_at' => now()]]);
                }
            }
            if ($changes) {
                $card->forceFill($changes + ['version' => $card->version + 1])->save();
                $this->board->log($card, $by, 'planned', ['fields' => array_keys($changes)]);
            }

            return $card->fresh();
        });
    }

    /** One note, ready for the editor. */
    public function open(Card $card): array
    {
        $card->loadMissing(['lane:id,uuid,name,color', 'folder:id,uuid', 'author:id,full_name', 'labels:id,uuid,name,color', 'members:id,full_name', 'watchers:users.id'])->loadCount(['comments', 'attachments']);
        $page = $this->page($card);

        $clips = $this->clips(NoteHtml::assets($page?->body));

        return $this->summary($card, $page, null, $clips, $this->steps([$card->id])) + [
            // A card made on the board may have no page yet: the editor starts
            // from its title. Escaped here; it is the server writing the HTML.
            'html' => $page?->body ?? '<h1>'.e($card->title).'</h1>',
            'version' => $page?->version ?? 0,
            'media' => $clips->filter(fn (Asset $a) => $a->kind !== 'image')->map(fn (Asset $a) => Clips::describe($a))->all(),
        ];
    }

    /**
     * A new note: a card at the top of the first lane, with its page — or, when
     * it is added from a list on the board ("Add a card"), at the bottom of that list.
     */
    public function create(User $by, string $html, ?Lane $lane = null): Card
    {
        return DB::transaction(function () use ($by, $html, $lane) {
            /** @var Lane $inbox */
            $inbox = $lane ?? $this->board->lanes()->first();
            $card = $this->board->create($by, $inbox, __('devboard::messages.note_untitled'));
            // Written in the notebook: a NOTE. Added from a list on the board: a CARD.
            $card->forceFill(['kind' => $lane ? 'card' : 'note', 'in_notebook' => ! $lane])->save();
            if ($lane) {
                $last = Card::where('lane_id', $lane->id)->whereKeyNot($card->id)->max('position');
                if ($last !== null) {
                    $card->forceFill(['position' => $last + 1024])->save();
                }
            }
            $this->notes->create($card, $by, $html);
            $this->syncTitle($card);

            return $card;
        });
    }

    /**
     * A note becomes a card (it goes to the top of a list on the board — the
     * first, unless one is named — and stays in the notebook), or a card goes
     * back to being only a note. Its words, pictures, comments and history go with it.
     */
    public function convert(Card $card, User $by, string $to, ?Lane $lane = null): Card
    {
        return DB::transaction(function () use ($card, $by, $to, $lane) {
            if ($to === 'card') {
                $lane ??= $this->board->lanes()->first();
                $top = Card::cards()->where('lane_id', $lane->id)->whereKeyNot($card->id)->min('position');
                $card->forceFill(['kind' => 'card', 'in_notebook' => $card->kind === 'note' || $card->in_notebook, 'lane_id' => $lane->id, 'position' => $top === null ? 1024 : $top - 1024, 'version' => $card->version + 1])->save();
            } else {
                $card->forceFill(['kind' => 'note', 'in_notebook' => true, 'version' => $card->version + 1])->save();
            }
            $this->board->log($card, $by, 'converted', ['to' => $to, 'lane' => $to === 'card' ? $lane->name : null]);

            return $card->fresh();
        });
    }

    /**
     * Write the page. Null when $version is stale — somebody else saved first,
     * and the caller answers 409 with the note as it now stands.
     */
    public function write(Card $card, User $by, int $version, string $html): ?Card
    {
        return DB::transaction(function () use ($card, $by, $version, $html) {
            $page = $this->page($card, lock: true);

            if (($page?->version ?? 0) !== $version) {
                return null;
            }

            $page
                ? $this->notes->update($page, $by, $html)
                : $this->notes->create($card, $by, $html);

            $this->syncTitle($card);

            return $card;
        });
    }

    /** The card's first live note item — its page. */
    public function page(Card $card, bool $lock = false): ?Item
    {
        $q = $card->items()->where('type', 'note')->orderBy('position');
        if ($lock) {
            $q->lockForUpdate();
        }

        return $q->first();
    }

    /** The card's title follows the page's first line, and the card moves to the top of "recent". */
    private function syncTitle(Card $card): void
    {
        $body = $this->page($card)?->body;
        [$title] = NoteHtml::parts($body);
        $title = mb_substr(trim((string) $title), 0, (int) config('devboard.limits.title', 200)) ?: $this->untitled($card, $body);

        if ($title !== $card->title) {
            $card->forceFill(['title' => $title, 'version' => $card->version + 1])->save();
        } else {
            $card->touch();
        }
    }

    /**
     * A note with no words yet is named for what it holds and when it was
     * made — "Voice note · 27 Oct 11:00" — so a list of recordings is not a
     * list of "Untitled". A name it already earned this way is kept.
     */
    private function untitled(Card $card, ?string $body): string
    {
        $lead = NoteHtml::kinds($body)['lead'];
        if ($lead === 'image' && self::isSketch(Asset::where('uuid', NoteHtml::pictures($body)[0] ?? '')->first())) {
            $lead = 'sketch';
        }
        $label = match ($lead) {
            'sketch' => __('devboard::messages.tn_drawing'),
            'audio' => __('devboard::messages.tn_voice_note'),
            'video' => __('devboard::messages.tn_video_note'),
            'image' => __('devboard::messages.tn_photo'),
            default => null,
        };
        if (! $label) {
            return __('devboard::messages.note_untitled');
        }
        if (str_starts_with($card->title, $label.' · ')) {
            return $card->title;
        }

        return $label.' · '.$card->created_at->timezone(config('app.timezone'))->format('j M H:i');
    }

    /** @return Collection<int,Item> card id → its page */
    private function pages(array $cardIds): Collection
    {
        if (! $cardIds) {
            return collect();
        }

        return Item::query()
            ->whereIn('card_id', $cardIds)
            ->where('type', 'note')
            ->orderBy('position')
            ->get(['id', 'uuid', 'card_id', 'body', 'version', 'updated_by', 'updated_at'])
            ->unique('card_id')
            ->keyBy('card_id');
    }

    /** @return Collection<int,array{0:int,1:int}> card id → [checklist items done, all of them] */
    private function steps(array $cardIds): Collection
    {
        if (! $cardIds) {
            return collect();
        }

        return DB::table('devboard_steps as s')
            ->join('devboard_items as i', 'i.id', '=', 's.item_id')
            ->whereIn('i.card_id', $cardIds)->where('i.type', 'checklist')->whereNull('i.deleted_at')
            ->groupBy('i.card_id')
            ->selectRaw('i.card_id, count(*) as total, sum(case when s.done_at is not null then 1 else 0 end) as done')
            ->get()->mapWithKeys(fn ($r) => [(int) $r->card_id => [(int) $r->done, (int) $r->total]]);
    }

    /** @return Collection<string,Asset> uuid → the things these notes hold (recordings, pictures, drawings) */
    private function clips(array $uuids): Collection
    {
        return $uuids ? Asset::whereIn('uuid', array_unique($uuids))->get()->keyBy('uuid') : collect();
    }

    /** Is this picture a drawing on a blank page? */
    private static function isSketch(?Asset $a): bool
    {
        return $a && $a->kind === 'image' && (($a->meta['ink']['kind'] ?? null) === 'sketch');
    }

    private function summary(Card $card, ?Item $page, ?Collection $editors = null, ?Collection $clips = null, ?Collection $steps = null): array
    {
        [, $excerpt] = NoteHtml::parts($page?->body, 160);
        $pictures = NoteHtml::pictures($page?->body);
        $kinds = NoteHtml::kinds($page?->body);
        $mine = collect(NoteHtml::assets($page?->body))->map(fn ($u) => $clips?->get($u))->filter();
        $voice = $mine->firstWhere('kind', 'audio');
        $video = $mine->first(fn (Asset $a) => $a->kind === 'video' && $a->status === 'ready' && $a->preview_path);
        $editor = $page?->updated_by ? ($editors?->get($page->updated_by) ?? User::find($page->updated_by, ['id', 'full_name'])) : null;
        $touched = max($card->updated_at?->getTimestamp() ?? 0, $page?->updated_at?->getTimestamp() ?? 0);

        return [
            'uuid' => $card->uuid,
            'kind' => $card->kind,
            'in_notebook' => $card->kind === 'note' || (bool) $card->in_notebook, // a card that is also a note
            'folder' => $card->folder?->uuid,
            'links' => NoteHtml::noteLinks($page?->body), // the notes this one links to (the map's lines, and "linked from")
            'title' => $card->title,
            'excerpt' => $excerpt,
            'cover' => $card->cover_uuid,
            // a cover is an attached picture (served by the card's file door) or one placed in its text (the picture door)
            'cover_file' => $card->cover_uuid ? \App\DevBoard\Models\Attachment::where('card_id', $card->id)->where('uuid', $card->cover_uuid)->exists() : null,
            'attachments' => (int) ($card->attachments_count ?? 0),
            'pictures' => count($pictures),
            'voice' => $kinds['audio'],
            'video' => $kinds['video'],
            // What the tile leads with: the first thing in the note, and how long it runs.
            'lead' => $kinds['lead'] === 'image' && self::isSketch($clips?->get($pictures[0] ?? '')) ? 'sketch' : ($kinds['lead'] ?? 'text'),
            'lead_ms' => in_array($kinds['lead'], ['audio', 'video'], true) ? $mine->firstWhere('kind', $kinds['lead'])?->duration_ms : null,
            'voice_ms' => $voice?->duration_ms,
            'thumb' => $pictures[0] ?? null,
            'poster' => ! $pictures && $video ? $video->uuid : null,
            'lane' => $card->lane ? ['uuid' => $card->lane->uuid, 'name' => $card->lane->name, 'color' => $card->lane->color] : null,
            'position' => $card->position,
            'priority' => $card->priority,
            'assignee' => $this->board->person($card->assignee),
            'due_on' => $card->due_on?->toDateString(),
            'author' => $this->board->person($card->author),
            'editor' => $this->board->person($editor),
            'replies' => (int) ($card->comments_count ?? 0),
            // Trello's card tools (cards only; a note has none of them)
            'labels' => $card->relationLoaded('labels') ? $card->labels->map(fn ($l) => ['uuid' => $l->uuid, 'name' => $l->name, 'color' => $l->color])->values()->all() : [],
            'members' => $card->relationLoaded('members') ? $card->members->map(fn (User $u) => $this->board->person($u))->values()->all() : [],
            'watchers' => $card->relationLoaded('watchers') ? $card->watchers->pluck('id')->map(fn ($v) => (int) $v)->values()->all() : [],
            'start_on' => $card->start_on?->toDateString(),
            'due_time' => $card->due_time,
            'reminder' => $card->reminder_minutes,
            'done' => (bool) $card->done_at,
            'pinned' => (bool) $card->pinned_at, // top of the notebook's list
            'cover_color' => $card->cover_color,
            'cover_size' => $card->cover_size,
            'steps' => $steps?->get($card->id) ?? [0, 0],
            'template' => (bool) $card->is_template,
            'archived' => (bool) $card->archived_at,
            'updated_at' => $touched ? date(DATE_ATOM, $touched) : null,
        ];
    }
}
