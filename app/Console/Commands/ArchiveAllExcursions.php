<?php

namespace App\Console\Commands;

use App\Models\ArchivedBooking;
use App\Models\ArchivedExcursion;
use App\Models\Excursion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveAllExcursions extends Command
{
    protected $signature = 'excursions:archive-all {--dry-run : Только показать, без изменений}';

    protected $description = 'Архивировать все экскурсии из базы данных';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('Поиск всех экскурсий...');
        
        $allExcursions = Excursion::query()
            ->with(['bookings.walletTransactions'])
            ->orderBy('date_time', 'desc')
            ->get();
        
        if ($allExcursions->isEmpty()) {
            $this->info('Нет экскурсий для архивации.');
            return self::SUCCESS;
        }
        
        $this->info("Найдено экскурсий: {$allExcursions->count()}");
        
        if ($dryRun) {
            foreach ($allExcursions->take(30) as $excursion) {
                $this->line("[DRY-RUN] Архивировать экскурсию #{$excursion->id} '{$excursion->title}' на {$excursion->date_time} (бронирований: {$excursion->bookings->count()})");
            }
            if ($allExcursions->count() > 30) {
                $this->line("... и еще " . ($allExcursions->count() - 30) . " экскурсий");
            }
            return self::SUCCESS;
        }
        
        if (!$this->confirm('Вы уверены, что хотите архивировать ВСЕ экскурсии? Это действие необратимо!')) {
            $this->info('Операция отменена.');
            return self::SUCCESS;
        }
        
        $archived = 0;
        $bar = $this->output->createProgressBar($allExcursions->count());
        $bar->start();
        
        foreach ($allExcursions->chunk(50) as $chunk) {
            foreach ($chunk as $excursion) {
                $this->archiveExcursion($excursion);
                $archived++;
                $bar->advance();
            }
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("Архивировано {$archived} экскурсий");
        return self::SUCCESS;
    }
    
    private function archiveExcursion(Excursion $excursion): void
    {
        DB::transaction(function () use ($excursion) {
            $payload = $excursion->toArray();
            
            // Архивируем бронирования
            foreach ($excursion->bookings as $booking) {
                $walletPayload = $booking->walletTransactions->toArray();
                
                ArchivedBooking::create([
                    'original_booking_id' => $booking->id,
                    'original_excursion_id' => $excursion->id,
                    'excursion_id' => $booking->excursion_id,
                    'bus_seat_id' => $booking->bus_seat_id,
                    'booked_by' => $booking->booked_by,
                    'price' => $booking->price,
                    'customer_name' => $booking->customer_name,
                    'customer_phone' => $booking->customer_phone,
                    'passenger_type' => $booking->passenger_type,
                    'stop_id' => $booking->stop_id,
                    'booked_at' => $booking->booked_at,
                    'payload' => $booking->toArray(),
                    'wallet_transactions' => $walletPayload,
                    'archived_reason' => 'All excursions archived',
                    'archived_at' => now(),
                ]);
                
                $booking->walletTransactions()->delete();
                $booking->delete();
            }
            
            // Архивируем экскурсию
            ArchivedExcursion::create([
                'original_excursion_id' => $excursion->id,
                'title' => $excursion->title,
                'description' => $excursion->description,
                'date_time' => $excursion->date_time,
                'is_active' => $excursion->is_active,
                'payload' => $payload,
                'archived_reason' => 'All excursions archived',
                'archived_at' => now(),
            ]);
            
            $excursion->delete();
        });
    }
}
