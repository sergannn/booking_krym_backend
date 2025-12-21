<?php

namespace App\Console\Commands;

use App\Models\ArchivedBooking;
use App\Models\ArchivedExcursion;
use App\Models\Excursion;
use App\Models\ScheduleTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ArchiveDuplicateExcursionsNotInSchedule extends Command
{
    protected $signature = 'excursions:archive-duplicates-not-in-schedule {--dry-run : Только показать, без изменений}';

    protected $description = 'Архивировать дублирующиеся экскурсии (по названию), которые отсутствуют в расписании. Оставляет по одной экскурсии на название.';

    public function handle(): int
    {
        $allowedTitles = $this->allowedTitles();
        
        $this->info('Разрешенные названия в расписании: ' . implode(', ', $allowedTitles));
        
        // Находим все названия экскурсий, которые дублируются и не в расписании
        $duplicateTitles = Excursion::query()
            ->select('title')
            ->whereNotIn('title', $allowedTitles)
            ->groupBy('title')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('title')
            ->toArray();

        if (empty($duplicateTitles)) {
            $this->info('Нет дублирующихся экскурсий вне расписания.');
            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $this->info("Найдено " . count($duplicateTitles) . " дублирующихся названий вне расписания: " . implode(', ', $duplicateTitles));

        $totalArchived = 0;

        foreach ($duplicateTitles as $title) {
            $excursions = Excursion::query()
                ->with(['bookings.walletTransactions'])
                ->where('title', $title)
                ->orderBy('date_time', 'desc') // Сначала самые новые
                ->get();

            if ($excursions->count() <= 1) {
                continue;
            }

            // Оставляем самую актуальную (самую новую по дате)
            $keep = $excursions->first();
            $extras = $excursions->skip(1);

            $this->info("Название '{$title}': оставляем #{$keep->id} ({$keep->date_time}), архивируем " . $extras->count() . " шт.");

            if ($dryRun) {
                foreach ($extras as $extra) {
                    $this->line("[DRY-RUN] Архивировать экскурсию #{$extra->id} ({$extra->date_time})");
                }
                $totalArchived += $extras->count();
                continue;
            }

            $this->archiveExtras($extras);
            $totalArchived += $extras->count();
        }

        if ($dryRun) {
            $this->info("[DRY-RUN] Всего будет архивировано: {$totalArchived} экскурсий");
        } else {
            $this->info("Всего архивировано: {$totalArchived} экскурсий");
        }

        return self::SUCCESS;
    }

    /**
     * @param Collection<int, Excursion> $extras
     */
    private function archiveExtras(Collection $extras): void
    {
        foreach ($extras as $excursion) {
            DB::transaction(function () use ($excursion) {
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
                        'archived_reason' => 'Duplicate excursion not in schedule',
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
                    'payload' => $excursion->toArray(),
                    'archived_reason' => 'Duplicate excursion not in schedule',
                    'archived_at' => now(),
                ]);

                $excursion->delete();
            });

            $this->info("Архивирована экскурсия #{$excursion->id} {$excursion->title}");
        }
    }

    private function allowedTitles(): array
    {
        // Получаем названия из базы данных (ScheduleTemplate)
        $dbTitles = ScheduleTemplate::query()
            ->pluck('title')
            ->map(fn ($title) => trim((string) $title))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Получаем названия из config
        $configTemplates = config('excursion_schedule.templates', []);
        $configTitles = collect($configTemplates)
            ->pluck('title')
            ->filter()
            ->map(fn ($title) => trim((string) $title))
            ->unique()
            ->values()
            ->all();

        // Объединяем и убираем дубликаты
        return collect(array_merge($dbTitles, $configTitles))
            ->unique()
            ->values()
            ->all();
    }
}





