<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ExcursionController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\StopsController;
use App\Http\Controllers\API\WalletController;
use App\Http\Controllers\API\SeatPermissionController;
use App\Http\Controllers\API\SettlementController;
use App\Http\Controllers\API\BusController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Публичные маршруты (без аутентификации)
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/excursions', [ExcursionController::class, 'index']);
// Публичный endpoint для тестирования цветов схемы рассадки
Route::get('/test-seating-data', function (Request $request) {
    $excursionId = $request->query('excursion_id');
    $date = $request->query('date', '2026-01-19');
    $time = $request->query('time', '13:30');
    
    // Если не указан ID, ищем "Дегустация в Массандре"
    if (!$excursionId) {
        $excursion = \App\Models\Excursion::where('title', 'LIKE', '%Дегустация%Массандр%')
            ->orWhere('title', 'LIKE', '%Массандр%Дегустация%')
            ->first();
        if (!$excursion) {
            return response()->json(['error' => 'Excursion not found'], 404);
        }
        $excursionId = $excursion->id;
    } else {
        $excursion = \App\Models\Excursion::find($excursionId);
        if (!$excursion) {
            return response()->json(['error' => 'Excursion not found'], 404);
        }
    }
    
    // Получаем все места автобуса
    $busSeats = $excursion->busSeats()->orderBy('seat_number')->get();
    
    // Получаем бронирования на указанную дату и время
    $targetDate = \Carbon\Carbon::parse($date)->format('Y-m-d');
    $targetDayOfWeek = \Carbon\Carbon::parse($date)->dayOfWeek;
    
    $bookings = \App\Models\Booking::with(['busSeat', 'bookedByUser', 'stop'])
        ->where('excursion_id', $excursionId)
        ->where(function($query) use ($targetDate, $targetDayOfWeek, $time) {
            $query->where(function($q) use ($targetDate, $time) {
                $q->whereDate('excursion_date', $targetDate);
                if ($time) {
                    $normalizedTime = substr($time, 0, 5);
                    $q->where(function($tq) use ($normalizedTime) {
                        $tq->where('time', $normalizedTime)
                           ->orWhere('time', $normalizedTime . ':00')
                           ->orWhere('time', 'LIKE', $normalizedTime . '%');
                    });
                }
            })->orWhere(function($q) use ($targetDayOfWeek, $time) {
                $q->where('weekday', $targetDayOfWeek);
                if ($time) {
                    $normalizedTime = substr($time, 0, 5);
                    $q->where(function($tq) use ($normalizedTime) {
                        $tq->where('time', $normalizedTime)
                           ->orWhere('time', $normalizedTime . ':00')
                           ->orWhere('time', 'LIKE', $normalizedTime . '%');
                    });
                }
            });
        })
        ->get();
    
    // Создаем мапу бронирований по местам
    $bookingsBySeat = $bookings->keyBy('bus_seat_id');
    
    // Формируем данные мест
    $seatsData = $busSeats->map(function($seat) use ($bookingsBySeat) {
        $booking = $bookingsBySeat->get($seat->id);
        $hasBooking = $booking !== null;
        
        $data = [
            'id' => $seat->id,
            'seat_number' => $seat->seat_number,
            'status' => $hasBooking ? 'booked' : ($seat->status === 'available' ? 'available' : 'unavailable'),
        ];
        
        if ($hasBooking) {
            $data['booking'] = [
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'passenger_type' => $booking->passenger_type,
                'stop_title' => $booking->stop?->name,
            ];
            
            if ($booking->bookedByUser) {
                $data['booked_by_info'] = [
                    'id' => $booking->bookedByUser->id,
                    'name' => $booking->bookedByUser->name,
                    'color' => $booking->bookedByUser->color,
                ];
            }
        }
        
        return $data;
    });
    
    return response()->json([
        'excursion' => [
            'id' => $excursion->id,
            'title' => $excursion->title,
        ],
        'date' => $date,
        'time' => $time,
        'seats' => $seatsData,
        'total_seats' => $busSeats->count(),
        'booked_seats' => $seatsData->where('status', 'booked')->count(),
        'available_seats' => $seatsData->where('status', 'available')->count(),
    ]);
});
// Важно: конкретные маршруты должны быть ПЕРЕД маршрутами с параметрами
Route::get('/schedule', [ExcursionController::class, 'schedule']);
// Маршрут statistics должен быть перед {id}, поэтому выносим его сюда с middleware
Route::middleware('auth:sanctum')->get('/excursions/statistics', [ExcursionController::class, 'statistics']);
// Проверка новых назначений - должен быть перед /excursions/{id}
Route::middleware('auth:sanctum')->get('/excursions/check-new-assignments', [ExcursionController::class, 'checkNewAssignments']);
// Отмененные экскурсии - должен быть перед /excursions/{id}
Route::middleware('auth:sanctum')->get('/excursions/cancelled-dates', [ExcursionController::class, 'cancelledDates']);
// Показ экскурсии - публичный, но может требовать аутентификации для админов
Route::get('/excursions/{id}', [ExcursionController::class, 'show']);

// Остановки (публичные)
Route::get('/stops', [StopsController::class, 'index']);
Route::get('/excursions/{id}/stops', [StopsController::class, 'forExcursion']);

