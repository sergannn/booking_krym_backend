<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->tinyInteger('weekday')->nullable()->after('excursion_id')->comment('1-7: Понедельник-Воскресенье');
            $table->time('time')->nullable()->after('weekday')->comment('Время начала экскурсии');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['weekday', 'time']);
        });
    }
};
