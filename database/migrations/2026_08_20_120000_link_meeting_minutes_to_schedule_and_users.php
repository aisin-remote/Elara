<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_minutes', function (Blueprint $table): void {
            $table->foreignId('schedule_event_id')->nullable()->after('creator_id')->unique()->constrained()->nullOnDelete();
        });

        Schema::table('meeting_minute_items', function (Blueprint $table): void {
            $table->foreignId('pic_user_id')->nullable()->after('pic_name')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meeting_minute_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pic_user_id');
        });

        Schema::table('meeting_minutes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('schedule_event_id');
        });
    }
};
