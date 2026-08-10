<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->unsignedBigInteger('organization_department_id')->nullable()->unique()->after('owner_id');
            $table->string('organization_department_code', 32)->nullable()->index()->after('organization_department_id');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropIndex(['organization_department_code']);
            $table->dropUnique(['organization_department_id']);
            $table->dropColumn(['organization_department_id', 'organization_department_code']);
        });
    }
};
