<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\File\DeleteFile;
use App\Actions\File\StorePrivateFile;
use App\Http\Requests\Request\StoreRequestAttachmentRequest;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\ProjectFile;
use App\Models\ProjectRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Attachments on a request, using the private-file mechanism the board already uses (PRD-03,
 * PRD-04). Two entry points because route binding cannot resolve two model types into one
 * parameter; one private method because the rules are identical.
 */
class RequestAttachmentController extends Controller
{
    public function storeForFeature(StoreRequestAttachmentRequest $request, FeatureRequest $featureRequest, StorePrivateFile $store): JsonResponse|RedirectResponse
    {
        return $this->attach($request, $featureRequest, $store);
    }

    public function storeForProject(StoreRequestAttachmentRequest $request, ProjectRequest $projectRequest, StorePrivateFile $store): JsonResponse|RedirectResponse
    {
        return $this->attach($request, $projectRequest, $store);
    }

    public function destroy(Request $request, ProjectFile $file, DeleteFile $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $file);
        abort_unless($file->attachable !== null, 404);

        $delete->handle($file);

        return $this->success($request, [], 'Attachment removed.', back()->getTargetUrl());
    }

    private function attach(StoreRequestAttachmentRequest $request, Model $subject, StorePrivateFile $store): JsonResponse|RedirectResponse
    {
        $file = $store->handle($subject->workspace, $request->user(), $request->file('file'));

        // The uploader owns it, so project_id is deliberately left null: filing a requester's
        // draft screenshots into the target system's library is exactly what PRD-03 refused.
        $file->forceFill([
            'attachable_type' => $subject->getMorphClass(),
            'attachable_id' => $subject->getKey(),
        ])->save();

        ActivityLog::record($subject->workspace, $subject, 'request.attachment_added', $request->user(), [
            'file' => $file->original_name,
        ]);

        return $this->success($request, ['public_id' => $file->public_id], 'Attachment added.', back()->getTargetUrl(), 201);
    }
}
