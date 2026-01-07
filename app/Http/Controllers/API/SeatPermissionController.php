<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SeatPermission;
use App\Models\SeatAccessRequest;
use App\Models\Excursion;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SeatPermissionController extends Controller
{
    /**
     * Проверка, является ли пользователь админом
     */
    private function isAdmin($user): bool
    {
        return $user->isSuperUser() || (int) $user->moonshine_user_role_id === 1;
    }

    /**
     * Получить список разрешений для экскурсии и даты
     * GET /api/seat-permissions?excursion_id=1&excursion_date=2024-12-25
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = SeatPermission::with(['user', 'excursion']);

        if ($request->has('excursion_id')) {
            $query->where('excursion_id', $request->excursion_id);
        }

        if ($request->has('excursion_date')) {
            $query->whereDate('excursion_date', $request->excursion_date);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $permissions = $query->get();

        return response()->json([
            'permissions' => $permissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'excursion_id' => $permission->excursion_id,
                    'excursion' => [
                        'id' => $permission->excursion->id,
                        'title' => $permission->excursion->title,
                    ],
                    'user_id' => $permission->user_id,
                    'user' => [
                        'id' => $permission->user->id,
                        'name' => $permission->user->name,
                        'email' => $permission->user->email,
                    ],
                    'excursion_date' => $permission->excursion_date->format('Y-m-d'),
                    'seat_number' => $permission->seat_number,
                    'created_at' => $permission->created_at,
                ];
            }),
        ]);
    }

    /**
     * Создать разрешение
     * POST /api/seat-permissions
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'excursion_id' => 'required|exists:excursions,id',
            'user_id' => 'required|exists:moonshine_users,id',
            'excursion_date' => 'required|date',
            'seat_number' => 'required|integer|in:1,2',
        ]);

        // Проверяем, не существует ли уже такое разрешение
        $existing = SeatPermission::where('excursion_id', $validated['excursion_id'])
            ->where('user_id', $validated['user_id'])
            ->where('excursion_date', $validated['excursion_date'])
            ->where('seat_number', $validated['seat_number'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Разрешение уже существует',
                'permission' => $existing,
            ], 422);
        }

        $permission = SeatPermission::create($validated);

        return response()->json([
            'message' => 'Разрешение создано',
            'permission' => [
                'id' => $permission->id,
                'excursion_id' => $permission->excursion_id,
                'user_id' => $permission->user_id,
                'excursion_date' => $permission->excursion_date->format('Y-m-d'),
                'seat_number' => $permission->seat_number,
            ],
        ], 201);
    }

    /**
     * Удалить разрешение
     * DELETE /api/seat-permissions/{id}
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $permission = SeatPermission::findOrFail($id);
        $permission->delete();

        return response()->json(['message' => 'Разрешение удалено']);
    }

    /**
     * Получить список запросов доступа
     * GET /api/seat-access-requests?status=pending
     */
    public function requests(Request $request)
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = SeatAccessRequest::with(['user', 'excursion', 'reviewer'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->get();

        return response()->json([
            'requests' => $requests->map(function ($req) {
                return [
                    'id' => $req->id,
                    'excursion_id' => $req->excursion_id,
                    'excursion' => [
                        'id' => $req->excursion->id,
                        'title' => $req->excursion->title,
                    ],
                    'user_id' => $req->user_id,
                    'user' => [
                        'id' => $req->user->id,
                        'name' => $req->user->name,
                        'email' => $req->user->email,
                    ],
                    'excursion_date' => $req->excursion_date->format('Y-m-d'),
                    'seat_number' => $req->seat_number,
                    'status' => $req->status,
                    'reason' => $req->reason,
                    'reviewed_by' => $req->reviewed_by,
                    'reviewer' => $req->reviewer ? [
                        'id' => $req->reviewer->id,
                        'name' => $req->reviewer->name,
                    ] : null,
                    'reviewed_at' => $req->reviewed_at?->toISOString(),
                    'created_at' => $req->created_at->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Создать запрос доступа (для продавцов)
     * POST /api/seat-access-requests
     */
    public function createRequest(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'excursion_id' => 'required|exists:excursions,id',
            'excursion_date' => 'required|date',
            'seat_number' => 'required|integer|in:1,2',
            'reason' => 'nullable|string|max:500',
        ]);

        // Проверяем, не существует ли уже pending запрос
        $existing = SeatAccessRequest::where('excursion_id', $validated['excursion_id'])
            ->where('user_id', $user->id)
            ->where('excursion_date', $validated['excursion_date'])
            ->where('seat_number', $validated['seat_number'])
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Запрос уже существует и ожидает рассмотрения',
                'request' => $existing,
            ], 422);
        }

        $accessRequest = SeatAccessRequest::create([
            'excursion_id' => $validated['excursion_id'],
            'user_id' => $user->id,
            'excursion_date' => $validated['excursion_date'],
            'seat_number' => $validated['seat_number'],
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Запрос создан',
            'request' => [
                'id' => $accessRequest->id,
                'excursion_id' => $accessRequest->excursion_id,
                'excursion_date' => $accessRequest->excursion_date->format('Y-m-d'),
                'seat_number' => $accessRequest->seat_number,
                'status' => $accessRequest->status,
            ],
        ], 201);
    }

    /**
     * Одобрить запрос доступа
     * POST /api/seat-access-requests/{id}/approve
     */
    public function approveRequest(Request $request, $id)
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $accessRequest = SeatAccessRequest::findOrFail($id);

        if ($accessRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Запрос уже обработан',
            ], 422);
        }

        // Обновляем статус запроса
        $accessRequest->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Создаем разрешение
        SeatPermission::create([
            'excursion_id' => $accessRequest->excursion_id,
            'user_id' => $accessRequest->user_id,
            'excursion_date' => $accessRequest->excursion_date,
            'seat_number' => $accessRequest->seat_number,
        ]);

        return response()->json([
            'message' => 'Запрос одобрен, разрешение создано',
        ]);
    }

    /**
     * Отклонить запрос доступа
     * POST /api/seat-access-requests/{id}/reject
     */
    public function rejectRequest(Request $request, $id)
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $accessRequest = SeatAccessRequest::findOrFail($id);

        if ($accessRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Запрос уже обработан',
            ], 422);
        }

        $accessRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Запрос отклонен',
        ]);
    }

    /**
     * Проверить разрешения пользователя для экскурсии и даты
     * GET /api/seat-permissions/check?excursion_id=1&excursion_date=2024-12-30
     */
    public function checkPermissions(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'excursion_id' => 'required|exists:excursions,id',
            'excursion_date' => 'required|date',
        ]);

        $excursionId = $request->input('excursion_id');
        $excursionDate = \Carbon\Carbon::parse($request->input('excursion_date'))->format('Y-m-d');

        $permissions = SeatPermission::where('excursion_id', $excursionId)
            ->where('user_id', $user->id)
            ->whereDate('excursion_date', $excursionDate)
            ->whereIn('seat_number', [1, 2])
            ->pluck('seat_number')
            ->toArray();

        return response()->json([
            'has_permission_for_seat_1' => in_array(1, $permissions),
            'has_permission_for_seat_2' => in_array(2, $permissions),
            'permissions' => $permissions,
        ]);
    }

    /**
     * Получить мои запросы (для продавцов)
     * GET /api/seat-access-requests/my
     */
    public function myRequests(Request $request)
    {
        $user = $request->user();

        $requests = SeatAccessRequest::with(['excursion', 'reviewer'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'requests' => $requests->map(function ($req) {
                return [
                    'id' => $req->id,
                    'excursion_id' => $req->excursion_id,
                    'excursion' => [
                        'id' => $req->excursion->id,
                        'title' => $req->excursion->title,
                    ],
                    'excursion_date' => $req->excursion_date->format('Y-m-d'),
                    'seat_number' => $req->seat_number,
                    'status' => $req->status,
                    'reason' => $req->reason,
                    'reviewer' => $req->reviewer ? [
                        'id' => $req->reviewer->id,
                        'name' => $req->reviewer->name,
                    ] : null,
                    'reviewed_at' => $req->reviewed_at?->toISOString(),
                    'created_at' => $req->created_at->toISOString(),
                ];
            }),
        ]);
    }
}
