<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BusSeat;
use App\Models\Excursion;
use App\Models\Booking;
use App\Models\Stop;
use App\Models\WalletTransaction;
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
            'price' => 'required|numeric|min:0',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'passenger_type' => 'required|in:adult,child,senior,disabled',
            'stop_id' => 'required|exists:stops,id',
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
        $bookings = [];
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

            // Создаем запись бронирования
            $booking = Booking::create([
                'excursion_id' => $excursion->id,
                'bus_seat_id' => $seat->id,
                'booked_by' => $user->id,
                'price' => $request->price,
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'passenger_type' => $request->passenger_type,
                'stop_id' => $request->stop_id,
                'booked_at' => now(),
            ]);

            // Создаем транзакцию в кошельке продавца
            WalletTransaction::create([
                'user_id' => $user->id,
                'booking_id' => $booking->id,
                'amount' => $request->price,
                'description' => "Продажа места №{$seatNumber} на экскурсию '{$excursion->title}'",
            ]);

            $bookedSeats[] = $seat;
            $bookings[] = $booking;
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
            'bookings' => $bookings,
        ], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        $bookings = Booking::with(['excursion', 'stop', 'busSeat'])
            ->where('booked_by', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'bookings' => $bookings->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'excursion' => [
                        'id' => $booking->excursion->id,
                        'title' => $booking->excursion->title,
                        'date_time' => $booking->excursion->date_time->toISOString(),
                        'price' => $booking->excursion->price,
                    ],
                    'bus_seat' => [
                        'id' => $booking->busSeat->id,
                        'seat_number' => $booking->busSeat->seat_number,
                    ],
                    'customer_name' => $booking->customer_name,
                    'customer_phone' => $booking->customer_phone,
                    'passenger_type' => $booking->passenger_type,
                    'price' => $booking->price,
                    'stop' => $booking->stop ? [
                        'id' => $booking->stop->id,
                        'name' => $booking->stop->name,
                    ] : null,
                    'booked_at' => $booking->booked_at->toISOString(),
                ];
            }),
        ]);
    }

    public function destroy($id)
    {
        $booking = Booking::where('id', $id)
            ->where('booked_by', auth()->id())
            ->firstOrFail();

        $seat = $booking->busSeat;
        
        // Освобождаем место
        $seat->update([
            'status' => 'available',
            'booked_by' => null,
            'booked_at' => null,
        ]);

        // Создаем обратную транзакцию в кошельке
        WalletTransaction::create([
            'user_id' => auth()->id(),
            'booking_id' => $booking->id,
            'amount' => -$booking->price, // Отрицательная сумма для возврата
            'description' => "Отмена бронирования места №{$seat->seat_number} на экскурсию '{$booking->excursion->title}'",
        ]);

        // Удаляем бронирование
        $booking->delete();

        return response()->json([
            'message' => 'Booking cancelled successfully.',
            'refund_amount' => $booking->price,
        ]);
    }
}
