<?php

namespace App\Actions\File;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlanEntitlementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class StorePrivateFile
{
    public function __construct(private readonly PlanEntitlementService $entitlements) {}

    public function handle(Workspace $workspace, User $uploader, UploadedFile $upload, ?Project $project = null, ?Task $task = null): ProjectFile
    {
        if (($project && $project->workspace_id !== $workspace->id) || ($task && $task->workspace_id !== $workspace->id)) {
            throw new InvalidArgumentException('The file target must belong to the workspace.');
        }

        $this->entitlements->assertCanStoreBytes($workspace, (int) $upload->getSize());

        $disk = config('filesystems.default', 'local');
        $directory = "workspaces/{$workspace->public_id}/".now()->format('Y/m');
        $filename = Str::uuid().($upload->extension() ? '.'.$upload->extension() : '');
        $originalName = Str::limit(preg_replace('/[[:cntrl:]\\\\\/]+/', '_', $upload->getClientOriginalName()), 255, '');
        $path = $upload->storeAs($directory, $filename, $disk);

        try {
            return $workspace->files()->create([
                'project_id' => $project?->id ?? $task?->project_id,
                'task_id' => $task?->id,
                'uploader_id' => $uploader->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $upload->getMimeType() ?: 'application/octet-stream',
                'size' => $upload->getSize(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }
}
