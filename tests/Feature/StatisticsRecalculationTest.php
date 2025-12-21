<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Excursion;
use App\Models\Booking;
use App\Models\BusSeat;
use App\Models\ExcursionPrice;
use App\Models\Stop;
use MoonShine\Laravel\Models\MoonshineUser;

class StatisticsRecalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_recalculates_after_booking_cancellation()
    {
        // Создаем пользователя-админа
        $admin = MoonshineUser::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => 1,
        ]);

        // Создаем экскурсию
        $excursion = Excursion::create([
            'title' => 'Test Excursion',
            'description' => 'Test Description',
            'date_time' => now()->addDays(1),
            'max_seats' => 50,
            'is_active' => true,
        ]);

        // Создаем цены (проверяем, не существует ли уже)
        ExcursionPrice::updateOrCreate(
            [
                'excursion_id' => $excursion->id,
                'passenger_type' => 'adult',
            ],
            [
                'price' => 1000,
                'price_without_entry' => 1000,
                'price_with_entry' => 1200,
                'seller_commission_percent' => 10,
                'partner_commission_percent' => 10,
            ]
        );

        // Получаем или создаем место (места могут создаваться автоматически)
        $seat = BusSeat::firstOrCreate(
            [
                'excursion_id' => $excursion->id,
                'seat_number' => 1,
            ],
            [
                'status' => 'available',
            ]
        );

        // Создаем остановку
        $stop = Stop::firstOrCreate(
            ['name' => 'Test Stop'],
            ['order' => 1]
        );

        // Создаем продавца
        $seller = MoonshineUser::firstOrCreate(
            ['email' => 'seller@test.com'],
            [
                'name' => 'Seller',
                'password' => bcrypt('password'),
                'moonshine_user_role_id' => 2,
            ]
        );

        // Создаем первое бронирование
        $booking1 = Booking::create([
            'excursion_id' => $excursion->id,
            'bus_seat_id' => $seat->id,
            'booked_by' => $seller->id,
            'price' => 1000,
            'customer_name' => 'Customer 1',
            'customer_phone' => '1234567890',
            'passenger_type' => 'adult',
            'stop_id' => $stop->id,
            'booked_at' => now(),
        ]);

        $seat->update(['status' => 'booked', 'booked_by' => $seller->id]);

        // Получаем или создаем второе место
        $seat2 = BusSeat::firstOrCreate(
            [
                'excursion_id' => $excursion->id,
                'seat_number' => 2,
            ],
            [
                'status' => 'available',
            ]
        );

        $booking2 = Booking::create([
            'excursion_id' => $excursion->id,
            'bus_seat_id' => $seat2->id,
            'booked_by' => $seller->id,
            'price' => 1000,
            'customer_name' => 'Customer 2',
            'customer_phone' => '1234567891',
            'passenger_type' => 'adult',
            'stop_id' => $stop->id,
            'booked_at' => now(),
        ]);

        $seat2->update(['status' => 'booked', 'booked_by' => $seller->id]);

        // Получаем статистику ДО отмены
        $token = $admin->createToken('test-token')->plainTextToken;
        $response1 = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/excursions/statistics');

        $response1->assertStatus(200);
        $statistics1 = $response1->json('statistics');
        $this->assertNotEmpty($statistics1);
        
        $excursionStat1 = collect($statistics1)->firstWhere('excursion.id', $excursion->id);
        $this->assertNotNull($excursionStat1);
        $this->assertEquals(2, $excursionStat1['bookings_count']);
        $this->assertEquals(2000, $excursionStat1['income']); // 2 бронирования по 1000
        $profitBefore = $excursionStat1['net_profit'];

        // Отменяем первое бронирование
        $response2 = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/bookings/{$booking1->id}", [
                'reason' => 'Test cancellation',
            ]);

        $response2->assertStatus(200);

        // Получаем статистику ПОСЛЕ отмены
        $response3 = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/excursions/statistics');

        $response3->assertStatus(200);
        $statistics3 = $response3->json('statistics');
        
        $excursionStat3 = collect($statistics3)->firstWhere('excursion.id', $excursion->id);
        $this->assertNotNull($excursionStat3);
        $this->assertEquals(1, $excursionStat3['bookings_count']);
        $this->assertEquals(1000, $excursionStat3['income']); // 1 бронирование по 1000
        $profitAfter = $excursionStat3['net_profit'];

        // Прибыль должна уменьшиться
        $this->assertLessThan($profitBefore, $profitAfter);
        $this->assertEquals($profitBefore - 1000 + 100, $profitAfter); // -1000 (выручка) + 100 (комиссия продавца)
    }
}

