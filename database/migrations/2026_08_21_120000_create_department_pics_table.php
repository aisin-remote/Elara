<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_pics', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // Departments remain owned by the external PostgreSQL directory, so this is not
            // a foreign key. The code is only a resilient display snapshot.
            $table->unsignedBigInteger('organization_department_id');
            $table->string('organization_department_code', 32)->nullable();
            $table->foreignId('pic_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'organization_department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_pics');
    }
};
