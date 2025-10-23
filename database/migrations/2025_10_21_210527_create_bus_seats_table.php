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
        Schema::create('bus_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excursion_id')->constrained()->onDelete('cascade'); // Связь с экскурсией
            $table->integer('seat_number'); // Номер места (1-100)
            $table->enum('status', ['available', 'booked', 'reserved'])->default('available'); // Статус места
            $table->foreignId('booked_by')->nullable()->constrained('moonshine_users')->onDelete('set null'); // Кто забронировал
            $table->timestamp('booked_at')->nullable(); // Когда забронировано
            $table->timestamps();
            
            // Уникальность номера места для каждой экскурсии
            $table->unique(['excursion_id', 'seat_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bus_seats');
    }
};
