<?php

namespace App\Federations\Services;

use App\Federations\Models\Federation;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationConversation;
use App\Federations\Models\FederationStaff;
use App\Members\Models\Conversation;
use App\Members\Models\Message;
use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Takeone\Realtime\Support\Topics;

/**
 * The dashboard's chat with clubs, carried by the platform Messenger.
 *
 * One conversation per affiliated club (type 'federation'). Its participants
 * are the club's admins as they are NOW — owner plus club-admin / moderator /
 * staff roles — re-synced whenever the thread is used, so someone who stops
 * running the club stops reading the federation's correspondence. They read
 * and reply in their own Messenger, where the thread wears the federation's
 * name and logo; the federation's staff read and write from the dashboard.
 *
 * Realtime, both ways, on the channels the platform already uses: club admins
 * get the Messenger's own `messages` push (chat heads, badge, the phone's tray);
 * staff with a dashboard open get a `federation` {action:'messages'} refresh
 * signal and re-fetch what they may see.
 *
 * Only clubs ON the platform can be written to: an application has no admins
 * and no Messenger.
 */
class FederationMessages
{
    public const TYPE = 'federation';

    private const MAX_PER_THREAD = 200;

    public function __construct(private FederationDirectory $directory, private FederationRegistry $registry) {}

    /** Affiliated clubs in this viewer's tree, keyed by club key. @return Collection<string, FederationClub> */
    private function clubs(array $access): Collection
    {
        $ids = collect($this->directory->subtree($access['root']))->pluck('id');

        return FederationClub::with('club', 'federation')->whereIn('federation_id', $ids)
            ->where('status', FederationClub::STATUS_APPROVED)->get()
            ->filter(fn ($c) => $c->club)
            ->keyBy(fn ($c) => $this->registry->clubKey($c));
    }

    /** `name => club key`, for every club this viewer may write to. */
    public function clubMap(array $access): object
    {
        return (object) $this->clubs($access)->mapWithKeys(fn ($c, $key) => [$c->club->club_name => $key])->all();
    }

    /** `fed-messages`: club name => [{from: 'fed'|'club', text, ts}], oldest first. */
    public function threads(array $access): object
    {
        $clubs = $this->clubs($access);
        $links = FederationConversation::with('conversation')->whereIn('federation_club_id', $clubs->pluck('id'))->get();
        $out = [];
        foreach ($links as $link) {
            $fc = $clubs->first(fn ($c) => $c->id === $link->federation_club_id);
            if (! $fc || ! $link->conversation) {
                continue;
            }
            $this->syncAdmins($link->conversation, $fc);
            $msgs = $link->conversation->messages()->latest('id')->limit(self::MAX_PER_THREAD)->get()->reverse();
            if ($msgs->isEmpty()) {
                continue;
            }
            $ours = $this->sentForFederation($msgs->pluck('id'));
            $out[$fc->club->club_name] = $msgs->map(fn (Message $m) => [
                'from' => isset($ours[$m->id]) ? 'fed' : 'club',
                'text' => $this->text($m),
                'ts' => $m->created_at->getTimestampMs(),
            ])->values()->all();
        }

        return (object) $out;
    }

    /** A staff member writes to a club. Null when that club cannot be written to. */
    public function send(User $user, array $access, string $clubKey, string $text): ?object
    {
        $fc = $this->clubs($access)->get($clubKey);
        if (! $fc) {
            return null;
        }

        $conversation = DB::transaction(function () use ($fc) {
            $link = FederationConversation::where('federation_club_id', $fc->id)->first();
            if ($link && $link->conversation) {
                return $link->conversation;
            }
            $c = Conversation::create(['type' => self::TYPE, 'dm_key' => 'fed-'.Str::uuid()]);
            FederationConversation::updateOrCreate(['federation_club_id' => $fc->id], ['conversation_id' => $c->id]);

            return $c;
        });

        $admins = $this->syncAdmins($conversation, $fc);
        $message = $conversation->messages()->create(['sender_id' => $user->id, 'body' => $text]);
        DB::table('federation_sent_messages')->insert(['message_id' => $message->id, 'federation_id' => $fc->federation_id, 'created_at' => now(), 'updated_at' => now()]);
        $conversation->forceFill(['last_message_at' => $message->created_at])->save();

        $face = $this->face($fc);
        $payload = [
            'action' => 'new',
            'conversation_id' => $conversation->id,
            'id' => $message->id,
            'from_id' => (int) $user->id,
            'from_name' => $face['name'],
            'from_avatar' => $face['avatar'],
            'sender_name' => $face['name'],
            'body' => $text,
            'edited' => false,
            'deleted' => false,
            'kind' => null,
            'attachment' => null,
            'is_latest' => true,
            'created_at_human' => 'just now',
            'created_at' => $message->created_at->toIso8601String(),
            'time' => $message->created_at->format('g:i A'),
        ];
        $batch = array_map(fn ($uid) => ['topic' => Topics::user($uid, 'messages'), 'payload' => $payload], $admins);
        $this->publish($batch);
        $this->signalStaff($fc->federation, (int) $user->id);

        return $this->threads($access);
    }

