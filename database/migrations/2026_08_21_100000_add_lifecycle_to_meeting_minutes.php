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
            $table->string('publication_status', 20)->default('draft')->after('summary')->index();
            $table->timestamp('published_at')->nullable()->after('publication_status');
            $table->foreignId('published_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable()->after('published_by');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
        });

        DB::table('meeting_minutes')->update([
            'publication_status' => 'published',
            'published_at' => DB::raw('created_at'),
            'published_by' => DB::raw('creator_id'),
        ]);

        Schema::table('meeting_minute_items', function (Blueprint $table): void {
            $table->timestamp('due_reminded_at')->nullable();
            $table->timestamp('overdue_reminded_at')->nullable();
            $table->timestamp('tba_reminded_at')->nullable();
            $table->index(['pic_user_id', 'status', 'due_date'], 'mom_items_pic_status_due_idx');
        });

        Schema::create('meeting_minute_revisions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('meeting_minute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision');
            $table->json('snapshot_json');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['meeting_minute_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_minute_revisions');
        Schema::table('meeting_minute_items', function (Blueprint $table): void {
            $table->dropIndex('mom_items_pic_status_due_idx');
            $table->dropColumn(['due_reminded_at', 'overdue_reminded_at', 'tba_reminded_at']);
        });
        Schema::table('meeting_minutes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn(['publication_status', 'published_at', 'locked_at']);
        });
    }
};
