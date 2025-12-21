<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleDay;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Переносим данные из JSON поля schedule в таблицу schedule_days
        // Используем прямой запрос к БД, так как модель может не иметь поле schedule
        $templates = DB::table('schedule_templates')->get();
        
        foreach ($templates as $template) {
            $schedule = json_decode($template->schedule ?? '{}', true);
            
            if (is_array($schedule)) {
                foreach ($schedule as $weekday => $time) {
                    // Пропускаем пустые значения
                    if ($time !== false && $time !== null && $time !== '') {
                        ScheduleDay::updateOrCreate(
                            [
                                'schedule_template_id' => $template->id,
                                'weekday' => (int)$weekday,
                            ],
                            [
                                'time' => $time,
                            ]
                        );
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Очищаем таблицу schedule_days
        ScheduleDay::truncate();
    }
};
