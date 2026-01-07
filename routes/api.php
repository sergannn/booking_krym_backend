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
// Важно: конкретные маршруты должны быть ПЕРЕД маршрутами с параметрами
Route::get('/schedule', [ExcursionController::class, 'schedule']);
// Маршрут statistics должен быть перед {id}, поэтому выносим его сюда с middleware
Route::middleware('auth:sanctum')->get('/excursions/statistics', [ExcursionController::class, 'statistics']);
// Проверка новых назначений - должен быть перед /excursions/{id}
Route::middleware('auth:sanctum')->get('/excursions/check-new-assignments', [ExcursionController::class, 'checkNewAssignments']);
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

    // Управление экскурсиями (админ)
    Route::post('/excursions', [ExcursionController::class, 'store']);
    Route::post('/excursions/{id}/unscheduled-date', [ExcursionController::class, 'addUnscheduledDate']);
    Route::post('/excursions/{id}/assign', [ExcursionController::class, 'assign']);
    Route::delete('/excursions/{id}/assign/{user_id}', [ExcursionController::class, 'unassign']);
    Route::put('/excursions/{id}/prices', [ExcursionController::class, 'updatePrices']);
    Route::put('/excursions/{id}/staff-prices', [ExcursionController::class, 'updateStaffPrices']);
    
    // Кошелек и история продаж
    Route::get('/users/{id}/wallet', [WalletController::class, 'show']);
    Route::get('/users/{id}/sales', [WalletController::class, 'sales']);
    Route::get('/users/{id}/profit', [WalletController::class, 'profit']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    
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
});
