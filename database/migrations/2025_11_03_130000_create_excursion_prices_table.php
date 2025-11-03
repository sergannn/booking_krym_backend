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
        Schema::create('excursion_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excursion_id')->constrained()->onDelete('cascade');
            $table->enum('passenger_type', ['adult', 'child', 'senior', 'disabled']);
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['excursion_id', 'passenger_type']);
        });

        $types = ['adult', 'child', 'senior', 'disabled'];
        $now = now();
        $excursions = DB::table('excursions')->get(['id', 'price']);

        foreach ($excursions as $excursion) {
            foreach ($types as $type) {
                DB::table('excursion_prices')->insert([
                    'excursion_id' => $excursion->id,
                    'passenger_type' => $type,
                    'price' => $excursion->price,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excursion_prices');
    }
};
