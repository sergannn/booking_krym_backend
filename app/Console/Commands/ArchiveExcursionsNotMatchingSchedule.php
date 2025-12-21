<?php

namespace App\Console\Commands;

use App\Models\ArchivedBooking;
use App\Models\ArchivedExcursion;
use App\Models\Excursion;
use App\Models\ScheduleTemplate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ArchiveExcursionsNotMatchingSchedule extends Command
{
    protected $signature = 'excursions:archive-not-matching-schedule {--dry-run : Только показать, без изменений} {--past : Также архивировать прошедшие экскурсии}';

    protected $description = 'Архивировать экскурсии, которые не соответствуют расписанию (созданы на неправильные дни/время)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $archivePast = $this->option('past');
        
        $scheduleTemplates = $this->getScheduleTemplates();
        
        $this->info('Проверка соответствия экскурсий расписанию...');
        
        $query = Excursion::query()
            ->with(['bookings.walletTransactions'])
            ->orderBy('date_time', 'asc');
        
        if (!$archivePast) {
            $query->where('date_time', '>=', now());
        }
        
        $totalCount = $query->count();
        $this->info("Всего экскурсий для проверки: {$totalCount}");
        
        $toArchive = collect();
        
        $query->chunkById(50, function (Collection $chunk) use ($scheduleTemplates, &$toArchive, $archivePast) {
            foreach ($chunk as $excursion) {
                // Пропускаем прошедшие экскурсии, если не указан флаг --past
                if (!$archivePast && $excursion->date_time && $excursion->date_time < now()) {
                    continue;
                }
                
                // Проверяем, соответствует ли экскурсия расписанию
                if (!$this->matchesSchedule($excursion, $scheduleTemplates)) {
                    $toArchive->push($excursion);
                }
            }
        });
        
        if ($toArchive->isEmpty()) {
            $this->info('Нет экскурсий для архивации.');
            return self::SUCCESS;
        }
        
        $this->info("Найдено {$toArchive->count()} экскурсий, не соответствующих расписанию");
        
        if ($dryRun) {
            foreach ($toArchive->take(20) as $excursion) {
                $this->line("[DRY-RUN] Архивировать экскурсию #{$excursion->id} '{$excursion->title}' на {$excursion->date_time}");
            }
            if ($toArchive->count() > 20) {
                $this->line("... и еще " . ($toArchive->count() - 20) . " экскурсий");
            }
            return self::SUCCESS;
        }
        
        $archived = 0;
        foreach ($toArchive->chunk(50) as $chunk) {
            foreach ($chunk as $excursion) {
                $this->archiveExcursion($excursion);
                $archived++;
            }
        }
        
        $this->info("Архивировано {$archived} экскурсий");
        return self::SUCCESS;
    }
    
    private function matchesSchedule(Excursion $excursion, array $scheduleTemplates): bool
    {
        $title = trim($excursion->title);
        
        // Ищем шаблон с таким названием
        $template = collect($scheduleTemplates)->first(function ($tpl) use ($title) {
            return trim($tpl['title']) === $title;
        });
        
        if (!$template) {
            // Название не найдено в расписании
            return false;
        }
        
        if (!$excursion->date_time) {
            // Нет даты - не соответствует
            return false;
        }
        
        $date = Carbon::parse($excursion->date_time);
        $weekday = $date->isoWeekday(); // 1-7 (понедельник-воскресенье)
        $time = $date->format('H:i');
        
        $schedule = $template['schedule'] ?? [];
        $scheduledTime = $schedule[$weekday] ?? null;
        
        if (!$scheduledTime) {
            // В этот день недели такой экскурсии нет
            return false;
        }
        
        // Проверяем время (с допуском в 1 минуту)
        $scheduledTimeCarbon = Carbon::parse($scheduledTime);
        $excursionTimeCarbon = Carbon::parse($time);
        
        return abs($scheduledTimeCarbon->diffInMinutes($excursionTimeCarbon)) <= 1;
    }
    
    private function getScheduleTemplates(): array
    {
        // Получаем из базы данных
        $dbTemplates = ScheduleTemplate::all()->map(function ($template) {
            return [
                'title' => trim($template->title),
                'schedule' => $template->schedule ?? [],
            ];
        })->toArray();
        
        // Получаем из config
        $configTemplates = collect(config('excursion_schedule.templates', []))
            ->map(function ($template) {
                return [
                    'title' => trim($template['title'] ?? ''),
                    'schedule' => $template['schedule'] ?? [],
                ];
            })
            ->filter(fn($t) => !empty($t['title']))
            ->toArray();
        
        // Объединяем, приоритет у БД
        $merged = [];
        foreach (array_merge($dbTemplates, $configTemplates) as $template) {
            $title = $template['title'];
            if (!isset($merged[$title])) {
                $merged[$title] = $template;
            }
        }
        
        return array_values($merged);
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
                    'archived_reason' => 'Does not match schedule',
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
                'archived_reason' => 'Does not match schedule',
                'archived_at' => now(),
            ]);
            
            $excursion->delete();
        });
        
        $this->info("Архивирована экскурсия #{$excursion->id} {$excursion->title}");
    }
}





