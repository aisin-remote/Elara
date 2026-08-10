<?php

namespace Database\Seeders;

use App\Enums\ConversationType;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'development'])) {
            throw new \RuntimeException('DemoSeeder is restricted to local and development environments.');
        }

        $this->call(SupportArticleSeeder::class);

        $users = collect([
            ['email' => 'owner@example.com', 'first_name' => 'Maya', 'last_name' => 'Chen', 'job_title' => 'Product Director'],
            ['email' => 'manager@example.com', 'first_name' => 'Rafi', 'last_name' => 'Pratama', 'job_title' => 'Delivery Manager'],
            ['email' => 'member@example.com', 'first_name' => 'Nadia', 'last_name' => 'Putri', 'job_title' => 'Product Designer'],
            ['email' => 'viewer@example.com', 'first_name' => 'Leo', 'last_name' => 'Wijaya', 'job_title' => 'Stakeholder'],
            ['email' => 'supervisor@example.com', 'first_name' => 'Dewi', 'last_name' => 'Anggraini', 'job_title' => 'IT Supervisor'],
            ['email' => 'lead@example.com', 'first_name' => 'Bagas', 'last_name' => 'Nugroho', 'job_title' => 'IT Manager'],
            ['email' => 'requester@example.com', 'first_name' => 'Sari', 'last_name' => 'Lestari', 'job_title' => 'Finance Analyst'],
            ['email' => 'department-head@example.com', 'first_name' => 'Arif', 'last_name' => 'Santoso', 'job_title' => 'Finance Manager'],
        ])->mapWithKeys(fn (array $attributes) => [$attributes['email'] => User::updateOrCreate(
            ['email' => $attributes['email']],
            [...$attributes, 'password' => 'password', 'email_verified_at' => now(), 'company' => 'Product Studio', 'timezone' => 'Asia/Jakarta', 'locale' => 'en']
        )]);

        $owner = $users['owner@example.com'];
        $workspace = Workspace::updateOrCreate(
            ['owner_id' => $owner->id, 'name' => 'Product Studio'],
            ['icon' => '◉', 'timezone' => 'Asia/Jakarta', 'locale' => 'en', 'settings_json' => ['week_start' => 1]]
        );

        foreach ([
            'owner@example.com' => WorkspaceRole::OWNER,
            'manager@example.com' => WorkspaceRole::ADMIN,
            'member@example.com' => WorkspaceRole::MEMBER,
            'viewer@example.com' => WorkspaceRole::VIEWER,
            'supervisor@example.com' => WorkspaceRole::SUPERVISOR,
            'lead@example.com' => WorkspaceRole::MANAGER,
            'requester@example.com' => WorkspaceRole::REQUESTER,
            'department-head@example.com' => WorkspaceRole::REQUESTER,
        ] as $email => $role) {
            $workspace->memberships()->updateOrCreate(['user_id' => $users[$email]->id], [
                'role' => $role,
                'status' => WorkspaceMemberStatus::ACTIVE,
                'invited_by' => $owner->id,
                'joined_at' => now()->subDays(30),
            ]);
        }

        $projects = collect([
            ['name' => 'Website Redesign', 'description' => 'Refresh the public experience and launch a clearer product story.', 'color' => '#2eb0fb'],
            ['name' => 'Mobile App Development', 'description' => 'Coordinate the first responsive companion experience.', 'color' => '#8b5cf6'],
        ])->map(fn (array $attributes) => Project::updateOrCreate(
            ['workspace_id' => $workspace->id, 'name' => $attributes['name']],
            [...$attributes, 'owner_id' => $owner->id, 'status' => ProjectStatus::ACTIVE, 'start_date' => now()->subWeeks(3), 'due_date' => now()->addWeeks(6)]
        ));

        foreach ($projects as $project) {
            foreach ([
                $users['owner@example.com']->id => ProjectMemberRole::MANAGER,
                $users['manager@example.com']->id => ProjectMemberRole::MANAGER,
                $users['member@example.com']->id => ProjectMemberRole::MEMBER,
                $users['viewer@example.com']->id => ProjectMemberRole::VIEWER,
                $users['supervisor@example.com']->id => ProjectMemberRole::MEMBER,
                $users['lead@example.com']->id => ProjectMemberRole::MEMBER,
                // requester@example.com is deliberately absent: a requester is never a project member.
            ] as $userId => $role) {
                $project->memberships()->updateOrCreate(['user_id' => $userId], ['role' => $role]);
            }

            foreach ([
                ['Backlog', '#94a3b8', TaskStatusCategory::BACKLOG],
                ['To Do', '#6366f1', TaskStatusCategory::TODO],
                ['In Progress', '#f59e0b', TaskStatusCategory::IN_PROGRESS],
                ['Completed', '#10b981', TaskStatusCategory::COMPLETED],
            ] as $index => [$name, $color, $category]) {
                $project->taskStatuses()->updateOrCreate(['name' => $name], [
                    'color' => $color, 'category' => $category, 'position' => ($index + 1) * 1024, 'is_system' => true,
                ]);
            }
        }

        $titles = [
            'Confirm navigation architecture', 'Audit responsive typography', 'Prepare product photography direction', 'Build accessible pricing cards', 'Review landing page copy',
            'Map onboarding journey', 'Prototype task detail sheet', 'Connect analytics events', 'Test keyboard navigation', 'Prepare launch checklist',
            'Define mobile information hierarchy', 'Create schedule agenda view', 'Review notification preferences', 'Test offline recovery', 'Document API error states',
            'Balance team workload', 'Validate private file delivery', 'Run security regression', 'Draft release notes', 'Complete stakeholder review',
        ];
        $priorities = TaskPriority::cases();
        $assignees = [$users['manager@example.com'], $users['member@example.com'], $owner];

        foreach ($titles as $index => $title) {
            $project = $projects[$index < 10 ? 0 : 1];
            $statusName = ['Backlog', 'To Do', 'In Progress', 'Completed'][$index % 4];
            $status = $project->taskStatuses()->where('name', $statusName)->firstOrFail();
            $completed = $statusName === 'Completed';
            $dueAt = $completed ? now()->subDays(($index % 5) + 1) : ($index % 5 === 0 ? now()->subDays(2) : now()->addDays(($index % 8) + 1));
            $task = Task::updateOrCreate(['project_id' => $project->id, 'title' => $title], [
                'workspace_id' => $workspace->id,
                'status_id' => $status->id,
                'creator_id' => $owner->id,
                'description' => 'Demo work item for the Product Studio delivery workflow.',
                'priority' => $priorities[$index % count($priorities)],
                'start_at' => now()->subDays($index % 7),
                'due_at' => $dueAt,
                'completed_at' => $completed ? $dueAt->copy()->subHours(3) : null,
                'status_changed_at' => now()->subDays($index % 6),
                'estimate_minutes' => [60, 120, 180, 240][$index % 4],
                'position' => ($index + 1) * 1024,
            ]);
            $task->assignees()->syncWithPivotValues([$assignees[$index % count($assignees)]->id], [
                'assigned_by' => $owner->id,
                'assigned_at' => now()->subDays(7),
            ]);
        }

        foreach ([
            ['Weekly planning', 0, 9, 60, null],
            ['Design critique', 1, 14, 45, null],
            ['Engineering sync', 2, 10, 30, 'https://zoom.us/j/123456789'],
            ['Launch readiness review', 4, 15, 60, 'https://zoom.us/j/987654321'],
        ] as [$title, $day, $hour, $minutes, $meetingUrl]) {
            $start = now($workspace->timezone)->startOfWeek()->addDays($day)->setTime($hour, 0)->utc();
            $event = ScheduleEvent::updateOrCreate(['workspace_id' => $workspace->id, 'title' => $title], [
                'project_id' => $projects[$day % 2]->id,
                'creator_id' => $owner->id,
                'description' => 'Product Studio demo schedule event.',
                'start_at' => $start,
                'end_at' => $start->copy()->addMinutes($minutes),
                'timezone' => $workspace->timezone,
                'color' => $day % 2 ? '#8b5cf6' : '#2eb0fb',
                'meeting_url' => $meetingUrl,
            ]);
            $event->attendees()->syncWithPivotValues($users->pluck('id')->all(), ['response' => 'accepted']);
        }

        $fixturePath = 'demo/orbitra-product-brief.txt';
        Storage::disk('local')->put($fixturePath, "Orbitra Product Studio demo fixture.\nSafe to delete with demo data.\n");
        ProjectFile::updateOrCreate(['workspace_id' => $workspace->id, 'path' => $fixturePath], [
            'project_id' => $projects[0]->id,
            'uploader_id' => $owner->id,
            'disk' => 'local',
            'original_name' => 'orbitra-product-brief.txt',
            'mime_type' => 'text/plain',
            'size' => Storage::disk('local')->size($fixturePath),
            'metadata_json' => ['fixture' => true],
        ]);

        $conversation = Conversation::updateOrCreate(['workspace_id' => $workspace->id, 'title' => 'Website launch squad'], [
            'project_id' => $projects[0]->id,
            'type' => ConversationType::PROJECT,
            'created_by' => $owner->id,
            'last_message_at' => now()->subMinutes(15),
        ]);
        $conversation->participants()->syncWithPivotValues($users->pluck('id')->all(), ['joined_at' => now()->subWeeks(3)]);
        foreach ([
            [$owner, 'The revised launch plan is ready for review.'],
            [$users['manager@example.com'], 'Timeline looks good. I assigned the remaining accessibility checks.'],
            [$users['member@example.com'], 'I will attach the final mobile review after today’s critique.'],
        ] as $index => [$sender, $body]) {
            Message::updateOrCreate(['conversation_id' => $conversation->id, 'body' => $body], [
                'sender_id' => $sender->id,
                'created_at' => now()->subMinutes(45 - ($index * 15)),
                'updated_at' => now()->subMinutes(45 - ($index * 15)),
            ]);
        }

        foreach ($projects as $project) {
            ActivityLog::firstOrCreate([
                'workspace_id' => $workspace->id,
                'subject_type' => $project->getMorphClass(),
                'subject_id' => $project->id,
                'action' => 'demo.project_ready',
            ], ['actor_id' => $owner->id, 'metadata_json' => ['project' => $project->name], 'created_at' => now()->subDays(2)]);
        }

        DB::table('notifications')->updateOrInsert(['id' => '019facbd-f34f-73d0-859f-73432793efbf'], [
            'type' => 'demo.project_ready',
            'notifiable_type' => $owner->getMorphClass(),
            'notifiable_id' => $owner->id,
            'data' => json_encode([
                'workspace_public_id' => $workspace->public_id,
                'title' => 'Launch review is ready',
                'body' => 'The Product Studio demo workspace has meaningful project data to explore.',
                'url' => route('app.projects.show', $projects[0]),
            ], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $this->call(OrganizationDemoSeeder::class);
        $this->call(RequestDeskSeeder::class);
    }
}
