{{-- Files ride along with the answer: "kirim excel aja" is most of what ITD asks for, and
     sending it anywhere else means it is not attached to the question they asked. --}}
<div>
    <x-label :for="'files_'.$checkpoint->public_id">Attach files <span class="font-normal text-slate-400">— optional, up to 5</span></x-label>
    <input type="file" name="attachments[]" id="files_{{ $checkpoint->public_id }}" multiple
        accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
        class="mt-1 block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 dark:file:bg-slate-800 dark:file:text-slate-200">
    <x-field-error name="attachments" />
    <x-field-error name="attachments.0" />
    <p class="mt-1 text-xs text-slate-400">Spreadsheets, documents, images, or a zip — up to {{ (int) (config('orbitra.max_file_upload_kb') / 1024) }} MB each. ITD sees them on the task.</p>
</div>
