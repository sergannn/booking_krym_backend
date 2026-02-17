<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Settlement;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SettlementController extends Controller
{
    /**
     * Получить список продавцов
     * Продавцы - это пользователи с ролью "Организатор экскурсии" или "Партнер"
     * которые имеют бронирования
     */
    public function sellers(Request $request)
    {
        // Ищем пользователей с ролями, которые могут быть продавцами
        $sellerRoleNames = ['Организатор экскурсии', 'Партнер', 'Продавец'];
        $sellerRoleIds = MoonshineUserRole::whereIn('name', $sellerRoleNames)
            ->pluck('id')
            ->toArray();
        
        if (empty($sellerRoleIds)) {
            return response()->json(['sellers' => []], 200);
        }

        // Получаем пользователей с этими ролями, у которых есть бронирования
        // Используем прямой запрос, так как bookings() может быть не доступен в базовом MoonshineUser
        $sellers = MoonshineUser::whereIn('moonshine_user_role_id', $sellerRoleIds)
            ->whereIn('id', function($query) {
                $query->select('booked_by')
                    ->from('bookings')
                    ->whereNotNull('booked_by');
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json([
            'sellers' => $sellers,
        ]);
    }

    /**
     * Получить продажи продавца с фильтрацией по датам экскурсий
     */
    public function sellerSales(Request $request, $sellerId)
    {
        $seller = MoonshineUser::findOrFail($sellerId);
        
        // Проверяем, что это продавец (роль "Организатор экскурсии", "Партнер" или "Продавец")
        $sellerRoleNames = ['Организатор экскурсии', 'Партнер', 'Продавец'];
        $sellerRoleIds = MoonshineUserRole::whereIn('name', $sellerRoleNames)
            ->pluck('id')
            ->toArray();
        
        if (empty($sellerRoleIds) || !in_array((int) $seller->moonshine_user_role_id, $sellerRoleIds)) {
            return response()->json(['error' => 'Пользователь не является продавцом'], 400);
        }

        // Определяем, нужны ли рассчитанные или не рассчитанные продажи
        $settled = $request->has('settled') && $request->boolean('settled');
        
        $query = Booking::where('booked_by', $sellerId)
            ->with(['excursion', 'stop', 'settlements']);
        
        if ($settled) {
            $query->whereHas('settlements'); // Только рассчитанные
        } else {
            $query->whereDoesntHave('settlements'); // Только не рассчитанные
        }

        // Фильтрация по датам экскурсий
        if ($request->has('date_from')) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $query->where(function($q) use ($dateFrom) {
                $q->whereNotNull('excursion_date')
                    ->where('excursion_date', '>=', $dateFrom)
                    ->orWhere(function($subQ) use ($dateFrom) {
                        $subQ->whereNull('excursion_date')
                            ->whereHas('excursion', function($excQ) use ($dateFrom) {
                                $excQ->where('date_time', '>=', $dateFrom);
                            });
                    });
            });
        }

        if ($request->has('date_to')) {
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
            $query->where(function($q) use ($dateTo) {
                $q->whereNotNull('excursion_date')
                    ->where('excursion_date', '<=', $dateTo)
                    ->orWhere(function($subQ) use ($dateTo) {
                        $subQ->whereNull('excursion_date')
                            ->whereHas('excursion', function($excQ) use ($dateTo) {
                                $excQ->where('date_time', '<=', $dateTo);
                            });
                    });
            });
        }

        $bookings = $query->get();

        $sales = $bookings->map(function ($booking) use ($settled) {
            $excursionDateTime = $booking->excursion_date; // Аксессор
            
            $saleData = [
                'id' => $booking->id,
                'excursion' => [
                    'id' => $booking->excursion->id,
                    'title' => $booking->excursion->title,
                    'date_time' => $excursionDateTime?->toISOString(),
                ],
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'passenger_type' => $booking->passenger_type,
                'price' => (float) $booking->price,
                'stop' => $booking->stop ? [
                    'id' => $booking->stop->id,
                    'name' => $booking->stop->name,
                ] : null,
                'booked_at' => $booking->booked_at?->toISOString(),
            ];
            
            // Для рассчитанных продаж добавляем информацию о расчете
            if ($settled && $booking->settlements->isNotEmpty()) {
                $settlement = $booking->settlements->first();
                $saleData['settlement'] = [
                    'id' => $settlement->id,
                    'settlement_date' => $settlement->settlement_date->toDateString(),
                    'total_amount' => (float) $settlement->total_amount,
                ];
            }
            
            return $saleData;
        });

        $totalAmount = $sales->sum('price');

        // Получаем общую статистику за период (если указаны даты)
        $periodStats = null;
        if ($request->has('date_from') || $request->has('date_to')) {
            $allBookingsQuery = Booking::where('booked_by', $sellerId);
            
            // Применяем те же фильтры по датам
            if ($request->has('date_from')) {
                $dateFrom = Carbon::parse($request->date_from)->startOfDay();
                $allBookingsQuery->where(function($q) use ($dateFrom) {
                    $q->whereNotNull('excursion_date')
                        ->where('excursion_date', '>=', $dateFrom)
                        ->orWhere(function($subQ) use ($dateFrom) {
                            $subQ->whereNull('excursion_date')
                                ->whereHas('excursion', function($excQ) use ($dateFrom) {
                                    $excQ->where('date_time', '>=', $dateFrom);
                                });
                        });
                });
            }
            
            if ($request->has('date_to')) {
                $dateTo = Carbon::parse($request->date_to)->endOfDay();
                $allBookingsQuery->where(function($q) use ($dateTo) {
                    $q->whereNotNull('excursion_date')
                        ->where('excursion_date', '<=', $dateTo)
                        ->orWhere(function($subQ) use ($dateTo) {
                            $subQ->whereNull('excursion_date')
                                ->whereHas('excursion', function($excQ) use ($dateTo) {
                                    $excQ->where('date_time', '<=', $dateTo);
                                });
                        });
                });
            }
            
            $allBookings = $allBookingsQuery->with('settlements')->get();
            
            $periodStats = [
                'total_sales' => (float) round($allBookings->sum('price'), 2),
                'settled_sales' => (float) round($allBookings->filter(function($b) {
                    return $b->settlements->isNotEmpty();
                })->sum('price'), 2),
                'unsettled_sales' => (float) round($allBookings->filter(function($b) {
                    return $b->settlements->isEmpty();
                })->sum('price'), 2),
                'total_count' => $allBookings->count(),
                'settled_count' => $allBookings->filter(function($b) {
                    return $b->settlements->isNotEmpty();
                })->count(),
                'unsettled_count' => $allBookings->filter(function($b) {
                    return $b->settlements->isEmpty();
                })->count(),
            ];
        }

        return response()->json([
            'seller' => [
                'id' => $seller->id,
                'name' => $seller->name,
                'email' => $seller->email,
            ],
            'total_amount' => (float) round($totalAmount, 2),
            'sales' => $sales->values(),
            'period_stats' => $periodStats,
        ]);
    }

    /**
     * Создать расчет (settlement)
     */
    public function create(Request $request)
    {
        $request->validate([
            'seller_id' => 'required|exists:moonshine_users,id',
            'booking_ids' => 'required|array',
            'booking_ids.*' => 'exists:bookings,id',
            'notes' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $seller = MoonshineUser::findOrFail($request->seller_id);
        
        // Проверяем, что это продавец (роль "Организатор экскурсии", "Партнер" или "Продавец")
        $sellerRoleNames = ['Организатор экскурсии', 'Партнер', 'Продавец'];
        $sellerRoleIds = MoonshineUserRole::whereIn('name', $sellerRoleNames)
            ->pluck('id')
            ->toArray();
        
        if (empty($sellerRoleIds) || !in_array((int) $seller->moonshine_user_role_id, $sellerRoleIds)) {
            return response()->json(['error' => 'Пользователь не является продавцом'], 400);
        }

        // Проверяем, что все бронирования принадлежат этому продавцу и не рассчитаны
        $bookings = Booking::whereIn('id', $request->booking_ids)
            ->where('booked_by', $seller->id)
            ->whereDoesntHave('settlements')
            ->get();

        if ($bookings->count() !== count($request->booking_ids)) {
            return response()->json([
                'error' => 'Некоторые бронирования не найдены, не принадлежат продавцу или уже рассчитаны'
            ], 400);
        }

        $totalAmount = $bookings->sum('price');

        DB::beginTransaction();
        try {
            $settlement = Settlement::create([
                'seller_id' => $seller->id,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
                'settlement_date' => now(),
                'date_from' => $request->date_from ? Carbon::parse($request->date_from) : null,
                'date_to' => $request->date_to ? Carbon::parse($request->date_to) : null,
            ]);

            $settlement->bookings()->attach($request->booking_ids);

            DB::commit();

            return response()->json([
                'message' => 'Расчет создан успешно',
                'settlement' => [
                    'id' => $settlement->id,
                    'seller_id' => $settlement->seller_id,
                    'total_amount' => (float) $settlement->total_amount, // Явно приводим к float
                    'settlement_date' => $settlement->settlement_date->toDateString(),
                    'bookings_count' => $bookings->count(),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Ошибка при создании расчета',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Удалить конкретную продажу из расчета
     */
    public function removeBooking(Request $request, $settlementId)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $settlement = Settlement::with('bookings')->findOrFail($settlementId);
        $bookingId = $request->input('booking_id');
        
        // Проверяем, что бронирование принадлежит этому расчету
        if (!$settlement->bookings->contains($bookingId)) {
            return response()->json([
                'error' => 'Бронирование не найдено в этом расчете'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Отвязываем бронирование от расчета
            $settlement->bookings()->detach($bookingId);
            
            // Перезагружаем отношения, чтобы получить актуальный список
            $settlement->refresh();
            $remainingBookings = $settlement->bookings;
            
            // Если в расчете не осталось бронирований, удаляем расчет
            if ($remainingBookings->isEmpty()) {
                $settlement->delete();
                $message = 'Расчет отменен (не осталось продаж)';
                $settlementDeleted = true;
            } else {
                // Пересчитываем сумму расчета
                $newTotalAmount = $remainingBookings->sum('price');
                $settlement->update(['total_amount' => $newTotalAmount]);
                $message = 'Продажа удалена из расчета';
                $settlementDeleted = false;
            }
            
            DB::commit();
            
            return response()->json([
                'message' => $message,
                'settlement_deleted' => $settlementDeleted,
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Ошибка при удалении продажи из расчета',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Удалить расчет полностью (отменить весь расчет)
     */
    public function destroy($id)
    {
        $settlement = Settlement::with('bookings')->findOrFail($id);
        
        DB::beginTransaction();
        try {
            // Отвязываем бронирования от расчета
            $settlement->bookings()->detach();
            
            // Удаляем расчет
            $settlement->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Расчет отменен успешно',
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Ошибка при отмене расчета',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить статус дней для календаря (для конкретного продавца)
     */
    public function calendarStatus(Request $request, $sellerId)
    {
        $seller = MoonshineUser::findOrFail($sellerId);
        
        // Проверяем, что это продавец
        $sellerRoleNames = ['Организатор экскурсии', 'Партнер', 'Продавец'];
        $sellerRoleIds = MoonshineUserRole::whereIn('name', $sellerRoleNames)
            ->pluck('id')
            ->toArray();
        
        if (empty($sellerRoleIds) || !in_array((int) $seller->moonshine_user_role_id, $sellerRoleIds)) {
            return response()->json(['error' => 'Пользователь не является продавцом'], 400);
        }

        // Получаем все бронирования продавца
        $bookings = Booking::where('booked_by', $sellerId)
            ->with('settlements')
            ->get();

        // Группируем по датам экскурсий
        $daysStatus = [];
        
        foreach ($bookings as $booking) {
            $excursionDateTime = $booking->excursion_date; // Аксессор
            if (!$excursionDateTime) {
                continue;
            }
            
            $dateKey = $excursionDateTime->format('Y-m-d');
            
            if (!isset($daysStatus[$dateKey])) {
                $daysStatus[$dateKey] = [
                    'total' => 0,
                    'settled' => 0,
                    'unsettled' => 0,
                ];
            }
            
            $daysStatus[$dateKey]['total']++;
            
            if ($booking->settlements->isNotEmpty()) {
                $daysStatus[$dateKey]['settled']++;
            } else {
                $daysStatus[$dateKey]['unsettled']++;
            }
        }

        // Определяем статус каждого дня
        $result = [];
        foreach ($daysStatus as $date => $status) {
            if ($status['settled'] > 0 && $status['unsettled'] > 0) {
                // Смешанный статус - есть и рассчитанные, и не рассчитанные
                $result[$date] = 'partial'; // Оранжевый
            } elseif ($status['settled'] > 0) {
                // Все рассчитаны
                $result[$date] = 'settled'; // Зеленый
            } else {
                // Все не рассчитаны
                $result[$date] = 'unsettled'; // Красный
            }
        }

        return response()->json([
            'days_status' => $result,
        ]);
    }

    /**
     * Получить список расчетов продавца
     */
    public function index(Request $request, $sellerId = null)
    {
        $query = Settlement::with(['seller', 'bookings.excursion']);

        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        $settlements = $query->orderBy('settlement_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'settlements' => $settlements->map(function ($settlement) {
                return [
                    'id' => $settlement->id,
                    'seller' => [
                        'id' => $settlement->seller->id,
                        'name' => $settlement->seller->name,
                        'email' => $settlement->seller->email,
                    ],
                    'total_amount' => (float) $settlement->total_amount,
                    'notes' => $settlement->notes,
                    'settlement_date' => $settlement->settlement_date->toDateString(),
                    'date_from' => $settlement->date_from?->toDateString(),
                    'date_to' => $settlement->date_to?->toDateString(),
                    'bookings_count' => $settlement->bookings->count(),
                    'created_at' => $settlement->created_at->toISOString(),
                ];
            }),
        ]);
    }
}
