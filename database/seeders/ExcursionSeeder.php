<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Excursion;
use App\Models\BusSeat;

class ExcursionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем тестовые экскурсии
        $excursions = [
            [
                'title' => 'Обзорная экскурсия по городу',
                'description' => 'Познакомьтесь с историей и достопримечательностями нашего города',
                'date_time' => now()->addDays(7)->setTime(10, 0),
                'price' => 1500.00,
                'max_seats' => 50,
                'is_active' => true,
            ],
            [
                'title' => 'Экскурсия в музей',
                'description' => 'Посетите главный музей города с профессиональным гидом',
                'date_time' => now()->addDays(14)->setTime(14, 30),
                'price' => 2000.00,
                'max_seats' => 30,
                'is_active' => true,
            ],
            [
                'title' => 'Вечерняя прогулка по набережной',
                'description' => 'Романтическая прогулка по набережной с видом на закат',
                'date_time' => now()->addDays(21)->setTime(18, 0),
                'price' => 800.00,
                'max_seats' => 25,
                'is_active' => true,
            ],
        ];

        foreach ($excursions as $excursionData) {
            $excursion = Excursion::create($excursionData);
            
            // Создаем места в автобусе для каждой экскурсии
            for ($i = 1; $i <= $excursion->max_seats; $i++) {
                BusSeat::create([
                    'excursion_id' => $excursion->id,
                    'seat_number' => $i,
                    'status' => 'available',
                ]);
            }
        }
    }
}
