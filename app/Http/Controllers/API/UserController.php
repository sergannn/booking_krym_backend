<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use MoonShine\Laravel\Models\MoonshineUser;
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

            return response()->json([
                'users' => $users->items(),
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
            $roles = MoonshineUser::with('moonshineUserRole')
                ->get()
                ->map(function (MoonshineUser $user) {
                    if (is_null($user->moonshine_user_role_id)) {
                        return null;
                    }

                    return [
                        'id' => $user->moonshine_user_role_id,
                        'name' => $user->moonshineUserRole?->name ?? 'Unknown',
                    ];
                })
                ->filter()
                ->unique('id')
                ->values();

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
}
