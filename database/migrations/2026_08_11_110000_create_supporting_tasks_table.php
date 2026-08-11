<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supporting_tasks', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('category', 24);
            $table->string('priority', 16);
            $table->string('status', 24)->default('todo');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'due_date']);
            $table->index(['workspace_id', 'assignee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supporting_tasks');
    }
};
