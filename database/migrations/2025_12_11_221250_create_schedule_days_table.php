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
        Schema::create('schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_template_id')->constrained('schedule_templates')->onDelete('cascade');
            $table->tinyInteger('weekday')->comment('1-7: Понедельник-Воскресенье');
            $table->time('time')->nullable()->comment('Время начала экскурсии');
            $table->timestamps();
            
            // Уникальность: одна запись на день недели для каждого шаблона
            $table->unique(['schedule_template_id', 'weekday']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_days');
    }
};
