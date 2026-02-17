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
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        // Поддержка двух форматов:
        // 1. Старый: seat_numbers (массив) + passenger_type (один для всех)
        // 2. Новый: seats (массив объектов {seat_number, passenger_type})
        $hasNewFormat = $request->has('seats') && is_array($request->seats);
        $hasOldFormat = $request->has('seat_numbers') && is_array($request->seat_numbers);

        if (!$hasNewFormat && !$hasOldFormat) {
            throw ValidationException::withMessages([
                'seats' => ['Either "seats" or "seat_numbers" must be provided.'],
            ]);
        }

        $request->validate([
            'excursion_id' => 'required|exists:excursions,id',
            // customer_name и customer_phone обязательны только для старого формата
            'customer_name' => $hasOldFormat ? 'required|string|max:255' : 'sometimes|string|max:255',
            'customer_phone' => $hasOldFormat ? 'required|string|max:20' : 'sometimes|string|max:20',
            'stop_id' => 'required|exists:stops,id',
            // Дата/время экскурсии (нужно для проверки доступности мест)
            'weekday' => 'required|integer|min:1|max:7',
            'time' => 'required|string', // HH:MM
            'excursion_date' => 'required|date', // Конкретная дата экскурсии (YYYY-MM-DD)
            // booked_by_id - опционально, только для админов (для бронирования от лица продавца)
            'booked_by_id' => 'sometimes|nullable|exists:moonshine_users,id',
            // Старый формат
            'seat_numbers' => $hasOldFormat ? 'required|array' : 'sometimes|array',
            'seat_numbers.*' => $hasOldFormat ? 'integer|min:1|max:100' : 'sometimes|integer|min:1|max:100',
            'passenger_type' => $hasOldFormat ? 'required|in:adult,child,senior,disabled,special,concession' : 'sometimes|in:adult,child,senior,disabled,special,concession',
            // Новый формат
            'seats' => $hasNewFormat ? 'required|array' : 'sometimes|array',
            'seats.*.seat_number' => $hasNewFormat ? 'required|integer|min:1|max:100' : 'sometimes|integer|min:1|max:100',
            'seats.*.passenger_type' => $hasNewFormat ? 'required|in:adult,child,senior,disabled,special,concession' : 'sometimes|in:adult,child,senior,disabled,special,concession',
            'seats.*.customer_name' => $hasNewFormat ? 'required|string|max:255' : 'sometimes|string|max:255',
            'seats.*.customer_phone' => $hasNewFormat ? 'required|string|max:20' : 'sometimes|string|max:20',
        ]);

        $excursion = Excursion::with('prices')->findOrFail($request->excursion_id);
        $user = $request->user();
        
        // Определяем, от лица кого бронируем
        // Если админ указал booked_by_id, используем его, иначе используем текущего пользователя
        $isAdmin = $user->isSuperUser() || (int) $user->moonshine_user_role_id === 1;
        $bookedByUserId = $user->id; // По умолчанию - текущий пользователь
        
        if ($isAdmin && $request->has('booked_by_id') && $request->booked_by_id !== null) {
            // Админ может бронировать от лица другого пользователя (продавца)
            $bookedByUserId = (int) $request->booked_by_id;
            // Проверяем, что указанный пользователь существует
            $targetUser = \MoonShine\Laravel\Models\MoonshineUser::find($bookedByUserId);
            if (!$targetUser) {
                throw ValidationException::withMessages([
                    'booked_by_id' => ['Указанный пользователь не найден.'],
                ]);
            }
        }
        
        // Целевая дата/время бронирования
        $targetWeekday = (int)$request->input('weekday');
        $targetTime = $request->input('time');
        // Нормализуем время до HH:MM
        if ($targetTime && strlen($targetTime) >= 5) {
            $targetTime = substr($targetTime, 0, 5);
        }
        
        // Конкретная дата экскурсии
        $targetDate = Carbon::parse($request->input('excursion_date'))->format('Y-m-d');
        
        // Проверяем, что экскурсия активна
        if (!$excursion->is_active) {
            throw ValidationException::withMessages([
                'excursion_id' => ['This excursion is not available for booking.'],
            ]);
        }

        // Проверяем, не отменена ли экскурсия на эту дату и время
        $targetDateTime = Carbon::parse($targetDate . ' ' . $targetTime);
        $isCancelled = \App\Models\CancelledExcursionDate::where('excursion_id', $excursion->id)
            ->where('date_time', $targetDateTime->format('Y-m-d H:i:s'))
            ->exists();
        
        if ($isCancelled) {
            throw ValidationException::withMessages([
                'excursion_id' => ['Эта экскурсия отменена на указанную дату и время.'],
            ]);
        }

        // Преобразуем в единый формат: массив {seat_number, passenger_type, with_entry}
        $seatsToBook = [];
        if ($hasNewFormat) {
            foreach ($request->seats as $seatData) {
                $seatsToBook[] = [
                    'seat_number' => $seatData['seat_number'],
                    'passenger_type' => $seatData['passenger_type'],
                    'with_entry' => $seatData['with_entry'] ?? false,
                    'customer_name' => $seatData['customer_name'] ?? null,
                    'customer_phone' => $seatData['customer_phone'] ?? null,
                ];
            }
        } else {
            // Старый формат: один passenger_type для всех мест
            $passengerType = $request->passenger_type;
            $withEntry = $request->input('with_entry', false);
            foreach ($request->seat_numbers as $seatNumber) {
                $seatsToBook[] = [
                    'seat_number' => $seatNumber,
                    'passenger_type' => $passengerType,
                    'with_entry' => $withEntry,
                ];
            }
        }

        $bookedSeats = [];
        $bookings = collect([]); // Используем коллекцию для удобной работы с map
        $errors = [];

        foreach ($seatsToBook as $seatData) {
            $seatNumber = $seatData['seat_number'];
            $passengerType = $seatData['passenger_type'];
            $withEntry = $seatData['with_entry'] ?? false;

            // Проверка: места 1 и 2 могут продавать только администраторы или пользователи с разрешением
            if (in_array($seatNumber, [1, 2])) {
                $isAdmin = $user->isSuperUser() || (int) $user->moonshine_user_role_id === 1;
                
                if (!$isAdmin) {
                    // Проверяем наличие разрешения для этой даты и места
                    // Используем whereDate для корректного сравнения дат
                    $hasPermission = \App\Models\SeatPermission::where('excursion_id', $excursion->id)
                        ->where('user_id', $user->id)
                        ->whereDate('excursion_date', $targetDate)
                        ->where('seat_number', $seatNumber)
                        ->exists();
                    
                    if (!$hasPermission) {
                        $errors[] = "Для бронирования места {$seatNumber} необходимо разрешение администратора. Вы можете запросить доступ.";
                        continue;
                    }
                }
            }

            // Получаем тариф для типа пассажира
            $tariff = $excursion->prices
                ->firstWhere('passenger_type', $passengerType);

            if (! $tariff) {
                $errors[] = "Tariff for passenger type '{$passengerType}' on seat {$seatNumber} is not configured.";
                continue;
            }

            // Определяем цену в зависимости от наличия входного билета
            if ($withEntry) {
                $pricePerSeat = $tariff->price_with_entry ?? $tariff->price ?? 0;
            } else {
                $pricePerSeat = $tariff->price_without_entry ?? $tariff->price ?? 0;
            }
            
            // Если цена не настроена или <= 0, используем 0 вместо ошибки
            if ($pricePerSeat === null || $pricePerSeat <= 0) {
                $pricePerSeat = 0;
            }

            $seat = BusSeat::where('excursion_id', $excursion->id)
                ->where('seat_number', $seatNumber)
                ->first();

            if (!$seat) {
                $errors[] = "Seat {$seatNumber} does not exist for this excursion.";
                continue;
            }

            // Проверяем, нет ли брони на эту же конкретную дату/время для этого места
            // Нормализуем время из базы для сравнения (обрезаем секунды, если есть)
            $alreadyBooked = Booking::where('excursion_id', $excursion->id)
                ->where('bus_seat_id', $seat->id)
                ->where('excursion_date', $targetDate)
                ->whereRaw("SUBSTRING(time, 1, 5) = ?", [$targetTime])
                ->exists();
            if ($alreadyBooked) {
                $errors[] = "Seat {$seatNumber} is not available for the selected date/time.";
                continue;
            }

            // Создаем запись бронирования
            // Используем данные пассажира из массива seats, если они есть, иначе из общих полей
            $customerName = $seatData['customer_name'] ?? $request->input('customer_name');
            $customerPhone = $seatData['customer_phone'] ?? $request->input('customer_phone');
            
            // Проверяем, что данные пассажира есть
            if (empty($customerName) || empty($customerPhone)) {
                $errors[] = "Customer name and phone are required for seat {$seatNumber}.";
                continue;
            }
            
            $booking = Booking::create([
                'excursion_id' => $excursion->id,
                'weekday' => $targetWeekday,
                'time' => $targetTime,
                'excursion_date' => $targetDate,
                'bus_seat_id' => $seat->id,
                'booked_by' => $bookedByUserId, // Используем определенного пользователя (админ или продавец)
                'price' => $pricePerSeat,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'passenger_type' => $passengerType,
                'with_entry' => $withEntry,
                'stop_id' => $request->stop_id,
                'booked_at' => now(),
            ]);

            // Обновляем поле booked_by в bus_seats для отображения в схеме рассадки
            $seat->update([
                'booked_by' => $bookedByUserId,
                'booked_at' => now(),
                'status' => 'booked',
            ]);

            // Создаем транзакцию в кошельке продавца (того, от лица кого бронируем)
            WalletTransaction::create([
                'user_id' => $bookedByUserId,
                'booking_id' => $booking->id,
                'amount' => $pricePerSeat,
                'description' => "№{$seatNumber} ({$passengerType})",
            ]);

            $bookedSeats[] = $seat;
            $bookings->push($booking);
        }

        // Загружаем связь bookedByUser для всех бронирований
        if ($bookings->isNotEmpty()) {
            // Для коллекции моделей Eloquent используем loadMissing или загружаем через запрос
            $bookingIds = $bookings->pluck('id')->toArray();
            $bookingsWithRelations = Booking::with('bookedByUser')->whereIn('id', $bookingIds)->get()->keyBy('id');
            // Заменяем модели в коллекции на модели с загруженными связями
            $bookings = $bookings->map(function ($booking) use ($bookingsWithRelations) {
                return $bookingsWithRelations->get($booking->id, $booking);
            });
        }

        if (!empty($errors)) {
            // Логируем ошибки для отладки
            \Log::warning('Booking errors occurred', [
                'user_id' => $user->id,
                'excursion_id' => $excursion->id,
                'target_date' => $targetDate,
                'target_time' => $targetTime,
                'errors' => $errors,
                'successfully_booked_count' => count($bookings),
                'failed_count' => count($errors),
            ]);
            
            return response()->json([
                'message' => 'Some seats could not be booked.',
                'errors' => $errors,
                'booked_seats' => $bookedSeats,
                'bookings' => $bookings->map(function ($booking) {
                    // Дата/время экскурсии: сначала проверяем excursion_date, затем weekday/time
                    $bookingDateTime = null;
                    
                    // 1. Если есть конкретная дата экскурсии (excursion_date) - используем её
                    // Используем getRawOriginal, так как каст 'date' может давать неправильную дату
                    $rawExcursionDate = $booking->getRawOriginal('excursion_date');
                    if ($rawExcursionDate) {
                        $normalizedTime = is_string($booking->time)
                            ? substr($booking->time, 0, 5)
                            : ($booking->time ? $booking->time->format('H:i') : '00:00');
                        $bookingDateTime = \Carbon\Carbon::parse($rawExcursionDate . ' ' . $normalizedTime);
                    }
                    // 2. Если нет excursion_date, но есть weekday и time - строим ближайшую дату
                    elseif ($booking->weekday && $booking->time) {
                        $startDate = \Carbon\Carbon::now()->startOfDay();
                        $normalizedTime = is_string($booking->time)
                            ? substr($booking->time, 0, 5)
                            : $booking->time->format('H:i');
                        for ($i = 0; $i < 60; $i++) {
                            $date = $startDate->copy()->addDays($i);
                            if ($date->isoWeekday() == $booking->weekday) {
                                $bookingDateTime = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $normalizedTime);
                                break;
                            }
                        }
                    }
                    // 3. Фолбэк - дата/время из экскурсии
                    if (!$bookingDateTime) {
                        $bookingDateTime = $booking->excursion->date_time;
                    }

                    return [
                        'id' => $booking->id,
                        'excursion_id' => $booking->excursion_id,
                        'bus_seat_id' => $booking->bus_seat_id,
                        'price' => $booking->price,
                        'customer_name' => $booking->customer_name,
                        'customer_phone' => $booking->customer_phone,
                        'passenger_type' => $booking->passenger_type,
                        'with_entry' => $booking->with_entry ?? false,
                        'stop_id' => $booking->stop_id,
                        'date_time' => $bookingDateTime?->toISOString() ?? '',
                        'booked_at' => $booking->booked_at?->toISOString() ?? '',
                        'booked_by' => $booking->booked_by,
                        'booked_by_name' => $booking->bookedByUser?->name ?? null,
                    ];
                })->values()->all(),
            ], 422);
        }

        return response()->json([
            'message' => 'Seats booked successfully.',
            'booked_seats' => $bookedSeats,
            'bookings' => $bookings->map(function ($booking) {
                // Дата/время экскурсии: сначала проверяем excursion_date, затем weekday/time
                $bookingDateTime = null;
                
                // 1. Если есть конкретная дата экскурсии (excursion_date) - используем её
                // Используем getRawOriginal, так как каст 'date' может давать неправильную дату
                $rawExcursionDate = $booking->getRawOriginal('excursion_date');
                if ($rawExcursionDate) {
                    // Обрезаем до формата YYYY-MM-DD (первые 10 символов), так как может быть с временем
                    $dateOnly = is_string($rawExcursionDate) ? substr($rawExcursionDate, 0, 10) : $rawExcursionDate;
                    $normalizedTime = is_string($booking->time)
                        ? substr($booking->time, 0, 5)
                        : ($booking->time ? $booking->time->format('H:i') : '00:00');
                    $bookingDateTime = \Carbon\Carbon::parse($dateOnly . ' ' . $normalizedTime);
                }
                // 2. Если нет excursion_date, но есть weekday и time - строим ближайшую дату
                elseif ($booking->weekday && $booking->time) {
                    $startDate = \Carbon\Carbon::now()->startOfDay();
                    $normalizedTime = is_string($booking->time)
                        ? substr($booking->time, 0, 5)
                        : $booking->time->format('H:i');
                    for ($i = 0; $i < 60; $i++) {
                        $date = $startDate->copy()->addDays($i);
                        if ($date->isoWeekday() == $booking->weekday) {
                            $bookingDateTime = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $normalizedTime);
                            break;
                        }
                    }
                }
                // 3. Фолбэк - дата/время из экскурсии
                if (!$bookingDateTime) {
                    $bookingDateTime = $booking->excursion->date_time;
                }

                return [
                    'id' => $booking->id,
                    'excursion_id' => $booking->excursion_id,
                    'bus_seat_id' => $booking->bus_seat_id,
                    'price' => $booking->price,
                    'customer_name' => $booking->customer_name,
                    'customer_phone' => $booking->customer_phone,
                    'passenger_type' => $booking->passenger_type,
                    'with_entry' => $booking->with_entry ?? false,
                    'stop_id' => $booking->stop_id,
                    'date_time' => $bookingDateTime?->toISOString() ?? '',
                    'booked_at' => $booking->booked_at?->toISOString() ?? '',
                    'booked_by' => $booking->booked_by,
                    'booked_by_name' => $booking->bookedByUser?->name ?? null,
                ];
            })->values()->all(),
        ], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        // Администраторы видят все бронирования, остальные - только свои
        $isAdmin = $user->isSuperUser() || (int) $user->moonshine_user_role_id === 1;
        
        $query = Booking::with(['excursion', 'stop', 'busSeat', 'bookedByUser']);
        
        if (!$isAdmin) {
            $query->where('booked_by', $user->id);
        }
        
        $bookings = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'bookings' => $bookings->map(function ($booking) {
                // Строим дату экскурсии: сначала проверяем excursion_date, затем weekday/time
                $bookingDateTime = null;
                
                // 1. Если есть конкретная дата экскурсии (excursion_date) - используем её
                // Используем getRawOriginal, так как каст 'date' может давать неправильную дату
                $rawExcursionDate = $booking->getRawOriginal('excursion_date');
                if ($rawExcursionDate) {
                    // Обрезаем до формата YYYY-MM-DD (первые 10 символов), так как может быть с временем
                    $dateOnly = is_string($rawExcursionDate) ? substr($rawExcursionDate, 0, 10) : $rawExcursionDate;
                    $normalizedTime = is_string($booking->time)
                        ? substr($booking->time, 0, 5)
                        : ($booking->time ? $booking->time->format('H:i') : '00:00');
                    $bookingDateTime = \Carbon\Carbon::parse($dateOnly . ' ' . $normalizedTime);
                }
                // 2. Если нет excursion_date, но есть weekday и time - строим ближайшую дату
                elseif ($booking->weekday && $booking->time) {
                    $startDate = \Carbon\Carbon::now()->startOfDay();
                    $normalizedTime = is_string($booking->time)
                        ? substr($booking->time, 0, 5)
                        : $booking->time->format('H:i');
                    for ($i = 0; $i < 60; $i++) {
                        $date = $startDate->copy()->addDays($i);
                        if ($date->isoWeekday() == $booking->weekday) {
                            $bookingDateTime = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $normalizedTime);
                            break;
                        }
                    }
                }
                // 3. Фолбэк - дата/время из экскурсии
                if (!$bookingDateTime) {
                    $bookingDateTime = $booking->excursion->date_time;
                }

                return [
                    'id' => $booking->id,
                    'excursion' => [
                        'id' => $booking->excursion->id,
                        'title' => $booking->excursion->title,
                        'date_time' => $bookingDateTime?->toISOString() ?? '',
                        'price' => $booking->excursion->price,
                        'max_seats' => $booking->excursion->max_seats,
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
                    'booked_at' => $booking->booked_at?->toISOString() ?? '',
                    'booked_by' => $booking->booked_by,
                    'booked_by_name' => $booking->bookedByUser?->name ?? null,
                ];
            }),
        ]);
    }

    /**
     * Получить бронирования для водителя/экскурсовода
     * Показывает только бронирования для экскурсий, на которые назначен пользователь
     */
    public function driverBookings(Request $request)
    {
        $user = $request->user();
        
        // Проверяем, что это персонал (водитель или экскурсовод)
        $isStaff = $user && in_array((int) $user->moonshine_user_role_id, [3, 5], true);
        
        if (!$isStaff) {
            abort(403, 'Доступ только для водителей и экскурсоводов');
        }

        // Получаем назначения пользователя с датами
        $assignments = \DB::table('excursion_user')
            ->where('user_id', $user->id)
            ->get();

        if ($assignments->isEmpty()) {
            return response()->json([
                'bookings' => [],
            ]);
        }

        // Получаем бронирования для назначенных экскурсий с расписанием
        $bookings = Booking::with(['excursion.scheduleTemplate.scheduleDays', 'stop', 'busSeat'])
            ->where(function ($query) use ($assignments) {
                foreach ($assignments as $assignment) {
                    $query->orWhere(function ($q) use ($assignment) {
                        $q->where('excursion_id', $assignment->excursion_id);
                        
                        // Если назначение на конкретную дату — фильтруем по ней
                        if ($assignment->excursion_date) {
                            $assignmentDate = \Carbon\Carbon::parse($assignment->excursion_date)->format('Y-m-d');
                            $q->whereDate('excursion_date', $assignmentDate);
                            
                            // Если указано время — фильтруем и по нему
                            if ($assignment->time) {
                                $q->where(function ($timeQuery) use ($assignment) {
                                    $timeQuery->where('time', $assignment->time)
                                              ->orWhere('time', $assignment->time . ':00')
                                              ->orWhere('time', 'LIKE', $assignment->time . '%');
                                });
                            }
                        }
                        // Если дата не указана — показываем все бронирования этой экскурсии
                    });
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'bookings' => $bookings->map(function ($booking) {
                $excursion = $booking->excursion;
                
                // Строим дату экскурсии: сначала проверяем excursion_date, затем weekday/time
                $bookingDateTime = null;
                
                // 1. Если есть конкретная дата экскурсии (excursion_date) - используем её
                // Используем getRawOriginal, так как каст 'date' может давать неправильную дату
                $rawExcursionDate = $booking->getRawOriginal('excursion_date');
                if ($rawExcursionDate) {
                    // Обрезаем до формата YYYY-MM-DD (первые 10 символов), так как может быть с временем
                    $dateOnly = is_string($rawExcursionDate) ? substr($rawExcursionDate, 0, 10) : $rawExcursionDate;
                    $normalizedTime = is_string($booking->time)
                        ? substr($booking->time, 0, 5)
                        : ($booking->time ? $booking->time->format('H:i') : '00:00');
                    $bookingDateTime = \Carbon\Carbon::parse($dateOnly . ' ' . $normalizedTime);
                }
                // 2. Если нет excursion_date, но есть weekday и time - строим ближайшую дату
                elseif ($booking->weekday && $booking->time) {
                    // Находим ближайшую дату с нужным weekday
                    $startDate = \Carbon\Carbon::now()->startOfDay();
                    $normalizedTime = is_string($booking->time)
                        ? substr($booking->time, 0, 5)
                        : $booking->time->format('H:i');
                    for ($i = 0; $i < 60; $i++) {
                        $date = $startDate->copy()->addDays($i);
                        if ($date->isoWeekday() == $booking->weekday) {
                            $bookingDateTime = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $normalizedTime);
                            break;
                        }
                    }
                }
                
                // 3. Фолбэк - дата/время из экскурсии
                if (!$bookingDateTime) {
                    $bookingDateTime = $excursion->date_time;
                }
                
                return [
                    'id' => $booking->id,
                    'excursion' => [
                        'id' => $excursion->id,
                        'title' => $excursion->title,
                        'date_time' => $bookingDateTime?->toISOString() ?? '',
                        'price' => $excursion->price,
                        'max_seats' => $excursion->max_seats,
                        'schedule_by_date' => [], // Не нужны - у бронирования конкретная дата
                    ],
                    'weekday' => $booking->weekday,
                    'time' => $booking->time ? (is_string($booking->time) ? $booking->time : $booking->time->format('H:i')) : null,
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
                        'order' => $booking->stop->order,
                    ] : null,
                    'booked_at' => $booking->booked_at?->toISOString() ?? '',
                    'booked_by' => $booking->booked_by,
                    'booked_by_name' => $booking->bookedByUser?->name ?? null,
                ];
            }),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $isAdmin = $user->isSuperUser() || (int) $user->moonshine_user_role_id === 1;
        
        // Админ может отменять любые бронирования, продавец - только свои
        $query = Booking::with(['excursion', 'busSeat'])->where('id', $id);
        if (!$isAdmin) {
            $query->where('booked_by', $user->id);
        }
        
        $booking = $query->firstOrFail();

        $excursionDate = $booking->excursion?->date_time;
        
        // Проверка для продавцов: нельзя отменять прошедшие экскурсии
        if (!$isAdmin && $excursionDate && $excursionDate->isPast()) {
            return response()->json([
                'message' => 'Нельзя отменять прошедшие экскурсии.',
            ], 422);
        }
        
        // Отмена разрешена если:
        // - Для админа: всегда можно отменить (без ограничений по времени)
        // - Для продавцов: только если до экскурсии >= 24 часов
        // Исключение: если с момента бронирования прошло менее 30 минут, можно отменить даже если до экскурсии < 24 часов
        if (!$isAdmin && $excursionDate && $excursionDate->isFuture() && $excursionDate->diffInHours(now()) < 24) {
            // Проверяем, прошло ли менее 30 минут с момента создания бронирования
            $bookingCreatedAt = $booking->created_at ?? $booking->booked_at ?? now();
            $minutesSinceBooking = $bookingCreatedAt->diffInMinutes(now());
            
            // Если прошло 30 минут или больше, применяем ограничение 24 часа
            if ($minutesSinceBooking >= 30) {
                return response()->json([
                    'message' => 'Отмена невозможна менее чем за 24 часа до экскурсии.',
                ], 422);
            }
            // Если прошло менее 30 минут, разрешаем отмену (не возвращаем ошибку)
        }

        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return response()->json([
                'message' => 'Укажите причину отмены.',
            ], 422);
        }

        $seat = $booking->busSeat;
        
        // Освобождаем место
        $seat->update([
            'status' => 'available',
            'booked_by' => null,
            'booked_at' => null,
        ]);

        // Создаем обратную транзакцию в кошельке
        WalletTransaction::create([
            'user_id' => $request->user()->id,
            'booking_id' => $booking->id,
            'amount' => -$booking->price, // Отрицательная сумма для возврата
            'description' => "Отмена бронирования места №{$seat->seat_number} на экскурсию '{$booking->excursion->title}'. Причина: {$reason}",
        ]);

        // Удаляем бронирование
        $booking->delete();

        return response()->json([
            'message' => 'Booking cancelled successfully.',
            'refund_amount' => $booking->price,
        ]);
    }

    public function ticketPdf(Request $request, $id)
    {
        $user = $request->user();
        $isAdmin = $user->isSuperUser() || (int) $user->moonshine_user_role_id === 1;
        
        // Если передан массив ID бронирований через query параметр
        // Laravel автоматически парсит ids[]=827&ids[]=828 в массив через input('ids')
        $bookingIds = $request->input('ids');
        
        // Если не массив, пробуем через query
        if (!is_array($bookingIds)) {
            $bookingIds = $request->query('ids');
        }
        
        // Логируем для отладки
        \Log::info("PDF request ID={$id}, input('ids')=" . json_encode($request->input('ids')) . ", query('ids')=" . json_encode($request->query('ids')) . ", final=" . json_encode($bookingIds));
        
        if ($bookingIds && is_array($bookingIds) && !empty($bookingIds)) {
            // Преобразуем в массив целых чисел
            $ids = array_map('intval', $bookingIds);
            
            \Log::info("PDF generation using provided IDs: " . implode(', ', $ids));
            
            // Получаем все указанные бронирования
            $query = Booking::with(['excursion', 'stop', 'busSeat', 'bookedByUser'])
                ->whereIn('id', $ids);
            
            // Проверка прав доступа: админ видит все, продавец - только свои
            if (!$isAdmin) {
                $query->where('booked_by', $user->id);
            }
            
            $allBookings = $query->orderBy('bus_seat_id')->get();
            
            \Log::info("PDF generation found {$allBookings->count()} bookings from provided IDs. Requested: " . count($ids));
            
            if ($allBookings->isEmpty()) {
                \Log::warning("PDF generation: No bookings found for IDs: " . implode(', ', $ids) . ", User ID: {$user->id}, Is Admin: " . ($isAdmin ? 'yes' : 'no'));
                abort(404, 'Bookings not found');
            }
            
            // Используем первое бронирование для получения общей информации
            $booking = $allBookings->first();
        } else {
            // Старый способ: используем один ID и ищем связанные бронирования
            $query = Booking::with(['excursion', 'stop', 'busSeat', 'bookedByUser'])
                ->where('id', $id);
            
            // Проверка прав доступа: админ видит все, продавец - только свои
            if (!$isAdmin) {
                $query->where('booked_by', $user->id);
            }
            
            $booking = $query->firstOrFail();

            // Ищем все бронирования, созданные тем же пользователем на ту же экскурсию и дату
            // Имена клиентов могут быть разные, поэтому не используем customer_name/customer_phone
            $allBookings = Booking::with(['excursion.prices', 'stop', 'busSeat'])
                ->where('excursion_id', $booking->excursion_id)
                ->where('excursion_date', $booking->excursion_date)
                ->where('booked_by', $booking->booked_by);
            
            // Если есть время экскурсии, добавляем его в условия (но не обязательно)
            if ($booking->time) {
                $normalizedTime = strlen($booking->time) >= 5 ? substr($booking->time, 0, 5) : $booking->time;
                $allBookings->where(function($query) use ($normalizedTime) {
                    $query->where('time', $normalizedTime)
                          ->orWhere('time', $normalizedTime . ':00')
                          ->orWhere('time', 'LIKE', $normalizedTime . '%');
                });
            }
            
            // Если есть время создания, используем его для более точного поиска
            if ($booking->booked_at) {
                $timeWindow = Carbon::parse($booking->booked_at);
                $allBookings->whereBetween('booked_at', [
                    $timeWindow->copy()->subSeconds(30),
                    $timeWindow->copy()->addSeconds(30)
                ]);
            }
            
            $allBookings = $allBookings->orderBy('bus_seat_id')->get();
            
            \Log::info("PDF generation for booking {$id}: Found {$allBookings->count()} bookings using search method.");
        }

        // Если не найдено бронирований, используем исходное бронирование
        // Также убеждаемся, что исходное бронирование включено в результаты
        $hasOriginalBooking = $allBookings->contains(function ($b) use ($booking) {
            return $b->id === $booking->id;
        });
        
        if ($allBookings->isEmpty() || !$hasOriginalBooking) {
            if ($allBookings->isEmpty()) {
                \Log::warning("No bookings found for PDF. Booking ID: {$id}, Using single booking.");
            } else {
                \Log::warning("Original booking not found in results. Booking ID: {$id}, Adding it.");
            }
            // Перезагружаем исходное бронирование с отношениями, если нужно
            if (!$booking->relationLoaded('busSeat')) {
                $booking->load('busSeat');
            }
            $allBookings = $allBookings->isEmpty() ? collect([$booking]) : $allBookings->push($booking);
        }

        // Загружаем экскурсию с ценами, если еще не загружена
        if (!$booking->relationLoaded('excursion') || !$booking->excursion->relationLoaded('prices')) {
            $booking->load(['excursion.prices']);
        }
        $excursion = $booking->excursion;
        $stop = $booking->stop;
        $bookedBy = $booking->bookedByUser?->name ?? 'Неизвестный продавец';

        // Формируем данные для PDF
        $ticketNumber = 'T-' . $excursion->id . '-' . $booking->id . '-' . time();
        
        // Дата и время экскурсии (из бронирования)
        // Используем getRawOriginal, так как каст 'date' может давать неправильную дату
        $rawExcursionDate = $booking->getRawOriginal('excursion_date');
        if ($rawExcursionDate) {
            // Обрезаем до формата YYYY-MM-DD (первые 10 символов), так как может быть с временем
            $dateOnly = is_string($rawExcursionDate) ? substr($rawExcursionDate, 0, 10) : $rawExcursionDate;
            $excursionDate = Carbon::parse($dateOnly)->format('d.m.Y');
        } else {
            $excursionDate = $excursion->date_time ? $excursion->date_time->format('d.m.Y') : 'Не указана';
        }
        $excursionTime = $booking->time 
            ? (strlen($booking->time) >= 5 ? substr($booking->time, 0, 5) : $booking->time)
            : ($excursion->date_time ? $excursion->date_time->format('H:i') : '');
        $excursionDateTime = $excursionDate . ($excursionTime ? ' ' . $excursionTime : '');
        
        // Дата покупки (когда была сделана бронь)
        $createdAt = Carbon::parse($booking->booked_at)->format('d.m.Y H:i');

        // Подготавливаем данные о пассажирах
        $passengers = [];
        $total = 0;
        
        \Log::info("PDF: Starting to process {$allBookings->count()} bookings for passengers");
        
        foreach ($allBookings as $b) {
            \Log::info("PDF: Processing booking ID={$b->id}, has_busSeat=" . ($b->busSeat ? 'yes' : 'no') . ", price={$b->price}, passenger_type={$b->passenger_type}");
            // Пропускаем бронирования без места
            if (!$b->busSeat) {
                \Log::warning("Booking {$b->id} has no busSeat. Skipping.");
                continue;
            }

            $passengerTypeLabels = [
                'adult' => 'Взрослый',
                'child' => 'Ребенок',
                'senior' => 'Пенсионер',
                'disabled' => 'Инвалид',
                'special' => 'Спеццена',
                'concession' => 'Льготный',
            ];
            
            // Проверяем, что цена не null и не 0
            $bookingPrice = (float)($b->price ?? 0);
            $withEntry = (bool)($b->with_entry ?? false);
            
            if ($bookingPrice <= 0) {
                // Если цена 0 или null, пытаемся получить цену из тарифа
                // Убеждаемся, что prices загружены
                if (!$excursion->relationLoaded('prices')) {
                    $excursion->load('prices');
                }
                $tariff = $excursion->prices->firstWhere('passenger_type', $b->passenger_type);
                if ($tariff) {
                    if ($withEntry) {
                        $bookingPrice = (float)($tariff->price_with_entry ?? $tariff->price ?? 0);
                    } else {
                        $bookingPrice = (float)($tariff->price_without_entry ?? $tariff->price ?? 0);
                    }
                }
            }
            
            // Если цена всё ещё 0, это ошибка
            if ($bookingPrice <= 0) {
                \Log::warning("Booking {$b->id} has zero price. Passenger type: {$b->passenger_type}, With entry: " . ($withEntry ? 'yes' : 'no'));
            }
            
            // Получаем тип пассажира (переводим на русский)
            $passengerTypeLabel = $passengerTypeLabels[$b->passenger_type] ?? $b->passenger_type;
            
            $passengers[] = [
                'seat_number' => $b->busSeat->seat_number,
                'passenger_type' => $passengerTypeLabel,
                'price' => $bookingPrice,
                'with_entry' => $withEntry,
                'customer_name' => $b->customer_name ?? null,
                'customer_phone' => $b->customer_phone ?? null,
            ];
            $total += $bookingPrice;
        }

        // Если после обработки нет пассажиров, но есть исходное бронирование с местом, добавляем его
        if (empty($passengers) && $booking->busSeat) {
            \Log::warning("No passengers after processing. Adding original booking {$booking->id} manually.");
            $passengerTypeLabels = [
                'adult' => 'Взрослый',
                'child' => 'Ребенок',
                'senior' => 'Пенсионер',
                'disabled' => 'Инвалид',
                'special' => 'Спеццена',
                'concession' => 'Льготный',
            ];
            
            $bookingPrice = (float)($booking->price ?? 0);
            $withEntry = (bool)($booking->with_entry ?? false);
            
            if ($bookingPrice <= 0) {
                // Убеждаемся, что prices загружены
                if (!$excursion->relationLoaded('prices')) {
                    $excursion->load('prices');
                }
                $passengerType = $booking->passenger_type ?? 'adult';
                $tariff = $excursion->prices->firstWhere('passenger_type', $passengerType);
                if ($tariff) {
                    if ($withEntry) {
                        $bookingPrice = (float)($tariff->price_with_entry ?? $tariff->price ?? 0);
                    } else {
                        $bookingPrice = (float)($tariff->price_without_entry ?? $tariff->price ?? 0);
                    }
                }
            }
            
            $passengerType = $booking->passenger_type ?? 'adult';
            $passengers[] = [
                'seat_number' => $booking->busSeat->seat_number,
                'passenger_type' => $passengerTypeLabels[$passengerType] ?? $passengerType,
                'price' => $bookingPrice > 0 ? $bookingPrice : 0,
                'with_entry' => $withEntry,
                'customer_name' => $booking->customer_name ?? null,
                'customer_phone' => $booking->customer_phone ?? null,
            ];
            $total = $bookingPrice > 0 ? $bookingPrice : 0;
        }
        
        // Логируем данные для отладки
        \Log::info("PDF ticket data: allBookings count = " . $allBookings->count());
        \Log::info("PDF ticket data: passengers count = " . count($passengers));
        \Log::info("PDF ticket data: total amount = " . $total);
        foreach ($passengers as $idx => $p) {
            \Log::info("Passenger {$idx}: seat={$p['seat_number']}, type={$p['passenger_type']}, price={$p['price']}, with_entry=" . ($p['with_entry'] ? 'yes' : 'no'));
        }
        
        // Дополнительная проверка: логируем все бронирования
        foreach ($allBookings as $idx => $b) {
            \Log::info("Booking {$idx} (ID={$b->id}): seat_id={$b->bus_seat_id}, price={$b->price}, passenger_type={$b->passenger_type}, has_busSeat=" . ($b->busSeat ? 'yes' : 'no'));
        }

        // Проверяем, что есть пассажиры
        if (empty($passengers)) {
            \Log::error("PDF generation: No passengers found! allBookings count: {$allBookings->count()}");
            // Если нет пассажиров, но есть исходное бронирование, создаем хотя бы одного
            if ($booking->busSeat) {
                $passengerTypeLabels = [
                    'adult' => 'Взрослый',
                    'child' => 'Ребенок',
                    'senior' => 'Пенсионер',
                    'disabled' => 'Инвалид',
                    'special' => 'Спеццена',
                ];
                $bookingPrice = (float)($booking->price ?? 0);
                if ($bookingPrice <= 0 && $excursion->relationLoaded('prices')) {
                    $tariff = $excursion->prices->firstWhere('passenger_type', $booking->passenger_type ?? 'adult');
                    if ($tariff) {
                        $withEntry = (bool)($booking->with_entry ?? false);
                        if ($withEntry) {
                            $bookingPrice = (float)($tariff->price_with_entry ?? $tariff->price ?? 0);
                        } else {
                            $bookingPrice = (float)($tariff->price_without_entry ?? $tariff->price ?? 0);
                        }
                    }
                }
                $passengerType = $booking->passenger_type ?? 'adult';
                $passengers[] = [
                    'seat_number' => $booking->busSeat->seat_number,
                    'passenger_type' => $passengerTypeLabels[$passengerType] ?? $passengerType,
                    'price' => $bookingPrice > 0 ? $bookingPrice : 0,
                    'with_entry' => (bool)($booking->with_entry ?? false),
                    'customer_name' => $booking->customer_name ?? null,
                    'customer_phone' => $booking->customer_phone ?? null,
                ];
                $total = $bookingPrice > 0 ? $bookingPrice : 0;
            }
        }

        // Логируем финальные данные перед передачей в шаблон
        \Log::info("PDF template data: passengers count = " . count($passengers) . ", total = {$total}");
        \Log::info("PDF template data: passengers = " . json_encode($passengers));
        
        // Генерируем HTML для PDF
        $html = view('pdf.ticket', [
            'ticketNumber' => $ticketNumber,
            'excursion' => $excursion,
            'excursionDateTime' => $excursionDateTime,
            'createdAt' => $createdAt,
            'stop' => $stop,
            'customerName' => $booking->customer_name,
            'customerPhone' => $booking->customer_phone,
            'passengers' => $passengers,
            'total' => $total,
            'bookedBy' => $bookedBy,
        ])->render();

        // Генерируем PDF
        $pdf = Pdf::loadHTML($html);
        // A4: 210x297 мм
        // В points: 1mm = 2.83465 points, поэтому 210mm = 595.28 points, 297mm = 842.00 points
        $pdf->setPaper('a4', 'portrait'); // A4 формат
        $pdf->setOption('defaultFont', 'dejavu sans');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        
        return $pdf->download("ticket-{$ticketNumber}.pdf");
    }
}
