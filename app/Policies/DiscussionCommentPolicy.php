<?php

namespace App\Policies;

use App\Models\DiscussionComment;
use App\Models\User;

class DiscussionCommentPolicy
{
    public function view(User $user, DiscussionComment $comment): bool
    {
        return $comment->subject !== null && $user->can('view', $comment->subject);
    }

    public function create(User $user, object $subject): bool
    {
        return $user->can('view', $subject);
    }

    public function delete(User $user, DiscussionComment $comment): bool
    {
        return $comment->author_id === $user->id && $this->view($user, $comment);
    }

    public function pin(User $user, DiscussionComment $comment): bool
    {
        if (! $this->view($user, $comment)) {
            return false;
        }

        $membership = $comment->workspace->memberships()->active()->where('user_id', $user->id)->first();

        return $comment->author_id === $user->id || $membership?->role->canContribute() === true;
    }
}
