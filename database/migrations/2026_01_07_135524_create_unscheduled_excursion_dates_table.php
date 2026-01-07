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
        Schema::create('unscheduled_excursion_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excursion_id')->constrained('excursions')->onDelete('cascade');
            $table->dateTime('date_time');
            $table->timestamps();
            
            // Уникальность: одна экскурсия не может иметь две одинаковые внеплановые даты
            $table->unique(['excursion_id', 'date_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unscheduled_excursion_dates');
    }
};
