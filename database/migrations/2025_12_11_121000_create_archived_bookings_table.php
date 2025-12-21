<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('archived_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_booking_id')->index();
            $table->unsignedBigInteger('original_excursion_id')->index();
            $table->unsignedBigInteger('excursion_id')->nullable()->index();
            $table->unsignedBigInteger('bus_seat_id')->nullable();
            $table->unsignedBigInteger('booked_by')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('passenger_type')->nullable();
            $table->unsignedBigInteger('stop_id')->nullable();
            $table->dateTime('booked_at')->nullable();
            $table->json('payload')->nullable(); // Слепок бронирования
            $table->json('wallet_transactions')->nullable(); // Сопутствующие транзакции
            $table->string('archived_reason')->nullable();
            $table->timestamp('archived_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archived_bookings');
    }
};






