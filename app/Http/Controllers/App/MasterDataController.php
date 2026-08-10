<?php

namespace App\Http\Controllers\App;

use App\Enums\TaskStatusCategory;
use App\Http\Controllers\Controller;
use App\Models\CapacityException;
use App\Models\MemberCapacity;
use App\Models\SupportArticle;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskStatusTemplate;
use App\Models\Workspace;
use App\Models\WorkspaceHoliday;
use App\Services\OrganizationDirectory;
use App\Services\WorkspaceSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    /**
     * Every master shares one page shape: search, table, inline create, archive. Adding the
     * next master is a method here plus a column list, not another screen.
     */
    public function index(Workspace $workspace): View
    {
        $this->authorize('manageMasterData', $workspace);

        return view('app.settings.master.index', [
            'workspace' => $workspace,
            'counts' => [
                'systems' => $workspace->projects()->systems()->whereNull('archived_at')->count(),
                'categories' => $workspace->taskCategories()->whereNull('archived_at')->count(),
                'statuses' => TaskStatusTemplate::where('workspace_id', $workspace->id)->active()->count(),
                'articles' => SupportArticle::active()->count(),
            ],
        ]);
    }

    public function categories(Request $request, Workspace $workspace): View
    {
        $this->authorize('manageMasterData', $workspace);
        $search = trim($request->string('search'));

        $categories = $workspace->taskCategories()
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->withCount('tasks')
            ->orderBy('name')
            ->get();

        return view('app.settings.master.categories', [
            'workspace' => $workspace,
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function statusTemplates(Workspace $workspace): View
    {
        $this->authorize('manageMasterData', $workspace);

        return view('app.settings.master.status-templates', [
            'workspace' => $workspace,
            'templates' => TaskStatusTemplate::where('workspace_id', $workspace->id)
                ->orderBy('position')
                ->get(),
            'categories' => TaskStatusCategory::cases(),
            'usesFallback' => TaskStatusTemplate::where('workspace_id', $workspace->id)->active()->doesntExist(),
        ]);
    }

    public function systems(Request $request, Workspace $workspace): View
    {
        $this->authorize('manageMasterData', $workspace);
        $search = trim($request->string('search'));

        return view('app.settings.master.systems', [
            'workspace' => $workspace,
            'systems' => $workspace->projects()
                ->systems()
                ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                ->with('members:id,public_id,first_name,last_name,avatar_path')
                ->withCount(['features as active_features_count' => fn (Builder $query) => $query->whereNull('archived_at')])
                ->orderBy('name')
                ->get(),
            // A requester can never be a PIC: they cannot open the delivery desk at all.
            'candidates' => $workspace->memberships()
                ->active()
                ->with('user')
                ->get()
                ->filter(fn ($membership) => $membership->role->canContribute())
                ->sortBy('user.first_name'),
            // Empty when the organisation directory is unreachable. The view says so rather
            // than showing an empty picker that looks like the company has no departments.
            'departments' => app(OrganizationDirectory::class)->departments(),
            'search' => $search,
        ]);
    }

    public function capacity(Workspace $workspace, WorkspaceSettings $settings): View
    {
        $this->authorize('manageMasterData', $workspace);

        $members = $workspace->memberships()
            ->active()
            ->with('user')
            ->get()
            ->filter(fn ($membership) => $membership->role->canContribute())
            ->sortBy('user.first_name');

        return view('app.settings.master.capacity', [
            'workspace' => $workspace,
            'members' => $members,
            'capacities' => MemberCapacity::where('workspace_id', $workspace->id)
                ->orderByDesc('effective_from')
                ->get()
                ->keyBy('user_id'),
            'exceptions' => CapacityException::where('workspace_id', $workspace->id)
                ->where('ends_on', '>=', now()->subMonth())
                ->with('user')
                ->orderBy('starts_on')
                ->get(),
            'defaults' => [
                'hours' => MemberCapacity::DEFAULT_HOURS_PER_DAY,
                'days' => MemberCapacity::DEFAULT_WORKING_DAYS,
            ],
        ]);
    }

    public function holidays(Workspace $workspace): View
    {
        $this->authorize('manageMasterData', $workspace);

        return view('app.settings.master.holidays', [
            'workspace' => $workspace,
            'holidays' => WorkspaceHoliday::where('workspace_id', $workspace->id)
                ->orderBy('observed_on')
                ->get(),
        ]);
    }

    public function rules(Workspace $workspace, WorkspaceSettings $settings): View
    {
        $this->authorize('manageMasterData', $workspace);

        return view('app.settings.master.rules', [
            'workspace' => $workspace,
            'values' => $settings->all($workspace),
            'labels' => WorkspaceSettings::KEYS,
        ]);
    }

    public function articles(Request $request, Workspace $workspace): View
    {
        $this->authorize('manageMasterData', $workspace);
        $search = trim($request->string('search'));

        return view('app.settings.master.articles', [
            'workspace' => $workspace,
            'articles' => SupportArticle::query()
                ->when($search, fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
                ->orderBy('category')
                ->orderBy('title')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    /** Tasks still pointing at a category block its archive; the caller picks a replacement. */
    public static function categoryUsage(TaskCategory $category): int
    {
        return Task::where('category_id', $category->id)->count();
    }
}
