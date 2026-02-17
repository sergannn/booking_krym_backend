<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusController extends Controller
{
    /**
     * Получить список всех автобусов
     */
    public function index(Request $request)
    {
        try {
            $query = Bus::with('drivers');

            // Фильтрация по активности
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Поиск по номеру, модели или гос. номеру
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%")
                      ->orWhere('license_plate', 'like', "%{$search}%");
                });
            }

            $buses = $query->orderBy('number')->get();

            return response()->json([
                'buses' => $buses->map(function ($bus) {
                    return [
                        'id' => $bus->id,
                        'number' => $bus->number,
                        'model' => $bus->model,
                        'capacity' => $bus->capacity,
                        'license_plate' => $bus->license_plate,
                        'is_active' => $bus->is_active,
                        'drivers' => $bus->drivers->map(function ($driver) {
                            return [
                                'id' => $driver->id,
                                'name' => $driver->name,
                                'email' => $driver->email,
                            ];
                        }),
                        'created_at' => $bus->created_at,
                        'updated_at' => $bus->updated_at,
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при получении списка автобусов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить информацию об автобусе по ID
     */
    public function show($id)
    {
        try {
            $bus = Bus::with('drivers')->findOrFail($id);

            return response()->json([
                'bus' => [
                    'id' => $bus->id,
                    'number' => $bus->number,
                    'model' => $bus->model,
                    'capacity' => $bus->capacity,
                    'license_plate' => $bus->license_plate,
                    'is_active' => $bus->is_active,
                    'drivers' => $bus->drivers->map(function ($driver) {
                        return [
                            'id' => $driver->id,
                            'name' => $driver->name,
                            'email' => $driver->email,
                        ];
                    }),
                    'created_at' => $bus->created_at,
                    'updated_at' => $bus->updated_at,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Автобус не найден',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при получении информации об автобусе',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Создать новый автобус
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'number' => 'required|string|max:255|unique:buses,number',
                'model' => 'nullable|string|max:255',
                'capacity' => 'required|integer|min:1',
                'license_plate' => 'nullable|string|max:255',
                'is_active' => 'boolean',
            ]);

            $bus = Bus::create($validated);

            return response()->json([
                'message' => 'Автобус успешно создан',
                'bus' => [
                    'id' => $bus->id,
                    'number' => $bus->number,
                    'model' => $bus->model,
                    'capacity' => $bus->capacity,
                    'license_plate' => $bus->license_plate,
                    'is_active' => $bus->is_active,
                    'created_at' => $bus->created_at,
                    'updated_at' => $bus->updated_at,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при создании автобуса',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Обновить информацию об автобусе
     */
    public function update(Request $request, $id)
    {
        try {
            $bus = Bus::findOrFail($id);

            $validated = $request->validate([
                'number' => ['required', 'string', 'max:255', Rule::unique('buses')->ignore($bus->id)],
                'model' => 'nullable|string|max:255',
                'capacity' => 'required|integer|min:1',
                'license_plate' => 'nullable|string|max:255',
                'is_active' => 'boolean',
            ]);

            $bus->update($validated);

            return response()->json([
                'message' => 'Автобус успешно обновлен',
                'bus' => [
                    'id' => $bus->id,
                    'number' => $bus->number,
                    'model' => $bus->model,
                    'capacity' => $bus->capacity,
                    'license_plate' => $bus->license_plate,
                    'is_active' => $bus->is_active,
                    'created_at' => $bus->created_at,
                    'updated_at' => $bus->updated_at,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Автобус не найден',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при обновлении автобуса',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Удалить автобус
     */
    public function destroy($id)
    {
        try {
            $bus = Bus::findOrFail($id);

            // Проверяем, есть ли водители, привязанные к этому автобусу
            if ($bus->drivers()->count() > 0) {
                return response()->json([
                    'message' => 'Невозможно удалить автобус: к нему привязаны водители',
                ], 422);
            }

            $bus->delete();

            return response()->json([
                'message' => 'Автобус успешно удален',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Автобус не найден',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при удалении автобуса',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Привязать автобус к водителю
     */
    public function assignToDriver(Request $request, $busId)
    {
        try {
            $bus = Bus::findOrFail($busId);

            $validated = $request->validate([
                'driver_id' => 'required|exists:moonshine_users,id',
            ]);

            $driver = \App\Models\MoonshineUserExtension::findOrFail($validated['driver_id']);

            // Проверяем, что пользователь является водителем (role_id = 3)
            if ($driver->moonshine_user_role_id != 3) {
                return response()->json([
                    'message' => 'Пользователь не является водителем',
                ], 422);
            }

            // Отвязываем автобус от предыдущего водителя, если он был привязан
            if ($bus->drivers()->count() > 0) {
                $bus->drivers()->update(['bus_id' => null]);
            }

            // Привязываем к новому водителю
            $driver->update(['bus_id' => $bus->id]);

            return response()->json([
                'message' => 'Автобус успешно привязан к водителю',
                'bus' => [
                    'id' => $bus->id,
                    'number' => $bus->number,
                ],
                'driver' => [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'email' => $driver->email,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Автобус или водитель не найден',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при привязке автобуса к водителю',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Отвязать автобус от водителя
     */
    public function unassignFromDriver(Request $request, $busId)
    {
        try {
            $bus = Bus::findOrFail($busId);

            $validated = $request->validate([
                'driver_id' => 'required|exists:moonshine_users,id',
            ]);

            $driver = \App\Models\MoonshineUserExtension::findOrFail($validated['driver_id']);

            // Проверяем, что автобус действительно привязан к этому водителю
            if ($driver->bus_id != $bus->id) {
                return response()->json([
                    'message' => 'Автобус не привязан к этому водителю',
                ], 422);
            }

            // Отвязываем
            $driver->update(['bus_id' => null]);

            return response()->json([
                'message' => 'Автобус успешно отвязан от водителя',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Автобус или водитель не найден',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при отвязке автобуса от водителя',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
