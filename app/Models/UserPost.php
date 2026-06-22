<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A post authored by a member, shown on their personal ("/me") feed.
 * Persisted text + image attachments, with likes & comments.
 */
class UserPost extends Model
{
    protected $fillable = ['user_id', 'type', 'body', 'images', 'poll', 'cover'];

    protected $casts = [
        'images' => 'array',
        'poll'   => 'array',
        'cover'  => 'array',
    ];

    /** Auto-assign an unguessable token so each post gets a shareable permalink. */
    protected static function booted(): void
    {
        static::creating(function (self $post) {
            if (empty($post->token)) {
                do {
                    $token = Str::random(22);
                } while (static::where('token', $token)->exists());
                $post->token = $token;
            }
        });
    }

    /** Shareable, non-enumerable permalink to this post's own page. */
    public function permalink(): string
    {
        return route('posts.show', $this->token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(UserPostComment::class)->oldest();
    }

    public function likes(): HasMany
    {
        return $this->hasMany(UserPostLike::class);
    }

    public function pollVotes(): HasMany
    {
        return $this->hasMany(UserPostPollVote::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(UserPostView::class);
    }

    /**
     * Poll data shaped for the feed: each option with its live vote count, the
     * total, and which option (if any) the viewer picked. Null for non-polls.
     */
    public function pollFeedData(User $viewer): ?array
    {
        if ($this->type !== 'poll' || empty($this->poll['options'])) {
            return null;
        }

        $options = array_values($this->poll['options']);

        // Tally votes per option index (works whether or not pollVotes is loaded).
        $votes = $this->relationLoaded('pollVotes')
            ? $this->pollVotes
            : $this->pollVotes()->get(['user_post_id', 'user_id', 'option']);

        $counts = array_fill(0, count($options), 0);
        $myVote = null;
        foreach ($votes as $v) {
            if (isset($counts[$v->option])) {
                $counts[$v->option]++;
            }
            if ((int) $v->user_id === $viewer->id) {
                $myVote = (int) $v->option;
            }
        }

        return [
            'question'   => $this->poll['question'] ?? '',
            'options'    => array_map(
                fn ($text, $i) => ['text' => $text, 'votes' => $counts[$i]],
                $options,
                array_keys($options)
            ),
            'totalVotes' => array_sum($counts),
            'myVote'     => $myVote,
        ];
    }

    /** Delete attachment files from storage. */
    public function deleteImageFiles(): void
    {
        foreach ($this->images ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Shape the post for the feed UI (matches the JS post object the Blade
     * x-for template expects). Pass the viewing user to resolve "liked by me".
     */
    public function toFeedArray(User $viewer): array
    {
        $author = $this->user;

        return [
            'id'            => $this->id,
            'type'          => $this->type ?? 'text',
            'poll'          => $this->pollFeedData($viewer),
            'cover'         => $this->cover,
            'url'           => $this->permalink(),
            'author'        => [
                'id'     => $this->user_id,
                'slug'   => $author?->slug,
                'name'   => $author?->full_name ?? 'Member',
                'avatar' => $author && $author->profile_picture
                    ? asset('storage/' . $author->profile_picture) . '?v=' . optional($author->updated_at)->timestamp
                    : null,
                'url'    => $author ? route('wall.show', $author) : '#',
                'isMe'   => $this->user_id === $viewer->id,
            ],
            'body'          => $this->body ?? '',
            'images'        => collect($this->images ?? [])
                ->map(fn ($path) => ['url' => asset('storage/' . $path)])
                ->values()->all(),
            'liked'         => $this->relationLoaded('likes')
                ? $this->likes->contains('user_id', $viewer->id)
                : $this->likes()->where('user_id', $viewer->id)->exists(),
            'likes'         => $this->likes_count ?? $this->likes()->count(),
            'comments'      => $this->relationLoaded('comments')
                ? $this->comments->map(fn ($c) => $c->toFeedArray())->values()->all()
                : [],
            'showComments'  => false,
            'editing'       => false,
            'draft'         => '',
            'commentDraft'  => '',
            'edited'        => $this->updated_at->gt($this->created_at),
            'time'          => $this->created_at->diffForHumans(),
            'ts'            => $this->created_at->timestamp,
            'views'         => $this->views_count ?? ($this->relationLoaded('views') ? $this->views->count() : $this->views()->count()),
        ];
    }
}
