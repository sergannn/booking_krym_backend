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
        // Просто добавляем новый индекс, не удаляя старый
        Schema::table('excursion_user', function (Blueprint $table) {
            $table->index(['excursion_id', 'user_id', 'excursion_date', 'time'], 'excursion_user_date_time_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('excursion_user', function (Blueprint $table) {
            $table->dropIndex('excursion_user_date_time_idx');
        });
    }
};
