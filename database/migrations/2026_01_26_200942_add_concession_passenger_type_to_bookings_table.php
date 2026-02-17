<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Изменяем enum для добавления 'concession'
        DB::statement("ALTER TABLE bookings MODIFY COLUMN passenger_type ENUM('adult', 'child', 'senior', 'disabled', 'special', 'concession') NOT NULL DEFAULT 'adult'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Обновляем записи с типом 'concession' на 'adult' перед откатом
        DB::table('bookings')->where('passenger_type', 'concession')->update(['passenger_type' => 'adult']);
        
        // Возвращаем enum к исходному состоянию
        DB::statement("ALTER TABLE bookings MODIFY COLUMN passenger_type ENUM('adult', 'child', 'senior', 'disabled', 'special') NOT NULL DEFAULT 'adult'");
    }
};
