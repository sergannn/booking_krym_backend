<?php

namespace App\Console\Commands;

use App\Models\ArchivedBooking;
use App\Models\ArchivedExcursion;
use App\Models\Excursion;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ArchivePastAndExactDuplicates extends Command
{
    protected $signature = 'excursions:archive-past-and-duplicates {--dry-run : Только показать, без изменений} {--days=30 : Архивировать прошедшие экскурсии старше N дней}';

    protected $description = 'Архивировать прошедшие экскурсии и точные дубликаты (одинаковое название + дата/время)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $days = (int) $this->option('days');
        
        $this->info("Архивирование прошедших экскурсий (старше {$days} дней) и точных дубликатов...");
        
        $toArchive = collect();
        
        // 1. Прошедшие экскурсии
        $pastDate = now()->subDays($days);
        $pastExcursions = Excursion::query()
            ->where('date_time', '<', $pastDate)
            ->with(['bookings.walletTransactions'])
            ->get();
        
        $this->info("Найдено прошедших экскурсий: {$pastExcursions->count()}");
        $toArchive = $toArchive->merge($pastExcursions);
        
        // 2. Точные дубликаты (одинаковое название + дата/время)
        $exactDuplicates = DB::table('excursions')
            ->select('title', 'date_time', DB::raw('COUNT(*) as count'), DB::raw('GROUP_CONCAT(id ORDER BY id) as ids'))
            ->groupBy('title', 'date_time')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        
        $this->info("Найдено групп с точными дубликатами: {$exactDuplicates->count()}");
        
        foreach ($exactDuplicates as $dup) {
            $ids = explode(',', $dup->ids);
            // Оставляем первую (самую старую по ID), остальные архивируем
            $idsToArchive = array_slice($ids, 1);
            
            $excursions = Excursion::query()
                ->whereIn('id', $idsToArchive)
                ->with(['bookings.walletTransactions'])
                ->get();
            
            $toArchive = $toArchive->merge($excursions);
        }
        
        if ($toArchive->isEmpty()) {
            $this->info('Нет экскурсий для архивации.');
            return self::SUCCESS;
        }
        
        $this->info("Всего экскурсий для архивации: {$toArchive->count()}");
        
        if ($dryRun) {
            foreach ($toArchive->take(30) as $excursion) {
                $reason = $excursion->date_time && $excursion->date_time < $pastDate 
                    ? 'Прошедшая экскурсия' 
                    : 'Точный дубликат';
                $this->line("[DRY-RUN] Архивировать экскурсию #{$excursion->id} '{$excursion->title}' на {$excursion->date_time} ({$reason})");
            }
            if ($toArchive->count() > 30) {
                $this->line("... и еще " . ($toArchive->count() - 30) . " экскурсий");
            }
            return self::SUCCESS;
        }
        
        $archived = 0;
        foreach ($toArchive->chunk(50) as $chunk) {
            foreach ($chunk as $excursion) {
                $reason = $excursion->date_time && $excursion->date_time < $pastDate 
                    ? 'Past excursion' 
                    : 'Exact duplicate';
                $this->archiveExcursion($excursion, $reason);
                $archived++;
            }
        }
        
        $this->info("Архивировано {$archived} экскурсий");
        return self::SUCCESS;
    }
    
    private function archiveExcursion(Excursion $excursion, string $reason): void
    {
        DB::transaction(function () use ($excursion, $reason) {
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
                    'archived_reason' => $reason,
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
                'archived_reason' => $reason,
                'archived_at' => now(),
            ]);
            
            $excursion->delete();
        });
        
        $this->info("Архивирована экскурсия #{$excursion->id} {$excursion->title}");
    }
}





