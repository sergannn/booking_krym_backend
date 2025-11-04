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
        Schema::table('excursion_prices', function (Blueprint $table) {
            $table->decimal('seller_commission_percent', 5, 2)->default(10)->after('price');
            $table->decimal('partner_commission_percent', 5, 2)->default(10)->after('seller_commission_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('excursion_prices', function (Blueprint $table) {
            $table->dropColumn(['seller_commission_percent', 'partner_commission_percent']);
        });
    }
};
