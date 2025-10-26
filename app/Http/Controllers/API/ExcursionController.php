<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Excursion;
use Illuminate\Http\Request;

class ExcursionController extends Controller
{
    public function index()
    {
        $excursions = Excursion::with('busSeats')
            ->where('is_active', true)
            ->orderBy('date_time', 'asc')
            ->get();

        return response()->json([
            'data' => $excursions->map(function ($excursion) {
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
            }),
        ]);
    }

    public function show($id)
    {
        $excursion = Excursion::with('busSeats')
            ->where('is_active', true)
            ->findOrFail($id);

        return response()->json([
            'data' => [
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
            ],
        ]);
    }
}
