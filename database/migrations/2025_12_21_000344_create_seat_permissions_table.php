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
        Schema::create('seat_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excursion_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained('moonshine_users')->onDelete('cascade');
            $table->date('excursion_date'); // Конкретная дата экскурсии
            $table->integer('seat_number'); // 1 или 2
            $table->timestamps();
            
            // Уникальность: один пользователь может иметь разрешение на одно место на одну дату
            $table->unique(['excursion_id', 'user_id', 'excursion_date', 'seat_number'], 'seat_perm_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seat_permissions');
    }
};
