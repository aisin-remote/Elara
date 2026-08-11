<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_requests', function (Blueprint $table): void {
            $table->text('background')->nullable()->after('title');
            $table->text('why_needed')->nullable()->after('background');
            $table->json('objectives')->nullable()->after('why_needed');
            $table->text('illustration')->nullable()->after('objectives');
            $table->text('before_state')->nullable()->after('illustration');
            $table->text('after_state')->nullable()->after('before_state');
            $table->json('benefits')->nullable()->after('after_state');
            $table->json('cost_items')->nullable()->after('benefits');
            $table->text('roi')->nullable()->after('cost_items');
        });
    }

    public function down(): void
    {
        Schema::table('project_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'background', 'why_needed', 'objectives', 'illustration', 'before_state',
                'after_state', 'benefits', 'cost_items', 'roi',
            ]);
        });
    }
};
