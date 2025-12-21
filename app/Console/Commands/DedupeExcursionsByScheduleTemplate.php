<?php

namespace App\Console\Commands;

use App\Models\ArchivedBooking;
use App\Models\ArchivedExcursion;
use App\Models\Excursion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeExcursionsByScheduleTemplate extends Command
{
    protected $signature = 'excursions:dedupe-by-schedule-template {--dry-run : Только показать, без изменений}';

    protected $description = 'Оставить по одной экскурсии на schedule_template_id, остальные архивировать';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('Поиск дубликатов экскурсий по schedule_template_id...');
        
        // Получаем все уникальные schedule_template_id
        $templateIds = Excursion::whereNotNull('schedule_template_id')
            ->distinct()
            ->pluck('schedule_template_id');
        
        $archived = 0;
        
        foreach ($templateIds as $templateId) {
            $excursions = Excursion::query()
                ->with(['bookings.walletTransactions'])
                ->where('schedule_template_id', $templateId)
                ->orderBy('id', 'asc') // Оставляем первую (самую старую по ID)
                ->get();
            
            if ($excursions->count() <= 1) {
                continue;
            }
            
            // Оставляем первую экскурсию
            $keep = $excursions->first();
            $extras = $excursions->where('id', '!=', $keep->id);
            
            $template = $keep->scheduleTemplate;
            $title = $template ? $template->title : "Template #{$templateId}";
            
            $this->info("Template '{$title}': оставляем #{$keep->id}, архивируем {$extras->count()} шт.");
            
            if ($dryRun) {
                foreach ($extras as $extra) {
                    $this->line("[DRY-RUN] Архивировать экскурсию #{$extra->id}");
                }
                continue;
            }
            
            // Архивируем лишние экскурсии
            foreach ($extras as $extra) {
                $this->archiveExcursion($extra);
                $archived++;
            }
        }
        
        if ($dryRun) {
            $this->info("Будет заархивировано {$archived} экскурсий");
        } else {
            $this->info("Заархивировано {$archived} экскурсий");
        }
        
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
                    'archived_reason' => 'Duplicate by schedule_template_id',
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
                'archived_reason' => 'Duplicate by schedule_template_id',
                'archived_at' => now(),
            ]);
            
            $excursion->delete();
        });
    }
}
