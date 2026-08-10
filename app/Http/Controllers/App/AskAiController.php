<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\WorkspaceSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AskAiController extends Controller
{
    public function index(Request $request, Workspace $workspace, WorkspaceSettings $settings): View
    {
        return $this->page($request, $workspace, null, $settings);
    }

    public function show(
        Request $request,
        Workspace $workspace,
        AiConversation $aiConversation,
        WorkspaceSettings $settings,
    ): View {
        abort_unless($aiConversation->workspace_id === $workspace->id, 404);
        $this->authorize('view', $aiConversation);

        return $this->page($request, $workspace, $aiConversation, $settings);
    }

    private function page(
        Request $request,
        Workspace $workspace,
        ?AiConversation $selected,
        WorkspaceSettings $settings,
    ): View {
        $this->authorize('view', $workspace);
        $user = $request->user();

        $conversations = AiConversation::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->with('project:id,public_id,name')
            ->latest('updated_at')
            ->limit(50)
            ->get();
        $messages = $selected
            ? AiMessage::query()->where('ai_conversation_id', $selected->id)->latest('id')->limit(100)->get()->reverse()->values()
            : collect();
        $projects = Project::query()
            ->visibleTo($user)
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'type']);

        return view('app.ai.index', [
            'workspace' => $workspace,
            'conversations' => $conversations,
            'selectedConversation' => $selected?->loadMissing('project:id,public_id,name'),
            'messages' => $messages,
            'projects' => $projects,
            'model' => $settings->aiModel($workspace),
        ]);
    }
}
