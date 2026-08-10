<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The flag PRD-06 already collects and PRD-07 needs. It was validated and stored in
        // the breakdown payload but never reached the task it describes.
        Schema::table('tasks', function (Blueprint $table): void {
            $table->boolean('requires_user_validation')->default(false)->after('estimate_minutes');
            $table->text('validation_reason')->nullable()->after('requires_user_validation');
        });

        Schema::create('validation_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 191);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();

            $table->text('reason')->nullable();
            $table->string('status', 24)->default('open');
            $table->timestamp('opened_at');
            // Stored, not computed: shortening the window later must not retroactively expire
            // checkpoints that were opened under the old rule.
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('final_warning_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'expires_at']);
            $table->index(['requester_id', 'status']);
            // "One OPEN checkpoint per task" is the real rule, and MySQL has no partial index
            // to express it — a unique on (task_id, status) would also forbid a second
            // changes_requested, which is an ordinary second round of review. Enforced in
            // OpenValidationCheckpoints instead, inside its transaction.
            $table->index(['task_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validation_checkpoints');

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn(['requires_user_validation', 'validation_reason']);
        });
    }
};
