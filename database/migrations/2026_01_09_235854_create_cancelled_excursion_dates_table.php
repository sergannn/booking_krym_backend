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
        Schema::create('cancelled_excursion_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excursion_id')->constrained('excursions')->onDelete('cascade');
            $table->dateTime('date_time'); // Конкретная дата и время отмененной экскурсии
            $table->timestamps();
            
            // Уникальный индекс: одна экскурсия не может быть отменена дважды на одну дату
            $table->unique(['excursion_id', 'date_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cancelled_excursion_dates');
    }
};
