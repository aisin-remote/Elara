<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\File\DeleteFile;
use App\Actions\File\StorePrivateFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Discussion\ManageDiscussionCommentRequest;
use App\Http\Requests\Discussion\MarkDiscussionReadRequest;
use App\Http\Requests\Discussion\StoreDiscussionCommentRequest;
use App\Models\ActivityLog;
use App\Models\DiscussionComment;
use App\Models\DiscussionRead;
use App\Models\ProjectFile;
use App\Models\User;
use App\Services\DiscussionService;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiscussionController extends Controller
{
    public function store(StoreDiscussionCommentRequest $request, string $subjectType, string $subject, DiscussionService $discussions, StorePrivateFile $storeFile, NotificationPreferenceService $notifications): RedirectResponse
    {
        $model = $request->subject();
        $workspace = $discussions->workspace($model);
        $mentioned = User::query()->whereIn('public_id', $request->validated('mentioned_user_public_ids', []))->get();

        $comment = DB::transaction(function () use ($request, $model, $workspace, $mentioned, $storeFile): DiscussionComment {
            $parent = filled($request->validated('parent_public_id'))
                ? $model->discussionComments()->where('public_id', $request->validated('parent_public_id'))->firstOrFail()
                : null;
            $comment = $model->discussionComments()->create([
                'workspace_id' => $workspace->id,
                'author_id' => $request->user()->id,
                'parent_id' => $parent?->id,
                'body' => $request->validated('body'),
                'mentions_json' => $mentioned->pluck('id')->all(),
            ]);
            foreach ($request->file('attachments', []) as $upload) {
                $comment->files()->save($storeFile->handle($workspace, $request->user(), $upload));
            }

            return $comment;
        });

        ActivityLog::record($workspace, $model, 'discussion.comment_added', $request->user(), ['comment' => $comment->public_id], $request->ip());
        foreach ($discussions->recipients($model, $mentioned->pluck('id')->all(), $request->user()) as $recipient) {
            $url = $discussions->urlFor($model, $recipient);
            if ($url) {
                $notifications->notify($recipient, $workspace, 'comment_mention', 'New discussion comment', $request->user()->name.' commented on '.$model->title.'.', $url, ['comment' => $comment->public_id]);
            }
        }

        return back()->with('status', 'Comment posted.');
    }

    public function destroy(ManageDiscussionCommentRequest $request, DiscussionComment $discussionComment, DeleteFile $deleteFile): RedirectResponse
    {
        $discussionComment->files()->each(fn ($file) => $deleteFile->handle($file));
        $discussionComment->delete();

        return back()->with('status', 'Comment deleted.');
    }

    public function pin(ManageDiscussionCommentRequest $request, DiscussionComment $discussionComment): RedirectResponse
    {
        $pinned = $request->boolean('pinned', $discussionComment->pinned_at === null);
        $discussionComment->update(['pinned_at' => $pinned ? now() : null, 'pinned_by' => $pinned ? $request->user()->id : null]);

        return back()->with('status', $pinned ? 'Comment pinned.' : 'Comment unpinned.');
    }

    public function read(MarkDiscussionReadRequest $request, string $subjectType, string $subject, DiscussionService $discussions): RedirectResponse
    {
        $model = $request->subject();
        DiscussionRead::query()->updateOrCreate([
            'user_id' => $request->user()->id, 'subject_type' => $model->getMorphClass(), 'subject_id' => $model->getKey(),
        ], ['workspace_id' => $discussions->workspace($model)->id, 'last_read_at' => now()]);

        return back();
    }

    public function download(Request $request, string $file): StreamedResponse
    {
        $document = ProjectFile::query()->where('public_id', $file)->where('attachable_type', (new DiscussionComment)->getMorphClass())->firstOrFail();
        $this->authorize('view', $document);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name, ['Content-Type' => $document->mime_type]);
    }
}
