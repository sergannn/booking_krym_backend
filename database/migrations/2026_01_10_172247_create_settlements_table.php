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
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('moonshine_users')->onDelete('cascade');
            $table->decimal('total_amount', 10, 2);
            $table->text('notes')->nullable();
            $table->date('settlement_date');
            $table->date('date_from')->nullable(); // Начало периода фильтрации
            $table->date('date_to')->nullable(); // Конец периода фильтрации
            $table->timestamps();
        });

        // Связующая таблица для связи расчетов с бронированиями
        Schema::create('settlement_booking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('settlements')->onDelete('cascade');
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['settlement_id', 'booking_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_booking');
        Schema::dropIfExists('settlements');
    }
};
