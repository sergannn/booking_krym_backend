<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Models\MoonshineUserExtension as MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;

class UserController extends Controller
{
    /**
     * Создать нового пользователя
     */
    public function store(CreateUserRequest $request)
    {
        $validated = $request->validated();

        try {
            $user = MoonshineUser::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'plain_password' => $validated['password'], // Сохраняем исходный пароль для админа
                'moonshine_user_role_id' => $validated['role_id'],
            ]);

            // Загружаем связанную роль
            $user->load('moonshineUserRole');

            return response()->json([
                'message' => 'Пользователь успешно создан',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->moonshineUserRole?->name ?? 'Unknown',
                    'role_id' => $user->moonshine_user_role_id,
                    'password' => $user->plain_password, // Возвращаем пароль при создании
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при создании пользователя',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить информацию о пользователе по ID
     */
    public function show($id)
    {
        try {
            $user = MoonshineUser::with('moonshineUserRole')->findOrFail($id);

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->moonshineUserRole?->name ?? 'Unknown',
                    'role_id' => $user->moonshine_user_role_id,
                    'avatar' => $user->avatar,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Пользователь не найден',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при получении информации о пользователе',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить список всех пользователей
     */
    public function index(Request $request)
    {
        try {
            $query = MoonshineUser::with('moonshineUserRole');

            // Фильтрация по роли
            if ($request->has('role_id')) {
                $query->where('moonshine_user_role_id', $request->role_id);
            }

            // Поиск по имени или email
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->paginate($request->get('per_page', 15));

            // Для админов возвращаем пароль
            // Проверяем аутентификацию через заголовок Authorization
            $currentUser = $request->user();
            // Если user() вернул null, пытаемся получить пользователя из токена
            if (!$currentUser && $request->bearerToken()) {
                $token = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken());
                if ($token) {
                    $currentUser = $token->tokenable;
                }
            }
            $isAdmin = $currentUser && ($currentUser->isSuperUser() || (int) $currentUser->moonshine_user_role_id === 1);
            
            $usersData = collect($users->items())->map(function ($user) use ($isAdmin) {
                // Получаем баланс кошелька
                $balance = \App\Models\WalletTransaction::where('user_id', $user->id)->sum('amount');
                
                $userData = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->moonshineUserRole?->name ?? 'Unknown',
                    'role_id' => $user->moonshine_user_role_id,
                    'balance' => round($balance, 2),
                    'color' => $user->color, // Цвет продавца
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
                
                // Для админов показываем пароль
                if ($isAdmin) {
                    // Получаем plain_password напрямую из атрибутов модели
                    $attributes = $user->getAttributes();
                    $plainPassword = $attributes['plain_password'] ?? null;
                    $userData['password'] = $plainPassword !== null && $plainPassword !== '' ? $plainPassword : 'Не установлен';
                }
                
                return $userData;
            })->toArray();

            return response()->json([
                'users' => $usersData,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при получении списка пользователей',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить список всех ролей
     */
    public function roles()
    {
        try {
            $roles = \MoonShine\Laravel\Models\MoonshineUserRole::select('id', 'name')
                ->get()
                ->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                });

            return response()->json([
                'roles' => $roles,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при получении списка ролей',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Удалить пользователя
     */
    public function destroy(Request $request, $id)
    {
        $currentUser = $request->user();

        if ($currentUser->id == $id) {
            return response()->json([
                'message' => 'Нельзя удалить текущего пользователя',
            ], 422);
        }

        $user = MoonshineUser::findOrFail($id);

        if ($user->isSuperUser()) {
            return response()->json([
                'message' => 'Нельзя удалить суперпользователя',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'Пользователь удалён',
        ]);
    }

    /**
     * Обновить цвет пользователя
     */
    public function updateColor(Request $request, $id)
    {
        // Получаем пользователя (как в методе index)
        $currentUser = $request->user();
        // Если user() вернул null, пытаемся получить пользователя из токена
        if (!$currentUser && $request->bearerToken()) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken());
            if ($token) {
                $currentUser = $token->tokenable;
            }
        }
        
        $isAdmin = $currentUser && ($currentUser->isSuperUser() || (int) $currentUser->moonshine_user_role_id === 1);

        if (!$isAdmin) {
            return response()->json([
                'message' => 'Недостаточно прав для изменения цвета',
            ], 403);
        }

        $request->validate([
            'color' => 'nullable|string',
        ]);

        $color = $request->input('color');
        
        // Проверяем формат HEX цвета, если он не пустой
        if ($color !== null && $color !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/i', $color)) {
            return response()->json([
                'message' => 'Неверный формат цвета. Используйте HEX формат, например: #FF5733',
            ], 422);
        }

        $user = MoonshineUser::findOrFail($id);
        $user->color = $color === '' ? null : $color;
        $user->save();

        return response()->json([
            'message' => 'Цвет пользователя обновлён',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'color' => $user->color,
            ],
        ]);
    }
}
