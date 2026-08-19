<?php

namespace Tests\Feature;

use App\Actions\Project\CreateProject;
use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectRequestStatus;
use App\Enums\ProjectStatus;
use App\Enums\RequestUrgency;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Models\Feature;
use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Services\DepartmentPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_device_sees_aggregate_delivery_without_project_names(): void
    {
        [$owner, $workspace] = $this->deliveryWorkspace();
        $this->departmentProject($owner, $workspace, 10, 'FIN', 'Finance Modernization');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(asset('elara-favicon.svg'), false)
            ->assertSee('See how company initiatives are moving forward')
            ->assertSee('Company projects')
            ->assertSee('No department remembered on this device')
            ->assertSee('Login to personalize')
            ->assertDontSee('Finance Modernization');
    }

    public function test_a_remembered_department_sees_only_its_sanitized_project_and_feature_timelines(): void
    {
        [$owner, $workspace] = $this->deliveryWorkspace();
        [, $financeTask] = $this->departmentProject($owner, $workspace, 10, 'FIN', 'Finance Modernization');
        $this->departmentProject($owner, $workspace, 20, 'HR', 'Human Resources Portal');
        [, $financeFeatureTask] = $this->departmentFeature($owner, $workspace, 10, 'FIN', 'Finance Core', 'Automated journal export');
        $this->departmentFeature($owner, $workspace, 20, 'HR', 'People Core', 'Payroll export');
        $cookie = app(DepartmentPreference::class)->remember([
            'name' => 'Aldino Reza Saputra',
            'department_id' => 10,
            'department_code' => 'FIN',
            'department_name' => 'Finance',
        ]);

        $this->withCookie(DepartmentPreference::COOKIE, $cookie->getValue())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('FIN delivery, at a glance')
            ->assertSee('FIN timeline')
            ->assertSee('Projects')
            ->assertSee('Features')
            ->assertSee('Finance Modernization')
            ->assertSee('Forget this device')
            ->assertDontSee('Aldino Reza Saputra')
            ->assertDontSee('Human Resources Portal')
            ->assertDontSee($financeTask->title)
            ->assertDontSee($financeTask->description)
            ->assertDontSee(route('app.tasks.show', $financeTask), false);

        $this->withCookie(DepartmentPreference::COOKIE, $cookie->getValue())
            ->get(route('home', ['view' => 'features']))
            ->assertOk()
            ->assertSee('Feature roadmap')
            ->assertSee('Department features')
            ->assertSee('Automated journal export')
            ->assertDontSee('Finance Modernization')
            ->assertDontSee('Payroll export')
            ->assertDontSee($financeFeatureTask->title)
            ->assertDontSee($financeFeatureTask->description)
            ->assertDontSee(route('app.tasks.show', $financeFeatureTask), false);
    }

    public function test_a_guest_can_forget_the_remembered_department(): void
    {
        $cookie = app(DepartmentPreference::class)->remember([
            'department_id' => 10,
            'department_code' => 'FIN',
            'department_name' => 'Finance',
        ]);

        $this->withCookie(DepartmentPreference::COOKIE, $cookie->getValue())
            ->post(route('home.forget-department'))
            ->assertRedirect(route('home'))
            ->assertCookieExpired(DepartmentPreference::COOKIE);
    }

    private function deliveryWorkspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => "ITD's Workspace",
            'timezone' => 'Asia/Jakarta',
            'locale' => 'en',
            'week_start' => 1,
        ]);
        config()->set('organization.workspace_public_id', $workspace->public_id);
        config()->set('organization.it_department_code', 'ITD');

        return [$owner, $workspace];
    }

    private function departmentProject(User $owner, $workspace, int $departmentId, string $departmentCode, string $name): array
    {
        $requester = User::factory()->create();
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => $name,
            'description' => 'Confidential internal project description.',
            'color' => '#4f46e5',
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->addMonths(2)->toDateString(),
        ]);
        ProjectRequest::create([
            'workspace_id' => $workspace->id,
            'requester_id' => $requester->id,
            'title' => $name,
            'benefit' => 'Improves department delivery.',
            'concept' => 'Deliver a focused operational improvement.',
            'business_process' => 'The current process is manual.',
            'flow' => 'Submit, review, and deliver.',
            'status' => ProjectRequestStatus::IN_PROGRESS,
            'project_id' => $project->id,
            'requester_department_external_id' => $departmentId,
            'requester_department_code' => $departmentCode,
        ]);
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $task = $project->tasks()->create([
            'workspace_id' => $workspace->id,
            'status_id' => $status->id,
            'creator_id' => $owner->id,
            'title' => $name.' secret task',
            'description' => 'Confidential task implementation notes.',
            'priority' => TaskPriority::MEDIUM,
            'start_at' => now(),
            'due_at' => now()->addDays(7),
            'position' => 1024,
        ]);

        return [$project, $task];
    }

    private function departmentFeature(User $owner, $workspace, int $departmentId, string $departmentCode, string $systemName, string $name): array
    {
        $requester = User::factory()->create();
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => $systemName,
            'description' => 'Confidential system description.',
            'color' => '#0ea5e9',
            'pic_id' => $owner->id,
        ]);
        $feature = Feature::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'name' => $name,
        ]);
        FeatureRequest::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'requester_id' => $requester->id,
            'title' => $name,
            'problem' => 'The current process is manual.',
            'desired_outcome' => 'The process is automated.',
            'benefit' => 'Reduces repetitive work.',
            'urgency' => RequestUrgency::NORMAL,
            'status' => FeatureRequestStatus::IN_PROGRESS,
            'feature_id' => $feature->id,
            'requester_department_external_id' => $departmentId,
            'requester_department_code' => $departmentCode,
        ]);
        $status = $system->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $task = $system->tasks()->create([
            'workspace_id' => $workspace->id,
            'feature_id' => $feature->id,
            'status_id' => $status->id,
            'creator_id' => $owner->id,
            'title' => $name.' secret task',
            'description' => 'Confidential feature implementation notes.',
            'priority' => TaskPriority::MEDIUM,
            'start_at' => now(),
            'due_at' => now()->addDays(8),
            'position' => 1024,
        ]);

        return [$feature, $task];
    }
}
