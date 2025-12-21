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
        Schema::create('archived_excursions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_excursion_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('date_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('payload')->nullable(); // Полный слепок исходной записи
            $table->string('archived_reason')->nullable();
            $table->timestamp('archived_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archived_excursions');
    }
};






