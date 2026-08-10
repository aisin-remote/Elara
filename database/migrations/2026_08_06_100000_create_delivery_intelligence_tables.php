<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_dependencies', function (Blueprint $table): void {
            $table->string('type', 16)->default('fs')->after('depends_on_task_id');
            $table->unsignedInteger('lag_minutes')->default(0)->after('type');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->timestamp('baseline_start_at')->nullable()->after('due_at');
            $table->timestamp('baseline_due_at')->nullable()->after('baseline_start_at');
        });

        Schema::create('task_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('minutes');
            $table->date('worked_on');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['task_id', 'worked_on']);
        });

        Schema::create('delivery_insights', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->text('summary');
            $table->json('payload');
            $table->string('source', 32)->default('rules');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['workspace_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_insights');
        Schema::dropIfExists('task_time_entries');

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn(['baseline_start_at', 'baseline_due_at']);
        });

        Schema::table('task_dependencies', function (Blueprint $table): void {
            $table->dropColumn(['type', 'lag_minutes']);
        });
    }
};
