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
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique()->comment('Номер автобуса (например, "123" или "А123БВ")');
            $table->string('model')->nullable()->comment('Модель автобуса (например, "Mercedes Sprinter")');
            $table->integer('capacity')->default(50)->comment('Вместимость - количество мест');
            $table->string('license_plate')->nullable()->comment('Государственный номер');
            $table->boolean('is_active')->default(true)->comment('Активен ли автобус');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buses');
    }
};
