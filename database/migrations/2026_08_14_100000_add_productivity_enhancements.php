<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_views', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('parameters_json');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['project_id', 'user_id', 'name']);
        });

        Schema::create('project_templates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->json('task_fields_json')->nullable();
            $table->json('statuses_json');
            $table->json('properties_json');
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('approval_delegations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope', 24)->default('all');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->index(['workspace_id', 'delegate_id', 'starts_at', 'ends_at'], 'approval_delegate_active_index');
        });

        Schema::table('supporting_tasks', function (Blueprint $table): void {
            $table->unsignedBigInteger('requester_department_id')->nullable()->after('creator_id');
            $table->string('requester_department_code', 32)->nullable()->after('requester_department_id');
            $table->string('requester_department_name')->nullable()->after('requester_department_code');
            $table->index(['requester_department_id', 'created_at'], 'supporting_request_department_index');
        });

        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('webhook_receipts');

        $cashierColumns = array_values(array_filter(
            ['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at'],
            fn (string $column): bool => Schema::hasColumn('users', $column),
        ));

        if ($cashierColumns !== []) {
            if (in_array('stripe_id', $cashierColumns, true)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['stripe_id']));
            }
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn($cashierColumns));
        }
    }

    public function down(): void
    {
        Schema::table('supporting_tasks', function (Blueprint $table): void {
            $table->dropIndex('supporting_request_department_index');
            $table->dropColumn(['requester_department_id', 'requester_department_code', 'requester_department_name']);
        });

        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('project_templates');
        Schema::dropIfExists('task_views');
    }
};
