<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reference rows are archived, never deleted: tasks and articles keep pointing at
        // them, so a hard delete would either orphan history or cascade into it.
        Schema::table('task_categories', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('color');
            $table->index(['workspace_id', 'archived_at']);
        });

        Schema::table('support_articles', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('is_published');
        });

        // The starting status set copied into every new project and system. Projects keep
        // editing their own statuses afterwards; this is a template, not a constraint.
        Schema::create('task_status_templates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 9);
            $table->string('category', 32);
            $table->unsignedInteger('position');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'archived_at', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_templates');

        Schema::table('support_articles', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });

        Schema::table('task_categories', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
