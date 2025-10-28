<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Models\Booking;
use MoonShine\Laravel\Models\MoonshineUser;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * Получить кошелек пользователя
     */
    public function show($userId)
    {
        $user = MoonshineUser::findOrFail($userId);
        
        $transactions = WalletTransaction::where('user_id', $userId)
            ->with('booking.excursion')
            ->orderBy('created_at', 'desc')
            ->get();
            
        $balance = $transactions->sum('amount');
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'balance' => $balance,
            'transactions' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'description' => $transaction->description,
                    'booking' => $transaction->booking ? [
                        'id' => $transaction->booking->id,
                        'excursion' => [
                            'id' => $transaction->booking->excursion->id,
                            'title' => $transaction->booking->excursion->title,
                            'date_time' => $transaction->booking->excursion->date_time,
                        ],
                        'customer_name' => $transaction->booking->customer_name,
                        'price' => $transaction->booking->price,
                    ] : null,
                    'created_at' => $transaction->created_at,
                ];
            })
        ]);
    }

    /**
     * Получить историю продаж пользователя
     */
    public function sales($userId)
    {
        $user = MoonshineUser::findOrFail($userId);
        
        $bookings = Booking::where('booked_by', $userId)
            ->with(['excursion', 'stop'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        $totalSales = $bookings->sum('price');
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'total_sales' => $totalSales,
            'bookings' => $bookings->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'excursion' => [
                        'id' => $booking->excursion->id,
                        'title' => $booking->excursion->title,
                        'date_time' => $booking->excursion->date_time,
                    ],
                    'customer_name' => $booking->customer_name,
                    'customer_phone' => $booking->customer_phone,
                    'passenger_type' => $booking->passenger_type,
                    'price' => $booking->price,
                    'stop' => $booking->stop ? [
                        'id' => $booking->stop->id,
                        'name' => $booking->stop->name,
                    ] : null,
                    'booked_at' => $booking->booked_at,
                ];
            })
        ]);
    }
}
