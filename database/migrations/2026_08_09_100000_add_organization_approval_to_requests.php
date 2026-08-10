<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['feature_requests', 'project_requests'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->unsignedBigInteger('organization_user_id')->nullable();
                $table->string('requester_job_rank_code', 24)->nullable();
                $table->string('requester_job_rank_name')->nullable();
                $table->unsignedBigInteger('requester_division_external_id')->nullable();
                $table->string('requester_division_code', 32)->nullable();
                $table->string('requester_division_name')->nullable();
                $table->unsignedBigInteger('requester_department_external_id')->nullable();
                $table->string('requester_department_code', 32)->nullable();
                $table->string('requester_department_name')->nullable();
                $table->unsignedBigInteger('requester_section_external_id')->nullable();
                $table->string('requester_section_name')->nullable();
                $table->foreignId('department_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('department_reviewed_at')->nullable();
                $table->text('department_decision_note')->nullable();
                $table->string('needs_info_stage', 24)->nullable();
                $table->index(
                    ['workspace_id', 'requester_department_external_id', 'status'],
                    $tableName === 'feature_requests'
                        ? 'feature_requests_department_status_index'
                        : 'project_requests_department_status_index'
                );
            });
        }
    }

    public function down(): void
    {
        foreach (['feature_requests', 'project_requests'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName === 'feature_requests'
                    ? 'feature_requests_department_status_index'
                    : 'project_requests_department_status_index');
                $table->dropConstrainedForeignId('department_reviewed_by');
                $table->dropColumn([
                    'organization_user_id',
                    'requester_job_rank_code',
                    'requester_job_rank_name',
                    'requester_division_external_id',
                    'requester_division_code',
                    'requester_division_name',
                    'requester_department_external_id',
                    'requester_department_code',
                    'requester_department_name',
                    'requester_section_external_id',
                    'requester_section_name',
                    'department_reviewed_at',
                    'department_decision_note',
                    'needs_info_stage',
                ]);
            });
        }
    }
};
