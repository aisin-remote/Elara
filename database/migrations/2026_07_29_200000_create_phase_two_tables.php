<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_statuses', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('color', 7);
            $table->string('category', 24);
            $table->unsignedInteger('position');
            $table->boolean('is_system')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'name']);
            $table->index(['project_id', 'position']);
        });

        Schema::create('task_categories', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('color', 7);
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('status_id')->constrained('task_statuses')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('task_categories')->nullOnDelete();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('priority', 16);
            $table->dateTime('start_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('estimate_minutes')->nullable();
            $table->unsignedInteger('position');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'status_id', 'position']);
            $table->index(['workspace_id', 'due_at']);
            $table->index('priority');
            $table->index('completed_at');
        });

        Schema::create('task_assignees', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->primary(['task_id', 'user_id']);
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->boolean('is_completed')->default(false);
            $table->unsignedInteger('position');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['task_id', 'position']);
        });

        Schema::create('task_watchers', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['task_id', 'user_id']);
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['task_id', 'created_at']);
        });

        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workspace_id', 'project_id', 'task_id'], 'files_workspace_project_task_index');
            $table->index('uploader_id');
        });

        Schema::create('task_move_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        $defaults = [
            ['Backlog', '#94a3b8', 'backlog'],
            ['To Do', '#6366f1', 'todo'],
            ['In Progress', '#f59e0b', 'in_progress'],
            ['Completed', '#10b981', 'completed'],
        ];

        foreach (DB::table('projects')->select('id')->get() as $project) {
            foreach ($defaults as $index => [$name, $color, $category]) {
                DB::table('task_statuses')->insert([
                    'public_id' => (string) Str::ulid(),
                    'project_id' => $project->id,
                    'name' => $name,
                    'color' => $color,
                    'category' => $category,
                    'position' => ($index + 1) * 1024,
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_move_operations');
        Schema::dropIfExists('files');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_watchers');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_categories');
        Schema::dropIfExists('task_statuses');
    }
};