// Управление пользователями (публичные)
Route::delete('/users/{id}', [UserController::class, 'destroy']);
Route::post('/users', [UserController::class, 'store']);
Route::get('/users/roles', [UserController::class, 'roles']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::get('/users', [UserController::class, 'index']);

// Flutter Web App redirect
Route::get('/app', function() {
    $flutterAppPath = public_path('flutter_app');
    
    if (file_exists($flutterAppPath . '/index.html')) {
        return redirect('/flutter_app/');
    }
    
    return response()->json([
        'error' => 'Flutter app not built yet',
        'message' => 'Please run: cd flutter_app && flutter build web',
        'path' => $flutterAppPath
    ], 404);
});

// Тестовый маршрут
Route::get('/test', function() {
    return response()->json(['message' => 'Test route works']);
});

// Временный маршрут для ролей
Route::get('/roles-test', function() {
    $roles = \MoonShine\Laravel\Models\MoonshineUserRole::select('id', 'name')->get();
    return response()->json(['roles' => $roles]);
});

// Защищенные маршруты (требуют аутентификации)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    
    // Бронирование мест
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/driver', [BookingController::class, 'driverBookings']); // Бронирования для водителя/экскурсовода
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);
    Route::get('/bookings/{id}/ticket-pdf', [BookingController::class, 'ticketPdf']);
    
    // Временный тестовый роут для проверки парсинга массива IDs
    Route::get('/test-ids-parsing', function (Request $request) {
        return response()->json([
            'raw_query_string' => $request->getQueryString(),
            'input_ids' => $request->input('ids'),
            'input_ids_type' => gettype($request->input('ids')),
            'query_ids' => $request->query('ids'),
            'query_ids_type' => gettype($request->query('ids')),
            'all_query' => $request->query(),
            'all_input' => $request->all(),
        ]);
    });

    // Управление экскурсиями (админ)
    Route::post('/excursions', [ExcursionController::class, 'store']);
    Route::post('/excursions/{id}/unscheduled-date', [ExcursionController::class, 'addUnscheduledDate']);
    Route::delete('/excursions/{id}/unscheduled-date/{date_id}', [ExcursionController::class, 'deleteUnscheduledDate']);
    Route::post('/excursions/{id}/cancel-date', [ExcursionController::class, 'cancelDate']);
    Route::delete('/excursions/{id}/cancel-date/{cancelled_date_id}', [ExcursionController::class, 'restoreDate']);
    Route::post('/excursions/{id}/assign', [ExcursionController::class, 'assign']);
    Route::delete('/excursions/{id}/assign/{user_id}', [ExcursionController::class, 'unassign']);
    Route::put('/excursions/{id}/prices', [ExcursionController::class, 'updatePrices']);
    Route::put('/excursions/{id}/staff-prices', [ExcursionController::class, 'updateStaffPrices']);
    
    // Кошелек и история продаж
    Route::get('/users/{id}/wallet', [WalletController::class, 'show']);
    Route::get('/users/{id}/sales', [WalletController::class, 'sales']);
    Route::get('/users/{id}/profit', [WalletController::class, 'profit']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::put('/users/{id}/color', [UserController::class, 'updateColor']);
    
    // Расписание водителя
    Route::get('/users/{id}/assigned-excursions', function($id) {
        return app(ExcursionController::class)->index(request()->merge(['assigned_to' => $id]));
    });
    
    // Прибыль персонала (водители/экскурсоводы)
    Route::get('/users/{id}/staff-profit', [WalletController::class, 'staffProfit']);
    
    // Управление разрешениями на места 1-2 (только для админов)
    Route::get('/seat-permissions', [SeatPermissionController::class, 'index']);
    Route::get('/seat-permissions/check', [SeatPermissionController::class, 'checkPermissions']);
    Route::post('/seat-permissions', [SeatPermissionController::class, 'store']);
    Route::delete('/seat-permissions/{id}', [SeatPermissionController::class, 'destroy']);
    
    // Запросы доступа к местам 1-2
    Route::get('/seat-access-requests', [SeatPermissionController::class, 'requests']); // Админ: все запросы
    Route::get('/seat-access-requests/my', [SeatPermissionController::class, 'myRequests']); // Продавец: свои запросы
    Route::post('/seat-access-requests', [SeatPermissionController::class, 'createRequest']); // Создать запрос
    Route::post('/seat-access-requests/{id}/approve', [SeatPermissionController::class, 'approveRequest']); // Одобрить
    Route::post('/seat-access-requests/{id}/reject', [SeatPermissionController::class, 'rejectRequest']); // Отклонить
    
    // Расчетные листы
    Route::get('/settlements/sellers', [SettlementController::class, 'sellers']);
    Route::get('/settlements/sellers/{sellerId}/sales', [SettlementController::class, 'sellerSales']);
    Route::get('/settlements/sellers/{sellerId}/calendar-status', [SettlementController::class, 'calendarStatus']);
    Route::post('/settlements', [SettlementController::class, 'create']);
    Route::post('/settlements/{id}/remove-booking', [SettlementController::class, 'removeBooking']);
    Route::delete('/settlements/{id}', [SettlementController::class, 'destroy']);
    Route::get('/settlements', [SettlementController::class, 'index']);
    Route::get('/settlements/seller/{sellerId}', [SettlementController::class, 'index']);
    
    // Управление автобусами
    Route::get('/buses', [BusController::class, 'index']);
    Route::get('/buses/{id}', [BusController::class, 'show']);
    Route::post('/buses', [BusController::class, 'store']);
    Route::put('/buses/{id}', [BusController::class, 'update']);
    Route::delete('/buses/{id}', [BusController::class, 'destroy']);
    Route::post('/buses/{id}/assign-driver', [BusController::class, 'assignToDriver']);
    Route::post('/buses/{id}/unassign-driver', [BusController::class, 'unassignFromDriver']);
});
