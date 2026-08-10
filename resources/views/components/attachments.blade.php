@props(['files', 'uploadUrl' => null, 'canUpload' => false, 'locale' => 'en'])

@php
    $t = fn (string $en, string $id) => $locale === 'id' ? $id : $en;
@endphp

<section {{ $attributes->class('rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900') }} aria-labelledby="attachments-title">
    <h3 id="attachments-title" class="font-bold">{{ $t('Attachments', 'Lampiran') }}</h3>

    @if ($files->isEmpty())
        <p class="mt-2 text-sm text-slate-500">{{ $t('Nothing attached yet.', 'Belum ada lampiran.') }}</p>
    @else
        <ul class="mt-3 space-y-2">
            @foreach ($files as $file)
                <li class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                    <x-icon name="files" class="size-4 shrink-0 text-slate-400" />
                    <span class="min-w-0 flex-1">
                        <a href="{{ route('internal.files.download', $file) }}" class="block truncate text-sm font-semibold hover:underline">{{ $file->original_name }}</a>
                        <span class="mt-0.5 block text-xs text-slate-400">
                            {{ number_format($file->size / 1024, 0) }} KB · {{ $file->uploader?->name }}
                        </span>
                    </span>
                    @can('delete', $file)
                        <form method="POST" action="{{ route('internal.request-attachments.destroy', $file) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800"
                                aria-label="{{ $t('Remove', 'Hapus') }} {{ $file->original_name }}"><x-icon name="trash" class="size-4" /></button>
                        </form>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif

    @if ($canUpload && $uploadUrl)
        <form method="POST" action="{{ $uploadUrl }}" enctype="multipart/form-data" class="mt-4 space-y-3">
            @csrf
            <x-form-errors :except="['file']" />
            <input type="file" name="file" required
                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold hover:file:bg-slate-200 dark:text-slate-300 dark:file:bg-slate-800"
                aria-label="{{ $t('Choose a file', 'Pilih berkas') }}">
            <x-field-error name="file" />
            <x-button variant="secondary">{{ $t('Attach', 'Lampirkan') }}</x-button>
        </form>
        <p class="mt-2 text-xs text-slate-400">
            {{ $t('Images, documents, spreadsheets, or a zip.', 'Gambar, dokumen, spreadsheet, atau zip.') }}
        </p>
    @endif
</section>
