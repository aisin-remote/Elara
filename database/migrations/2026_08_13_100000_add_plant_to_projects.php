<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plant is only meaningful for systems; delivery projects leave it null.
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('plant', 16)->nullable()->after('type');
            $table->index(['workspace_id', 'type', 'plant']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'type', 'plant']);
            $table->dropColumn('plant');
        });
    }
};
