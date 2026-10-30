<?php

namespace App\DevBoard\Controllers;

use App\DevBoard\Models\Card;
use App\DevBoard\Models\Lane;
use App\DevBoard\Services\Board;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The board screen and its card/lane writes. Every route is behind
 * EnsureBoardAccess; every write answers with the record it changed, so the
 * screen patches itself in place (No-Reload rule).
 */
class BoardController extends Controller
{
    public function __construct(private Board $board) {}

    public function show(Request $request)
    {
        // One screen for both until the desktop board (phase 7) is built.
        return view('devboard::mobile.board', [
            'payload' => $this->board->payload($request->user()),
            'backUrl' => url('/me'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json(['success' => true] + $this->board->payload($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lane' => ['required', 'uuid', Rule::exists('devboard_lanes', 'uuid')],
            'title' => ['required', 'string', 'max:'.config('devboard.limits.title')],
        ]);

        $title = $this->clean($data['title']);
        abort_if($title === '', 422);

        $card = $this->board->create($request->user(), Lane::where('uuid', $data['lane'])->firstOrFail(), $title);

        return response()->json(['success' => true, 'message' => __('devboard::messages.saved'), 'card' => $this->board->cardPayload($card)]);
    }

    public function update(Request $request, Card $card): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'integer'],
            'title' => ['sometimes', 'required', 'string', 'max:'.config('devboard.limits.title')],
            'priority' => ['sometimes', Rule::in(Card::PRIORITIES)],
        ]);

        if ($data['version'] !== $card->version) {
            return response()->json([
                'success' => false,
                'message' => __('devboard::messages.conflict'),
                'card' => $this->board->cardPayload($this->board->fresh($card)),
            ], 409);
        }

        $changes = [];
        if (array_key_exists('title', $data)) {
            $title = $this->clean($data['title']);
            abort_if($title === '', 422);
            if ($title !== $card->title) {
                $changes['title'] = $title;
            }
        }
        if (array_key_exists('priority', $data) && $data['priority'] !== $card->priority) {
            $changes['priority'] = $data['priority'];
        }

        if ($changes) {
            $card->forceFill($changes + ['version' => $card->version + 1])->save();
            $this->board->log($card, $request->user(), 'updated', ['fields' => array_keys($changes)]);
        }

        return response()->json(['success' => true, 'message' => __('devboard::messages.saved'), 'card' => $this->board->cardPayload($this->board->fresh($card))]);
    }

    public function move(Request $request, Card $card): JsonResponse
    {
        $data = $request->validate([
            'lane' => ['required', 'uuid'],
            'above' => ['nullable', 'uuid'],
            'below' => ['nullable', 'uuid'],
        ]);

        $lane = Lane::where('uuid', $data['lane'])->firstOrFail();
        $above = $this->neighbour($data['above'] ?? null, $lane, $card);
        $below = $this->neighbour($data['below'] ?? null, $lane, $card);

        $card = $this->board->move($request->user(), $card, $lane, $above, $below);

        return response()->json(['success' => true, 'message' => __('devboard::messages.moved', ['lane' => $lane->name]), 'card' => $this->board->cardPayload($card)]);
    }

    public function destroy(Request $request, Card $card): JsonResponse
    {
        $card->delete();
        $this->board->log($card, $request->user(), 'deleted');

        return response()->json(['success' => true, 'message' => __('devboard::messages.binned'), 'card' => ['uuid' => $card->uuid], 'bin' => Card::onlyTrashed()->count()]);
    }

    public function bin(): JsonResponse
    {
        $cards = Card::onlyTrashed()->with('lane:id,uuid,name')->latest('deleted_at')->limit(100)->get();

        return response()->json([
            'success' => true,
            'cards' => $cards->map(fn (Card $c) => $this->board->cardPayload($c) + ['lane_name' => $c->lane?->name])->values(),
        ]);
    }

    public function restore(Request $request, string $uuid): JsonResponse
    {
        $card = Card::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
        $card->restore();
        $this->board->log($card, $request->user(), 'restored');

        return response()->json(['success' => true, 'message' => __('devboard::messages.restored'), 'card' => $this->board->cardPayload($this->board->fresh($card)), 'bin' => Card::onlyTrashed()->count()]);
    }

    public function updateLane(Request $request, Lane $lane): JsonResponse
    {
        abort_if(\App\DevBoard\Support\Notebooks::fence() !== null, 403); // the owner's lists and labels: used in a shared folder, never changed from one
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        $name = $this->clean($data['name']);
        abort_if($name === '', 422);

        $lane->update(['name' => $name]);

        return response()->json(['success' => true, 'message' => __('devboard::messages.saved'), 'lane' => $this->board->lanePayload($lane)]);
    }

    public function moveLane(Request $request, Lane $lane): JsonResponse
    {
        abort_if(\App\DevBoard\Support\Notebooks::fence() !== null, 403); // the owner's lists and labels: used in a shared folder, never changed from one
        $data = $request->validate([
            'before' => ['nullable', 'uuid'],
            'after' => ['nullable', 'uuid'],
        ]);

        $before = isset($data['before']) ? Lane::where('uuid', $data['before'])->whereKeyNot($lane->id)->firstOrFail() : null;
        $after = isset($data['after']) ? Lane::where('uuid', $data['after'])->whereKeyNot($lane->id)->firstOrFail() : null;

        return response()->json(['success' => true, 'lane' => $this->board->lanePayload($this->board->moveLane($lane, $before, $after))]);
    }

    /** A drop neighbour must be a live card in the destination lane, and not the card itself. */
    private function neighbour(?string $uuid, Lane $lane, Card $card): ?Card
    {
        if (! $uuid) {
            return null;
        }

        return Card::where('uuid', $uuid)->where('lane_id', $lane->id)->whereKeyNot($card->id)->firstOrFail();
    }

    /** Collapse whitespace and strip control characters from a one-line field. */
    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value)));
    }
}
