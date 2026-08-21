@props(['subject'])
@php
    $discussion = app(App\Services\DiscussionService::class)->comments($subject, auth()->user());
    $storeUrl = route('internal.discussions.comments.store', [$discussion['type'], $subject->public_id]);
@endphp

<section {{ $attributes->class(['mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900']) }} aria-labelledby="discussion-title">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
        <div><h3 id="discussion-title" class="text-lg font-bold">Discussion</h3><p class="mt-1 text-sm text-slate-500">Questions, decisions, replies, and files stay with this record.</p></div>
        @if($discussion['unread'])<form method="POST" action="{{ route('internal.discussions.read', [$discussion['type'], $subject->public_id]) }}" class="flex items-center gap-2">@csrf<span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-300">{{ $discussion['unread'] }} unread</span><button class="text-xs font-bold text-orbit-700 dark:text-orbit-300">Mark read</button></form>@endif
    </div>

    <form method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data" class="space-y-3 border-b border-slate-200 p-5 dark:border-slate-800">
        @csrf
        <x-textarea name="body" rows="3" maxlength="5000" required placeholder="Write a comment or decision… Use @Name when mentioning someone."></x-textarea>
        <div class="grid gap-3 sm:grid-cols-2">
            <details class="rounded-xl border border-slate-200 p-3 dark:border-slate-700"><summary class="cursor-pointer text-sm font-bold">@ Mention people</summary><div class="mt-3 max-h-36 space-y-2 overflow-y-auto">@foreach($discussion['mentionable'] as $person)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="mentioned_user_public_ids[]" value="{{ $person->public_id }}" class="rounded border-slate-300 text-orbit-600"><span class="truncate">{{ $person->name }}</span></label>@endforeach</div></details>
            <input type="file" name="attachments[]" multiple class="block w-full self-center text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-bold dark:file:bg-slate-800" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
        </div>
        <div class="text-right"><x-button>Post comment</x-button></div>
    </form>

    <div class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse($discussion['roots'] as $comment)
            <article class="p-5 {{ $comment->pinned_at ? 'bg-amber-50/60 dark:bg-amber-950/20' : '' }}">
                <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="text-sm font-bold">{{ $comment->author?->name ?? 'Deleted user' }} @if($comment->pinned_at)<span class="ml-2 text-xs text-amber-700 dark:text-amber-300">Pinned</span>@endif</p><time class="text-xs text-slate-500">{{ $comment->created_at->diffForHumans() }}</time></div><div class="flex gap-2">@can('pin', $comment)<form method="POST" action="{{ route('internal.discussions.comments.pin', $comment) }}">@csrf @method('PATCH')<input type="hidden" name="pinned" value="{{ $comment->pinned_at ? 0 : 1 }}"><button class="text-xs font-bold text-slate-500 hover:text-orbit-700">{{ $comment->pinned_at ? 'Unpin' : 'Pin' }}</button></form>@endcan @can('delete', $comment)<form method="POST" action="{{ route('internal.discussions.comments.destroy', $comment) }}" onsubmit="return confirm('Delete this comment?')">@csrf @method('DELETE')<button class="text-xs font-bold text-rose-600">Delete</button></form>@endcan</div></div>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $comment->body }}</p>
                @if($comment->files->isNotEmpty())<div class="mt-3 flex flex-wrap gap-2">@foreach($comment->files as $file)<a href="{{ route('internal.discussions.files.download', $file->public_id) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold hover:border-orbit-400 dark:border-slate-700">{{ $file->original_name }}</a>@endforeach</div>@endif

                @foreach($comment->replies as $reply)<div class="ml-4 mt-4 border-l-2 border-slate-200 pl-4 dark:border-slate-700"><div class="flex justify-between gap-3"><p class="text-sm font-bold">{{ $reply->author?->name ?? 'Deleted user' }} <time class="ml-1 text-xs font-normal text-slate-500">{{ $reply->created_at->diffForHumans() }}</time></p>@can('delete', $reply)<form method="POST" action="{{ route('internal.discussions.comments.destroy', $reply) }}">@csrf @method('DELETE')<button class="text-xs font-bold text-rose-600">Delete</button></form>@endcan</div><p class="mt-2 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $reply->body }}</p>@if($reply->files->isNotEmpty())<div class="mt-2 flex flex-wrap gap-2">@foreach($reply->files as $file)<a href="{{ route('internal.discussions.files.download', $file->public_id) }}" class="text-xs font-bold text-orbit-700 dark:text-orbit-300">{{ $file->original_name }}</a>@endforeach</div>@endif</div>@endforeach

                <details class="mt-4"><summary class="cursor-pointer text-xs font-bold text-orbit-700 dark:text-orbit-300">Reply</summary><form method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data" class="mt-3 space-y-2">@csrf<input type="hidden" name="parent_public_id" value="{{ $comment->public_id }}"><x-textarea name="body" rows="2" maxlength="5000" required placeholder="Write a reply…"></x-textarea><div class="flex flex-wrap items-center justify-between gap-2"><input type="file" name="attachments[]" multiple class="text-xs text-slate-500"><x-button>Reply</x-button></div></form></details>
            </article>
        @empty
            <p class="p-8 text-center text-sm text-slate-500">No comments yet. Start the shared record here.</p>
        @endforelse
    </div>
</section>
