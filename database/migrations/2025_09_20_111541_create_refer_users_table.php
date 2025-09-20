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
        Schema::create('refer_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer')->nullable()->constrained('users')->cascadeOnDelete()->comment('user who is referred');
            $table->foreignId('referred')->nullable()->constrained('users')->cascadeOnDelete()->comment('user who referred by another user');
            $table->decimal('referral_rewards', 8, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])
              ->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refer_users');
    }
};
