<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('stripe_id')->nullable()->collation('utf8_bin')->change();
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('stripe_id')->collation('utf8_bin')->change();
        });
        Schema::table('subscription_items', function (Blueprint $table): void {
            $table->string('stripe_id')->collation('utf8_bin')->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('stripe_id')->nullable()->collation('utf8mb4_unicode_ci')->change();
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('stripe_id')->collation('utf8mb4_unicode_ci')->change();
        });
        Schema::table('subscription_items', function (Blueprint $table): void {
            $table->string('stripe_id')->collation('utf8mb4_unicode_ci')->change();
        });
    }
};
