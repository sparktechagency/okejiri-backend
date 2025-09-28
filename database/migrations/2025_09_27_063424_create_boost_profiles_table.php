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
        Schema::create('boost_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->date('started_date');
            $table->date('ending_date');
            $table->unsignedBigInteger('total_click')->default(0);
            $table->unsignedBigInteger('total_bookings')->default(0);
            $table->boolean('is_boosting_pause')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boost_profiles');
    }
};
