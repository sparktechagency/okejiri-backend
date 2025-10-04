<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('referral_bonus_amount', 8, 2)->nullable();
            $table->decimal('minimum_withdrawal_threshold', 8, 2)->nullable();
            $table->decimal('three_day_boosting_price', 8, 2)->nullable();
            $table->decimal('seven_day_boosting_price', 8, 2)->nullable();
            $table->decimal('fifteen_day_boosting_price', 8, 2)->nullable();
            $table->decimal('thirty_day_boosting_price', 8, 2)->nullable();
            $table->decimal('profit', 8, 2)->nullable()->comment('in percent');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
