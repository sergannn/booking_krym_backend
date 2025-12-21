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
        // Изменяем enum для добавления 'special'
        DB::statement("ALTER TABLE excursion_prices MODIFY COLUMN passenger_type ENUM('adult', 'child', 'senior', 'disabled', 'special') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем записи с типом 'special' перед откатом
        DB::table('excursion_prices')->where('passenger_type', 'special')->delete();
        
        // Возвращаем enum к исходному состоянию
        DB::statement("ALTER TABLE excursion_prices MODIFY COLUMN passenger_type ENUM('adult', 'child', 'senior', 'disabled') NOT NULL");
    }
};
