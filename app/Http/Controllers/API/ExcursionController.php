<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Excursion;
use MoonShine\Laravel\Models\MoonshineUser;
use Illuminate\Http\Request;

class ExcursionController extends Controller
{
    public function index(Request $request)
    {
        $query = Excursion::with(['busSeats', 'assignedUsers', 'prices']);
        
        // Фильтр по дате (только будущие экскурсии)
        if ($request->has('from') && $request->from === 'today') {
            $query->where('date_time', '>=', now()->startOfDay());
        }
        
        // Включить прошедшие экскурсии для админов
        if (!$request->has('include_past') || !$request->include_past) {
            $query->where('date_time', '>=', now());
        }
        
        // Фильтр по назначенному пользователю
        if ($request->has('assigned_to')) {
            $query->whereHas('assignedUsers', function($q) use ($request) {
                $q->where('user_id', $request->assigned_to)
                  ->where('role_in_excursion', $request->assigned_to);
            });
        }
        
        $excursions = $query->orderBy('date_time', 'asc')->get();

        return response()->json([
            'data' => $excursions->map(fn (Excursion $excursion) => $this->transformExcursion($excursion)),
        ]);
    }

    public function show($id)
    {
        $excursion = Excursion::with(['busSeats', 'assignedUsers', 'prices'])
            ->where('is_active', true)
            ->findOrFail($id);

        return response()->json([
            'data' => $this->transformExcursion($excursion),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user || (! $user->isSuperUser() && (int) $user->moonshine_user_role_id !== 1)) {
            abort(403, 'Недостаточно прав для создания экскурсии.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date_time' => 'required|date',
            'price' => 'required|numeric|min:0',
            'max_seats' => 'required|integer|min:1|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $excursion = Excursion::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'date_time' => $validated['date_time'],
            'price' => $validated['price'],
            'max_seats' => $validated['max_seats'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        foreach (['adult', 'child', 'senior', 'disabled'] as $type) {
            $excursion->prices()->create([
                'passenger_type' => $type,
                'price' => $validated['price'],
            ]);
        }

        $excursion->load(['busSeats', 'prices', 'assignedUsers']);

        return response()->json([
            'message' => 'Экскурсия создана',
            'data' => $this->transformExcursion($excursion),
        ], 201);
    }

    /**
     * Назначить пользователей на экскурсию
     */
    public function assign(Request $request, $id)
    {
        $user = $request->user();

        if (! $user || (! $user->isSuperUser() && (int) $user->moonshine_user_role_id !== 1)) {
            abort(403, 'Недостаточно прав для назначения сотрудников.');
        }

        $excursion = Excursion::findOrFail($id);

        $validated = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.user_id' => 'required|exists:moonshine_users,id',
            'assignments.*.role_in_excursion' => 'required|in:driver,guide',
        ]);

        $assignedUsers = [];

        foreach ($validated['assignments'] as $assignment) {
            $excursion->assignedUsers()->syncWithoutDetaching([
                $assignment['user_id'] => ['role_in_excursion' => $assignment['role_in_excursion']]
            ]);
            
            $assignedUsers[] = [
                'user_id' => $assignment['user_id'],
                'role_in_excursion' => $assignment['role_in_excursion'],
            ];
        }

        return response()->json([
            'message' => 'Сотрудники назначены на экскурсию',
            'assigned_users' => $assignedUsers,
        ]);
    }

    /**
     * Удалить назначение пользователя с экскурсии
     */
    public function unassign(Request $request, $id, $userId)
    {
        $user = $request->user();

        if (! $user || (! $user->isSuperUser() && (int) $user->moonshine_user_role_id !== 1)) {
            abort(403, 'Недостаточно прав для отмены назначения сотрудников.');
        }

        $excursion = Excursion::findOrFail($id);
        $excursion->assignedUsers()->detach($userId);

        return response()->json([
            'message' => 'Назначение отменено',
        ]);
    }

    private function transformExcursion(Excursion $excursion): array
    {
        return [
            'id' => $excursion->id,
            'title' => $excursion->title,
            'description' => $excursion->description,
            'date_time' => $excursion->date_time->toISOString(),
            'date' => $excursion->date_time->format('Y-m-d'),
            'time' => $excursion->date_time->format('H:i'),
            'price' => $excursion->price,
            'max_seats' => $excursion->max_seats,
            'booked_seats_count' => $excursion->booked_seats_count,
            'available_seats_count' => $excursion->available_seats_count,
            'assigned_staff' => $excursion->assignedUsers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_in_excursion' => $user->pivot->role_in_excursion,
                ];
            }),
            'bus_seats' => $excursion->busSeats->map(function ($seat) {
                return [
                    'id' => $seat->id,
                    'seat_number' => $seat->seat_number,
                    'status' => $seat->status,
                    'booked_by' => $seat->booked_by,
                    'booked_at' => $seat->booked_at?->toISOString(),
                ];
            }),
            'prices' => $excursion->prices->map(function ($price) {
                return [
                    'passenger_type' => $price->passenger_type,
                    'price' => $price->price,
                ];
            })->values(),
        ];
    }

    public function updatePrices(Request $request, $id)
    {
        $user = $request->user();

        if (! $user || (! $user->isSuperUser() && (int) $user->moonshine_user_role_id !== 1)) {
            abort(403, 'Недостаточно прав для изменения цен.');
        }

        $validated = $request->validate([
            'prices' => 'required|array',
            'prices.adult' => 'required|numeric|min:0',
            'prices.child' => 'required|numeric|min:0',
            'prices.senior' => 'required|numeric|min:0',
            'prices.disabled' => 'required|numeric|min:0',
        ]);

        $excursion = Excursion::with('prices')->findOrFail($id);

        $types = ['adult', 'child', 'senior', 'disabled'];

        foreach ($types as $type) {
            $price = $validated['prices'][$type];

            $excursion->prices()->updateOrCreate(
                ['passenger_type' => $type],
                ['price' => $price]
            );
        }

        $excursion->refresh()->load(['busSeats', 'assignedUsers', 'prices']);

        return response()->json([
            'message' => 'Тарифы обновлены',
            'data' => $this->transformExcursion($excursion),
        ]);
    }
}
