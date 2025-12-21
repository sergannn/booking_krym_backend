<?php

namespace App\Console\Commands;

use App\Services\ExcursionScheduler;
use Illuminate\Console\Command;

class CreateExcursionsFromSchedule extends Command
{
    protected $signature = 'excursions:create-from-schedule {--days=15 : Количество дней вперед для создания экскурсий}';

    protected $description = 'Создать экскурсии на основе расписания из БД';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        
        $this->info("Создание экскурсий на основе расписания на {$days} дней вперед...");
        
        $scheduler = new ExcursionScheduler();
        $scheduler->ensureUpcoming($days);
        
        $this->info("Экскурсии созданы успешно!");
        return self::SUCCESS;
    }
}
