<?php

namespace Tests\Feature\File;

use App\Actions\Project\CreateProject;
use App\Actions\Task\CreateTask;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectFileFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_file_upload_is_private_randomized_and_listed(): void
    {
        Storage::fake('local');
        [$owner, $workspace, $project] = $this->project();

        $response = $this->actingAs($owner)->postJson(route('internal.files.store', $workspace), [
            'project_public_id' => $project->public_id,
            'file' => UploadedFile::fake()->image('design-board.jpg'),
        ])->assertCreated()->assertJsonPath('data.name', 'design-board.jpg');
        $file = ProjectFile::where('public_id', $response->json('data.public_id'))->firstOrFail();

        Storage::disk('local')->assertExists($file->path);
        $this->assertStringStartsWith("workspaces/{$workspace->public_id}/", $file->path);
        $this->assertStringNotContainsString('design-board', $file->path);
        $this->actingAs($owner)->get(route('app.projects.files', [$workspace, $project]))->assertOk()->assertSee('design-board.jpg');
    }

    public function test_upload_rejects_invalid_mime_size_and_foreign_target(): void
    {
        Storage::fake('local');
        [$owner, $workspace] = $this->project();
        [, , $otherProject] = $this->project();

        $this->actingAs($owner)->postJson(route('internal.files.store', $workspace), [
            'project_public_id' => $otherProject->public_id,
            'file' => UploadedFile::fake()->create('malware.exe', 20, 'application/x-msdownload'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['file', 'project_public_id']);

        $this->actingAs($owner)->postJson(route('internal.files.store', $workspace), [
            'file' => UploadedFile::fake()->image('too-large.jpg')->size(config('orbitra.max_file_upload_kb') + 1),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_file_preview_download_rename_attach_and_delete_cleanup_work(): void
    {
        Storage::fake('local');
        [$owner, $workspace, $project] = $this->project();
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $task = app(CreateTask::class)->handle($project, $owner, [
            'title' => 'Review file', 'description' => null, 'status_public_id' => $status->public_id,
            'category_public_id' => null, 'priority' => TaskPriority::MEDIUM->value,
            'start_at' => null, 'due_at' => null, 'estimate_minutes' => null, 'assignee_public_ids' => [],
        ]);
        $response = $this->actingAs($owner)->postJson(route('internal.files.store', $workspace), [
            'project_public_id' => $project->public_id,
            'file' => UploadedFile::fake()->image('wireframe.png'),
        ])->assertCreated();
        $file = ProjectFile::where('public_id', $response->json('data.public_id'))->firstOrFail();

        $this->actingAs($owner)->get(route('internal.files.preview', $file))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($owner)->get(route('internal.files.download', $file))->assertOk();
        $this->actingAs($owner)->patchJson(route('internal.files.update', $file), [
            'original_name' => 'approved-wireframe.png', 'task_public_id' => $task->public_id,
        ])->assertOk()->assertJsonPath('data.name', 'approved-wireframe.png');
        $this->assertSame($task->id, $file->fresh()->task_id);

        $path = $file->path;
        $this->actingAs($owner)->deleteJson(route('internal.files.destroy', $file))->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->assertSoftDeleted('files', ['id' => $file->id]);
    }

    public function test_non_preview_file_and_file_authorization_are_enforced(): void
    {
        Storage::fake('local');
        [$owner, $workspace, $project] = $this->project();
        $response = $this->actingAs($owner)->postJson(route('internal.files.store', $workspace), [
            'project_public_id' => $project->public_id,
            'file' => UploadedFile::fake()->create('notes.txt', 2, 'text/plain'),
        ])->assertCreated();
        $file = ProjectFile::where('public_id', $response->json('data.public_id'))->firstOrFail();
        $viewer = $this->addProjectMember($project, ProjectMemberRole::VIEWER, WorkspaceRole::VIEWER);

        $this->actingAs($viewer)->get(route('internal.files.download', $file))->assertOk();
        $this->actingAs($viewer)->get(route('internal.files.preview', $file))->assertStatus(415);
        $this->actingAs($viewer)->deleteJson(route('internal.files.destroy', $file))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('internal.files.download', $file))->assertNotFound();
    }

    private function project(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, ['name' => fake()->company(), 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1]);
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => fake()->words(3, true), 'description' => null, 'color' => '#4f46e5',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => null, 'due_date' => null,
        ]);

        return [$owner, $workspace, $project];
    }

    private function addProjectMember($project, ProjectMemberRole $projectRole, WorkspaceRole $workspaceRole): User
    {
        $user = User::factory()->create();
        $project->workspace->memberships()->create([
            'user_id' => $user->id, 'role' => $workspaceRole, 'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);
        $project->memberships()->create(['user_id' => $user->id, 'role' => $projectRole]);

        return $user;
    }
}
