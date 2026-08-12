<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_properties', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('type', 24);
            $table->json('options_json')->nullable();
            $table->unsignedInteger('position');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'archived_at', 'position']);
        });

        Schema::create('task_property_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_property_id')->constrained('task_properties')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->json('value_json')->nullable();
            $table->timestamps();
            $table->unique(['task_property_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_property_values');
        Schema::dropIfExists('task_properties');
    }
};
