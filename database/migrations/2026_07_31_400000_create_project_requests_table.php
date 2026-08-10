<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_requests', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);

            // Four separate columns rather than one blob: approvers read them independently,
            // and PRD-06 weights flow and business_process differently from benefit.
            $table->text('benefit');
            $table->text('concept');
            $table->text('business_process');
            $table->text('flow');

            $table->date('target_date')->nullable();
            $table->string('status', 32)->default('pending_meeting');

            // The scoping meeting is a gate, not a formality.
            $table->foreignId('schedule_event_id')->nullable()->constrained('schedule_events')->nullOnDelete();
            $table->timestamp('meeting_held_at')->nullable();
            $table->text('meeting_note')->nullable();

            $table->foreignId('spv_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('spv_at')->nullable();
            $table->text('spv_note')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_at')->nullable();
            $table->text('manager_note')->nullable();

            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workspace_id', 'status']);
            $table->index(['requester_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_requests');
    }
};
