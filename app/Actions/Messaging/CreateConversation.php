<?php

namespace App\Actions\Messaging;

use App\Enums\ConversationType;
use App\Enums\WorkspaceMemberStatus;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateConversation
{
    public function handle(Workspace $workspace, User $actor, array $data): Conversation
    {
        $type = ConversationType::from($data['type']);
        $requested = collect($data['participant_public_ids'] ?? [])->filter()->unique();
        $members = $workspace->memberships()
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->whereHas('user', fn ($query) => $query->whereIn('public_id', $requested))
            ->with('user')
            ->get();

        if ($members->count() !== $requested->count()) {
            throw ValidationException::withMessages(['participant_public_ids' => 'Every participant must be an active workspace member.']);
        }

        $participantIds = $members->pluck('user_id')->push($actor->id)->unique()->values();
        $project = null;

        if ($type === ConversationType::DIRECT) {
            if ($participantIds->count() !== 2) {
                throw ValidationException::withMessages(['participant_public_ids' => 'A direct conversation needs exactly one other member.']);
            }

            $existing = Conversation::query()
                ->where('workspace_id', $workspace->id)
                ->where('type', ConversationType::DIRECT->value)
                ->has('participantRecords', '=', 2)
                ->whereHas('participantRecords', fn ($query) => $query->where('user_id', $participantIds[0]))
                ->whereHas('participantRecords', fn ($query) => $query->where('user_id', $participantIds[1]))
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        if ($type === ConversationType::GROUP) {
            if (blank($data['title'] ?? null)) {
                throw ValidationException::withMessages(['title' => 'A group name is required.']);
            }

            if ($participantIds->count() < 2) {
                throw ValidationException::withMessages(['participant_public_ids' => 'A group needs at least one other member.']);
            }
        }

        if ($type === ConversationType::PROJECT) {
            $project = Project::query()
                ->visibleTo($actor)
                ->where('workspace_id', $workspace->id)
                ->where('public_id', $data['project_public_id'] ?? null)
                ->first();

            if (! $project) {
                throw ValidationException::withMessages(['project_public_id' => 'Choose a project you can access.']);
            }

            $participantIds = $project->memberships()->pluck('user_id')->push($project->owner_id)->push($actor->id)->unique()->values();
        }

        return DB::transaction(function () use ($workspace, $actor, $data, $type, $project, $participantIds) {
            $conversation = $workspace->conversations()->create([
                'project_id' => $project?->id,
                'type' => $type,
                'title' => $type === ConversationType::DIRECT ? null : ($data['title'] ?? $project?->name),
                'created_by' => $actor->id,
            ]);

            $joinedAt = now();
            $conversation->participantRecords()->createMany($participantIds->map(fn ($id) => [
                'user_id' => $id,
                'joined_at' => $joinedAt,
            ])->all());

            return $conversation;
        });
    }
}
