<?php

namespace App\Http\Controllers\App;

use App\Enums\WorkspaceMemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(SearchRequest $request, Workspace $workspace): JsonResponse|View
    {
        $query = (string) ($request->validated('q') ?? '');

        if ($request->expectsJson()) {
            if ($query === '') {
                return response()->json(['query' => '', 'results' => [], 'total' => 0]);
            }

            $results = $this->results($request, $workspace, $query);

            return response()->json([
                'query' => $query,
                'results' => collect($results->items())->map(fn (object $result): array => [
                    'type' => $result->result_type,
                    'label' => $result->label,
                    'description' => $result->description,
                    'url' => $result->url,
                ])->values(),
                'total' => $results->total(),
            ]);
        }

        return view('app.search.index', [
            'workspace' => $workspace,
            'query' => $query,
            'results' => $query === '' ? null : $this->results($request, $workspace, $query),
        ]);
    }

    private function results(SearchRequest $request, Workspace $workspace, string $query): LengthAwarePaginator
    {
        $user = $request->user();
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';
        $matching = fn (Builder $builder, array $columns) => $builder->where(function (Builder $search) use ($columns, $like): void {
            foreach ($columns as $index => $column) {
                $index === 0 ? $search->where($column, 'like', $like) : $search->orWhere($column, 'like', $like);
            }
        });

        $projects = Project::query()
            ->delivery()
            ->visibleTo($user)
            ->where('projects.workspace_id', $workspace->id)
            ->where(fn (Builder $builder) => $matching($builder, ['projects.name', 'projects.description']))
            ->selectRaw("'project' as result_type")
            ->addSelect(['projects.public_id as result_id', 'projects.name as title', 'projects.description as detail', 'projects.public_id as context_id', DB::raw('NULL as meta'), 'projects.updated_at as sort_date']);

        $tasks = Task::query()
            ->visibleTo($user)
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->where('tasks.workspace_id', $workspace->id)
            ->where(fn (Builder $builder) => $matching($builder, ['tasks.title', 'tasks.description']))
            ->selectRaw("'task' as result_type")
            ->addSelect(['tasks.public_id as result_id', 'tasks.title as title', 'projects.name as detail', 'projects.public_id as context_id', DB::raw('NULL as meta'), 'tasks.updated_at as sort_date']);

        $files = ProjectFile::query()
            ->where('files.workspace_id', $workspace->id)
            ->where('files.original_name', 'like', $like)
            ->where(function (Builder $access) use ($user): void {
                $access->whereHas('messages.conversation.participantRecords', fn (Builder $participant) => $participant->where('user_id', $user->id))
                    ->orWhere(function (Builder $withoutMessages) use ($user): void {
                        $withoutMessages->whereDoesntHave('messages')->where(function (Builder $scope) use ($user): void {
                            $scope->whereHas('task.project', fn (Builder $project) => $project->visibleTo($user))
                                ->orWhere(fn (Builder $file) => $file->whereNull('task_id')->whereHas('project', fn (Builder $project) => $project->visibleTo($user)))
                                ->orWhere(fn (Builder $file) => $file->whereNull('task_id')->whereNull('project_id'));
                        });
                    });
            })
            ->leftJoin('projects', 'projects.id', '=', 'files.project_id')
            ->selectRaw("'file' as result_type")
            ->addSelect(['files.public_id as result_id', 'files.original_name as title', 'files.mime_type as detail', 'projects.public_id as context_id', DB::raw('NULL as meta'), 'files.updated_at as sort_date']);

        $members = WorkspaceMember::query()
            ->join('users', 'users.id', '=', 'workspace_members.user_id')
            ->where('workspace_members.workspace_id', $workspace->id)
            ->where('workspace_members.status', WorkspaceMemberStatus::ACTIVE->value)
            ->where(fn (Builder $builder) => $matching($builder, ['users.first_name', 'users.last_name', 'users.email', 'users.job_title']))
            ->selectRaw("'member' as result_type")
            ->addSelect(['users.public_id as result_id', 'users.first_name as title', 'users.last_name as detail', 'workspace_members.public_id as context_id', 'users.email as meta', 'users.updated_at as sort_date']);

        $conversations = Conversation::query()
            ->visibleTo($user)
            ->where('conversations.workspace_id', $workspace->id)
            ->where(fn (Builder $builder) => $matching($builder, ['conversations.title']))
            ->selectRaw("'conversation' as result_type")
            ->addSelect(['conversations.public_id as result_id', DB::raw("COALESCE(conversations.title, 'Direct conversation') as title"), 'conversations.type as detail', DB::raw('NULL as context_id'), DB::raw('NULL as meta'), 'conversations.updated_at as sort_date']);

        $union = $projects->toBase()
            ->unionAll($tasks->toBase())
            ->unionAll($files->toBase())
            ->unionAll($members->toBase())
            ->unionAll($conversations->toBase());

        $results = DB::query()->fromSub($union, 'search_results')
            ->orderByDesc('sort_date')
            ->paginate(15)
            ->withQueryString();

        return $results->through(function (object $result) use ($workspace): object {
            $result->label = $result->result_type === 'member' ? trim($result->title.' '.$result->detail) : $result->title;
            $result->description = match ($result->result_type) {
                'project' => $result->detail ?: 'Project',
                'task' => 'Task in '.$result->detail,
                'file' => 'Private file · '.$result->detail,
                'member' => $result->meta,
                'conversation' => ucfirst($result->detail).' conversation',
            };
            $result->url = match ($result->result_type) {
                'project' => route('app.projects.show', $result->result_id),
                'task' => route('app.tasks.show', $result->result_id),
                'file' => $result->context_id
                    ? route('app.projects.files', [$workspace, $result->context_id]).'?search='.urlencode($result->title)
                    : route('internal.files.download', $result->result_id),
                'member' => route('app.workspaces.team', $workspace).'?search='.urlencode($result->meta),
                'conversation' => route('app.messages.index', $workspace).'?conversation='.urlencode($result->result_id),
            };

            return $result;
        });
    }
}
