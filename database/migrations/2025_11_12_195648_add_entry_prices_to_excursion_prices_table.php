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
        Schema::table('excursion_prices', function (Blueprint $table) {
            // Добавляем цены без входа и со входом
            $table->decimal('price_without_entry', 10, 2)->nullable()->after('price');
            $table->decimal('price_with_entry', 10, 2)->nullable()->after('price_without_entry');
        });

        // Копируем существующие цены в price_without_entry (можно будет изменить позже)
        DB::table('excursion_prices')->update([
            'price_without_entry' => DB::raw('price'),
            'price_with_entry' => DB::raw('price'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('excursion_prices', function (Blueprint $table) {
            $table->dropColumn(['price_without_entry', 'price_with_entry']);
        });
    }
};
