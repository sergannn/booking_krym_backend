<?php

namespace App\Console\Commands;

use App\Models\ScheduleTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ImportScheduleTemplates extends Command
{
    protected $signature = 'schedule:import-templates {--fresh : Очистить таблицу перед импортом}';

    protected $description = 'Импортировать шаблоны расписания из config/excursion_schedule.php в БД schedule_templates';

    public function handle(): int
    {
        $templates = Config::get('excursion_schedule.templates', []);

        if (empty($templates)) {
            $this->warn('В config/excursion_schedule.php нет шаблонов.');
            return self::SUCCESS;
        }

        if ($this->option('fresh')) {
            DB::table('schedule_templates')->truncate();
            $this->info('Таблица schedule_templates очищена.');
        }

        $created = 0;
        foreach ($templates as $tpl) {
            $title = trim((string)($tpl['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            ScheduleTemplate::updateOrCreate(
                ['title' => $title],
                [
                    'description' => $tpl['description'] ?? null,
                    'schedule' => $tpl['schedule'] ?? [],
                ],
            );
            $created++;
        }

        $this->info("Импортировано/обновлено записей: {$created}");
        return self::SUCCESS;
    }
}






