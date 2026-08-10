<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('auth_source', 24)->default('local')->after('email');
            $table->unsignedBigInteger('organization_user_id')->nullable()->unique()->after('auth_source');
            $table->timestamp('organization_synced_at')->nullable()->after('organization_user_id');
            $table->index('auth_source');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['auth_source']);
            $table->dropUnique(['organization_user_id']);
            $table->dropColumn(['auth_source', 'organization_user_id', 'organization_synced_at']);
        });
    }
};
