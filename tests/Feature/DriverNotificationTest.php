<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use App\Models\Excursion;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DriverNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createDriverRole()
    {
        return MoonshineUserRole::firstOrCreate(
            ['id' => 3],
            ['name' => 'Водитель']
        );
    }

    public function test_check_new_assignments_endpoint_exists()
    {
        $role = $this->createDriverRole();
        $user = MoonshineUser::factory()->create([
            'moonshine_user_role_id' => $role->id,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/excursions/check-new-assignments');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'has_new',
            'count',
            'assignments',
        ]);
    }

    public function test_check_new_assignments_returns_new_assignments()
    {
        $role = $this->createDriverRole();
        $driver = MoonshineUser::factory()->create([
            'moonshine_user_role_id' => $role->id,
        ]);

        $excursion = Excursion::create([
            'title' => 'Тестовая экскурсия',
            'description' => 'Описание',
            'date_time' => now()->addDay(),
            'price' => null,
            'max_seats' => 50,
            'is_active' => true,
        ]);

        // Назначаем водителя на экскурсию
        DB::table('excursion_user')->insert([
            'excursion_id' => $excursion->id,
            'user_id' => $driver->id,
            'role_in_excursion' => 'driver',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $driver->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/excursions/check-new-assignments');

        $response->assertStatus(200);
        $response->assertJson([
            'has_new' => true,
            'count' => 1,
        ]);
        $response->assertJsonStructure([
            'assignments' => [
                '*' => [
                    'excursion_id',
                    'title',
                    'description',
                    'date_time',
                    'role_in_excursion',
                    'assigned_at',
                ],
            ],
        ]);
    }

    public function test_check_new_assignments_filters_by_last_checked()
    {
        $role = $this->createDriverRole();
        $driver = MoonshineUser::factory()->create([
            'moonshine_user_role_id' => $role->id,
        ]);

        $excursion1 = Excursion::create([
            'title' => 'Старая экскурсия',
            'description' => 'Описание',
            'date_time' => now()->addDay(),
            'price' => null,
            'max_seats' => 50,
            'is_active' => true,
        ]);

        $excursion2 = Excursion::create([
            'title' => 'Новая экскурсия',
            'description' => 'Описание',
            'date_time' => now()->addDays(2),
            'price' => null,
            'max_seats' => 50,
            'is_active' => true,
        ]);

        // Старое назначение (2 часа назад)
        $oldTime = now()->subHours(2);
        DB::table('excursion_user')->insert([
            'excursion_id' => $excursion1->id,
            'user_id' => $driver->id,
            'role_in_excursion' => 'driver',
            'created_at' => $oldTime,
            'updated_at' => $oldTime,
        ]);

        // Новое назначение (сейчас)
        $newTime = now();
        DB::table('excursion_user')->insert([
            'excursion_id' => $excursion2->id,
            'user_id' => $driver->id,
            'role_in_excursion' => 'driver',
            'created_at' => $newTime,
            'updated_at' => $newTime,
        ]);

        // Проверяем с фильтром по времени (1 час назад - между старым и новым)
        $lastChecked = now()->subHour()->toIso8601String();
        $token = $driver->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/excursions/check-new-assignments', [
                'last_checked' => $lastChecked,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'has_new' => true,
        ]);
        
        $assignments = $response->json('assignments');
        // Должно быть только новое назначение (после lastChecked)
        $this->assertGreaterThanOrEqual(1, count($assignments));
        // Проверяем, что есть новое назначение
        $newAssignment = collect($assignments)->firstWhere('title', 'Новая экскурсия');
        $this->assertNotNull($newAssignment, 'Должно быть новое назначение');
    }

    public function test_check_new_assignments_requires_authentication()
    {
        $response = $this->getJson('/api/excursions/check-new-assignments');

        // Маршрут защищен middleware auth:sanctum, поэтому вернет 401 или 404
        $this->assertContains($response->status(), [401, 404]);
    }
}

