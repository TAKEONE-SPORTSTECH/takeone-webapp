<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Models\UserPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Member-authored posts on the personal ("/me") feed. All write endpoints
 * return the updated entity as JSON so the feed can patch the UI in place
 * (no page reload), per the project's No Page Reload rule.
 */
class UserPostController extends Controller
{
    /**
     * Dedicated page for a single post, reached by its unguessable token.
     * Any signed-in member with the link may view it (the token is the share
     * capability), except where a block exists between viewer and author.
     */
    public function show(UserPost $post): View
    {
        $viewer = Auth::user();
        $author = $post->user;

        // A hidden (moderated) post is only reachable by a super-admin.
        abort_if($post->isHidden() && ! $viewer->hasRole('super-admin'), 404);

        // A block either way hides the post entirely.
        if ($author) {
            $blocked = \App\Models\UserBlock::where(function ($q) use ($viewer, $author) {
                $q->where('blocker_id', $viewer->id)->where('blocked_id', $author->id);
            })->orWhere(function ($q) use ($viewer, $author) {
                $q->where('blocker_id', $author->id)->where('blocked_id', $viewer->id);
            })->exists();
            abort_if($blocked, 404);
        }

        $post->load([
            'user:id,slug,full_name,profile_picture,updated_at',
            'likes:id,user_post_id,user_id',
            'comments.user:id,full_name,profile_picture,updated_at',
        ])->loadCount(['likes', 'views']);

        return view('personal.post', [
            'post' => $post->toFeedArray($viewer),
            'canEdit' => $viewer->id === $post->user_id,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:text,poll,highlight'],
            'body' => ['nullable', 'string', 'max:5000'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,gif,webp', 'max:8192'],
            'poll_options' => ['nullable', 'array', 'max:6'],
            'poll_options.*' => ['nullable', 'string', 'max:120'],
            'cover_label' => ['nullable', 'string', 'max:80'],
            'cover_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'cover_icon' => ['nullable', 'string', 'max:40'],
        ]);

        $type = $data['type'] ?? 'text';
        $body = trim($data['body'] ?? '');
        $user = Auth::user();

        // A decorative gradient "highlight" banner can ride along with a post.
        $cover = null;
        if (! empty($data['cover_label'])) {
            $cover = [
                'color' => $data['cover_color'] ?? '#7c3aed',
                'icon' => $data['cover_icon'] ?? 'bi-stars',
                'label' => trim($data['cover_label']),
            ];
        }

        // ----- Poll post: question (body) + 2..6 non-empty options -----
        if ($type === 'poll') {
            $options = collect($data['poll_options'] ?? [])
                ->map(fn ($o) => trim((string) $o))
                ->filter()->values();
            if ($body === '') {
                return response()->json(['success' => false, 'message' => 'Add a poll question.'], 422);
            }
            if ($options->count() < 2) {
                return response()->json(['success' => false, 'message' => 'A poll needs at least two options.'], 422);
            }

            $post = UserPost::create([
                'user_id' => $user->id,
                'type' => 'poll',
                'body' => $body,
                'poll' => ['question' => $body, 'options' => $options->all()],
            ]);

            $post->setRelation('user', $user);
            $post->setRelation('pollVotes', collect());
            $card = $post->toFeedArray($user);

            return $this->fanOutNewPost($user, $post, $card, $body);
        }

        // ----- Text / photo / highlight post -----
        if ($type === 'highlight' && ! $cover) {
            return response()->json(['success' => false, 'message' => 'Add a title for your highlight.'], 422);
        }
        if ($body === '' && ! $request->hasFile('images') && ! $cover) {
            return response()->json(['success' => false, 'message' => 'Write something or attach a photo.'], 422);
        }

        $paths = [];
        foreach ($request->file('images', []) as $file) {
            $paths[] = $file->store('user-posts/'.$user->id, 'public');
        }

        $post = UserPost::create([
            'user_id' => $user->id,
            'type' => $cover ? 'highlight' : 'text',
            'body' => $body !== '' ? $body : null,
            'images' => $paths ?: null,
            'cover' => $cover,
        ]);

        $post->setRelation('user', $user);
        $card = $post->toFeedArray($user);

        $snippet = $body !== ''
            ? \Illuminate\Support\Str::limit($body, 60)
            : ($cover['label'] ?? (! empty($paths) ? 'shared a photo' : 'shared a new post'));

        return $this->fanOutNewPost($user, $post, $card, $snippet);
    }

    /**
     * Live-deliver a freshly created post: MQTT push to followers (Following+All)
     * and club-mates (All), plus a "shared a new post" notification to each.
     * Shared by the text/photo and poll paths of store().
     */
    private function fanOutNewPost($user, UserPost $post, array $card, string $snippet): JsonResponse
    {
        // Who should see this post live, and in which feed:
        //  • followers   → their "Following" AND "All" tabs
        //  • club-mates  → their "All" tab (the All feed surfaces club-mates)
        // minus anyone blocked either way, and never the author themselves.
        $followerIds = $this->followerIds($user->id);
        $clubIds = $user->memberClubs()->pluck('tenants.id');
        $clubMateIds = $clubIds->isEmpty()
            ? collect()
            : \Illuminate\Support\Facades\DB::table('memberships')
                ->whereIn('tenant_id', $clubIds)
                ->where('user_id', '!=', $user->id)
                ->distinct()->pluck('user_id');
        $blockedIds = \App\Models\UserBlock::where('blocker_id', $user->id)->pluck('blocked_id')
            ->merge(\App\Models\UserBlock::where('blocked_id', $user->id)->pluck('blocker_id'))
            ->map(fn ($id) => (int) $id);

        $allowed = fn ($id) => (int) $id !== (int) $user->id && ! $blockedIds->contains((int) $id);
        $followers = $followerIds->filter($allowed)->unique()->values();
        $clubOnly = $clubMateIds->filter(fn ($id) => $allowed($id) && ! $followerIds->contains($id))
            ->unique()->values();

        // Live feed push (MQTT) — tag the feeds each recipient should patch.
        $followerCard = $card;
        $followerCard['author']['isMe'] = false;
        $this->broadcastPost($followers, ['action' => 'new', 'feeds' => ['following', 'all'], 'post' => $followerCard]);
        $this->broadcastPost($clubOnly, ['action' => 'new', 'feeds' => ['all'], 'post' => $followerCard]);

        foreach ($followers->merge($clubOnly)->unique() as $recipientId) {
            UserNotification::notifyUser((int) $recipientId, 'post', $user->full_name.' shared a new post', [
                'actor_id' => $user->id,
                // Deep-link straight to the post's own page (not the author's
                // whole wall) so tapping the bell opens exactly this post.
                'action_url' => $post->permalink(),
                'icon' => 'bi-postcard-heart',
                'body' => $snippet,
                'subject_type' => 'post',
                'subject_id' => $post->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Post shared',
            'post' => $card,
        ]);
    }

    /**
     * Cast (or change) the viewer's vote on a poll post, then return the live
     * tallies. One vote per member; re-voting the same option is a no-op.
     */
    public function vote(Request $request, UserPost $post): JsonResponse
    {
        abort_unless($post->type === 'poll' && ! empty($post->poll['options']), 404);
        abort_unless(Auth::user()->can('interact', $post), 403);

        $optionCount = count($post->poll['options']);
        $data = $request->validate([
            'option' => ['required', 'integer', 'min:0', 'max:'.($optionCount - 1)],
        ]);

        \App\Models\UserPostPollVote::updateOrCreate(
            ['user_post_id' => $post->id, 'user_id' => Auth::id()],
            ['option' => $data['option']],
        );

        $poll = $post->load('pollVotes')->pollFeedData(Auth::user());

        // Live: update everyone who can see this post (author + followers). Send
        // shared tallies only — each client keeps its own myVote.
        $shared = $poll;
        $shared['myVote'] = null;
        $this->broadcastPost(
            $this->followerIds($post->user_id)->push($post->user_id)->reject(fn ($id) => (int) $id === Auth::id()),
            ['action' => 'poll', 'post_id' => $post->id, 'poll' => $shared]
        );

        return response()->json(['success' => true, 'poll' => $poll]);
    }

    public function update(Request $request, UserPost $post): JsonResponse
    {
        $this->authorizeOwner($post);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        $body = trim($data['body'] ?? '');
        if ($body === '' && empty($post->images)) {
            return response()->json(['success' => false, 'message' => 'Post cannot be empty.'], 422);
        }

        $post->update(['body' => $body !== '' ? $body : null]);

        $post->load('comments.user')->loadCount('likes');

        // Live: patch the edited body for the author's followers.
        $this->broadcastPost(
            $this->followerIds($post->user_id),
            ['action' => 'edit', 'post_id' => $post->id, 'body' => (string) $post->body]
        );

        return response()->json([
            'success' => true,
            'message' => 'Post updated',
            'post' => $post->toFeedArray(Auth::user()),
        ]);
    }

    public function destroy(UserPost $post): JsonResponse
    {
        // The author may delete their own post; a super-admin may remove ANY post
        // (moderation) — it is force-hidden from every feed via the fan-out below.
        abort_unless($post->user_id === Auth::id() || Auth::user()->hasRole('super-admin'), 403);

        $authorId = $post->user_id;
        $postId = $post->id;
        $byAdmin = $authorId !== Auth::id();

        // Everyone who could have this post in a feed: followers + club-mates
        // (the "All" feed shows club-mates' posts) + the author themselves, so
        // every open tab/device drops it live too.
        $audience = $this->postAudience($authorId);

        // Remove (and live-clear) every notification raised for this post —
        // the "shared a new post" bells on followers, plus like/comment bells.
        UserNotification::removeForSubject('post', $postId);

        $post->deleteImageFiles();
        $post->delete();

        // Live: remove the post from every viewer's feed/wall instantly (MQTT).
        $this->broadcastPost($audience, ['action' => 'delete', 'post_id' => $postId]);

        // A moderation delete (super-admin removing someone else's post) tells the
        // author their post was taken down.
        if ($byAdmin) {
            UserNotification::notifyUser($authorId, 'moderation', __('Your post was removed by a moderator'), [
                'actor_id' => Auth::id(),
                'icon' => 'bi-trash',
                'body' => \Illuminate\Support\Str::limit((string) $post->body, 60),
                'subject_type' => 'post',
                'subject_id' => $postId,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Post deleted']);
    }

    /**
     * Super-admin moderation: hide a post from public view without deleting it.
     * It stays in the DB (still visible to super-admins, flagged) so it can be
     * reviewed and, if it breaks no rules, unhidden. The author is notified.
     */
    public function hide(UserPost $post): JsonResponse
    {
        abort_unless(Auth::user()->hasRole('super-admin'), 403);

        if (! $post->isHidden()) {
            $post->update(['hidden_at' => now(), 'hidden_by' => Auth::id()]);
        }

        // Live: everyone but super-admins drops it from their feed; super-admins
        // keep it, flagged. One payload — each client decides by its own role.
        $this->broadcastPost($this->postAudience($post->user_id), [
            'action' => 'hide',
            'post_id' => $post->id,
        ]);

        if ($post->user_id !== Auth::id()) {
            UserNotification::notifyUser($post->user_id, 'moderation', __('Your post was hidden by a moderator'), [
                'actor_id' => Auth::id(),
                'icon' => 'bi-eye-slash',
                'body' => \Illuminate\Support\Str::limit((string) $post->body, 60),
                'subject_type' => 'post',
                'subject_id' => $post->id,
            ]);
        }

        return response()->json(['success' => true, 'message' => __('Post hidden'), 'post_id' => $post->id]);
    }

    /** Super-admin moderation: restore a previously hidden post for everyone. */
    public function unhide(UserPost $post): JsonResponse
    {
        abort_unless(Auth::user()->hasRole('super-admin'), 403);

        if ($post->isHidden()) {
            $post->update(['hidden_at' => null, 'hidden_by' => null]);
        }

        $post->load([
            'user:id,slug,full_name,profile_picture,updated_at',
            'likes:id,user_post_id,user_id',
            'comments.user:id,full_name,profile_picture,updated_at',
            'pollVotes:id,user_post_id,user_id,option',
        ])->loadCount(['likes', 'views']);

        // Live: super-admins just clear the flag; everyone else gets the post back
        // in their feed. Send a card (author-agnostic) so recipients can re-insert.
        $card = $post->toFeedArray(Auth::user());
        $card['author']['isMe'] = false;
        $this->broadcastPost($this->postAudience($post->user_id), [
            'action' => 'unhide',
            'post_id' => $post->id,
            'post' => $card,
        ]);

        if ($post->user_id !== Auth::id()) {
            UserNotification::notifyUser($post->user_id, 'moderation', __('Your post is visible again'), [
                'actor_id' => Auth::id(),
                'action_url' => $post->permalink(),
                'icon' => 'bi-eye',
                'body' => \Illuminate\Support\Str::limit((string) $post->body, 60),
                'subject_type' => 'post',
                'subject_id' => $post->id,
            ]);
        }

        return response()->json(['success' => true, 'message' => __('Post is visible again'), 'post_id' => $post->id]);
    }

    /**
     * Everyone who could have a member's post in a feed: their followers +
     * club-mates (the "All" feed surfaces club-mates) + the author themselves.
     */
    private function postAudience(int $authorId)
    {
        $authorClubIds = \Illuminate\Support\Facades\DB::table('memberships')
            ->where('user_id', $authorId)->pluck('tenant_id');
        $clubMateIds = $authorClubIds->isEmpty()
            ? collect()
            : \Illuminate\Support\Facades\DB::table('memberships')
                ->whereIn('tenant_id', $authorClubIds)
                ->where('user_id', '!=', $authorId)
                ->distinct()->pluck('user_id');

        return $this->followerIds($authorId)
            ->merge($clubMateIds)
            ->push($authorId)
            ->unique()->values();
    }

    public function like(UserPost $post): JsonResponse
    {
        // Anyone who can see the author's wall (club-mate, follower, connection;
        // never a blocked user) may like — enforced by UserPostPolicy.
        abort_unless(Auth::user()->can('interact', $post), 403);

        $userId = Auth::id();
        $existing = $post->likes()->where('user_id', $userId)->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            $post->likes()->create(['user_id' => $userId]);
            $liked = true;
            UserNotification::notifyUser($post->user_id, 'like', Auth::user()->full_name.' liked your post', [
                'actor_id' => $userId,
                'action_url' => $post->permalink(),
                'icon' => 'bi-heart-fill',
                'subject_type' => 'post',
                'subject_id' => $post->id,
            ]);
        }

        $likes = $post->likes()->count();

        // Live: update the like count for the author + this post's followers.
        $this->broadcastPost(
            $this->followerIds($post->user_id)->push($post->user_id)->reject(fn ($id) => (int) $id === $userId),
            ['action' => 'like', 'post_id' => $post->id, 'likes' => $likes]
        );

        return response()->json([
            'success' => true,
            'liked' => $liked,
            'likes' => $likes,
        ]);
    }

    public function comment(Request $request, UserPost $post): JsonResponse
    {
        // Same wall-visibility rule as like() (UserPostPolicy::interact).
        abort_unless(Auth::user()->can('interact', $post), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment = $post->comments()->create([
            'user_id' => Auth::id(),
            'body' => trim($data['body']),
        ]);

        UserNotification::notifyUser($post->user_id, 'comment', Auth::user()->full_name.' commented on your post', [
            'actor_id' => Auth::id(),
            'action_url' => $post->permalink(),
            'icon' => 'bi-chat-fill',
            'body' => \Illuminate\Support\Str::limit(trim($data['body']), 60),
            'subject_type' => 'post',
            'subject_id' => $post->id,
        ]);

        $commentCard = $comment->load('user')->toFeedArray();

        // Live: append the comment for the author + this post's followers — but never to
        // anyone blocked-either-way with the commenter (they must never see it, live or not).
        $blockedWithCommenter = \App\Models\UserBlock::idsBlockedEitherWayWith(Auth::id());
        $this->broadcastPost(
            $this->followerIds($post->user_id)->push($post->user_id)
                ->reject(fn ($id) => (int) $id === Auth::id() || $blockedWithCommenter->contains((int) $id)),
            ['action' => 'comment', 'post_id' => $post->id, 'comment' => $commentCard]
        );

        return response()->json([
            'success' => true,
            'comment' => $commentCard,
            'commentsCount' => $post->comments()->count(),
        ]);
    }

    /**
     * Record that the current user has viewed this post (once per user) and
     * return the live view count. The author's own views never count.
     */
    public function view(UserPost $post): JsonResponse
    {
        $viewer = Auth::user();

        // Only people allowed to see the post (club-mate / follower / connection,
        // never blocked) register a view — same rule as like/comment.
        abort_unless($viewer->can('interact', $post), 403);

        if ($post->user_id !== $viewer->id) {
            \App\Models\UserPostView::firstOrCreate([
                'user_post_id' => $post->id,
                'user_id' => $viewer->id,
            ]);
        }

        $views = $post->views()->count();

        // Live: tick the count up on the owner's own card/page.
        $this->broadcastPost(
            collect([$post->user_id])->reject(fn ($id) => (int) $id === $viewer->id),
            ['action' => 'view', 'post_id' => $post->id, 'views' => $views]
        );

        return response()->json(['success' => true, 'views' => $views]);
    }

    /**
     * The list of people who viewed this post — owner only ("seen by").
     */
    public function viewers(UserPost $post): JsonResponse
    {
        $this->authorizeOwner($post);

        return response()->json([
            'success' => true,
            'people' => $this->peopleFrom($post->views()),
        ]);
    }

    /**
     * The list of people who liked this post — visible to anyone who can see it
     * (likes are public, like Facebook).
     */
    public function likers(UserPost $post): JsonResponse
    {
        abort_unless(Auth::user()->can('interact', $post), 403);

        return response()->json([
            'success' => true,
            'people' => $this->peopleFrom($post->likes()),
        ]);
    }

    /** Shape a views/likes relation into a people list for the modal. */
    private function peopleFrom($relation): \Illuminate\Support\Collection
    {
        return $relation
            ->with('user:id,slug,full_name,profile_picture,updated_at')
            ->latest()
            ->get()
            ->map(fn ($row) => [
                'id' => $row->user_id,
                'name' => $row->user?->full_name ?? 'Member',
                'avatar' => $row->user && $row->user->profile_picture
                    ? asset('storage/'.$row->user->profile_picture).'?v='.optional($row->user->updated_at)->timestamp
                    : null,
                'url' => $row->user ? route('wall.show', $row->user) : '#',
                'time' => $row->created_at->diffForHumans(),
            ])->values();
    }

    private function authorizeOwner(UserPost $post): void
    {
        abort_unless($post->user_id === Auth::id(), 403);
    }

    /** Best-effort live fan-out of a post event to a set of users' "posts" channel. */
    private function broadcastPost($userIds, array $payload): void
    {
        try {
            if (! (function_exists('Realtime') && Realtime()->enabled())) {
                return;
            }
            $batch = collect($userIds)->map(fn ($id) => (int) $id)->unique()->values()
                ->map(fn ($uid) => ['topic' => Realtime()->userTopic($uid, 'posts'), 'payload' => $payload])
                ->all();
            if ($batch) {
                Realtime()->publishMany($batch);
            }
        } catch (\Throwable $e) {
            // Realtime is best-effort; the DB is the source of truth.
        }
    }

    /** IDs of everyone who follows this post's author (their Following feed shows it). */
    private function followerIds(int $authorId)
    {
        return \App\Models\UserFollow::where('followee_id', $authorId)->pluck('follower_id');
    }
}
