<?php

namespace App\Services;

use App\Enums\WorkspaceRole;
use App\Models\DiscussionRead;
use App\Models\FeatureRequest;
use App\Models\MeetingMinute;
use App\Models\ProjectRequest;
use App\Models\SupportingTask;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class DiscussionService
{
    private const TYPES = [
        'feature-request' => FeatureRequest::class,
        'project-request' => ProjectRequest::class,
        'supporting' => SupportingTask::class,
        'mom' => MeetingMinute::class,
    ];

    public function resolve(string $type, string $publicId): Model
    {
        $model = self::TYPES[$type] ?? throw new InvalidArgumentException('Unsupported discussion type.');

        return $model::query()->where('public_id', $publicId)->firstOrFail();
    }

    public function typeFor(Model $subject): string
    {
        return array_search($subject::class, self::TYPES, true) ?: throw new InvalidArgumentException('Unsupported discussion subject.');
    }

    public function workspace(Model $subject): Workspace
    {
        return $subject->workspace;
    }

    public function comments(Model $subject, User $user): array
    {
        $lastRead = DiscussionRead::query()->where('user_id', $user->id)
            ->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
            ->value('last_read_at');
        $roots = $subject->discussionComments()->whereNull('parent_id')
            ->with(['author', 'pinnedBy', 'files', 'replies.author', 'replies.files'])
            ->orderByRaw('pinned_at IS NULL')->orderByDesc('pinned_at')->oldest()->get();

        return [
            'roots' => $roots,
            'unread' => $subject->discussionComments()->where('author_id', '!=', $user->id)
                ->when($lastRead, fn ($query) => $query->where('created_at', '>', $lastRead))->count(),
            'mentionable' => $this->mentionableUsers($subject),
            'type' => $this->typeFor($subject),
        ];
    }

    public function mentionableUsers(Model $subject): Collection
    {
        $workspaceUsers = $this->workspace($subject)->memberships()->active()->whereHas('user')->with('user')->get()->pluck('user');

        return $workspaceUsers->merge($this->keyUsers($subject))
            ->filter(fn (?User $user) => $user && $user->can('view', $subject))
            ->unique('id')->sortBy('name')->values();
    }

    public function recipients(Model $subject, array $mentionedIds, User $actor): Collection
    {
        $commenters = $subject->discussionComments()->with('author')->get()->pluck('author');

        return $this->keyUsers($subject)->merge($commenters)
            ->merge(User::query()->whereIn('id', $mentionedIds)->get())
            ->filter(fn (?User $user) => $user && $user->id !== $actor->id)
            ->unique('id')->values();
    }

    public function urlFor(Model $subject, User $user): ?string
    {
        $workspace = $this->workspace($subject);
        $delivery = $workspace->memberships()->active()->where('user_id', $user->id)->first();
        if ($delivery?->role->canAccessDeliveryDesk()) {
            return match (true) {
                $subject instanceof FeatureRequest => route('app.approvals.show', [$workspace, $subject]),
                $subject instanceof ProjectRequest => route('app.approvals.projects.show', [$workspace, $subject]),
                $subject instanceof SupportingTask => route('app.supporting.edit', [$workspace, $subject]),
                $subject instanceof MeetingMinute => route('app.schedule.minutes.show', [$workspace, $subject]),
            };
        }

        $requesterMembership = $user->workspaceMemberships()->active()->where('role', WorkspaceRole::REQUESTER->value)->with('workspace')->first();
        if (! $requesterMembership) {
            return null;
        }

        return match (true) {
            $subject instanceof FeatureRequest => route('desk.requests.show', $subject),
            $subject instanceof ProjectRequest => route('desk.project-requests.show', $subject),
            $subject instanceof SupportingTask => route('desk.supporting.show', $subject),
            $subject instanceof MeetingMinute => route('desk.schedule.mom.show', [$requesterMembership->workspace, $subject->public_id]),
        };
    }

    private function keyUsers(Model $subject): Collection
    {
        return match (true) {
            $subject instanceof FeatureRequest => collect([$subject->requester, $subject->assignee, $subject->reviewer]),
            $subject instanceof ProjectRequest => collect([$subject->requester, $subject->assignee, $subject->supervisor, $subject->manager]),
            $subject instanceof SupportingTask => collect([$subject->creator, $subject->assignee]),
            $subject instanceof MeetingMinute => collect([$subject->creator])
                ->merge($subject->items()->with('pic')->get()->pluck('pic'))
                ->merge($subject->scheduleEvent?->attendees ?? collect()),
        };
    }
}
