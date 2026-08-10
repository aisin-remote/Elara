<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', Workspace::class);

        return view('app.workspaces.create');
    }

    public function settings(Workspace $workspace): View
    {
        $this->authorize('manageSettings', $workspace);

        return view('app.workspaces.settings', compact('workspace'));
    }
}
