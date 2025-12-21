<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\ScheduleTemplate;
use App\Models\Excursion;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Добавляем колонку schedule_template_id
        Schema::table('excursions', function (Blueprint $table) {
            $table->foreignId('schedule_template_id')->nullable()->after('id');
        });
        
        // Заполняем schedule_template_id на основе совпадения title
        $templates = ScheduleTemplate::all()->keyBy('title');
        
        Excursion::chunk(100, function ($excursions) use ($templates) {
            foreach ($excursions as $excursion) {
                $template = $templates->get($excursion->title);
                if ($template) {
                    $excursion->schedule_template_id = $template->id;
                    $excursion->save();
                }
            }
        });
        
        // Удаляем экскурсии, которых нет в расписании
        $templateTitles = ScheduleTemplate::pluck('title')->toArray();
        Excursion::whereNotIn('title', $templateTitles)->delete();
        
        // Теперь делаем поле обязательным и добавляем внешний ключ
        Schema::table('excursions', function (Blueprint $table) {
            $table->foreignId('schedule_template_id')->nullable(false)->change();
            $table->foreign('schedule_template_id')
                ->references('id')
                ->on('schedule_templates')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('excursions', function (Blueprint $table) {
            $table->dropForeign(['schedule_template_id']);
            $table->dropColumn('schedule_template_id');
        });
    }
};
