<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE boost_profile_requests
            MODIFY payment_method
            ENUM('stripe', 'flutterwave', 'referral_balance')
            DEFAULT 'referral_balance'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE boost_profile_requests
            MODIFY payment_method
            ENUM('stripe', 'referral_balance')
            DEFAULT 'referral_balance'
        ");
    }
};
