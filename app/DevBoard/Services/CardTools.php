<?php

namespace App\DevBoard\Services;

use App\DevBoard\Models\Asset;
use App\DevBoard\Models\Attachment;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Comment;
use App\DevBoard\Models\Item;
use App\DevBoard\Models\Label;
use App\DevBoard\Models\Lane;
use App\DevBoard\Models\Step;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Support\StoragePath;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The card tools Trello has, for the action-plan board: labels, members and
 * watchers, start/due dates with a time and a reminder, "complete", colour
 * covers, archive, templates, copy, checklist items with a person and a date
 * (or turned into cards), reactions and @mentions — and the notifications
 * they send (UserNotification::notifyUser: the row and the realtime push).
 */
class CardTools
{
    /** Reactions are drawn as icons (never emoji — the platform's rule). */
    public const REACTIONS = ['up', 'heart', 'check', 'star', 'fire', 'eyes'];

    /** How many different reactions one person may leave on one comment. */
    public const REACTIONS_EACH = 20;

    /** A reaction is one of the first six (drawn as icons) or any standard emoji (public/data/emoji — the editor's own list). */
    public static function reactionAllowed(string $kind): bool
    {
        if (in_array($kind, self::REACTIONS, true)) {
            return true;
        }
        static $emoji = null;
        if ($emoji === null) {
            $emoji = [];
            $data = json_decode((string) @file_get_contents(public_path('data/emoji/emoji.json')), true);
            foreach ($data['groups'] ?? [] as $g) {
                foreach ($g['e'] ?? [] as $e) {
                    $emoji[$e[0]] = true;
                    foreach ($e[2] ?? [] as $tone) {
                        $emoji[$tone] = true;
                    }
                }
            }
        }

        return isset($emoji[$kind]);
    }

    public function __construct(private Board $board) {}

    /* ── labels ──────────────────────────────────────────────────────── */

    public function labels(): array
    {
        return Label::orderBy('position')->get()->map(fn (Label $l) => $this->label($l))->values()->all();
    }

    public function label(Label $l): array
    {
        return ['uuid' => $l->uuid, 'name' => $l->name, 'color' => $l->color];
    }

    public function createLabel(?string $name, string $color): Label
    {
        return Label::create(['uuid' => (string) Str::uuid(), 'name' => $this->clean($name, 40) ?: null, 'color' => $color, 'position' => (Label::max('position') ?? 0) + 1024]);
    }

    public function toggleLabel(Card $card, Label $label, bool $on): void
    {
        $on ? $card->labels()->syncWithoutDetaching([$label->id]) : $card->labels()->detach($label->id);
        $card->touch();
    }

    /* ── members and watchers ────────────────────────────────────────── */

    public function toggleMember(Card $card, User $by, User $who, bool $on): void
    {
        if ($on) {
            $card->members()->syncWithoutDetaching([$who->id => ['created_at' => now()]]);
            $this->notify($who->id, $by, 'devboard_member', __('devboard::messages.ct_n_added', ['name' => $this->board->person($by)['name'] ?? '', 'card' => $card->title]), $card);
        } else {
            $card->members()->detach($who->id);
        }
        // the board's older one-person field follows the first member
        $first = $card->members()->first();
        $card->forceFill(['assignee_id' => $first?->id])->save();
    }

    public function toggleWatch(Card $card, User $who, bool $on): void
    {
        $on ? $card->watchers()->syncWithoutDetaching([$who->id]) : $card->watchers()->detach($who->id);
    }

    /* ── dates and "complete" ────────────────────────────────────────── */

    /** @param array{start?: ?string, due?: ?string, time?: ?string, reminder?: ?int} $d */
    public function setDates(Card $card, User $by, array $d): void
    {
        $changes = [];
        foreach (['start' => 'start_on', 'due' => 'due_on', 'time' => 'due_time', 'reminder' => 'reminder_minutes'] as $k => $col) {
            if (array_key_exists($k, $d)) {
                $changes[$col] = $d[$k];
            }
        }
        if (array_key_exists('due_on', $changes) || array_key_exists('due_time', $changes) || array_key_exists('reminder_minutes', $changes)) {
            $changes['reminded_at'] = null; // a new moment: remind again
        }
        $card->forceFill($changes + ['version' => $card->version + 1])->save();
        $this->board->log($card, $by, 'dates');
    }

    public function complete(Card $card, User $by, bool $done): void
    {
        $card->forceFill(['done_at' => $done ? now() : null])->save();
        $this->board->log($card, $by, $done ? 'completed' : 'reopened');
    }

    /** The due moment in the board's time (09:00 when no time was set). */
    public static function dueAt(Card $card): ?Carbon
    {
        if (! $card->due_on) {
            return null;
        }

        return Carbon::parse($card->due_on->toDateString().' '.($card->due_time ?: '09:00'), config('app.timezone'));
    }

    /* ── cover, archive ──────────────────────────────────────────────── */

    public function setCoverStyle(Card $card, ?string $color, ?string $size): void
    {
        $card->forceFill(['cover_color' => $color, 'cover_size' => $size, 'cover_uuid' => $color ? null : $card->cover_uuid])->save();
    }

