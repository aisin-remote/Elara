<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A polymorphic owner rather than another nullable foreign key per request type.
        // PRD-03 deferred attachments precisely so this column is added once for both.
        Schema::table('files', function (Blueprint $table): void {
            $table->string('attachable_type', 191)->nullable()->after('task_id');
            $table->unsignedBigInteger('attachable_id')->nullable()->after('attachable_type');
            $table->index(['attachable_type', 'attachable_id'], 'files_attachable_index');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropIndex('files_attachable_index');
            $table->dropColumn(['attachable_type', 'attachable_id']);
        });
    }
};
