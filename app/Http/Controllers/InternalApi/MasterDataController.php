<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Project\AssignSystemPic;
use App\Actions\Project\CreateSystem;
use App\Http\Requests\Master\ArchiveTaskCategoryRequest;
use App\Http\Requests\Master\AssignSystemPicRequest;
use App\Http\Requests\Master\MasterActionRequest;
use App\Http\Requests\Master\StoreSupportArticleRequest;
use App\Http\Requests\Master\StoreSystemRequest;
use App\Http\Requests\Master\StoreTaskStatusTemplateRequest;
use App\Http\Requests\Master\UpdateTaskCategoryRequest;
use App\Models\ActivityLog;
use App\Models\CapacityException;
use App\Models\MemberCapacity;
use App\Models\Project;
use App\Models\SupportArticle;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskStatusTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceHoliday;
use App\Services\OrganizationDirectory;
use App\Services\WorkspaceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MasterDataController extends Controller
{
    public function updateCategory(UpdateTaskCategoryRequest $request, TaskCategory $category): JsonResponse|RedirectResponse
    {
        $category->update($request->safe()->only(['name', 'color']));
        ActivityLog::record($category->workspace, $category, 'task_category.updated', $request->user());

        return $this->success($request, ['public_id' => $category->public_id], 'Category updated.', back()->getTargetUrl());
    }

    public function archiveCategory(ArchiveTaskCategoryRequest $request, TaskCategory $category): JsonResponse|RedirectResponse
    {
        $inUse = Task::where('category_id', $category->id)->count();
        $replacementId = $request->string('replacement_public_id')->toString();

        if ($inUse > 0 && ! $replacementId && ! $request->boolean('clear_tasks')) {
            throw ValidationException::withMessages([
                'replacement_public_id' => "{$inUse} tasks still use this category. Choose a replacement, or confirm clearing them.",
            ]);
        }

        DB::transaction(function () use ($category, $replacementId, $request): void {
            $replacement = $replacementId
                ? TaskCategory::where('workspace_id', $category->workspace_id)->where('public_id', $replacementId)->firstOrFail()
                : null;

            Task::where('category_id', $category->id)->update(['category_id' => $replacement?->id]);
            $category->update(['archived_at' => now()]);

            ActivityLog::record($category->workspace, $category, 'task_category.archived', $request->user(), [
                'moved_to' => $replacement?->public_id,
            ]);
        });

        return $this->success($request, ['public_id' => $category->public_id], 'Category archived.', back()->getTargetUrl());
    }

    public function restoreCategory(MasterActionRequest $request, TaskCategory $category): JsonResponse|RedirectResponse
    {
        $category->update(['archived_at' => null]);
        ActivityLog::record($category->workspace, $category, 'task_category.restored', $request->user());

        return $this->success($request, ['public_id' => $category->public_id], 'Category restored.', back()->getTargetUrl());
    }

    public function storeStatusTemplate(StoreTaskStatusTemplateRequest $request, Workspace $workspace): JsonResponse|RedirectResponse
    {
        $template = TaskStatusTemplate::create([
            'workspace_id' => $workspace->id,
            ...$request->safe()->only(['name', 'color', 'category']),
            'position' => (int) TaskStatusTemplate::where('workspace_id', $workspace->id)->max('position') + 1024,
        ]);

        ActivityLog::record($workspace, $template, 'task_status_template.created', $request->user());

        return $this->success($request, ['public_id' => $template->public_id], 'Status template added.', back()->getTargetUrl(), 201);
    }

    public function updateStatusTemplate(StoreTaskStatusTemplateRequest $request, TaskStatusTemplate $template): JsonResponse|RedirectResponse
    {
        $template->update($request->safe()->only(['name', 'color', 'category']));
        ActivityLog::record($template->workspace, $template, 'task_status_template.updated', $request->user());

        return $this->success($request, ['public_id' => $template->public_id], 'Status template updated.', back()->getTargetUrl());
    }

    public function archiveStatusTemplate(MasterActionRequest $request, TaskStatusTemplate $template): JsonResponse|RedirectResponse
    {
        // Projects already created keep their own statuses; only future projects change.
        $template->update(['archived_at' => $template->archived_at ? null : now()]);
        ActivityLog::record($template->workspace, $template, $template->archived_at ? 'task_status_template.archived' : 'task_status_template.restored', $request->user());

        return $this->success($request, ['public_id' => $template->public_id], $template->archived_at ? 'Status template archived.' : 'Status template restored.', back()->getTargetUrl());
    }

    public function storeArticle(StoreSupportArticleRequest $request): JsonResponse|RedirectResponse
    {
        $article = SupportArticle::create([
            ...$request->safe()->only(['title', 'category', 'body', 'slug']),
            'is_published' => $request->boolean('is_published'),
        ]);

        return $this->success($request, ['public_id' => $article->public_id], 'Article created.', back()->getTargetUrl(), 201);
    }

    public function updateArticle(StoreSupportArticleRequest $request, SupportArticle $article): JsonResponse|RedirectResponse
    {
        $article->update([
            ...$request->safe()->only(['title', 'category', 'body', 'slug']),
            'is_published' => $request->boolean('is_published'),
        ]);

        return $this->success($request, ['public_id' => $article->public_id], 'Article updated.', back()->getTargetUrl());
    }

    public function archiveArticle(MasterActionRequest $request, SupportArticle $article): JsonResponse|RedirectResponse
    {
        $article->update(['archived_at' => $article->archived_at ? null : now()]);

        return $this->success($request, ['public_id' => $article->public_id], $article->archived_at ? 'Article archived.' : 'Article restored.', back()->getTargetUrl());
    }

    public function saveCapacity(MasterActionRequest $request, Workspace $workspace): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'user_public_id' => ['required', 'string', 'exists:users,public_id'],
            'hours_per_day' => ['required', 'numeric', 'min:0.5', 'max:12'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7'],
        ]);

        $user = User::where('public_id', $data['user_public_id'])->firstOrFail();

        MemberCapacity::updateOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id, 'effective_from' => now()->toDateString()],
            ['hours_per_day' => $data['hours_per_day'], 'working_days' => array_values($data['working_days'])],
        );

        ActivityLog::record($workspace, $workspace, 'capacity.updated', $request->user(), ['user' => $user->public_id]);

        return $this->success($request, ['user' => $user->public_id], 'Capacity saved.', back()->getTargetUrl());
    }

    public function storeException(MasterActionRequest $request, Workspace $workspace): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'user_public_id' => ['required', 'string', 'exists:users,public_id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'in:leave,training,other'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $exception = CapacityException::create([
            'workspace_id' => $workspace->id,
            'user_id' => User::where('public_id', $data['user_public_id'])->value('id'),
            ...collect($data)->except('user_public_id')->all(),
        ]);

        return $this->success($request, ['public_id' => $exception->public_id], 'Time off recorded.', back()->getTargetUrl(), 201);
    }

    public function destroyException(MasterActionRequest $request, CapacityException $exception): JsonResponse|RedirectResponse
    {
        $exception->delete();

        return $this->success($request, [], 'Time off removed.', back()->getTargetUrl());
    }

    public function storeHoliday(MasterActionRequest $request, Workspace $workspace): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'observed_on' => ['required', 'date', Rule::unique('workspace_holidays', 'observed_on')->where('workspace_id', $workspace->id)],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $holiday = WorkspaceHoliday::create(['workspace_id' => $workspace->id, ...$data]);

        return $this->success($request, ['public_id' => $holiday->public_id], 'Holiday added.', back()->getTargetUrl(), 201);
    }

    public function destroyHoliday(MasterActionRequest $request, WorkspaceHoliday $holiday): JsonResponse|RedirectResponse
    {
        $holiday->delete();

        return $this->success($request, [], 'Holiday removed.', back()->getTargetUrl());
    }

    public function saveRules(MasterActionRequest $request, Workspace $workspace, WorkspaceSettings $settings): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'validation_window_days' => ['required', 'integer', 'between:1,60'],
            'pic_grace_days' => ['required', 'integer', 'between:0,90'],
            'horizon_days' => ['required', 'integer', 'between:7,365'],
            'ai_model' => ['nullable', 'string', 'max:100'],
        ]);

        $settings->put($workspace, $data);
        ActivityLog::record($workspace, $workspace, 'request_rules.updated', $request->user(), $data);

        return $this->success($request, $data, 'Request rules saved.', back()->getTargetUrl());
    }

    public function storeSystem(StoreSystemRequest $request, Workspace $workspace, CreateSystem $action, AssignSystemPic $assign): JsonResponse|RedirectResponse
    {
        $rows = collect($request->validated('pics'));

        // The first row creates the system with its PIC; the rest are assigned onto it. Both
        // paths end in the same place, so naming three departments here and naming them one at
        // a time afterwards produce the same system.
        $first = $rows->shift();
        $firstDepartmentId = $first['organization_department_id'] ?? null;

        $system = DB::transaction(function () use ($request, $workspace, $action, $assign, $rows, $first, $firstDepartmentId): Project {
            $system = $action->handle($workspace, $request->user(), [
                ...$request->safe()->only(['name', 'description', 'color', 'plant']),
                'organization_department_id' => $firstDepartmentId,
                'organization_department_code' => $this->departmentCode($firstDepartmentId),
                'pic_id' => $this->picByPublicId($first)->id,
            ]);

            foreach ($rows as $row) {
                $departmentId = (int) $row['organization_department_id'];

                $assign->assign(
                    $system,
                    $this->picByPublicId($row),
                    $departmentId,
                    $this->departmentCode($departmentId),
                    $request->user(),
                );
            }

            return $system;
        });

        return $this->success($request, ['public_id' => $system->public_id], 'System created.', back()->getTargetUrl(), 201);
    }

    /** @param array<string, mixed> $row */
    private function picByPublicId(array $row): User
    {
        return User::where('public_id', $row['pic_public_id'])->firstOrFail();
    }

    public function updateSystem(StoreSystemRequest $request, Project $system): JsonResponse|RedirectResponse
    {
        abort_unless($system->isSystem(), 404);

        DB::transaction(function () use ($request, $system): void {
            $system->update($request->safe()->only(['name', 'description', 'color', 'plant']));

            ActivityLog::record($system->workspace, $system, 'system.updated', $request->user());
        });

        return $this->success($request, ['public_id' => $system->public_id], 'System updated.', back()->getTargetUrl());
    }

    /**
     * Names the PIC for one department of a system. Assigning again replaces whoever held that
     * department, so the screen never has to ask which of two people is really responsible.
     */
    public function assignSystemPic(AssignSystemPicRequest $request, Project $system, AssignSystemPic $action): JsonResponse|RedirectResponse
    {
        abort_unless($system->isSystem(), 404);

        $pic = User::where('public_id', $request->string('pic_public_id'))->firstOrFail();
        $departmentId = $request->integer('organization_department_id');

        $action->assign($system, $pic, $departmentId, $this->departmentCode($departmentId), $request->user());

        return $this->success($request, ['public_id' => $system->public_id], 'PIC assigned.', back()->getTargetUrl());
    }

    /**
     * Removal names only the department, so it authorises like the other master actions and
     * validates nothing further: an id no PIC holds is refused by the Action itself.
     */
    public function removeSystemPic(MasterActionRequest $request, Project $system, AssignSystemPic $action): JsonResponse|RedirectResponse
    {
        abort_unless($system->isSystem(), 404);

        $action->remove($system, $request->integer('organization_department_id'), $request->user());

        return $this->success($request, ['public_id' => $system->public_id], 'PIC removed.', back()->getTargetUrl());
    }

    /**
     * The code looked up beside the id. Storing only the id would make every screen depend on
     * the external directory being up just to print a label.
     */
    private function departmentCode(?int $id): ?string
    {
        return $id
            ? app(OrganizationDirectory::class)->departments()->firstWhere('id', $id)?->code
            : null;
    }

    public function archiveSystem(MasterActionRequest $request, Project $system): JsonResponse|RedirectResponse
    {
        abort_unless($system->isSystem(), 404);
        $activeFeatures = $system->features()->whereNull('archived_at')->count();

        // Archiving a system with live features would hide work that is still being done.
        if ($activeFeatures > 0 && ! $system->archived_at) {
            throw ValidationException::withMessages([
                'system' => "{$activeFeatures} active features still belong to this system. Finish or archive them first.",
            ]);
        }

        $system->update(['archived_at' => $system->archived_at ? null : now()]);
        ActivityLog::record($system->workspace, $system, $system->archived_at ? 'system.archived' : 'system.restored', $request->user());

        return $this->success($request, ['public_id' => $system->public_id], $system->archived_at ? 'System archived.' : 'System restored.', back()->getTargetUrl());
    }
}
