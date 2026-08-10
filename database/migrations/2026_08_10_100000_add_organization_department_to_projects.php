<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same pair of columns, same names as workspaces already uses: the id to join on and
        // the code to read. No foreign key — departments live in a PostgreSQL database owned
        // by another application, so this side can only ever hold a copy.
        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedBigInteger('organization_department_id')->nullable()->after('type');
            $table->string('organization_department_code', 32)->nullable()->after('organization_department_id');
            $table->index('organization_department_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['organization_department_id']);
            $table->dropColumn(['organization_department_id', 'organization_department_code']);
        });
    }
};
