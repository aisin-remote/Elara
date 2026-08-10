<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_capacities', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Deliberately below eight: meetings, support, and slack live in the difference
            // between a plan and a wish.
            $table->decimal('hours_per_day', 4, 1)->default(6.0);
            $table->json('working_days')->nullable();
            $table->date('effective_from');
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id', 'effective_from']);
        });

        Schema::create('capacity_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason', 32)->default('leave');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'user_id', 'starts_on', 'ends_on'], 'capacity_exceptions_lookup_index');
        });

        Schema::create('workspace_holidays', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('observed_on');
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['workspace_id', 'observed_on']);
        });

        // Effort is what the planner schedules against. Until PRD-06 fills it from an AI
        // breakdown, an approver types it; either way the planner reads one field.
        Schema::table('feature_requests', function (Blueprint $table): void {
            $table->unsignedInteger('estimated_minutes')->nullable()->after('urgency');
            $table->foreignId('assignee_id')->nullable()->after('estimated_minutes')->constrained('users')->nullOnDelete();
            $table->date('scheduled_start')->nullable()->after('assignee_id');
            $table->date('scheduled_due')->nullable()->after('scheduled_start');
        });

        Schema::table('project_requests', function (Blueprint $table): void {
            $table->unsignedInteger('estimated_minutes')->nullable()->after('target_date');
            $table->foreignId('assignee_id')->nullable()->after('estimated_minutes')->constrained('users')->nullOnDelete();
            $table->date('scheduled_start')->nullable()->after('assignee_id');
            $table->date('scheduled_due')->nullable()->after('scheduled_start');
        });
    }

    public function down(): void
    {
        foreach (['feature_requests', 'project_requests'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['assignee_id']);
                $blueprint->dropColumn(['estimated_minutes', 'assignee_id', 'scheduled_start', 'scheduled_due']);
            });
        }

        Schema::dropIfExists('workspace_holidays');
        Schema::dropIfExists('capacity_exceptions');
        Schema::dropIfExists('member_capacities');
    }
};
