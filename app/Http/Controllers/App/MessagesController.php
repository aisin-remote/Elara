<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\View\View;

class MessagesController extends Controller
{
    public function index(Workspace $workspace): View
    {
        $this->authorize('viewAny', [Conversation::class, $workspace]);

        return view('app.messages.index', [
            'workspace' => $workspace,
            'members' => $workspace->memberships()->active()->with('user')->get()->pluck('user')->filter()->sortBy('name')->values(),
            'projects' => Project::query()->visibleTo(request()->user())->where('workspace_id', $workspace->id)->orderBy('name')->get(),
        ]);
    }
}
