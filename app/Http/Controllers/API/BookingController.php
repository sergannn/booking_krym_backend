<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BusSeat;
use App\Models\Excursion;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'excursion_id' => 'required|exists:excursions,id',
            'seat_numbers' => 'required|array',
            'seat_numbers.*' => 'integer|min:1|max:100',
        ]);

        $excursion = Excursion::findOrFail($request->excursion_id);
        $user = $request->user();
        
        // Проверяем, что экскурсия активна
        if (!$excursion->is_active) {
            throw ValidationException::withMessages([
                'excursion_id' => ['This excursion is not available for booking.'],
            ]);
        }

        $bookedSeats = [];
        $errors = [];

        foreach ($request->seat_numbers as $seatNumber) {
            $seat = BusSeat::where('excursion_id', $excursion->id)
                ->where('seat_number', $seatNumber)
                ->first();

            if (!$seat) {
                $errors[] = "Seat {$seatNumber} does not exist for this excursion.";
                continue;
            }

            if ($seat->status !== 'available') {
                $errors[] = "Seat {$seatNumber} is not available.";
                continue;
            }

            // Бронируем место
            $seat->update([
                'status' => 'booked',
                'booked_by' => $user->id,
                'booked_at' => now(),
            ]);

            $bookedSeats[] = $seat;
        }

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Some seats could not be booked.',
                'errors' => $errors,
                'booked_seats' => $bookedSeats,
            ], 422);
        }

        return response()->json([
            'message' => 'Seats booked successfully.',
            'booked_seats' => $bookedSeats,
        ], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        $bookings = BusSeat::with(['excursion', 'bookedBy'])
            ->where('booked_by', $user->id)
            ->where('status', 'booked')
            ->get()
            ->groupBy('excursion_id');

        return response()->json([
            'data' => $bookings->map(function ($seats, $excursionId) {
                $excursion = $seats->first()->excursion;
                return [
                    'excursion' => [
                        'id' => $excursion->id,
                        'title' => $excursion->title,
                        'date_time' => $excursion->date_time->toISOString(),
                        'price' => $excursion->price,
                    ],
                    'seats' => $seats->map(function ($seat) {
                        return [
                            'id' => $seat->id,
                            'seat_number' => $seat->seat_number,
                            'booked_at' => $seat->booked_at->toISOString(),
                        ];
                    }),
                ];
            }),
        ]);
    }

    public function destroy($id)
    {
        $seat = BusSeat::where('id', $id)
            ->where('booked_by', auth()->id())
            ->where('status', 'booked')
            ->firstOrFail();

        $seat->update([
            'status' => 'available',
            'booked_by' => null,
            'booked_at' => null,
        ]);

        return response()->json([
            'message' => 'Booking cancelled successfully.',
        ]);
    }
}
