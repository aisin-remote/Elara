<?php

namespace App\Providers;

use App\Models\AiConversation;
use App\Models\Conversation;
use App\Models\FeatureRequest;
use App\Models\IntegrationConnection;
use App\Models\MeetingMinute;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectRequest;
use App\Models\ScheduleEvent;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\TaskBreakdown;
use App\Models\TaskComment;
use App\Models\TaskStatus;
use App\Models\ValidationCheckpoint;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Policies\AiConversationPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\FeatureRequestPolicy;
use App\Policies\FilePolicy;
use App\Policies\IntegrationConnectionPolicy;
use App\Policies\MeetingMinutePolicy;
use App\Policies\MessagePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ProjectRequestPolicy;
use App\Policies\ReportPolicy;
use App\Policies\ScheduleEventPolicy;
use App\Policies\SupportTicketPolicy;
use App\Policies\TaskBreakdownPolicy;
use App\Policies\TaskCommentPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TaskStatusPolicy;
use App\Policies\ValidationCheckpointPolicy;
use App\Policies\WorkspaceMemberPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        AiConversation::class => AiConversationPolicy::class,
        Workspace::class => WorkspacePolicy::class,
        WorkspaceMember::class => WorkspaceMemberPolicy::class,
        Project::class => ProjectPolicy::class,
        FeatureRequest::class => FeatureRequestPolicy::class,
        ProjectRequest::class => ProjectRequestPolicy::class,
        TaskBreakdown::class => TaskBreakdownPolicy::class,
        ValidationCheckpoint::class => ValidationCheckpointPolicy::class,
        Task::class => TaskPolicy::class,
        TaskStatus::class => TaskStatusPolicy::class,
        TaskComment::class => TaskCommentPolicy::class,
        ProjectFile::class => FilePolicy::class,
        ScheduleEvent::class => ScheduleEventPolicy::class,
        SupportTicket::class => SupportTicketPolicy::class,
        Conversation::class => ConversationPolicy::class,
        IntegrationConnection::class => IntegrationConnectionPolicy::class,
        Message::class => MessagePolicy::class,
        MeetingMinute::class => MeetingMinutePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('viewReport', [ReportPolicy::class, 'view']);
    }
}
