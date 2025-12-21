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
        Schema::create('seat_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excursion_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained('moonshine_users')->onDelete('cascade');
            $table->date('excursion_date'); // Конкретная дата экскурсии
            $table->integer('seat_number'); // 1 или 2
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason')->nullable(); // Причина запроса (опционально)
            $table->foreignId('reviewed_by')->nullable()->constrained('moonshine_users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            
            // Индекс для быстрого поиска ожидающих запросов
            $table->index(['status', 'excursion_id', 'excursion_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seat_access_requests');
    }
};
