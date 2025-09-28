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
        Schema::create('boost_profile_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->integer('number_of_days');
            $table->enum('payment_method', ['stripe', 'referral_balance'])->default('referral_balance');
            $table->string('payment_amount');
            $table->string('payment_intent_id')->nullable();
            $table->enum('status',['pending','accept','reject'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boost_profile_requests');
    }
};
