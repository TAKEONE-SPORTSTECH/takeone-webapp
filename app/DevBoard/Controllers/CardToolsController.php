<?php

namespace App\DevBoard\Controllers;

use App\DevBoard\Models\Card;
use App\DevBoard\Models\Comment;
use App\DevBoard\Models\Item;
use App\DevBoard\Models\Label;
use App\DevBoard\Models\Lane;
use App\DevBoard\Models\Step;
use App\DevBoard\Services\Board;
use App\DevBoard\Services\CardBack;
use App\DevBoard\Services\CardTools;
use App\DevBoard\Services\TakeNote;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Trello's card tools on TakeNote's board (Services\CardTools): labels,
 * members, watching, dates and "complete", cover style, archive, templates,
 * copy, checklist items (a person, a date, or a card of their own) and
 * comment reactions. Behind the member stack and EnsureBoardAccess; every
 * child is looked up inside the card in the address, every person must be on
 * the board, and only cards (never notes) take these tools.
 */
class CardToolsController extends Controller
{
    public function __construct(private CardTools $tools, private TakeNote $takenote, private Board $board, private CardBack $back) {}

    private function card(Card $card): Card
    {
        abort_if($card->kind !== 'card', 422);

        return $card;
    }

    private function answer(Card $card, array $more = []): JsonResponse
    {
        return response()->json(['success' => true, 'note' => $this->takenote->one($card->fresh())] + $more);
    }

    private function person(int $id): User
    {
        abort_unless(in_array($id, collect($this->board->people())->pluck('id')->map(fn ($v) => (int) $v)->all(), true), 422);

        return User::findOrFail($id);
    }

    /* ── labels ──────────────────────────────────────────────────────── */

    public function storeLabel(Request $request): JsonResponse
    {
        $d = $request->validate(['name' => ['nullable', 'string', 'max:40'], 'color' => ['required', Rule::in(Label::COLORS)]]);
        abort_if(Label::count() >= 60, 422);
        $label = $this->tools->createLabel($d['name'] ?? null, $d['color']);

        return response()->json(['success' => true, 'label' => $this->tools->label($label), 'labels' => $this->tools->labels()]);
    }