    // ------------------------------------------------ the Messenger's side

    public function link(Conversation $conversation): ?FederationClub
    {
        return FederationConversation::with('affiliation.club', 'affiliation.federation')
            ->where('conversation_id', $conversation->id)->first()?->affiliation;
    }

    /** The thread's face in a club admin's Messenger: the federation. */
    public function partner(Conversation $conversation): array
    {
        $fc = $this->link($conversation);

        return $fc ? $this->face($fc) : ['id' => null, 'uuid' => null, 'slug' => null, 'name' => 'Federation', 'avatar' => null, 'initial' => 'F', 'blocked' => false, 'kind' => 'thread'];
    }

    /** A club admin is named; anyone else writing here writes for the federation. */
    public function senderName(Conversation $conversation, Message $message): ?string
    {
        $fc = $this->link($conversation);
        if (! $fc) {
            return null;
        }
        if ($this->sentForFederation(collect([$message->id])) !== []) {
            return $fc->federation?->name;
        }
        $u = User::find($message->sender_id);

        return $u ? ($u->full_name ?: $u->name) : null;
    }

    /** A club admin posted (or edited, or deleted) in the Messenger: tell the federation's staff. */
    public function posted(Conversation $conversation, Message $message): void
    {
        $fc = $this->link($conversation);
        if (! $fc) {
            return;
        }
        $this->syncAdmins($conversation, $fc);
        $this->signalStaff($fc->federation, (int) $message->sender_id);
    }

    // ------------------------------------------------------------ helpers

    /** Participants = the club's admins now. @return array<int, int> */
    private function syncAdmins(Conversation $conversation, FederationClub $fc): array
    {
        $ids = $fc->club->staffUserIds();
        $conversation->participants()->sync($ids);

        return $ids;
    }

    /** Of these message ids, the ones sent from the dashboard. @return array<int, true> */
    private function sentForFederation(Collection $ids): array
    {
        return DB::table('federation_sent_messages')->whereIn('message_id', $ids)->pluck('message_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    private function face(FederationClub $fc): array
    {
        $f = $fc->federation;
        $name = $f?->name ?: 'Federation';

        return [
            'id' => null, 'uuid' => null, 'slug' => null,
            'name' => $name,
            'avatar' => $f && $f->logo && $f->isApproved() ? route('federations.logo', $f->uuid) : null,
            'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
            'blocked' => false,
            'kind' => 'thread',
        ];
    }

    private function text(Message $m): string
    {
        if ($m->isDeleted()) {
            return 'This message was deleted';
        }
        if ($m->attachment_kind !== null) {
            return '📎 '.($m->attachment_name ?: 'Attachment');
        }

        return (string) $m->body;
    }

    /** Everyone who runs this federation's dashboard: its owners and staff, and theirs above it. */
    private function staffIds(?Federation $f): array
    {
        $ids = [];
        $guard = 0;
        while ($f && $guard++ < 10) {
            $ids[] = (int) $f->owner_user_id;
            $ids = array_merge($ids, FederationStaff::where('federation_id', $f->id)->pluck('user_id')->map(fn ($i) => (int) $i)->all());
            $f = $f->parent_id ? Federation::find($f->parent_id) : null;
        }

        return array_values(array_unique($ids));
    }

    /** Refresh signal only — each dashboard re-fetches what its viewer may see. */
    private function signalStaff(?Federation $f, int $except): void
    {
        $batch = [];
        foreach ($this->staffIds($f) as $uid) {
            if ($uid !== $except) {
                $batch[] = ['topic' => Topics::user($uid, 'federation'), 'payload' => ['action' => 'messages']];
            }
        }
        $this->publish($batch);
    }

    private function publish(array $batch): void
    {
        if ($batch === []) {
            return;
        }
        try {
            \Realtime()->publishMany($batch);
        } catch (\Throwable $e) {
            report($e);   // best-effort: the database is the truth
        }
    }
}
