<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_minutes', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('schedule_event_id')->constrained()->nullOnDelete();
        });

        DB::table('meeting_minutes')->select('id')->chunkById(100, function ($minutes): void {
            foreach ($minutes as $minute) {
                $projectId = DB::table('meeting_minute_items')
                    ->where('meeting_minute_id', $minute->id)
                    ->whereNotNull('project_id')
                    ->orderBy('position')
                    ->value('project_id');

                if ($projectId) {
                    DB::table('meeting_minutes')->where('id', $minute->id)->update(['project_id' => $projectId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('meeting_minutes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
