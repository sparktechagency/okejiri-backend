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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('avatar')->default('default_avatar.png');
            $table->enum('role', ['ADMIN', 'USER', 'PROVIDER'])->default('USER');
            $table->enum('provider_type', ['Individual', 'Company'])->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('referral_code')->nullable();
            $table->decimal('wallet_balance')->default(0);
            $table->decimal('referral_balance')->default(0);
            $table->string('id_card_front')->nullable();
            $table->string('id_card_back')->nullable();
            $table->string('selfie')->nullable();
            $table->enum('kyc_status', ['Unverified', 'In Review', 'Verified', 'Rejected'])
            ->default('Unverified');
            $table->longText('about')->nullable();
            $table->boolean('has_service')->default(false);
            $table->boolean('is_personalization_complete')->default(false);
            $table->boolean('is_boosted')->default(false);
            // // Connected account
            // $table->string('stripe_account_id')->nullable();
            // $table->boolean('stripe_charges_enabled')->default(false);
            // $table->boolean('stripe_payouts_enabled')->default(false);

            $table->decimal('discount')->default(0)->comment('product discount. this only for provider role');
            $table->string('otp')->nullable()->unique();
            $table->string('otp_expires_at')->nullable();
            $table->string('google_id')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
