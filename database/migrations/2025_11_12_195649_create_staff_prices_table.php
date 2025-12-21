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
        Schema::create('staff_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excursion_id')->constrained()->onDelete('cascade');
            $table->enum('staff_type', ['driver', 'guide']);
            $table->integer('min_passengers')->default(0); // Минимальное количество пассажиров
            $table->integer('max_passengers')->nullable(); // Максимальное количество пассажиров (null = без ограничений)
            $table->decimal('price', 10, 2);
            $table->timestamps();

            // Уникальность: одна цена для одного типа персонала и диапазона пассажиров на экскурсию
            $table->unique(['excursion_id', 'staff_type', 'min_passengers', 'max_passengers'], 'unique_staff_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_prices');
    }
};
