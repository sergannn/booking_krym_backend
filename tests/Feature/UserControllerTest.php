<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use App\Models\WalletTransaction;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Тест получения списка пользователей для админа
     * Проверяет, что API возвращает корректный формат данных без ошибки 500
     */
    public function test_index_returns_users_list_for_admin()
    {
        // Создаем роль админа, если её нет
        $adminRole = MoonshineUserRole::firstOrCreate(
            ['id' => 1],
            ['name' => 'Admin']
        );

        // Создаем админа
        $admin = MoonshineUser::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => $adminRole->id,
            'plain_password' => 'password123',
        ]);

        // Создаем несколько пользователей
        $sellerRole = MoonshineUserRole::firstOrCreate(
            ['id' => 2],
            ['name' => 'Seller']
        );

        $driverRole = MoonshineUserRole::firstOrCreate(
            ['id' => 3],
            ['name' => 'Driver']
        );

        $user1 = MoonshineUser::create([
            'name' => 'Seller User',
            'email' => 'seller@test.com',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => $sellerRole->id,
            'plain_password' => 'seller123',
        ]);

        $user2 = MoonshineUser::create([
            'name' => 'Driver User',
            'email' => 'driver@test.com',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => $driverRole->id,
            'plain_password' => 'driver123',
        ]);

        // Создаем транзакции для проверки баланса
        WalletTransaction::create([
            'user_id' => $user1->id,
            'amount' => 1000.50,
            'description' => 'Test transaction',
        ]);

        WalletTransaction::create([
            'user_id' => $user2->id,
            'amount' => 500.25,
            'description' => 'Test transaction 2',
        ]);

        // Аутентифицируемся как админ
        $token = $admin->createToken('test-token')->plainTextToken;

        // Вызываем API endpoint
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users');

        // Проверяем, что ответ успешный (не 500)
        $response->assertStatus(200);

        // Проверяем структуру ответа
        $response->assertJsonStructure([
            'users' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'role_id',
                    'balance',
                    'created_at',
                    'updated_at',
                    'password', // Для админа должен быть пароль
                ],
            ],
            'pagination' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);

        // Проверяем, что все пользователи присутствуют
        $users = $response->json('users');
        $this->assertCount(3, $users); // admin + 2 users

        // Проверяем, что для админа возвращается пароль
        $adminData = collect($users)->firstWhere('id', $admin->id);
        $this->assertNotNull($adminData);
        $this->assertEquals('password123', $adminData['password']);

        // Проверяем балансы
        $user1Data = collect($users)->firstWhere('id', $user1->id);
        $this->assertEquals(1000.50, $user1Data['balance']);

        $user2Data = collect($users)->firstWhere('id', $user2->id);
        $this->assertEquals(500.25, $user2Data['balance']);
    }

    /**
     * Тест получения списка пользователей для не-админа
     * Проверяет, что пароль не возвращается для обычных пользователей
     */
    public function test_index_does_not_return_password_for_non_admin()
    {
        $sellerRole = MoonshineUserRole::firstOrCreate(
            ['id' => 2],
            ['name' => 'Seller']
        );

        $seller = MoonshineUser::create([
            'name' => 'Seller User',
            'email' => 'seller@test.com',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => $sellerRole->id,
            'plain_password' => 'seller123',
        ]);

        $user2 = MoonshineUser::create([
            'name' => 'Another User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => $sellerRole->id,
            'plain_password' => 'user123',
        ]);

        // Аутентифицируемся как продавец (не админ)
        $token = $seller->createToken('test-token')->plainTextToken;

        // Вызываем API endpoint
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users');

        // Проверяем, что ответ успешный
        $response->assertStatus(200);

        // Проверяем, что пароль не возвращается
        $users = $response->json('users');
        foreach ($users as $user) {
            $this->assertArrayNotHasKey('password', $user);
        }
    }

    /**
     * Тест фильтрации пользователей по роли
     */
    public function test_index_filters_users_by_role()
    {
        $adminRole = MoonshineUserRole::firstOrCreate(
            ['id' => 1],
            ['name' => 'Admin']
        );

        $admin = MoonshineUser::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => $adminRole->id,
            'plain_password' => 'admin123',
        ]);

        $driverRole = MoonshineUserRole::firstOrCreate(
            ['id' => 3],
            ['name' => 'Driver']
        );

        $driver1 = MoonshineUser::create([
            'name' => 'Driver 1',
            'email' => 'driver1@test.com',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => $driverRole->id,
        ]);

        $driver2 = MoonshineUser::create([
            'name' => 'Driver 2',
            'email' => 'driver2@test.com',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => $driverRole->id,
        ]);

        $token = $admin->createToken('test-token')->plainTextToken;

        // Фильтруем по роли водителя
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users?role_id=3');

        $response->assertStatus(200);
        $users = $response->json('users');
        
        // Должны быть только водители
        $this->assertCount(2, $users);
        foreach ($users as $user) {
            $this->assertEquals(3, $user['role_id']);
        }
    }

    /**
     * Тест поиска пользователей
     */
    public function test_index_searches_users_by_name_or_email()
    {
        $adminRole = MoonshineUserRole::firstOrCreate(
            ['id' => 1],
            ['name' => 'Admin']
        );

        $admin = MoonshineUser::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => $adminRole->id,
            'plain_password' => 'admin123',
        ]);

        $user1 = MoonshineUser::create([
            'name' => 'John Doe',
            'email' => 'john@test.com',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => $adminRole->id,
        ]);

        $user2 = MoonshineUser::create([
            'name' => 'Jane Smith',
            'email' => 'jane@test.com',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => $adminRole->id,
        ]);

        $token = $admin->createToken('test-token')->plainTextToken;

        // Ищем по имени
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users?search=John');

        $response->assertStatus(200);
        $users = $response->json('users');
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users[0]['name']);

        // Ищем по email
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users?search=jane@test.com');

        $response->assertStatus(200);
        $users = $response->json('users');
        $this->assertCount(1, $users);
        $this->assertEquals('jane@test.com', $users[0]['email']);
    }

    /**
     * Тест пагинации
     */
    public function test_index_pagination_works()
    {
        $adminRole = MoonshineUserRole::firstOrCreate(
            ['id' => 1],
            ['name' => 'Admin']
        );

        $admin = MoonshineUser::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => $adminRole->id,
            'plain_password' => 'admin123',
        ]);

        // Создаем больше пользователей, чем помещается на одной странице
        for ($i = 1; $i <= 20; $i++) {
            MoonshineUser::create([
                'name' => "User $i",
                'email' => "user$i@test.com",
                'password' => bcrypt('password'),
                'moonshine_user_role_id' => $adminRole->id,
            ]);
        }

        $token = $admin->createToken('test-token')->plainTextToken;

        // Запрашиваем с пагинацией
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users?per_page=10');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'users',
            'pagination' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);

        $pagination = $response->json('pagination');
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(21, $pagination['total']); // admin + 20 users
        $this->assertGreaterThan(1, $pagination['last_page']);
    }
}









