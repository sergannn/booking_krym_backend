<?php

namespace App\Console\Commands;

use App\Models\ArchivedBooking;
use App\Models\ArchivedExcursion;
use App\Models\Excursion;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DedupeExcursionsByTitle extends Command
{
    protected $signature = 'excursions:dedupe-by-title {--dry-run : Только показать, без изменений}';

    protected $description = 'Оставить по одной экскурсии на title, остальные архивировать (с бронированиями)';

    public function handle(): int
    {
        $titles = Excursion::query()->distinct()->pluck('title');
        $dryRun = $this->option('dry-run');
        $now = now();

        foreach ($titles as $title) {
            $excursions = Excursion::query()
                ->with(['bookings.walletTransactions'])
                ->where('title', $title)
                ->orderBy('date_time')
                ->get();

            if ($excursions->count() <= 1) {
                continue;
            }

            // Оставляем ближайшую будущую, если есть, иначе самую позднюю
            $keep = $excursions->first(function ($excursion) use ($now) {
                return $excursion->date_time && $excursion->date_time >= $now;
            }) ?? $excursions->last();

            $extras = $excursions->where('id', '!=', $keep->id);

            $this->info("Title '{$title}': оставляем #{$keep->id}, архивируем ".$extras->count()." шт.");

            if ($dryRun) {
                foreach ($extras as $extra) {
                    $this->line("[DRY-RUN] Архивировать экскурсию #{$extra->id} ({$extra->date_time})");
                }
                continue;
            }

            $this->archiveExtras($extras);
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
                        'archived_reason' => 'Dedup by title',
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
                    'archived_reason' => 'Dedup by title',
                    'archived_at' => now(),
                ]);

                $excursion->delete();
            });

            $this->info("Архивирована экскурсия #{$excursion->id} {$excursion->title}");
        }
    }
}






