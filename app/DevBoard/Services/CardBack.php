<?php

namespace App\DevBoard\Services;

use App\DevBoard\Models\Activity;
use App\DevBoard\Models\Asset;
use App\DevBoard\Models\Attachment;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Comment;
use App\DevBoard\Models\Item;
use App\DevBoard\Models\Step;
use App\Members\Models\User;
use Illuminate\Support\Str;

/**
 * The open card — Trello's "card back": everything on one card in one read
 * (its page, attachments, checklists, comments and history), and the small
 * writes it offers: comments, checklists, the cover. Every value goes back as
 * text for the page to draw with textContent; only the page body is HTML, and
 * that was cleaned by NoteHtml on its way in.
 */
class CardBack
{
    public function __construct(private TakeNote $takenote, private Board $board, private Attachments $attachments, private CardTools $tools) {}

    public function detail(Card $card): array
    {
        $card->loadMissing(['attachments.author:id,full_name']);

        return $this->takenote->open($card) + [
            'attachment_list' => $card->attachments->map(fn (Attachment $a) => $this->attachments->payload($a))->values()->all(),
            'checklists' => $this->checklists($card),
            'comments' => $card->comments()->with('user:id,full_name')->get()->map(fn (Comment $c) => $this->comment($c))->values()->all(),
            'activity' => $card->activity()->with('user:id,full_name')->limit(80)->get()->map(fn (Activity $a) => [
                'action' => $a->action,
                'meta' => $this->safeMeta($a->meta),
                'by' => $this->board->person($a->user),
                'at' => $a->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /* ── comments ─────────────────────────────────────────────────────── */

    public function addComment(Card $card, User $by, string $body): Comment
    {
        $c = Comment::create(['uuid' => (string) Str::uuid(), 'card_id' => $card->id, 'user_id' => $by->id, 'body' => $this->clean($body, (int) config('devboard.limits.comment', 4000))]);
        $card->touch();

        return $c;
    }

    public function comment(Comment $c): array
    {
        $c->loadMissing('user:id,full_name');

        return [
            'uuid' => $c->uuid,
            'body' => $c->body,
            'by' => $this->board->person($c->user),
            'at' => $c->created_at?->toIso8601String(),
            'edited' => $c->updated_at && $c->created_at && $c->updated_at->gt($c->created_at->copy()->addSeconds(5)),
            'reactions' => $this->tools->reactions($c), // kind → user ids
        ];
    }

    /* ── checklists (an item of type checklist, its steps) ────────────── */

    public function checklists(Card $card): array
    {
        return $card->items()->where('type', 'checklist')->get()->map(fn (Item $i) => [
            'uuid' => $i->uuid,
            'title' => (string) $i->body,
            'steps' => Step::where('item_id', $i->id)->orderBy('position')->get()->map(fn (Step $s) => [
                'uuid' => $s->uuid, 'text' => $s->text, 'done' => (bool) $s->done_at,
                'assignee' => $s->assignee_id, 'due' => $s->due_on?->toDateString(),
            ])->values()->all(),
        ])->values()->all();
    }

    public function addChecklist(Card $card, User $by, string $title): Item
    {
        $last = $card->items()->max('position');
        $item = Item::create([
            'uuid' => (string) Str::uuid(), 'card_id' => $card->id, 'type' => 'checklist',
            'position' => ($last ?? 0) + 1024, 'body' => $this->clean($title, 120), 'created_by' => $by->id, 'updated_by' => $by->id,
        ]);
        $this->board->log($card, $by, 'checklist_added', ['title' => $item->body]);

        return $item;
    }

    public function addStep(Item $list, string $text): ?Step
    {
        if (Step::where('item_id', $list->id)->count() >= (int) config('devboard.limits.checklist_steps', 200)) {
            return null;
        }
        $last = Step::where('item_id', $list->id)->max('position');

        return Step::create(['uuid' => (string) Str::uuid(), 'item_id' => $list->id, 'text' => $this->clean($text, 500), 'position' => ($last ?? 0) + 1024]);
    }

    public function updateStep(Step $step, User $by, array $data): Step
    {
        if (array_key_exists('text', $data)) {
            $step->text = $this->clean((string) $data['text'], 500);
        }
        if (array_key_exists('done', $data)) {
            $step->done_at = $data['done'] ? ($step->done_at ?? now()) : null;
            $step->done_by = $data['done'] ? $by->id : null;
        }
        $step->save();

        return $step;
    }

    /* ── the cover: a picture attached to the card, or one placed in its text ── */

    /** False when $uuid is neither of this card's pictures. */
    public function setCover(Card $card, User $by, ?string $uuid): bool
    {
        if ($uuid !== null) {
            $isFile = Attachment::where('card_id', $card->id)->where('uuid', $uuid)->where('kind', 'file')->whereIn('ext', Attachments::IMAGES)->exists();
            $isPicture = Asset::where('card_id', $card->id)->where('uuid', $uuid)->where('kind', 'image')->exists();
            if (! $isFile && ! $isPicture) {
                return false;
            }
        }
        // a picture cover takes the place of a colour one (Trello: one cover at a time)
        $card->forceFill(['cover_uuid' => $uuid] + ($uuid ? ['cover_color' => null] : []))->save();
        $this->board->log($card, $by, $uuid ? 'cover_set' : 'cover_removed');

        return true;
    }

    private function clean(string $text, int $max): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $text) ?? '';

        return mb_substr(trim($text), 0, $max);
    }

    /** History entries carry names and lane names only — never ids, paths or anything else stored with them. */
    private function safeMeta(?array $meta): array
    {
        $out = [];
        foreach (['name', 'lane', 'from', 'to', 'title'] as $k) {
            if (isset($meta[$k]) && is_scalar($meta[$k])) {
                $out[$k] = mb_substr((string) $meta[$k], 0, 200);
            }
        }

        if (isset($meta['fields']) && is_array($meta['fields'])) {
            $out['fields'] = array_values(array_intersect($meta['fields'], ['assignee_id', 'due_on', 'priority', 'title', 'lane_id']));
        }

        return $out;
    }
}
