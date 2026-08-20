<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The "presentation" category was dropped from the picker. Rows still holding it would
     * fail to cast once the enum no longer knows the value, so they move to "other" — the
     * category that has always meant "supporting work that fits nowhere else".
     */
    public function up(): void
    {
        DB::table('supporting_tasks')
            ->where('category', 'presentation')
            ->update(['category' => 'other', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // The original rows are indistinguishable from other "other" work, so nothing to undo.
    }
};
