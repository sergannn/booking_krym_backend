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
        Schema::table('excursions', function (Blueprint $table) {
            // Поля цен для спеццены
            $table->decimal('price_special_without_entry', 10, 2)->nullable()->after('price_disabled_partner_commission');
            $table->decimal('price_special_with_entry', 10, 2)->nullable()->after('price_special_without_entry');
            $table->decimal('price_special_seller_commission', 5, 2)->nullable()->default(10)->after('price_special_with_entry');
            $table->decimal('price_special_partner_commission', 5, 2)->nullable()->default(10)->after('price_special_seller_commission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('excursions', function (Blueprint $table) {
            $columns = [
                'price_special_without_entry',
                'price_special_with_entry',
                'price_special_seller_commission',
                'price_special_partner_commission',
            ];
            
            // Проверяем наличие колонок перед удалением
            $existingColumns = [];
            foreach ($columns as $column) {
                if (Schema::hasColumn('excursions', $column)) {
                    $existingColumns[] = $column;
                }
            }
            
            if (!empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
