<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use App\Models\BusSeat;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем роль "Продавец" если её нет
        $sellerRole = MoonshineUserRole::firstOrCreate([
            'name' => 'Продавец'
        ]);

        // Создаем тестовых продавцов
        $sellers = [
            [
                'name' => 'Анна Петрова',
                'email' => 'anna@excursion.ru',
                'password' => bcrypt('password'),
                'moonshine_user_role_id' => $sellerRole->id,
            ],
            [
                'name' => 'Иван Сидоров',
                'email' => 'ivan@excursion.ru',
                'password' => bcrypt('password'),
                'moonshine_user_role_id' => $sellerRole->id,
            ],
            [
                'name' => 'Мария Козлова',
                'email' => 'maria@excursion.ru',
                'password' => bcrypt('password'),
                'moonshine_user_role_id' => $sellerRole->id,
            ],
        ];

        foreach ($sellers as $sellerData) {
            MoonshineUser::firstOrCreate(
                ['email' => $sellerData['email']],
                $sellerData
            );
        }

        // Забронируем некоторые места
        $this->bookRandomSeats();
    }

    private function bookRandomSeats(): void
    {
        $sellers = MoonshineUser::whereHas('moonshineUserRole', function($query) {
            $query->where('name', 'Продавец');
        })->get();

        if ($sellers->isEmpty()) {
            return;
        }

        // Получаем все свободные места
        $availableSeats = BusSeat::where('status', 'available')->get();

        // Забронируем случайные места (примерно 30% от общего количества)
        $seatsToBook = $availableSeats->random(min(30, $availableSeats->count()));

        foreach ($seatsToBook as $seat) {
            $randomSeller = $sellers->random();
            
            $seat->update([
                'status' => 'booked',
                'booked_by' => $randomSeller->id,
                'booked_at' => now()->subDays(rand(1, 30)),
            ]);
        }
    }
}
