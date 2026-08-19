<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Waiting on me" carried one kind of item: the automatic checkpoint a finished task opens.
     * ITD also has to be able to ask the requester for something mid-task — a sample file, a
     * missing rule — and that question belongs in the same queue rather than a second one.
     */
    public function up(): void
    {
        Schema::table('validation_checkpoints', function (Blueprint $table): void {
            $table->string('kind', 16)->default('validation')->after('subject_id');
            $table->foreignId('opened_by')->nullable()->after('requester_id')->constrained('users')->nullOnDelete();
        });

        // A question has no takedown deadline, so it has no expiry either. Blueprint::change()
        // needs doctrine/dbal, which this project does not carry — MySQL gets a plain ALTER,
        // and a database created from scratch already has the column nullable.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE validation_checkpoints MODIFY expires_at TIMESTAMP NULL');
        }
    }

    public function down(): void
    {
        Schema::table('validation_checkpoints', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('opened_by');
            $table->dropColumn('kind');
        });
    }
};
