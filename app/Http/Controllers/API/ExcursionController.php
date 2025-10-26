<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BusSeat;
use App\Models\Excursion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExcursionController extends Controller
{
    public function index()
    {
        $excursions = Excursion::with('busSeats')
            ->where('is_active', true)
            ->orderBy('date_time', 'asc')
            ->get();

        return response()->json([
            'data' => $excursions->map(fn (Excursion $excursion) => $this->transformExcursion($excursion)),
        ]);
    }

    public function show($id)
    {
        $excursion = Excursion::with('busSeats')
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

        $excursion = DB::transaction(function () use ($validated) {
            $excursion = Excursion::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'date_time' => $validated['date_time'],
                'price' => $validated['price'],
                'max_seats' => $validated['max_seats'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $now = now();
            $seats = [];
            for ($i = 1; $i <= $excursion->max_seats; $i++) {
                $seats[] = [
                    'excursion_id' => $excursion->id,
                    'seat_number' => $i,
                    'status' => 'available',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            BusSeat::insert($seats);

            return $excursion;
        });

        $excursion->load('busSeats');

        return response()->json([
            'message' => 'Экскурсия создана',
            'data' => $this->transformExcursion($excursion),
        ], 201);
    }

    private function transformExcursion(Excursion $excursion): array
    {
        return [
            'id' => $excursion->id,
            'title' => $excursion->title,
            'description' => $excursion->description,
            'date_time' => $excursion->date_time->toISOString(),
            'price' => $excursion->price,
            'max_seats' => $excursion->max_seats,
            'booked_seats_count' => $excursion->booked_seats_count,
            'available_seats_count' => $excursion->available_seats_count,
            'bus_seats' => $excursion->busSeats->map(function ($seat) {
                return [
                    'id' => $seat->id,
                    'seat_number' => $seat->seat_number,
                    'status' => $seat->status,
                    'booked_by' => $seat->booked_by,
                    'booked_at' => $seat->booked_at?->toISOString(),
                ];
            }),
        ];
    }
}
