<?php

namespace Tests\Feature\Ai;

use App\Actions\Project\CreateProject;
use App\Actions\Project\CreateSystem;
use App\Actions\Request\TransitionFeatureRequest;
use App\Actions\Workspace\CreateWorkspace;
use App\Contracts\TaskBreakdownGenerator;
use App\Enums\BreakdownStatus;
use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectStatus;
use App\Enums\RequestUrgency;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\GenerateTaskBreakdown;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\TaskBreakdown;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\OrbitraNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaskBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config()->set('services.openai.key', 'test-key-not-a-real-secret');
        config()->set('services.openai.model', 'gpt-4o');
    }

    public function test_approving_a_request_queues_a_breakdown(): void
    {
        Queue::fake();
        [$workspace, $supervisor, $system] = $this->system();
        $request = $this->pendingRequest($workspace, $system);

        app(TransitionFeatureRequest::class)->handle($request, FeatureRequestStatus::APPROVED, $supervisor);

        Queue::assertPushed(GenerateTaskBreakdown::class);
    }

    public function test_a_successful_call_stores_a_ready_breakdown_with_model_and_tokens(): void
    {
        Http::fake([
            '*/responses' => Http::response($this->openAiBody([
                ['title' => 'Add the export endpoint', 'estimate_minutes' => 180, 'requires_user_validation' => false],
                ['title' => 'Add the download button', 'estimate_minutes' => 120, 'requires_user_validation' => true, 'validation_reason' => 'The requester must confirm the columns.'],
            ])),
        ]);

        [$workspace, , $system] = $this->system();
        $request = $this->approvedRequest($workspace, $system);

        (new GenerateTaskBreakdown($request))->handle(app(TaskBreakdownGenerator::class));

        $breakdown = TaskBreakdown::forSubject($request)->firstOrFail();
        $this->assertSame(BreakdownStatus::READY, $breakdown->status);
        $this->assertSame('openai', $breakdown->provider);
        $this->assertSame('gpt-4o', $breakdown->model);
        $this->assertSame(1200, $breakdown->input_tokens);
        $this->assertSame(340, $breakdown->output_tokens);
        $this->assertCount(2, $breakdown->tasks());
        $this->assertSame(300, $breakdown->totalMinutes());
        $this->assertNotNull($breakdown->generated_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'task_breakdown.ready']);
    }

    public function test_a_refusal_is_a_failure_not_a_crash(): void
    {
        Http::fake([
            '*/responses' => Http::response([
                'model' => 'gpt-4o',
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'refusal', 'refusal' => 'I cannot help with that.']],
                ]],
                'usage' => ['input_tokens' => 900, 'output_tokens' => 12],
            ]),
        ]);

        [$workspace, , $system] = $this->system();
        $request = $this->approvedRequest($workspace, $system);

        (new GenerateTaskBreakdown($request))->handle(app(TaskBreakdownGenerator::class));

        $breakdown = TaskBreakdown::forSubject($request)->firstOrFail();
        $this->assertSame(BreakdownStatus::FAILED, $breakdown->status);
        $this->assertStringContainsString('I cannot help with that.', $breakdown->error_message);
        $this->assertNull($breakdown->payload_json);

        // The request is untouched: manual entry stays available.
        $this->assertSame(FeatureRequestStatus::APPROVED, $request->fresh()->status);
    }

    public function test_an_api_error_lands_in_failed_with_the_reason_visible(): void
    {
        Http::fake([
            '*/responses' => Http::response(['error' => ['message' => 'Rate limit reached.']], 429),
        ]);

        [$workspace, , $system] = $this->system();
        $request = $this->approvedRequest($workspace, $system);

        (new GenerateTaskBreakdown($request))->handle(app(TaskBreakdownGenerator::class));

        $breakdown = TaskBreakdown::forSubject($request)->firstOrFail();
        $this->assertSame(BreakdownStatus::FAILED, $breakdown->status);
        $this->assertStringContainsString('429', $breakdown->error_message);
        $this->assertStringContainsString('Rate limit reached.', $breakdown->error_message);
    }

    public function test_a_missing_key_fails_visibly_without_calling_out(): void
    {
        config()->set('services.openai.key', null);
        Http::fake();

        [$workspace, , $system] = $this->system();
        $request = $this->approvedRequest($workspace, $system);

        (new GenerateTaskBreakdown($request))->handle(app(TaskBreakdownGenerator::class));

        $this->assertSame(BreakdownStatus::FAILED, TaskBreakdown::forSubject($request)->firstOrFail()->status);
        Http::assertNothingSent();
    }

    public function test_running_the_job_twice_produces_one_ready_breakdown(): void
    {
        Http::fake([
            '*/responses' => Http::response($this->openAiBody([
                ['title' => 'Add the export endpoint', 'estimate_minutes' => 180, 'requires_user_validation' => false],
            ])),
        ]);

        [$workspace, , $system] = $this->system();
        $request = $this->approvedRequest($workspace, $system);

        (new GenerateTaskBreakdown($request))->handle(app(TaskBreakdownGenerator::class));
        (new GenerateTaskBreakdown($request))->handle(app(TaskBreakdownGenerator::class));

        $this->assertSame(1, TaskBreakdown::forSubject($request)->count());
        Http::assertSentCount(1);
    }

    public function test_a_retry_after_failure_reuses_the_same_row_and_can_succeed(): void
    {
        // A sequence, not two fake() calls: fake() merges stubs, so the first one keeps winning.
        Http::fakeSequence()
            ->push(['error' => ['message' => 'Upstream down.']], 500)
            ->push($this->openAiBody([
                ['title' => 'Add the export endpoint', 'estimate_minutes' => 60, 'requires_user_validation' => false],
            ]), 200);

        [$workspace, , $system] = $this->system();
        $request = $this->approvedRequest($workspace, $system);

        (new GenerateTaskBreakdown($request))->handle(app(TaskBreakdownGenerator::class));
        $breakdown = TaskBreakdown::forSubject($request)->firstOrFail();
        $this->assertSame(BreakdownStatus::FAILED, $breakdown->status);

        // Retrying is dispatching the job again. It reuses the failed row.
        (new GenerateTaskBreakdown($request))->handle(app(TaskBreakdownGenerator::class));

        $this->assertSame(1, TaskBreakdown::forSubject($request)->count());
        $this->assertSame(BreakdownStatus::READY, $breakdown->fresh()->status);
        $this->assertNull($breakdown->fresh()->error_message);
    }

    public function test_the_api_key_never_reaches_stored_data(): void
    {
        Http::fake(['*/responses' => Http::response(['error' => ['message' => 'Bad request.']], 400)]);

        [$workspace, , $system] = $this->system();
        $request = $this->approvedRequest($workspace, $system);

        (new GenerateTaskBreakdown($request))->handle(app(TaskBreakdownGenerator::class));

        $breakdown = TaskBreakdown::forSubject($request)->firstOrFail();
        $key = config('services.openai.key');
        $this->assertStringNotContainsString($key, (string) $breakdown->error_message);
        $this->assertStringNotContainsString($key, json_encode($breakdown->payload_json));
        $this->assertStringNotContainsString($key, json_encode(ActivityLog::pluck('metadata_json')->all()));
    }

    public function test_the_request_body_carries_a_strict_schema_and_the_system_context_first(): void
    {
        Http::fake([
            '*/responses' => Http::response($this->openAiBody([
                ['title' => 'Add the export endpoint', 'estimate_minutes' => 60, 'requires_user_validation' => false],
            ])),
        ]);

        [$workspace, , $system] = $this->system();
        $request = $this->approvedRequest($workspace, $system);

        (new GenerateTaskBreakdown($request))->handle(app(TaskBreakdownGenerator::class));

        Http::assertSent(function ($sent) {
            $body = $sent->data();

            $this->assertSame('json_schema', $body['text']['format']['type']);
            $this->assertTrue($body['text']['format']['strict']);
            $this->assertFalse($body['text']['format']['schema']['additionalProperties']);
            $taskSchema = $body['text']['format']['schema']['properties']['tasks']['items'];
            $this->assertContains('checklist', $taskSchema['required']);
            $this->assertSame(2, $taskSchema['properties']['checklist']['minItems']);
            $this->assertArrayNotHasKey('depends_on', $taskSchema['properties']);
            $this->assertSame('system', $body['input'][0]['role']);
            $this->assertStringContainsString('Inventory Core', $body['input'][0]['content']);
            $this->assertStringContainsString('Export the monthly stock report', $body['input'][1]['content']);

            return true;
        });
    }

    public function test_a_direct_it_project_can_generate_a_reviewable_plan(): void
    {
        Http::fake([
            '*/responses' => Http::response($this->openAiBody([
                ['title' => 'Map the current workflow', 'estimate_minutes' => 90, 'requires_user_validation' => false],
            ])),
        ]);

        [$workspace, $owner] = $this->system();
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Internal delivery automation',
            'description' => 'Automate the internal IT delivery workflow.',
            'color' => '#4f46e5',
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => null,
            'due_date' => null,
        ]);

        (new GenerateTaskBreakdown($project))->handle(app(TaskBreakdownGenerator::class));

        $this->assertSame(BreakdownStatus::READY, TaskBreakdown::forSubject($project)->firstOrFail()->status);
        Http::assertSent(function ($sent) {
            $body = $sent->data();
            $this->assertStringContainsString('Internal delivery automation', $body['input'][1]['content']);
            $this->assertStringContainsString('Set requires_user_validation to false', $body['input'][0]['content']);

            return true;
        });
    }

    public function test_a_ready_plan_tells_the_pic_and_appears_in_the_approvals_queue(): void
    {
        Http::fake([
            '*/responses' => Http::response($this->openAiBody([
                ['title' => 'Add the export endpoint', 'estimate_minutes' => 180, 'requires_user_validation' => false],
            ])),
        ]);

        [$workspace, $pic, $system] = $this->system();
        $request = $this->approvedRequest($workspace, $system);
        $request->forceFill(['assignee_id' => $pic->id])->save();

        (new GenerateTaskBreakdown($request->fresh()))->handle(app(TaskBreakdownGenerator::class));

        // A plan nobody is told about is a plan nobody accepts.
        Notification::assertSentTo($pic, OrbitraNotification::class);

        $this->actingAs($pic)
            ->get(route('app.approvals.index', $workspace))
            ->assertOk()
            // Nothing is pending review, so the page opens on the tab that does have work.
            ->assertSee('Proposed plans')
            ->assertSee('Export the monthly stock report')
            // The row carries real data, not just the title: the PIC it is waiting on.
            ->assertSee($pic->name);
    }

    public function test_a_payload_queued_before_the_note_parameter_existed_still_runs(): void
    {
        Http::fake([
            '*/responses' => Http::response($this->openAiBody([
                ['title' => 'Add the export endpoint', 'estimate_minutes' => 60, 'requires_user_validation' => false],
            ])),
        ]);

        [$workspace, , $system] = $this->system();
        $request = $this->approvedRequest($workspace, $system);

        // Exactly how the queue rebuilds a job: no constructor, properties restored from the
        // payload. An older payload has no `note`, so the property must default rather than
        // stay uninitialized.
        $job = (new \ReflectionClass(GenerateTaskBreakdown::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(GenerateTaskBreakdown::class, 'request');
        $property->setValue($job, $request);

        $job->handle(app(TaskBreakdownGenerator::class));

        $this->assertSame(BreakdownStatus::READY, TaskBreakdown::forSubject($request)->firstOrFail()->status);
    }

    /** @param array<int, array<string, mixed>> $tasks */
    private function openAiBody(array $tasks): array
    {
        $tasks = array_map(fn (array $task) => [
            'title' => $task['title'],
            'description' => $task['description'] ?? 'Proposed by the model.',
            'estimate_minutes' => $task['estimate_minutes'],
            'checklist' => $task['checklist'] ?? ['Implement the change', 'Verify the result'],
            'requires_user_validation' => $task['requires_user_validation'],
            'validation_reason' => $task['validation_reason'] ?? null,
        ], $tasks);

        return [
            'model' => 'gpt-4o',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode(['tasks' => $tasks], JSON_THROW_ON_ERROR),
                ]],
            ]],
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 340],
        ];
    }

    private function system(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Inventory Core', 'description' => 'Stock levels and receiving.', 'color' => '#8b5cf6', 'pic_id' => $owner->id,
        ]);

        return [$workspace, $owner, $system];
    }

    private function pendingRequest(Workspace $workspace, Project $system): FeatureRequest
    {
        $requester = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $requester->id, 'role' => WorkspaceRole::REQUESTER,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);

        return FeatureRequest::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'requester_id' => $requester->id,
            'title' => 'Export the monthly stock report',
            'problem' => 'We copy the numbers into a spreadsheet by hand every month and it takes two days.',
            'desired_outcome' => 'A download button that produces the same columns we already use.',
            'benefit' => 'Saves about two staff days each month and reduces transcription errors.',
            'urgency' => RequestUrgency::NORMAL,
            'status' => FeatureRequestStatus::PENDING_REVIEW,
        ]);
    }

    private function approvedRequest(Workspace $workspace, Project $system): FeatureRequest
    {
        $request = $this->pendingRequest($workspace, $system);
        $request->forceFill(['status' => FeatureRequestStatus::APPROVED, 'estimated_minutes' => 300])->save();

        return $request->fresh();
    }
}