    public function updateLabel(Request $request, Label $label): JsonResponse
    {
        abort_if(\App\DevBoard\Support\Notebooks::fence() !== null, 403); // the owner's lists and labels: used in a shared folder, never changed from one
        $d = $request->validate(['name' => ['nullable', 'string', 'max:40'], 'color' => ['sometimes', Rule::in(Label::COLORS)]]);
        if (array_key_exists('name', $d)) {
            $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $d['name']) ?? '');
            $label->name = $name !== '' ? mb_substr($name, 0, 40) : null;
        }
        if (isset($d['color'])) {
            $label->color = $d['color'];
        }
        $label->save();

        return response()->json(['success' => true, 'labels' => $this->tools->labels()]);
    }

    public function destroyLabel(Label $label): JsonResponse
    {
        abort_if(\App\DevBoard\Support\Notebooks::fence() !== null, 403); // the owner's lists and labels: used in a shared folder, never changed from one
        $label->delete(); // taken off every card with it (the pivot cascades)

        return response()->json(['success' => true, 'labels' => $this->tools->labels()]);
    }

    public function toggleLabel(Request $request, Card $card): JsonResponse
    {
        $d = $request->validate(['label' => ['required', 'uuid'], 'on' => ['required', 'boolean']]);
        $this->tools->toggleLabel($this->card($card), Label::where('uuid', $d['label'])->firstOrFail(), $d['on']);

        return $this->answer($card);
    }

    /* ── people ──────────────────────────────────────────────────────── */

    public function toggleMember(Request $request, Card $card): JsonResponse
    {
        $d = $request->validate(['user' => ['required', 'integer'], 'on' => ['required', 'boolean']]);
        $this->tools->toggleMember($this->card($card), $request->user(), $this->person((int) $d['user']), $d['on']);

        return $this->answer($card);
    }

    public function watch(Request $request, Card $card): JsonResponse
    {
        $d = $request->validate(['on' => ['required', 'boolean']]);
        $this->tools->toggleWatch($this->card($card), $request->user(), $d['on']);

        return $this->answer($card);
    }

    /* ── dates and complete ──────────────────────────────────────────── */

    public function dates(Request $request, Card $card): JsonResponse
    {
        $d = $request->validate([
            'start' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
            'due' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
            'time' => ['sometimes', 'nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'reminder' => ['sometimes', 'nullable', 'integer', Rule::in([0, 5, 10, 15, 60, 120, 1440, 2880])],
        ]);
        $due = array_key_exists('due', $d) ? $d['due'] : $card->due_on?->toDateString();
        $start = array_key_exists('start', $d) ? $d['start'] : $card->start_on?->toDateString();
        abort_if($start && $due && $start > $due, 422, __('devboard::messages.ct_start_after_due'));
        $this->tools->setDates($this->card($card), $request->user(), $d);

        return $this->answer($card);
    }

    public function complete(Request $request, Card $card): JsonResponse
    {
        $d = $request->validate(['done' => ['required', 'boolean']]);
        $this->tools->complete($this->card($card), $request->user(), $d['done']);

        return $this->answer($card);
    }

    /* ── cover style, archive, template ──────────────────────────────── */

    public function coverStyle(Request $request, Card $card): JsonResponse
    {
        $d = $request->validate(['color' => ['nullable', Rule::in(Label::COLORS)], 'size' => ['nullable', Rule::in(['half', 'full'])]]);
        $this->tools->setCoverStyle($this->card($card), $d['color'] ?? null, $d['size'] ?? null);

        return $this->answer($card);
    }

    public function archive(Request $request, Card $card): JsonResponse
    {
        $d = $request->validate(['on' => ['required', 'boolean']]);
        $this->tools->archive($this->card($card), $request->user(), $d['on']);

        return $this->answer($card);
    }

    public function archived(): JsonResponse
    {
        return response()->json(['success' => true, 'cards' => $this->takenote->archived()]);
    }

    public function template(Request $request, Card $card): JsonResponse
    {
        $d = $request->validate(['on' => ['required', 'boolean']]);
        $this->card($card)->forceFill(['is_template' => $d['on']])->save();

        return $this->answer($card);
    }

    /* ── copy (and a card from a template) ───────────────────────────── */

    public function copy(Request $request, Card $card): JsonResponse
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'lane' => ['required', 'uuid'],
            'where' => ['required', Rule::in(['top', 'bottom'])],
            'keep' => ['array'], 'keep.*' => [Rule::in(['checklists', 'labels', 'members', 'attachments'])],
        ]);
        // a copy duplicates the card's pictures and files on disk: it has to fit in the notebook's 1 GB too
        $source = $this->card($card);
        \App\DevBoard\Support\Storage::guard((int) $source->notebook_id,
            (int) \Illuminate\Support\Facades\DB::table('devboard_assets')->where('card_id', $source->id)->sum('bytes')
            + (int) \Illuminate\Support\Facades\DB::table('devboard_attachments')->where('card_id', $source->id)->sum('size'));
        $copy = $this->tools->copy($source, $request->user(), $d['title'], Lane::where('uuid', $d['lane'])->firstOrFail(), $d['where'], $d['keep'] ?? []);

        return response()->json(['success' => true, 'note' => $this->takenote->one($copy)]);
    }

    /* ── checklist items: a person, a date, or a card of their own ───── */

    public function stepPlan(Request $request, Card $card, string $step): JsonResponse
    {
        $d = $request->validate(['assignee' => ['sometimes', 'nullable', 'integer'], 'due' => ['sometimes', 'nullable', 'date_format:Y-m-d']]);
        $s = $this->stepOf($card, $step);
        if (array_key_exists('assignee', $d)) {
            $s->assignee_id = $d['assignee'] ? $this->person((int) $d['assignee'])->id : null;
        }
        if (array_key_exists('due', $d)) {
            $s->due_on = $d['due'];
        }
        $s->save();

        return response()->json(['success' => true, 'checklists' => $this->back->checklists($card)]);
    }

    public function stepToCard(Request $request, Card $card, string $step): JsonResponse
    {
        $new = $this->tools->stepToCard($this->stepOf($this->card($card), $step), $card, $request->user());

        return response()->json(['success' => true, 'checklists' => $this->back->checklists($card), 'note' => $this->takenote->one($new)]);
    }

    /* ── reactions ───────────────────────────────────────────────────── */

    public function react(Request $request, Card $card, string $comment): JsonResponse
    {
        $d = $request->validate(['kind' => ['required', 'string', 'max:64', fn ($a, $v, $fail) => CardTools::reactionAllowed((string) $v) ? null : $fail('Not a reaction.')]]);
        $c = Comment::where('card_id', $card->id)->where('uuid', $comment)->firstOrFail();
        abort_unless($this->tools->react($c, $request->user(), $d['kind']), 422, __('devboard::messages.ct_react_limit'));

        return response()->json(['success' => true, 'comment' => $this->back->comment($c)]);
    }

    private function stepOf(Card $card, string $uuid): Step
    {
        return Step::where('uuid', $uuid)->whereIn('item_id', Item::where('card_id', $card->id)->where('type', 'checklist')->select('id'))->firstOrFail();
    }
}
