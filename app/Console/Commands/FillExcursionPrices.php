<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Excursion;

class FillExcursionPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excursions:fill-prices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создать цены для всех экскурсий, у которых их нет';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $excursions = Excursion::with('prices')->get();
        $created = 0;

        foreach ($excursions as $excursion) {
            if ($excursion->prices->isEmpty()) {
                $excursion->createDefaultPrices();
                $created++;
                $this->info("Созданы цены для экскурсии: {$excursion->title} (ID: {$excursion->id})");
            }
        }

        if ($created === 0) {
            $this->info('Все экскурсии уже имеют цены.');
        } else {
            $this->info("Создано цен для {$created} экскурсий.");
        }

        return Command::SUCCESS;
    }
}
