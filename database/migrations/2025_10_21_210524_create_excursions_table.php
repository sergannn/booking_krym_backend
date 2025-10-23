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
        Schema::create('excursions', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Название экскурсии
            $table->text('description')->nullable(); // Описание экскурсии
            $table->datetime('date_time'); // Дата и время экскурсии
            $table->decimal('price', 10, 2); // Цена экскурсии
            $table->integer('max_seats')->default(100); // Максимальное количество мест
            $table->boolean('is_active')->default(true); // Активна ли экскурсия
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excursions');
    }
};
