<?php

namespace App\Console\Commands;

use App\Models\ArchivedBooking;
use App\Models\ArchivedExcursion;
use App\Models\Excursion;
use App\Models\ScheduleTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ArchiveExcursionsNotInSchedule extends Command
{
    protected $signature = 'excursions:archive-not-in-schedule {--dry-run : Только показать, без изменений}';

    protected $description = 'Перенести экскурсии, отсутствующие в шаблонах расписания, в архивную таблицу и удалить из основной (вместе с их бронированиями)';

    public function handle(): int
    {
        $allowedTitles = $this->allowedTitles();

        $query = Excursion::query()
            ->whereNotIn('title', $allowedTitles)
            ->orderBy('date_time', 'asc');

        $count = $query->count();
        if ($count === 0) {
            $this->info('Нет экскурсий для архивации.');
            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $this->info("Найдено {$count} экскурсий вне расписания");

        $query->with(['bookings.walletTransactions'])->chunkById(50, function (Collection $chunk) use ($dryRun) {
            foreach ($chunk as $excursion) {
                $payload = $excursion->toArray();

                if ($dryRun) {
                    $this->line("[DRY-RUN] Архивировать экскурсию #{$excursion->id} {$excursion->title} (бронирований: {$excursion->bookings->count()})");
                    continue;
                }

                DB::transaction(function () use ($excursion, $payload) {
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
                            'archived_reason' => 'Parent excursion not in schedule templates',
                            'archived_at' => now(),
                        ]);

                        // Удаляем связанные транзакции и бронирование
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
                        'archived_reason' => 'Title not present in schedule templates',
                        'archived_at' => now(),
                    ]);

                    $excursion->delete();
                });

                $this->info("Архивирована экскурсия #{$excursion->id} {$excursion->title}");
            }
        });

        return self::SUCCESS;
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

