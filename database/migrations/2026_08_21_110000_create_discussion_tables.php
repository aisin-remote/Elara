<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_comments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('discussion_comments')->nullOnDelete();
            $table->text('body');
            $table->json('mentions_json')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['subject_type', 'subject_id', 'created_at'], 'discussion_subject_created_idx');
        });

        Schema::create('discussion_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'subject_type', 'subject_id'], 'discussion_reads_user_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_reads');
        Schema::dropIfExists('discussion_comments');
    }
};
