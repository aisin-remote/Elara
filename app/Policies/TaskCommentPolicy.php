<?php

namespace App\Policies;

use App\Models\TaskComment;
use App\Models\User;

class TaskCommentPolicy
{
    public function update(User $user, TaskComment $comment): bool
    {
        return $comment->author_id === $user->id
            && app(TaskPolicy::class)->update($user, $comment->task);
    }

    public function delete(User $user, TaskComment $comment): bool
    {
        return $this->update($user, $comment)
            || app(TaskPolicy::class)->manageWorkflow($user, $comment->task->project);
    }
}
