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
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('booking_process', ['instant', 'schedule'])
                ->after('package_id')
                ->default('instant');

            $table->enum('booking_type', ['single', 'group'])
                ->default('single');

            $table->date('schedule_date')->nullable();
            $table->string('schedule_time_slot')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->integer('number_of_people')->nullable();
            $table->string('order_id')->nullable();
            $table->string('payment_intent_id')->nullable();
            $table->string('status')->default('New');
            $table->enum('payment_type', ['from_balance', 'make_payment'])
                ->default('from_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'booking_process',
                'booking_type',
                'schedule_date',
                'schedule_time_slot',
                'price',
                'number_of_people',
                'order_id',
                'payment_intent_id',
                'status',
                'payment_type',
            ]);
        });
    }
};
