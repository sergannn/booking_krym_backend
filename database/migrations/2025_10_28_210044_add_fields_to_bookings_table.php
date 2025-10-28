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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excursion_id')->constrained()->onDelete('cascade');
            $table->foreignId('bus_seat_id')->constrained()->onDelete('cascade');
            $table->foreignId('booked_by')->constrained('moonshine_users')->onDelete('cascade');
            $table->decimal('price', 10, 2);
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->enum('passenger_type', ['adult', 'child', 'senior', 'disabled'])->default('adult');
            $table->foreignId('stop_id')->constrained('stops');
            $table->timestamp('booked_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
