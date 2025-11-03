<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ExcursionController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\StopsController;
use App\Http\Controllers\API\WalletController;

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
Route::get('/excursions/{id}', [ExcursionController::class, 'show']);

// Остановки (публичные)
Route::get('/stops', [StopsController::class, 'index']);
Route::get('/excursions/{id}/stops', [StopsController::class, 'forExcursion']);

// Управление пользователями (публичные)
Route::post('/users', [UserController::class, 'store']);
Route::get('/users/roles', [UserController::class, 'roles']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::get('/users', [UserController::class, 'index']);

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
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);

    // Управление экскурсиями (админ)
    Route::post('/excursions', [ExcursionController::class, 'store']);
    Route::post('/excursions/{id}/assign', [ExcursionController::class, 'assign']);
    Route::delete('/excursions/{id}/assign/{user_id}', [ExcursionController::class, 'unassign']);
    Route::put('/excursions/{id}/prices', [ExcursionController::class, 'updatePrices']);
    
    // Кошелек и история продаж
    Route::get('/users/{id}/wallet', [WalletController::class, 'show']);
    Route::get('/users/{id}/sales', [WalletController::class, 'sales']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    
    // Расписание водителя
    Route::get('/users/{id}/assigned-excursions', function($id) {
        return app(ExcursionController::class)->index(request()->merge(['assigned_to' => $id]));
    });
});
