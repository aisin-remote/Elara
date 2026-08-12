@extends('layouts.app')

@section('title', $project->name.' Files')
@section('page-title', $project->name)

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-sm text-slate-500">Projects / {{ $project->name }}</p><h2 class="mt-1 text-2xl font-bold tracking-tight">Project files</h2></div>@can('create', [App\Models\ProjectFile::class, $workspace])<x-button type="button" onclick="document.getElementById('file-upload-dialog').showModal()"><x-icon name="plus"/>Upload file</x-button>@endcan</div>
    @if ($project->isSystem()) @include('app.features._tabs', ['workspace' => $workspace, 'system' => $project]) @else @include('app.projects._tabs', ['project' => $project]) @endif

    <form method="GET" class="mt-5 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="relative min-w-64 flex-1"><x-icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"/><x-input name="search" value="{{ request('search') }}" placeholder="Search files" class="pl-9"/></div>
        <x-select name="mime" class="sm:max-w-40"><option value="">All types</option><option value="image" @selected(request('mime') === 'image')>Images</option><option value="pdf" @selected(request('mime') === 'pdf')>PDF</option><option value="document" @selected(request('mime') === 'document')>Documents</option><option value="archive" @selected(request('mime') === 'archive')>Archives</option></x-select>
        <x-select name="uploader" class="sm:max-w-48"><option value="">All uploaders</option>@foreach($uploaders as $uploader)<option value="{{ $uploader->public_id }}" @selected(request('uploader') === $uploader->public_id)>{{ $uploader->name }}</option>@endforeach</x-select>
        <x-input type="date" name="from" value="{{ request('from') }}" class="sm:max-w-40" aria-label="Uploaded from"/><x-input type="date" name="to" value="{{ request('to') }}" class="sm:max-w-40" aria-label="Uploaded to"/><x-button variant="secondary">Filter</x-button>
    </form>

    <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($files as $file)
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="grid h-36 place-items-center bg-slate-100 dark:bg-slate-800">
                    @if(str_starts_with($file->mime_type, 'image/'))<img src="{{ route('internal.files.preview', $file) }}" alt="" class="h-full w-full object-cover" loading="lazy">@elseif($file->mime_type === 'application/pdf')<span class="rounded-xl bg-rose-100 px-4 py-3 text-sm font-bold text-rose-700">PDF</span>@else<x-icon name="files" class="size-10 text-slate-400"/>@endif
                </div>
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="truncate font-bold">{{ $file->original_name }}</h3><p class="mt-1 text-xs text-slate-500">{{ number_format($file->size / 1024, 1) }} KB · {{ $file->uploader->name }}</p><p class="mt-1 text-xs text-slate-400">{{ $file->created_at->format('M j, Y') }}@if($file->task) · {{ $file->task->title }}@endif</p></div><details class="relative"><summary class="grid size-8 cursor-pointer list-none place-items-center rounded-lg text-slate-500 hover:bg-slate-100">•••</summary><div class="absolute right-0 z-20 mt-1 w-64 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl dark:border-slate-700 dark:bg-slate-900">@can('update',$file)<form method="POST" action="{{ route('internal.files.update',$file) }}" class="space-y-2">@csrf @method('PATCH')<x-label for="rename-{{ $file->public_id }}">File name</x-label><x-input id="rename-{{ $file->public_id }}" name="original_name" value="{{ $file->original_name }}" required/><x-label for="task-{{ $file->public_id }}">Attach to task</x-label><x-select id="task-{{ $file->public_id }}" name="task_public_id"><option value="">Project only</option>@foreach($tasks as $task)<option value="{{ $task->public_id }}" @selected($file->task_id === $task->id)>{{ $task->title }}</option>@endforeach</x-select><x-button variant="secondary" class="w-full">Save</x-button></form>@endcan @can('delete',$file)<form method="POST" action="{{ route('internal.files.destroy',$file) }}" class="mt-3 border-t border-slate-100 pt-3" onsubmit="return confirm('Delete this file?')">@csrf @method('DELETE')<x-button variant="danger" class="w-full">Delete file</x-button></form>@endcan</div></details></div>
                    <div class="mt-4 flex gap-2">@if($file->isPreviewable())<a href="{{ route('internal.files.preview',$file) }}" target="_blank" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl border border-slate-300 px-3 text-sm font-semibold hover:border-orbit-400">Preview</a>@endif<a href="{{ route('internal.files.download',$file) }}" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl bg-slate-950 px-3 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Download</a></div>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-900"><x-icon name="files" class="mx-auto size-10 text-slate-400"/><h3 class="mt-3 font-bold">No files found</h3><p class="mt-1 text-sm text-slate-500">Upload the first project file or adjust the filters.</p></div>
        @endforelse
    </div>
    <div class="mt-5">{{ $files->links() }}</div>

    @can('create', [App\Models\ProjectFile::class, $workspace])
        <dialog id="file-upload-dialog" class="m-0 h-full max-h-none w-full max-w-none bg-transparent p-0 backdrop:bg-slate-950/60 sm:m-auto sm:h-auto sm:w-[520px] sm:rounded-2xl">
            <div class="h-full bg-white p-5 dark:bg-slate-900 sm:rounded-2xl sm:border sm:border-slate-200 sm:p-6 dark:sm:border-slate-800"><div class="flex items-center justify-between"><h3 class="text-xl font-bold">Upload project file</h3><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" onclick="this.closest('dialog').close()" aria-label="Close">✕</button></div><form method="POST" action="{{ route('internal.files.store',$workspace) }}" enctype="multipart/form-data" class="mt-5 space-y-4">@csrf<input type="hidden" name="project_public_id" value="{{ $project->public_id }}"><div><x-label for="project-file">File</x-label><input id="project-file" type="file" name="file" required accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"><p class="mt-2 text-xs text-slate-500">Image, PDF, Office, text, or ZIP up to {{ number_format(config('orbitra.max_file_upload_kb') / 1024) }} MB.</p></div><div><x-label for="upload-task">Attach to task (optional)</x-label><x-select id="upload-task" name="task_public_id"><option value="">Project only</option>@foreach($tasks as $task)<option value="{{ $task->public_id }}">{{ $task->title }}</option>@endforeach</x-select></div><div class="flex justify-end gap-2"><x-button type="button" variant="secondary" onclick="this.closest('dialog').close()">Cancel</x-button><x-button>Upload</x-button></div></form></div>
        </dialog>
    @endcan
@endsection
