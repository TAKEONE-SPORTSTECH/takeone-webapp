<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserPost;

class UserPostPolicy
{
    /** View a post = can view the author's wall (club-mate, follower, connection; not blocked). */
    public function view(User $user, UserPost $post): bool
    {
        return $user->canViewWall($post->user);
    }

    /** Like & comment require the same visibility as viewing. */
    public function interact(User $user, UserPost $post): bool
    {
        return $user->canViewWall($post->user);
    }

    public function update(User $user, UserPost $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function delete(User $user, UserPost $post): bool
    {
        return $user->id === $post->user_id;
    }
}
