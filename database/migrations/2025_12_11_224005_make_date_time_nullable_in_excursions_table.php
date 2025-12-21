<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Делаем date_time nullable, так как теперь дата/время хранятся в bookings
        Schema::table('excursions', function (Blueprint $table) {
            $table->dateTime('date_time')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Восстанавливаем обязательное поле (но это может вызвать ошибки, если есть NULL значения)
        Schema::table('excursions', function (Blueprint $table) {
            $table->dateTime('date_time')->nullable(false)->change();
        });
    }
};
