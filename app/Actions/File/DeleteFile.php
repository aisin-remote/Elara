<?php

namespace App\Actions\File;

use App\Models\ProjectFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteFile
{
    public function handle(ProjectFile $file): void
    {
        DB::transaction(function () use ($file) {
            $disk = Storage::disk($file->disk);

            if ($disk->exists($file->path) && ! $disk->delete($file->path)) {
                throw new RuntimeException('The stored file could not be deleted.');
            }

            $file->delete();
        });
    }
}
