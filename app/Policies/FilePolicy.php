<?php

namespace App\Policies;

use App\Models\ProjectFile;
use App\Models\User;
use App\Models\Workspace;

class FilePolicy
{
    /**
     * A request's attachment answers to that request, never to the workspace. Without this it
     * would fall through to WorkspacePolicy::view and every member — including other
     * requesters — could read one person's screenshots.
     */
    public function view(User $user, ProjectFile $file): bool
    {
        if ($file->attachable) {
            return $user->can('view', $file->attachable);
        }

        if ($file->messages()->exists()) {
            return $file->messages()->whereHas('conversation.participantRecords', fn ($participant) => $participant->where('user_id', $user->id))->exists();
        }

        if ($file->task) {
            return app(TaskPolicy::class)->view($user, $file->task);
        }

        return $file->project
            ? app(ProjectPolicy::class)->view($user, $file->project)
            : app(WorkspacePolicy::class)->view($user, $file->workspace);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        $membership = $workspace->memberships()->active()->where('user_id', $user->id)->first();

        return $membership && $membership->role->value !== 'viewer';
    }

    public function update(User $user, ProjectFile $file): bool
    {
        // Only the person who attached it may remove it, and only while the request is still
        // open. Evidence a decision was made on should not vanish afterwards.
        if ($file->attachable) {
            return $file->uploader_id === $user->id
                && $file->attachable->status->isOpen()
                && $user->can('view', $file->attachable);
        }

        if ($file->messages()->exists()) {
            return $file->uploader_id === $user->id
                && $file->messages()->whereHas('conversation.participantRecords', fn ($participant) => $participant->where('user_id', $user->id))->exists();
        }

        $membership = $file->workspace->memberships()->active()->where('user_id', $user->id)->first();

        if (! $membership?->role->canContribute()) {
            return false;
        }

        if ($file->uploader_id === $user->id) {
            return true;
        }

        if ($file->task) {
            return app(TaskPolicy::class)->update($user, $file->task);
        }

        return $file->project
            ? app(TaskPolicy::class)->create($user, $file->project)
            : app(WorkspacePolicy::class)->update($user, $file->workspace);
    }

    public function delete(User $user, ProjectFile $file): bool
    {
        return $this->update($user, $file);
    }
}
