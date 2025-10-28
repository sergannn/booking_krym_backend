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
        Schema::create('excursion_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excursion_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained('moonshine_users')->onDelete('cascade');
            $table->enum('role_in_excursion', ['driver', 'guide']);
            $table->timestamps();
            
            $table->unique(['excursion_id', 'user_id', 'role_in_excursion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excursion_user');
    }
};
