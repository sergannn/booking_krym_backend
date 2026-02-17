<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Models\Booking;
use App\Models\Excursion;
use MoonShine\Laravel\Models\MoonshineUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
                // Используем дату из бронирования (excursion_date) или вычисляем через аксессор
                $excursionDateTime = $booking->excursion_date; // Это аксессор, который возвращает правильную дату
                
                return [
                    'id' => $booking->id,
                    'excursion' => [
                        'id' => $booking->excursion->id,
                        'title' => $booking->excursion->title,
                        'date_time' => $excursionDateTime?->toISOString(),
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

    /**
     * Получить рассчитанную прибыль пользователя
     */
    public function profit($userId)
    {
        $user = MoonshineUser::with('moonshineUserRole')->findOrFail($userId);

        $bookings = Booking::where('booked_by', $userId)
            ->with(['excursion.prices'])
            ->orderBy('created_at', 'desc')
            ->get();

        $isPartner = (int) $user->moonshine_user_role_id === 4;

        $totalProfit = 0;
        $breakdown = [];
        $totalsByType = [];

        foreach ($bookings as $booking) {
            $passengerType = $booking->passenger_type ?? 'adult';
            $price = (float) $booking->price;

            $priceRecord = optional($booking->excursion->prices)
                ->firstWhere('passenger_type', $passengerType);

            if ($priceRecord) {
                $commissionPercent = $isPartner
                    ? (float) $priceRecord->partner_commission_percent
                    : (float) $priceRecord->seller_commission_percent;
            } else {
                $commissionPercent = $isPartner ? 0.0 : 10.0;
            }

            $commissionAmount = round($price * $commissionPercent / 100, 2);
            $totalProfit += $commissionAmount;

            if (! isset($totalsByType[$passengerType])) {
                $totalsByType[$passengerType] = [
                    'sales' => 0.0,
                    'commission' => 0.0,
                ];
            }

            $totalsByType[$passengerType]['sales'] += $price;
            $totalsByType[$passengerType]['commission'] += $commissionAmount;

            $breakdown[] = [
                'booking_id' => $booking->id,
                'excursion' => [
                    'id' => $booking->excursion->id,
                    'title' => $booking->excursion->title,
                    'date_time' => $booking->excursion->date_time,
                ],
                'passenger_type' => $passengerType,
                'price' => $price,
                'commission_percent' => $commissionPercent,
                'commission_amount' => $commissionAmount,
                'booked_at' => $booking->booked_at,
            ];
        }

        $formattedTotals = [];
        foreach ($totalsByType as $type => $totals) {
            $formattedTotals[$type] = [
                'sales' => round($totals['sales'], 2),
                'commission' => round($totals['commission'], 2),
            ];
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->moonshine_user_role_id,
                'role' => $user->moonshineUserRole?->name,
                'is_partner' => $isPartner,
            ],
            'total_profit' => round($totalProfit, 2),
            'breakdown' => $breakdown,
            'totals_by_type' => $formattedTotals,
        ]);
    }

    /**
     * Получить прибыль персонала (водителей/экскурсоводов)
     */
    public function staffProfit($userId)
    {
        $user = MoonshineUser::findOrFail($userId);
        $request = request();
        
        // Проверяем, что пользователь запрашивает свою прибыль или это админ
        // Поддерживаем и Sanctum токены, и сессию MoonShine
        $currentUser = $request->user('sanctum') 
            ?? $request->user('web') 
            ?? Auth::guard('moonshine')->user()
            ?? $request->user();
        
        // Если user() вернул null, пытаемся получить пользователя из токена
        if (!$currentUser && $request->bearerToken()) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken());
            if ($token) {
                $currentUser = $token->tokenable;
            }
        }
        
        if (!$currentUser || ($currentUser->id != $userId && !$currentUser->isSuperUser() && (int) $currentUser->moonshine_user_role_id !== 1)) {
            abort(403, 'Недостаточно прав для просмотра прибыли персонала.');
        }

        // Получаем все экскурсии, на которые назначен пользователь
        $assignedExcursions = Excursion::whereHas('assignedUsers', function ($query) use ($userId) {
            $query->where('moonshine_users.id', $userId);
        })
        ->with(['bookings', 'staffPrices', 'assignedUsers'])
        ->where('is_active', true)
        ->orderBy('date_time', 'desc')
        ->get();

        $totalProfit = 0;
        $breakdown = [];

        foreach ($assignedExcursions as $excursion) {
            // Определяем роль пользователя в этой экскурсии
            $userAssignment = $excursion->assignedUsers->firstWhere('id', $userId);
            if (!$userAssignment) {
                continue;
            }
            
            $roleInExcursion = $userAssignment->pivot->role_in_excursion;
            if (!in_array($roleInExcursion, ['driver', 'guide'])) {
                continue;
            }

            // Получаем бронирования для этой экскурсии
            $bookings = $excursion->bookings;
            
            // Группируем бронирования по дате экскурсии
            // Для правильной группировки используем реальное значение excursion_date из БД
            // Если его нет, используем аксессор для вычисления даты
            $bookingsByDate = $bookings->groupBy(function ($booking) {
                // Сначала пытаемся получить реальное значение из БД
                $rawExcursionDate = $booking->getRawOriginal('excursion_date');
                $bookingTime = $booking->time;
                
                if ($rawExcursionDate) {
                    // Если есть реальное значение в БД - используем его
                    $dateOnly = is_string($rawExcursionDate) ? substr($rawExcursionDate, 0, 10) : $rawExcursionDate->format('Y-m-d');
                    $normalizedTime = is_string($bookingTime)
                        ? substr($bookingTime, 0, 5)
                        : ($bookingTime ? $bookingTime->format('H:i') : '00:00');
                    return $dateOnly . ' ' . $normalizedTime;
                }
                
                // Если нет реального значения, используем аксессор (он вычисляет дату)
                $excursionDate = $booking->excursion_date; // Это аксессор
                if ($excursionDate) {
                    return $excursionDate->format('Y-m-d H:i');
                }
                
                // Если нет даты, используем booked_at как fallback
                return $booking->booked_at ? $booking->booked_at->format('Y-m-d H:i') : 'unknown';
            });
            
            // Для каждой даты считаем прибыль отдельно
            foreach ($bookingsByDate as $dateKey => $dateBookings) {
                $passengerCount = $dateBookings->count();
                
                // Подсчитываем пассажиров по типам для этой даты
                $passengersByType = [];
                $totalRevenue = 0;
                foreach ($dateBookings as $booking) {
                    $passengerType = $booking->passenger_type ?? 'adult';
                    if (!isset($passengersByType[$passengerType])) {
                        $passengersByType[$passengerType] = 0;
                    }
                    $passengersByType[$passengerType]++;
                    $totalRevenue += (float) $booking->price;
                }
                
                // Находим подходящую цену для персонала по количеству пассажиров на эту дату
                $staffPrice = $excursion->staffPrices
                    ->where('staff_type', $roleInExcursion)
                    ->first(function ($price) use ($passengerCount) {
                        return $price->matchesPassengerCount($passengerCount);
                    });

                $profit = 0;
                $staffPriceInfo = null;
                if ($staffPrice) {
                    $profit = (float) $staffPrice->price;
                    $totalProfit += $profit;
                    $staffPriceInfo = [
                        'price' => round((float) $staffPrice->price, 2),
                        'min_passengers' => $staffPrice->min_passengers,
                        'max_passengers' => $staffPrice->max_passengers,
                    ];
                }

                // Определяем дату для отображения
                $excursionDate = null;
                if ($dateKey !== 'unknown') {
                    try {
                        // Парсим дату и время из ключа (формат: Y-m-d H:i)
                        $excursionDate = \Carbon\Carbon::parse($dateKey);
                    } catch (\Exception $e) {
                        // Если не удалось распарсить, используем date_time экскурсии
                        $excursionDate = $excursion->date_time;
                    }
                } else {
                    $excursionDate = $excursion->date_time;
                }

                $breakdown[] = [
                    'excursion_id' => $excursion->id,
                    'excursion_title' => $excursion->title,
                    'excursion_date' => $excursionDate?->format('Y-m-d'),
                    'date_time' => $excursionDate?->toISOString(),
                    'role' => $roleInExcursion,
                    'passenger_count' => $passengerCount,
                    'passengers_by_type' => $passengersByType,
                    'total_revenue' => round($totalRevenue, 2),
                    'profit' => round($profit, 2),
                    'staff_price_info' => $staffPriceInfo,
                ];
            }
        }

        return response()->json([
            'total_profit' => round($totalProfit, 2),
            'breakdown' => $breakdown,
        ]);
    }
}
