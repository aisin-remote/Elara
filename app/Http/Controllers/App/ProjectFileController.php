<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectFileController extends Controller
{
    public function index(Request $request, Workspace $workspace, Project $project): View
    {
        abort_unless($project->workspace_id === $workspace->id, 404);
        $this->authorize('view', $project);

        $files = $project->files()
            ->with(['uploader', 'task'])
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where('original_name', 'like', '%'.$search.'%'))
            ->when($request->string('mime')->toString(), function ($query, $mime) {
                match ($mime) {
                    'image' => $query->where('mime_type', 'like', 'image/%'),
                    'pdf' => $query->where('mime_type', 'application/pdf'),
                    'archive' => $query->where('mime_type', 'like', '%zip%'),
                    'document' => $query->where(fn ($types) => $types->where('mime_type', 'like', '%word%')->orWhere('mime_type', 'like', '%sheet%')->orWhere('mime_type', 'text/plain')),
                    default => $query,
                };
            })
            ->when($request->string('uploader')->toString(), fn ($query, $uploader) => $query->whereHas('uploader', fn ($user) => $user->where('public_id', $uploader)))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->string('from')->toString()) ? $request->string('from')->toString() : null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->string('to')->toString()) ? $request->string('to')->toString() : null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('app.files.index', [
            'workspace' => $workspace,
            'project' => $project,
            'files' => $files,
            'tasks' => $project->tasks()->orderBy('title')->get(['id', 'public_id', 'title']),
            'uploaders' => $workspace->memberships()->active()->with('user')
                ->whereIn('user_id', $project->files()->select('uploader_id')->distinct())
                ->get()->pluck('user')->sortBy('name'),
        ]);
    }
}