    public function archive(Card $card, User $by, bool $on): void
    {
        $card->forceFill(['archived_at' => $on ? now() : null])->save();
        $this->board->log($card, $by, $on ? 'archived' : 'unarchived');
    }

    /* ── copy (also: a new card from a template) ─────────────────────── */

    /** @param  string[]  $keep  checklists | labels | members | attachments */
    public function copy(Card $card, User $by, string $title, Lane $lane, string $where, array $keep): Card
    {
        return DB::transaction(function () use ($card, $by, $title, $lane, $where, $keep) {
            $edge = $where === 'top' ? Card::where('lane_id', $lane->id)->min('position') : Card::where('lane_id', $lane->id)->max('position');
            $copy = $card->replicate(['uuid', 'version', 'archived_at', 'reminded_at', 'done_at', 'is_template', 'cover_uuid']);
            $copy->forceFill([
                'uuid' => (string) Str::uuid(), 'kind' => 'card', 'title' => $this->clean($title, 200) ?: $card->title,
                'lane_id' => $lane->id, 'position' => $edge === null ? 1024 : ($where === 'top' ? $edge - 1024 : $edge + 1024),
                'created_by' => $by->id, 'version' => 1, 'is_template' => false, 'archived_at' => null, 'done_at' => null,
            ])->save();

            // the description, with every picture and recording in it copied and re-keyed
            $map = [];
            $page = $card->items()->where('type', 'note')->orderBy('position')->first();
            if ($page) {
                $body = (string) $page->body;
                foreach (NoteHtml::assets($body) as $u) {
                    $asset = Asset::where('card_id', $card->id)->where('uuid', $u)->first();
                    if ($asset && ($new = $this->copyAsset($asset, $copy))) {
                        $map[$u] = $new->uuid;
                    }
                }
                Item::create(['uuid' => (string) Str::uuid(), 'card_id' => $copy->id, 'type' => 'note', 'position' => 1024,
                    'body' => strtr($body, $map), 'created_by' => $by->id, 'updated_by' => $by->id]);
            }
            if (in_array('checklists', $keep, true)) {
                foreach ($card->items()->where('type', 'checklist')->get() as $list) {
                    $nl = Item::create(['uuid' => (string) Str::uuid(), 'card_id' => $copy->id, 'type' => 'checklist', 'position' => $list->position, 'body' => $list->body, 'created_by' => $by->id, 'updated_by' => $by->id]);
                    foreach (Step::where('item_id', $list->id)->orderBy('position')->get() as $st) {
                        Step::create(['uuid' => (string) Str::uuid(), 'item_id' => $nl->id, 'text' => $st->text, 'position' => $st->position, 'due_on' => $st->due_on, 'assignee_id' => $st->assignee_id]);
                    }
                }
            }
            if (in_array('labels', $keep, true)) {
                $copy->labels()->sync($card->labels()->pluck('devboard_labels.id'));
            }
            if (in_array('members', $keep, true)) {
                $copy->members()->sync($card->members()->pluck('users.id')->mapWithKeys(fn ($id) => [$id => ['created_at' => now()]]));
            } else {
                $copy->forceFill(['assignee_id' => null])->save();
            }
            if (in_array('attachments', $keep, true)) {
                foreach ($card->attachments as $a) {
                    if ($n = $this->copyAttachment($a, $copy, $by)) {
                        $map[$a->uuid] = $n->uuid;
                    }
                }
            }
            if ($card->cover_uuid && isset($map[$card->cover_uuid])) {
                $copy->forceFill(['cover_uuid' => $map[$card->cover_uuid]])->save();
            }
            $this->board->log($copy, $by, 'copied', ['title' => $card->title]);

            return $copy;
        });
    }

    private function copyAsset(Asset $a, Card $to): ?Asset
    {
        $uuid = (string) Str::uuid();
        $dir = StoragePath::platform('devboard/cards/'.$to->uuid);
        $disk = Storage::disk('local');
        $paths = ['path' => null, 'preview_path' => null, 'source_path' => null];
        foreach (array_keys($paths) as $col) {
            if ($a->{$col} && $disk->exists($a->{$col})) {
                $suffix = $col === 'preview_path' ? '-preview' : ($col === 'source_path' ? '-source' : '');
                $paths[$col] = StoragePath::file($dir, $uuid.$suffix, pathinfo($a->{$col}, PATHINFO_EXTENSION));
                $disk->copy($a->{$col}, $paths[$col]);
            }
        }
        if (! $paths['path']) {
            return null;
        }
        $copy = $a->replicate(['uuid', 'card_id', 'path', 'preview_path', 'source_path']);
        $copy->forceFill(['uuid' => $uuid, 'card_id' => $to->id] + $paths)->save();

        return $copy;
    }

