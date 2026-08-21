<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->dateTime('meeting_at');
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workspace_id', 'meeting_at']);
        });

        Schema::create('meeting_minute_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('meeting_minute_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->string('pic_name', 120);
            $table->string('related_type', 20)->default('general');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('feature_id')->nullable()->constrained()->nullOnDelete();
            $table->string('related_name', 200)->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 24)->default('outstanding');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['meeting_minute_id', 'position']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_minute_items');
        Schema::dropIfExists('meeting_minutes');
    }
};
