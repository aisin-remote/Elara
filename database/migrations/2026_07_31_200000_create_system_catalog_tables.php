<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A standing system is a long-lived project: it already needs statuses, a board,
        // a calendar, progress, files, and the whole policy stack. One column buys all of it.
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('type', 16)->default('project')->after('name');
            $table->index(['workspace_id', 'type', 'archived_at']);
        });

        DB::table('projects')->update(['type' => 'project']);

        Schema::create('features', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('scheduled');
            $table->date('starts_at')->nullable();
            $table->date('due_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workspace_id', 'status']);
            $table->index(['project_id', 'archived_at']);
        });

        // Tasks keep project_id so every existing query, policy, and view still works; the
        // feature is the grouping a request produced.
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('feature_id')->nullable()->after('project_id')->constrained('features')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign(['feature_id']);
            $table->dropColumn('feature_id');
        });

        Schema::dropIfExists('features');

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'type', 'archived_at']);
            $table->dropColumn('type');
        });
    }
};
