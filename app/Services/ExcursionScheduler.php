<?php

namespace App\Services;

use App\Models\Excursion;
use App\Models\ScheduleTemplate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ExcursionScheduler
{
    /**
     * Убеждаемся, что для каждого шаблона расписания есть экскурсия
     * Теперь экскурсии - это шаблоны, а не конкретные даты
     */
    public function ensureUpcoming(?int $daysAhead = null): void
    {
        $defaultMaxSeats = Config::get('excursion_schedule.default_max_seats', 50);
        $defaultTariffs = Config::get('excursion_schedule.default_tariffs', []);

        // Получаем все шаблоны из БД
        $templates = ScheduleTemplate::all();

        foreach ($templates as $template) {
            // Проверяем, есть ли уже экскурсия для этого шаблона
            $exists = Excursion::where('schedule_template_id', $template->id)->exists();

            if ($exists) {
                continue;
            }

            // Создаем экскурсию для шаблона (без конкретной даты)
            DB::transaction(function () use ($template, $defaultMaxSeats, $defaultTariffs) {
                $adultConfig = $defaultTariffs['adult'] ?? [];
                $adultPrice = is_array($adultConfig)
                    ? ($adultConfig['price'] ?? 0)
                    : (float) $adultConfig;

                $excursion = Excursion::create([
                    'schedule_template_id' => $template->id,
                    'title' => $template->title,
                    'description' => $template->description ?? '',
                    'date_time' => null, // Экскурсии теперь без конкретной даты
                    'price' => $adultPrice,
                    'max_seats' => $defaultMaxSeats,
                    'is_active' => true,
                ]);

                // Создаем цены для всех типов пассажиров
                foreach ($defaultTariffs as $type => $config) {
                    $price = is_array($config)
                        ? ($config['price'] ?? 0)
                        : (float) $config;
                    $sellerCommission = is_array($config)
                        ? ($config['seller_commission_percent'] ?? 10)
                        : 10;
                    $partnerCommission = is_array($config)
                        ? ($config['partner_commission_percent'] ?? 10)
                        : 10;

                    $excursion->prices()->create([
                        'passenger_type' => $type,
                        'price' => null, // Основная цена не используется
                        'price_without_entry' => $price,
                        'price_with_entry' => $price,
                        'seller_commission_percent' => $sellerCommission,
                        'partner_commission_percent' => $partnerCommission,
                    ]);
                }
            });
        }
    }
}
