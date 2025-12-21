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
            // Поля цен для взрослых
            $table->decimal('price_adult_without_entry', 10, 2)->nullable()->after('price');
            $table->decimal('price_adult_with_entry', 10, 2)->nullable()->after('price_adult_without_entry');
            $table->decimal('price_adult_seller_commission', 5, 2)->nullable()->default(10)->after('price_adult_with_entry');
            $table->decimal('price_adult_partner_commission', 5, 2)->nullable()->default(10)->after('price_adult_seller_commission');
            
            // Поля цен для детей
            $table->decimal('price_child_without_entry', 10, 2)->nullable()->after('price_adult_partner_commission');
            $table->decimal('price_child_with_entry', 10, 2)->nullable()->after('price_child_without_entry');
            $table->decimal('price_child_seller_commission', 5, 2)->nullable()->default(10)->after('price_child_with_entry');
            $table->decimal('price_child_partner_commission', 5, 2)->nullable()->default(10)->after('price_child_seller_commission');
            
            // Поля цен для пенсионеров
            $table->decimal('price_senior_without_entry', 10, 2)->nullable()->after('price_child_partner_commission');
            $table->decimal('price_senior_with_entry', 10, 2)->nullable()->after('price_senior_without_entry');
            $table->decimal('price_senior_seller_commission', 5, 2)->nullable()->default(10)->after('price_senior_with_entry');
            $table->decimal('price_senior_partner_commission', 5, 2)->nullable()->default(10)->after('price_senior_seller_commission');
            
            // Поля цен для инвалидов
            $table->decimal('price_disabled_without_entry', 10, 2)->nullable()->after('price_senior_partner_commission');
            $table->decimal('price_disabled_with_entry', 10, 2)->nullable()->after('price_disabled_without_entry');
            $table->decimal('price_disabled_seller_commission', 5, 2)->nullable()->default(10)->after('price_disabled_with_entry');
            $table->decimal('price_disabled_partner_commission', 5, 2)->nullable()->default(10)->after('price_disabled_seller_commission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('excursions', function (Blueprint $table) {
            $columns = [
                'price_adult_without_entry', 'price_adult_with_entry',
                'price_adult_seller_commission', 'price_adult_partner_commission',
                'price_child_without_entry', 'price_child_with_entry',
                'price_child_seller_commission', 'price_child_partner_commission',
                'price_senior_without_entry', 'price_senior_with_entry',
                'price_senior_seller_commission', 'price_senior_partner_commission',
                'price_disabled_without_entry', 'price_disabled_with_entry',
                'price_disabled_seller_commission', 'price_disabled_partner_commission',
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