    private function copyAttachment(Attachment $a, Card $to, User $by): ?Attachment
    {
        $uuid = (string) Str::uuid();
        $path = null;
        if ($a->kind === 'file') {
            if (! $a->path || ! Storage::disk('local')->exists($a->path)) {
                return null;
            }
            $path = StoragePath::file(StoragePath::platform('devboard/cards/'.$to->uuid.'/files'), $uuid, (string) $a->ext);
            Storage::disk('local')->copy($a->path, $path);
        }

        return Attachment::create(['uuid' => $uuid, 'card_id' => $to->id, 'kind' => $a->kind, 'name' => $a->name, 'url' => $a->url,
            'path' => $path, 'ext' => $a->ext, 'mime' => $a->mime, 'size' => $a->size, 'created_by' => $by->id]);
    }

    /* ── checklist items ─────────────────────────────────────────────── */

    /** A checklist item becomes a card of its own, just under the card it came from. */
    public function stepToCard(Step $step, Card $from, User $by): Card
    {
        return DB::transaction(function () use ($step, $from, $by) {
            $card = $this->board->create($by, $from->lane, mb_substr($step->text, 0, 200));
            $card->forceFill(['kind' => 'card', 'due_on' => $step->due_on, 'position' => $from->position + 0.5])->save();
            if ($step->assignee_id) {
                $card->members()->syncWithoutDetaching([$step->assignee_id => ['created_at' => now()]]);
                $card->forceFill(['assignee_id' => $step->assignee_id])->save();
            }
            Item::create(['uuid' => (string) Str::uuid(), 'card_id' => $card->id, 'type' => 'note', 'position' => 1024,
                'body' => '<h1>'.e($step->text).'</h1>', 'created_by' => $by->id, 'updated_by' => $by->id]);
            $step->delete();

            return $card;
        });
    }

    /* ── comments: reactions and mentions ────────────────────────────── */

    /** A second tap takes it back. False when this person already left as many as one comment takes. */
    public function react(Comment $c, User $who, string $kind): bool
    {
        $q = DB::table('devboard_comment_reactions')->where(['comment_id' => $c->id, 'user_id' => $who->id, 'kind' => $kind]);
        if ($q->exists()) {
            $q->delete();

            return true;
        }
        if (DB::table('devboard_comment_reactions')->where(['comment_id' => $c->id, 'user_id' => $who->id])->count() >= self::REACTIONS_EACH) {
            return false;
        }
        DB::table('devboard_comment_reactions')->insert(['comment_id' => $c->id, 'user_id' => $who->id, 'kind' => $kind]);

        return true;
    }

    /** @return array<string, int[]> kind → user ids */
    public function reactions(Comment $c): array
    {
        return DB::table('devboard_comment_reactions')->where('comment_id', $c->id)->get()
            ->groupBy('kind')->map(fn ($rows) => $rows->pluck('user_id')->map(fn ($v) => (int) $v)->values()->all())->all();
    }

    /** People named in a comment hear about it; so does everyone watching the card. */
    public function commented(Card $card, User $by, Comment $c, array $mentionIds): void
    {
        $people = collect($this->board->people())->pluck('id')->map(fn ($v) => (int) $v)->all();
        $mentioned = array_values(array_intersect(array_map('intval', $mentionIds), $people));
        $who = $this->board->person($by)['name'] ?? '';
        foreach ($mentioned as $id) {
            $this->notify($id, $by, 'devboard_mention', __('devboard::messages.ct_n_mention', ['name' => $who, 'card' => $card->title]), $card, mb_substr($c->body, 0, 140));
        }
        foreach ($card->watchers()->pluck('users.id') as $id) {
            if (! in_array((int) $id, $mentioned, true)) {
                $this->notify((int) $id, $by, 'devboard_comment', __('devboard::messages.ct_n_comment', ['name' => $who, 'card' => $card->title]), $card, mb_substr($c->body, 0, 140));
            }
        }
    }

    /* ── reminders (devboard:remind, every minute) ───────────────────── */

    public function remindDue(): int
    {
        $sent = 0;
        $cards = Card::query()->cards()->whereNull('archived_at')->whereNull('done_at')->whereNull('reminded_at')
            ->whereNotNull('due_on')->whereNotNull('reminder_minutes')->with(['members:users.id', 'watchers:users.id'])->get();
        foreach ($cards as $card) {
            $due = self::dueAt($card);
            if (! $due || now()->lt($due->copy()->subMinutes($card->reminder_minutes))) {
                continue;
            }
            $ids = $card->members->pluck('id')->merge($card->watchers->pluck('id'))->unique();
            foreach ($ids as $id) {
                $this->notify((int) $id, null, 'devboard_reminder', __('devboard::messages.ct_n_reminder', ['card' => $card->title, 'when' => $due->format('D j M, H:i')]), $card);
                $sent++;
            }
            $card->forceFill(['reminded_at' => now()])->save();
        }

        return $sent;
    }

    private function notify(int $userId, ?User $by, string $type, string $title, Card $card, ?string $body = null): void
    {
        UserNotification::notifyUser($userId, $type, $title, [
            'actor_id' => $by?->id,
            'body' => $body,
            'icon' => 'bi-kanban',
            'action_url' => route('takenote.app').'?card='.$card->uuid,
        ]);
    }

    private function clean(?string $s, int $max): string
    {
        return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $s) ?? ''), 0, $max);
    }
}
