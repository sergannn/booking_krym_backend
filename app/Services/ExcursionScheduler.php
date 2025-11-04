<?php

namespace App\Services;

use App\Models\Excursion;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ExcursionScheduler
{
    public function ensureUpcoming(?int $daysAhead = null): void
    {
        $daysAhead ??= Config::get('excursion_schedule.default_days_ahead', 15);
        $start = Carbon::now()->startOfDay();
        $end = $start->copy()->addDays($daysAhead);

        $templates = Config::get('excursion_schedule.templates', []);
        $defaultMaxSeats = Config::get('excursion_schedule.default_max_seats', 50);
        $defaultTariffs = Config::get('excursion_schedule.default_tariffs', []);

        $dates = $start->toPeriod($end);

        foreach ($dates as $date) {
            $weekday = $date->isoWeekday();

            foreach ($templates as $template) {
                $schedule = Arr::get($template, 'schedule', []);
                if (! isset($schedule[$weekday])) {
                    continue;
                }

                $time = $schedule[$weekday];
                $dateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $time);

                $exists = Excursion::where('title', $template['title'])
                    ->whereDate('date_time', $dateTime->toDateString())
                    ->whereTime('date_time', $dateTime->format('H:i:s'))
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::transaction(function () use ($template, $dateTime, $defaultMaxSeats, $defaultTariffs) {
                    $tariffs = Arr::get($template, 'tariffs', $defaultTariffs);

                    $adultConfig = Arr::get($tariffs, 'adult', []);
                    $adultPrice = is_array($adultConfig)
                        ? Arr::get($adultConfig, 'price', 0)
                        : (float) $adultConfig;

                    $excursion = Excursion::create([
                        'title' => $template['title'],
                        'description' => Arr::get($template, 'description', ''),
                        'date_time' => $dateTime,
                        'price' => $adultPrice,
                        'max_seats' => Arr::get($template, 'max_seats', $defaultMaxSeats),
                        'is_active' => true,
                    ]);

                    foreach ($tariffs as $type => $config) {
                        $price = is_array($config)
                            ? Arr::get($config, 'price', 0)
                            : (float) $config;
                        $sellerCommission = is_array($config)
                            ? Arr::get($config, 'seller_commission_percent', 10)
                            : 10;
                        $partnerCommission = is_array($config)
                            ? Arr::get($config, 'partner_commission_percent', 10)
                            : 10;

                        $excursion->prices()->updateOrCreate(
                            ['passenger_type' => $type],
                            [
                                'price' => $price,
                                'seller_commission_percent' => $sellerCommission,
                                'partner_commission_percent' => $partnerCommission,
                            ]
                        );
                    }
                });
            }
        }
    }
}
