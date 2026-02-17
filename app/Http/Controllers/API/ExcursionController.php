<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Excursion;
use App\Models\UnscheduledExcursionDate;
use App\Models\CancelledExcursionDate;
use App\Services\ExcursionScheduler;
use MoonShine\Laravel\Models\MoonshineUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;

class ExcursionController extends Controller
{
    public function __construct(private readonly ExcursionScheduler $scheduler)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user('sanctum') ?? $request->user();
        $isAdmin = $user && ($user->isSuperUser() || (int) $user->moonshine_user_role_id === 1);
        $isStaff = $user && in_array((int) $user->moonshine_user_role_id, [3, 5], true);

        // Всегда подтягиваем бронирования, чтобы считать занятость мест на конкретную дату
        $relations = ['busSeats', 'assignedUsers', 'prices', 'staffPrices', 'bookings.stop', 'unscheduledDates', 'cancelledDates'];
        
        // Для админов также загружаем удаленные внеплановые даты
        if ($isAdmin) {
            $relations[] = 'allUnscheduledDates';
        }

        $query = Excursion::with($relations)->with('scheduleTemplate.scheduleDays');
        
        // Фильтр по дню недели (если передан weekday или date)
        $weekday = null;
        if ($request->has('weekday')) {
            $weekday = (int) $request->input('weekday');
        } elseif ($request->has('date')) {
            // Если передан date, вычисляем день недели
            try {
                $date = \Carbon\Carbon::parse($request->input('date'));
                $weekday = $date->isoWeekday(); // 1=Понедельник, 2=Вторник, и т.д.
            } catch (\Exception $e) {
                // Если дата невалидна, игнорируем
            }
        }
        
        if ($weekday !== null) {
            $query->whereHas('scheduleTemplate.scheduleDays', function($q) use ($weekday) {
                $q->where('weekday', $weekday);
            });
        }
        
        // Фильтр по назначенному пользователю
        if ($request->has('assigned_to')) {
            $userId = $request->input('assigned_to');
            $query->whereHas('assignedUsers', function($q) use ($userId) {
                $q->where('moonshine_users.id', $userId);
            });
        }

        // Водитель/экскурсовод видит только свои назначенные экскурсии
        // (назначенные без даты = на все даты, или на конкретную дату)
        if ($isStaff && $user) {
            $query->whereHas('assignedUsers', function($q) use ($user) {
                $q->where('moonshine_users.id', $user->id);
            });
        }
        
        $excursions = $query->orderBy('title', 'asc')->get();

        // Разворачиваем экскурсии по датам из schedule_by_date
        // Для каждой экскурсии-шаблона создаем отдельные записи для каждой даты
        $expandedExcursions = collect();
        // Для админов и продавцов показываем прошедшие экскурсии (30 дней назад) для возможности бронирования задним числом
        $includePast = $isAdmin || ($user && (int) $user->moonshine_user_role_id === 2); // role_id 2 = продавец
        foreach ($excursions as $excursion) {
            $transformed = $this->transformExcursion($excursion, $isStaff, $includePast);
            $bookingsBySeat = $excursion->bookings->groupBy('bus_seat_id');
            $scheduleByDate = $transformed['schedule_by_date'] ?? [];
            $buildBusSeats = function (?string $targetDate, ?string $targetTime) use ($excursion, $bookingsBySeat, $isStaff) {
                return $excursion->busSeats->map(function ($seat) use ($bookingsBySeat, $targetDate, $targetTime, $isStaff) {
                    $matchingBooking = null;
                    $seatBookings = $bookingsBySeat->get($seat->id, collect());
                    if ($targetDate !== null && $targetTime !== null) {
                        // Отладка для мест №14 и №20
                        if (in_array($seat->seat_number, [14, 20])) {
                            \Log::info("🔍 Поиск booking для места №{$seat->seat_number}:");
                            \Log::info("   targetDate: $targetDate, targetTime: $targetTime");
                            \Log::info("   Всего бронирований для этого места: " . $seatBookings->count());
                            foreach ($seatBookings as $b) {
                                $rawDate = $b->getRawOriginal('excursion_date');
                                $bookingTime = is_string($b->time) ? substr($b->time, 0, 5) : $b->time->format('H:i');
                                $bookingDate = is_string($rawDate) ? substr($rawDate, 0, 10) : $rawDate->format('Y-m-d');
                                \Log::info("   Booking ID={$b->id}: date=$bookingDate, time=$bookingTime, customer={$b->customer_name}");
                            }
                        }
                        
                        $matchingBooking = $seatBookings->first(function ($booking) use ($targetDate, $targetTime, $seat) {
                            if (!$booking->time) {
                                if (in_array($seat->seat_number, [14, 20])) {
                                    \Log::info("   ❌ Booking ID={$booking->id}: нет времени");
                                }
                                return false;
                            }
                            // Используем getRawOriginal для получения исходной даты из БД
                            // (аксессор getExcursionDateAttribute переопределяет стандартный доступ)
                            $rawExcursionDate = $booking->getRawOriginal('excursion_date');
                            if (!$rawExcursionDate) {
                                if (in_array($seat->seat_number, [14, 20])) {
                                    \Log::info("   ❌ Booking ID={$booking->id}: нет даты");
                                }
                                return false;
                            }
                            
                            // Нормализуем время до HH:MM (как в bookSeats)
                            $bookingTime = is_string($booking->time)
                                ? substr($booking->time, 0, 5) // нормализуем до HH:MM
                                : $booking->time->format('H:i');
                            
                            // Нормализуем дату до Y-m-d (обрезаем время, если есть)
                            $bookingDate = is_string($rawExcursionDate)
                                ? substr($rawExcursionDate, 0, 10) // обрезаем до YYYY-MM-DD
                                : $rawExcursionDate->format('Y-m-d');
                            
                            $matches = $bookingDate === $targetDate && $bookingTime === $targetTime;
                            
                            if (in_array($seat->seat_number, [14, 20])) {
                                \Log::info("   Проверка Booking ID={$booking->id}: bookingDate=$bookingDate vs targetDate=$targetDate, bookingTime=$bookingTime vs targetTime=$targetTime -> " . ($matches ? "✅ СОВПАЛО" : "❌ НЕ СОВПАЛО"));
                            }
                            
                            return $matches;
                        });
                        
                        if (in_array($seat->seat_number, [14, 20])) {
                            \Log::info("   Результат поиска: " . ($matchingBooking ? "✅ НАЙДЕН (ID={$matchingBooking->id})" : "❌ НЕ НАЙДЕН"));
                            \Log::info("   isStaff: " . ($isStaff ? "true" : "false"));
                        }
                    }

                    $data = [
                        'id' => $seat->id,
                        'seat_number' => $seat->seat_number,
                        'status' => $matchingBooking ? 'booked' : 'available',
                        'booked_by' => $matchingBooking?->booked_by ?? $seat->booked_by,
                        'booked_at' => $matchingBooking?->booked_at?->toIso8601String() ?? $seat->booked_at?->toIso8601String(),
                    ];

                    // Добавляем информацию о том, кто забронировал (имя и цвет)
                    $bookedByUserId = $matchingBooking?->booked_by ?? $seat->booked_by;
                    if ($bookedByUserId) {
                        $bookedByUser = \MoonShine\Laravel\Models\MoonshineUser::find($bookedByUserId);
                        if ($bookedByUser) {
                            $data['booked_by_info'] = [
                                'id' => $bookedByUser->id,
                                'name' => $bookedByUser->name,
                                'color' => $bookedByUser->color,
                            ];
                        }
                    }

                    // Возвращаем информацию о клиенте для персонала и админов
                    if ( $matchingBooking) {
                        $data['booking'] = [
                            'customer_name' => $matchingBooking->customer_name,
                            'customer_phone' => $matchingBooking->customer_phone,
                            'passenger_type' => $matchingBooking->passenger_type,
                            'stop_id' => $matchingBooking->stop_id,
                            'stop_title' => $matchingBooking->stop?->name,
                            'stop_order' => $matchingBooking->stop?->order,
                        ];
                    }

                    return $data;
                })->values();
            };
            
            if (empty($scheduleByDate)) {
                // Если нет расписания, возвращаем экскурсию как есть,
                // но пересчитываем занятость мест по конкретной дате экскурсии (если она есть)
                $targetDate = $excursion->date_time?->format('Y-m-d');
                $targetTime = $excursion->date_time?->format('H:i');
                $busSeats = $buildBusSeats($targetDate, $targetTime);
                $transformed['bus_seats'] = $busSeats;
                $transformed['booked_seats_count'] = $busSeats->where('status', 'booked')->count();
                $transformed['available_seats_count'] = $excursion->max_seats - $transformed['booked_seats_count'];
                $transformed['is_unscheduled'] = false; // Старые экскурсии без расписания не считаются внеплановыми
                
                $expandedExcursions->push($transformed);
                
                // Добавляем удаленные внеплановые даты как отдельные записи (только для админов)
                if ($isAdmin && isset($transformed['deleted_unscheduled_dates']) && !empty($transformed['deleted_unscheduled_dates'])) {
                    foreach ($transformed['deleted_unscheduled_dates'] as $deletedDate) {
                        $deletedDateTime = \Carbon\Carbon::parse($deletedDate['date_time']);
                        $deletedExcursionData = array_merge($transformed, [
                            'date_time' => $deletedDate['date_time'],
                            'date' => $deletedDateTime->format('Y-m-d'),
                            'time' => $deletedDateTime->format('H:i'),
                            'is_unscheduled' => true,
                            'is_deleted' => true,
                            'unscheduled_date_id' => $deletedDate['id'],
                            'deleted_at' => $deletedDate['deleted_at'],
                        ]);
                        $expandedExcursions->push($deletedExcursionData);
                    }
                }
            } else {
                // Для персонала: показываем бронирования только в первой записи (основной)
                // Для остальных дат убираем детали бронирований, чтобы не дублировать
                $firstDate = true;
                
                // Для персонала: получаем их назначения на эту экскурсию
                $staffAssignments = null;
                if ($isStaff) {
                    $staffAssignments = $excursion->assignedUsers
                        ->where('id', $user->id)
                        ->map(fn($u) => [
                            'date' => $u->pivot->excursion_date ? \Carbon\Carbon::parse($u->pivot->excursion_date)->format('Y-m-d') : null,
                            'time' => $u->pivot->time,
                        ]);
                    
                    \Log::info("Staff assignments for user {$user->id} on excursion {$excursion->id}", [
                        'assignments_count' => $staffAssignments->count(),
                        'assignments' => $staffAssignments->toArray(),
                    ]);
                }
                
                foreach ($scheduleByDate as $scheduleItem) {
                    // Для персонала: проверяем, назначен ли он на эту конкретную дату/время
                    if ($isStaff && $staffAssignments !== null) {
                        $scheduleDate = $scheduleItem['date'] ?? null;
                        $scheduleTime = $scheduleItem['time'] ?? null;
                        
                        $isAssignedToThisDate = $staffAssignments->contains(function ($assignment) use ($scheduleDate, $scheduleTime) {
                            // Если назначение без даты — показываем все даты
                            if ($assignment['date'] === null) {
                                return true;
                            }
                            // Иначе проверяем совпадение даты и времени
                            $dateMatches = $assignment['date'] === $scheduleDate;
                            $timeMatches = $assignment['time'] === null || $assignment['time'] === $scheduleTime;
                            return $dateMatches && $timeMatches;
                        });
                        
                        if (!$isAssignedToThisDate) {
                            continue; // Пропускаем эту дату, персонал на неё не назначен
                        }
                    }
                    
                    $excursionData = array_merge($transformed, [
                        'date_time' => $scheduleItem['date_time'],
                        'date' => $scheduleItem['date'],
                        'time' => $scheduleItem['time'],
                        'is_unscheduled' => $scheduleItem['is_unscheduled'] ?? false,
                        'is_cancelled' => $scheduleItem['is_cancelled'] ?? false,
                        'unscheduled_date_id' => $scheduleItem['unscheduled_date_id'] ?? null,
                        'deleted_unscheduled_dates' => $transformed['deleted_unscheduled_dates'] ?? [],
                    ]);

                    // Пересчитываем занятость мест для выбранной даты/времени
                    $busSeats = $buildBusSeats(
                        $scheduleItem['date'] ?? null,
                        $scheduleItem['time'] ?? null
                    );
                    $excursionData['bus_seats'] = $busSeats;
                    $excursionData['booked_seats_count'] = $busSeats->where('status', 'booked')->count();
                    $excursionData['available_seats_count'] = $excursion->max_seats - $excursionData['booked_seats_count'];
                    
                    // Если это не первая дата и это персонал, убираем детали бронирований
                    if ($isStaff && !$firstDate && isset($excursionData['bus_seats']) && is_array($excursionData['bus_seats'])) {
                        $excursionData['bus_seats'] = array_map(function ($seat) {
                            // Убираем детали бронирования, оставляем только базовую информацию
                            if (isset($seat['booking'])) {
                                unset($seat['booking']);
                            }
                            return $seat;
                        }, $excursionData['bus_seats']);
                    }
                    
                    $expandedExcursions->push($excursionData);
                    $firstDate = false;
                }
            }
        }

        return response()->json([
            'data' => $expandedExcursions->values()->all(),
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user('sanctum') ?? $request->user();
        $isAdmin = $user && ($user->isSuperUser() || (int) $user->moonshine_user_role_id === 1);
        $includeBookingDetails = $user && in_array((int) $user->moonshine_user_role_id, [3, 5], true);
        // Для админов и продавцов тоже показываем прошедшие экскурсии
        $includePast = $isAdmin || ($user && (int) $user->moonshine_user_role_id === 2);

        $relations = ['busSeats', 'assignedUsers', 'prices', 'staffPrices'];

        if ($includeBookingDetails) {
            $relations[] = 'bookings.stop';
        }

        $query = Excursion::with($relations);
        
        // Админы могут видеть неактивные экскурсии
        if (!$isAdmin) {
            $query->where('is_active', true);
        }
        
        $excursion = $query
            ->findOrFail($id);

        return response()->json([
            'data' => $this->transformExcursion($excursion, $includeBookingDetails, $includePast),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user || (! $user->isSuperUser() && (int) $user->moonshine_user_role_id !== 1)) {
            abort(403, 'Недостаточно прав для создания экскурсии.');
        }

        $validated = $request->validate([
            'schedule_template_id' => 'required|exists:schedule_templates,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'max_seats' => 'required|integer|min:1|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        // Проверяем, не существует ли уже экскурсия для этого шаблона
        $exists = Excursion::where('schedule_template_id', $validated['schedule_template_id'])->exists();
        if ($exists) {
            return response()->json([
                'message' => 'Экскурсия для этого шаблона уже существует',
            ], 422);
        }

        $excursion = Excursion::create([
            'schedule_template_id' => $validated['schedule_template_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'date_time' => null, // Экскурсии теперь без конкретной даты
            'price' => $validated['price'],
            'max_seats' => $validated['max_seats'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        foreach (['adult', 'child', 'senior', 'disabled', 'special'] as $type) {
            $excursion->prices()->create([
                'passenger_type' => $type,
                'price' => $validated['price'],
                'seller_commission_percent' => 10,
                'partner_commission_percent' => 10,
            ]);
        }

        $excursion->load(['busSeats', 'prices', 'assignedUsers']);

        return response()->json([
            'message' => 'Экскурсия создана',
            'data' => $this->transformExcursion($excursion, false, false),
        ], 201);
    }

    /**
     * Добавить внеплановую дату к экскурсии
     */
    public function addUnscheduledDate(Request $request, $id)
    {
        $user = $request->user();

        if (! $user || (! $user->isSuperUser() && (int) $user->moonshine_user_role_id !== 1)) {
            abort(403, 'Недостаточно прав для добавления внеплановой даты.');
        }

        $validated = $request->validate([
            'date_time' => 'required|date',
        ]);

        $excursion = Excursion::findOrFail($id);

        // Проверяем, не существует ли уже такая дата
        $exists = $excursion->unscheduledDates()
            ->where('date_time', $validated['date_time'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Эта внеплановая дата уже существует для данной экскурсии',
            ], 422);
        }

        $unscheduledDate = $excursion->unscheduledDates()->create([
            'date_time' => $validated['date_time'],
        ]);

        $excursion->load(['unscheduledDates']);

        $user = $request->user();
        $isAdmin = $user && ($user->isSuperUser() || (int) $user->moonshine_user_role_id === 1);
        $includePast = $isAdmin || ($user && (int) $user->moonshine_user_role_id === 2);
        
        return response()->json([
            'message' => 'Внеплановая дата добавлена',
            'data' => $this->transformExcursion($excursion, false, $includePast),
        ], 201);
    }

    /**
     * Удалить внеплановую дату экскурсии
     */
    public function deleteUnscheduledDate(Request $request, $id, $dateId)
    {
        $user = $request->user();

        if (! $user || (! $user->isSuperUser() && (int) $user->moonshine_user_role_id !== 1)) {
            abort(403, 'Недостаточно прав для удаления внеплановой даты.');
        }

        $excursion = Excursion::findOrFail($id);
        $unscheduledDate = $excursion->unscheduledDates()->findOrFail($dateId);

        // Помечаем как удаленную (soft delete)
        $unscheduledDate->markAsDeleted();

        $excursion->load(['unscheduledDates']);

        $user = $request->user();
        $isAdmin = $user && ($user->isSuperUser() || (int) $user->moonshine_user_role_id === 1);
        $includePast = $isAdmin || ($user && (int) $user->moonshine_user_role_id === 2);
        
        return response()->json([
            'message' => 'Внеплановая дата удалена',
            'data' => $this->transformExcursion($excursion, false, $includePast),
        ], 200);
    }

    /**
     * Назначить пользователей на экскурсию
     */
    public function assign(Request $request, $id)
    {
        $user = $request->user();

        if (! $user || (! $user->isSuperUser() && (int) $user->moonshine_user_role_id !== 1)) {
            abort(403, 'Недостаточно прав для назначения сотрудников.');
        }

        $excursion = Excursion::findOrFail($id);

        $validated = $request->validate([
            'assignments' => 'present|array',
            'assignments.*.user_id' => 'required|exists:moonshine_users,id',
            'assignments.*.role_in_excursion' => 'required|in:driver,guide',
            'excursion_date' => 'nullable|date',
            'time' => 'nullable|string|max:5',
        ]);

        $excursionDate = $validated['excursion_date'] ?? null;
        $time = $validated['time'] ?? null;

        // Удаляем старые назначения для этой даты/времени
        $query = $excursion->assignedUsers();
        if ($excursionDate) {
            $query->wherePivot('excursion_date', $excursionDate);
            if ($time) {
                $query->wherePivot('time', $time);
            }
        } else {
            $query->wherePivotNull('excursion_date');
        }
        $query->detach();

        // Добавляем новые назначения
        foreach ($validated['assignments'] as $assignment) {
            $excursion->assignedUsers()->attach($assignment['user_id'], [
                'role_in_excursion' => $assignment['role_in_excursion'],
                'excursion_date' => $excursionDate,
                'time' => $time,
            ]);
        }

        return response()->json([
            'message' => 'Назначения обновлены',
            'assigned_users' => $validated['assignments'],
            'excursion_date' => $excursionDate,
            'time' => $time,
        ]);
    }

    /**
     * Удалить назначение пользователя с экскурсии
     */
    public function unassign(Request $request, $id, $userId)
    {
        $user = $request->user();

        if (! $user || (! $user->isSuperUser() && (int) $user->moonshine_user_role_id !== 1)) {
            abort(403, 'Недостаточно прав для отмены назначения сотрудников.');
        }

        $excursion = Excursion::findOrFail($id);
        $excursion->assignedUsers()->detach($userId);

        return response()->json([
            'message' => 'Назначение отменено',
        ]);
    }

    /**
     * Проверить новые назначения для текущего пользователя
     */
    public function checkNewAssignments(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            abort(401, 'Необходима аутентификация');
        }

        $lastChecked = $request->input('last_checked');
        
        // Если last_checked не указан, возвращаем все назначения
        $query = \DB::table('excursion_user')
            ->where('user_id', $user->id)
            ->join('excursions', 'excursion_user.excursion_id', '=', 'excursions.id')
            ->select(
                'excursions.id',
                'excursions.title',
                'excursions.description',
                'excursions.date_time',
                'excursion_user.role_in_excursion',
                'excursion_user.created_at as assigned_at'
            );

        if ($lastChecked) {
            $query->where('excursion_user.created_at', '>', $lastChecked);
        }

        $newAssignments = $query->orderBy('excursion_user.created_at', 'desc')->get();

        return response()->json([
            'has_new' => $newAssignments->count() > 0,
            'count' => $newAssignments->count(),
            'assignments' => $newAssignments->map(function ($assignment) {
                return [
                    'excursion_id' => $assignment->id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'date_time' => $assignment->date_time,
                    'role_in_excursion' => $assignment->role_in_excursion,
                    'assigned_at' => $assignment->assigned_at,
                ];
            }),
        ]);
    }

    private function transformExcursion(Excursion $excursion, bool $includeBookingDetails = false, bool $includePast = false): array
    {
        // Для ускорения получаем коллекцию бронирований по bus_seat_id
        $bookingsBySeat = $includeBookingDetails
            ? $excursion->bookings->keyBy('bus_seat_id')
            : collect();

        // Получаем расписание (дни недели и время)
        $schedule = [];
        if ($excursion->scheduleTemplate && $excursion->scheduleTemplate->scheduleDays) {
            $weekdayLabels = [
                1 => 'Понедельник',
                2 => 'Вторник',
                3 => 'Среда',
                4 => 'Четверг',
                5 => 'Пятница',
                6 => 'Суббота',
                7 => 'Воскресенье',
            ];
            
            foreach ($excursion->scheduleTemplate->scheduleDays as $scheduleDay) {
                $time = is_string($scheduleDay->time) 
                    ? (strlen($scheduleDay->time) >= 5 ? substr($scheduleDay->time, 0, 5) : $scheduleDay->time)
                    : $scheduleDay->time->format('H:i');
                
                $schedule[] = [
                    'weekday' => $scheduleDay->weekday,
                    'weekday_name' => $weekdayLabels[$scheduleDay->weekday] ?? "День {$scheduleDay->weekday}",
                    'time' => $time,
                ];
            }
        }

        // Определяем, на какие дни недели запланирована экскурсия
        $weekdays = [];
        if (!empty($schedule)) {
            $weekdays = array_column($schedule, 'weekday');
        }
        
        // Создаем расширенный массив schedule с полной информацией для каждой даты
        // Это поможет Flutter группировать экскурсии по датам
        $scheduleByDate = [];
        if (!empty($schedule)) {
            // Для персонала (водителей/гидов) показываем и прошедшие экскурсии (30 дней назад + 60 дней вперед)
            // Для админов и продавцов тоже показываем прошедшие (30 дней назад) для возможности бронирования задним числом
            // Для остальных - только будущие (60 дней вперед)
            $daysBack = ($includeBookingDetails || $includePast) ? 30 : 0;
            $daysForward = 30;
            
            $startDate = \Carbon\Carbon::now()->startOfDay()->subDays($daysBack);
            $totalDays = $daysBack + $daysForward;
            
            for ($i = 0; $i < $totalDays; $i++) {
                $date = $startDate->copy()->addDays($i);
                $dateWeekday = $date->isoWeekday();
                
                // Проверяем, есть ли в расписании этот день недели
                $scheduleForDay = collect($schedule)->firstWhere('weekday', $dateWeekday);
                if ($scheduleForDay) {
                    $time = $scheduleForDay['time'];
                    // Нормализуем время до HH:MM
                    if (is_string($time) && strlen($time) >= 5) {
                        $time = substr($time, 0, 5);
                    } elseif (!is_string($time)) {
                        $time = $time->format('H:i');
                    }
                    $dateTime = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $time);
                    
                    // Проверяем, не отменена ли эта дата
                    $isCancelled = $excursion->cancelledDates->contains(function ($cancelled) use ($dateTime) {
                        $cancelledDateTime = \Carbon\Carbon::parse($cancelled->date_time);
                        return $cancelledDateTime->format('Y-m-d H:i') === $dateTime->format('Y-m-d H:i');
                    });
                    
                    // НЕ пропускаем отмененные даты - показываем их с флагом is_cancelled
                    $scheduleByDate[] = [
                        'date' => $date->format('Y-m-d'),
                        'date_time' => $dateTime->toIso8601String(),
                        'weekday' => $dateWeekday,
                        'weekday_name' => $scheduleForDay['weekday_name'] ?? '',
                        'time' => $time,
                        'is_unscheduled' => false,
                        'is_cancelled' => $isCancelled,
                    ];
                }
            }
        }
        
        // Добавляем внеплановые даты
        // Для персонала, админов и продавцов показываем и прошедшие (30 дней назад), для остальных - только будущие
        $now = \Carbon\Carbon::now();
        $pastLimit = ($includeBookingDetails || $includePast) ? \Carbon\Carbon::now()->subDays(30) : $now;
        
        foreach ($excursion->unscheduledDates as $unscheduledDate) {
            $dateTime = \Carbon\Carbon::parse($unscheduledDate->date_time);
            
            // Для персонала, админов и продавцов показываем прошедшие за последние 30 дней, для остальных - только будущие
            if (!($includeBookingDetails || $includePast) && $dateTime->isPast()) {
                continue;
            }
            
            // Для персонала, админов и продавцов пропускаем только очень старые (старше 30 дней)
            if (($includeBookingDetails || $includePast) && $dateTime->isBefore($pastLimit)) {
                continue;
            }
            
            // Проверяем, не отменена ли эта внеплановая дата
            $isCancelled = $excursion->cancelledDates->contains(function ($cancelled) use ($dateTime) {
                $cancelledDateTime = \Carbon\Carbon::parse($cancelled->date_time);
                return $cancelledDateTime->format('Y-m-d H:i') === $dateTime->format('Y-m-d H:i');
            });
            
            // НЕ пропускаем отмененные даты - показываем их с флагом is_cancelled
            $scheduleByDate[] = [
                'date' => $dateTime->format('Y-m-d'),
                'date_time' => $dateTime->toIso8601String(),
                'weekday' => $dateTime->isoWeekday(),
                'weekday_name' => '',
                'time' => $dateTime->format('H:i'),
                'is_unscheduled' => true,
                'is_cancelled' => $isCancelled,
                'unscheduled_date_id' => $unscheduledDate->id, // Добавляем ID для удаления
            ];
        }
        
        // Сортируем по дате/времени
        usort($scheduleByDate, function($a, $b) {
            return strcmp($a['date_time'], $b['date_time']);
        });

        $result = [
            'id' => $excursion->id,
            'title' => $excursion->title,
            'description' => $excursion->description,
            'date_time' => $excursion->date_time?->toIso8601String(),
            'date' => $excursion->date_time?->format('Y-m-d'),
            'time' => $excursion->date_time?->format('H:i'),
            'schedule' => $schedule,
            'schedule_by_date' => $scheduleByDate, // Массив всех дат, на которые запланирована экскурсия (на 60 дней вперед + внеплановые)
            'weekdays' => $weekdays, // Массив дней недели (1-7), на которые запланирована экскурсия
            'has_unscheduled' => $excursion->unscheduledDates->isNotEmpty(), // Есть ли внеплановые даты
            'price' => $excursion->price,
            'max_seats' => $excursion->max_seats,
            'booked_seats_count' => $excursion->booked_seats_count,
            'available_seats_count' => $excursion->available_seats_count,
            'assigned_staff' => $excursion->assignedUsers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_in_excursion' => $user->pivot->role_in_excursion,
                    'excursion_date' => $user->pivot->excursion_date,
                    'time' => $user->pivot->time,
                ];
            }),
            'bus_seats' => $excursion->busSeats->map(function ($seat) use ($bookingsBySeat, $includeBookingDetails) {
                $data = [
                    'id' => $seat->id,
                    'seat_number' => $seat->seat_number,
                    'status' => $seat->status,
                    'booked_by' => $seat->booked_by,
                    'booked_at' => $seat->booked_at?->toIso8601String(),
                ];

                // Добавляем информацию о том, кто забронировал (имя и цвет)
                if ($seat->booked_by) {
                    $bookedByUser = \MoonShine\Laravel\Models\MoonshineUser::find($seat->booked_by);
                    if ($bookedByUser) {
                        $data['booked_by_info'] = [
                            'id' => $bookedByUser->id,
                            'name' => $bookedByUser->name,
                            'color' => $bookedByUser->color,
                        ];
                    }
                }

                if ($includeBookingDetails && $bookingsBySeat->has($seat->id)) {
                    $booking = $bookingsBySeat->get($seat->id);
                    $data['booking'] = [
                        'customer_name' => $booking->customer_name,
                        'customer_phone' => $booking->customer_phone,
                        'passenger_type' => $booking->passenger_type,
                        'stop_id' => $booking->stop_id,
                        'stop_title' => $booking->stop?->name,
                        'stop_order' => $booking->stop?->order,
                    ];
                }

                return $data;
            }),
            'prices' => $excursion->prices->map(function ($price) {
                return [
                    'passenger_type' => $price->passenger_type,
                    'price' => $price->price,
                    'price_without_entry' => $price->price_without_entry,
                    'price_with_entry' => $price->price_with_entry,
                    'seller_commission_percent' => $price->seller_commission_percent,
                    'partner_commission_percent' => $price->partner_commission_percent,
                ];
            })->values(),
            'staff_prices' => $excursion->staffPrices->map(function ($staffPrice) {
                return [
                    'staff_type' => $staffPrice->staff_type,
                    'min_passengers' => $staffPrice->min_passengers,
                    'max_passengers' => $staffPrice->max_passengers,
                    'price' => $staffPrice->price,
                ];
            })->values(),
        ];
        
        // Добавляем удаленные внеплановые даты для админов
        if ($excursion->relationLoaded('allUnscheduledDates') && $excursion->allUnscheduledDates) {
            $deletedDates = $excursion->allUnscheduledDates->whereNotNull('deleted_at');
            $result['deleted_unscheduled_dates'] = $deletedDates->map(function ($date) {
                return [
                    'id' => $date->id,
                    'date_time' => $date->date_time ? $date->date_time->toIso8601String() : null,
                    'deleted_at' => $date->deleted_at ? $date->deleted_at->toIso8601String() : null,
                ];
            })->values();
        }
        
        return $result;
    }

    public function updatePrices(Request $request, $id)
    {
        $user = $request->user();

        if (! $user || (! $user->isSuperUser() && (int) $user->moonshine_user_role_id !== 1)) {
            abort(403, 'Недостаточно прав для изменения цен.');
        }

        $validated = $request->validate([
            'prices' => 'required|array',
            'prices.adult.price' => 'required|numeric|min:0',
            'prices.adult.price_without_entry' => 'nullable|numeric|min:0',
            'prices.adult.price_with_entry' => 'nullable|numeric|min:0',
            'prices.adult.seller_commission_percent' => 'required|numeric|min:0|max:100',
            'prices.adult.partner_commission_percent' => 'required|numeric|min:0|max:100',
            'prices.child.price' => 'required|numeric|min:0',
            'prices.child.price_without_entry' => 'nullable|numeric|min:0',
            'prices.child.price_with_entry' => 'nullable|numeric|min:0',
            'prices.child.seller_commission_percent' => 'required|numeric|min:0|max:100',
            'prices.child.partner_commission_percent' => 'required|numeric|min:0|max:100',
            'prices.senior.price' => 'required|numeric|min:0',
            'prices.senior.price_without_entry' => 'nullable|numeric|min:0',
            'prices.senior.price_with_entry' => 'nullable|numeric|min:0',
            'prices.senior.seller_commission_percent' => 'required|numeric|min:0|max:100',
            'prices.senior.partner_commission_percent' => 'required|numeric|min:0|max:100',
            'prices.disabled.price' => 'required|numeric|min:0',
            'prices.disabled.price_without_entry' => 'nullable|numeric|min:0',
            'prices.disabled.price_with_entry' => 'nullable|numeric|min:0',
            'prices.disabled.seller_commission_percent' => 'required|numeric|min:0|max:100',
            'prices.disabled.partner_commission_percent' => 'required|numeric|min:0|max:100',
            'prices.special.price' => 'required|numeric|min:0',
            'prices.special.price_without_entry' => 'nullable|numeric|min:0',
            'prices.special.price_with_entry' => 'nullable|numeric|min:0',
            'prices.special.seller_commission_percent' => 'required|numeric|min:0|max:100',
            'prices.special.partner_commission_percent' => 'required|numeric|min:0|max:100',
            'prices.concession.price' => 'required|numeric|min:0',
            'prices.concession.price_without_entry' => 'nullable|numeric|min:0',
            'prices.concession.price_with_entry' => 'nullable|numeric|min:0',
            'prices.concession.seller_commission_percent' => 'required|numeric|min:0|max:100',
            'prices.concession.partner_commission_percent' => 'required|numeric|min:0|max:100',
        ]);

        $excursion = Excursion::with('prices')->findOrFail($id);

        $types = ['adult', 'child', 'senior', 'disabled', 'special', 'concession'];
        
        // Подготавливаем данные для обновления таблицы excursions
        $excursionUpdateData = [];

        foreach ($types as $type) {
            $priceData = $request->input("prices.$type");
            
            if (!$priceData) {
                \Log::warning("Price data missing for type: $type", [
                    'excursion_id' => $id,
                    'request_data' => $request->all(),
                ]);
                continue;
            }
            
            // Если price_without_entry не передан, используем price
            // Но если передан явно (даже если null), используем его значение
            $priceWithoutEntry = array_key_exists('price_without_entry', $priceData) 
                ? ($priceData['price_without_entry'] ?? $priceData['price'])
                : $priceData['price'];
            $priceWithEntry = array_key_exists('price_with_entry', $priceData)
                ? ($priceData['price_with_entry'] ?? $priceData['price'])
                : $priceData['price'];
            $sellerCommission = $priceData['seller_commission_percent'] ?? 10;
            $partnerCommission = $priceData['partner_commission_percent'] ?? 10;

            \Log::info("Updating price for type: $type", [
                'excursion_id' => $id,
                'price' => $priceData['price'],
                'price_without_entry' => $priceWithoutEntry,
                'price_with_entry' => $priceWithEntry,
            ]);

            // Сохраняем в excursion_prices
            $priceRecord = $excursion->prices()->updateOrCreate(
                ['passenger_type' => $type],
                [
                    'price' => $priceData['price'],
                    'price_without_entry' => $priceWithoutEntry,
                    'price_with_entry' => $priceWithEntry,
                    'seller_commission_percent' => $sellerCommission,
                    'partner_commission_percent' => $partnerCommission,
                ]
            );
            
            \Log::info("Price updated for type: $type", [
                'excursion_id' => $id,
                'price_record_id' => $priceRecord->id,
                'saved_price' => $priceRecord->price,
                'saved_price_without_entry' => $priceRecord->price_without_entry,
                'saved_price_with_entry' => $priceRecord->price_with_entry,
            ]);
            
            // Подготавливаем данные для сохранения в excursions
            $excursionUpdateData["price_{$type}_without_entry"] = $priceWithoutEntry;
            $excursionUpdateData["price_{$type}_with_entry"] = $priceWithEntry;
            $excursionUpdateData["price_{$type}_seller_commission"] = $sellerCommission;
            $excursionUpdateData["price_{$type}_partner_commission"] = $partnerCommission;
        }
        
        // Обновляем поля в таблице excursions
        $excursion->update($excursionUpdateData);

        $excursion->refresh()->load(['busSeats', 'assignedUsers', 'prices', 'staffPrices']);

        $user = $request->user();
        $isAdmin = $user && ($user->isSuperUser() || (int) $user->moonshine_user_role_id === 1);
        $includePast = $isAdmin || ($user && (int) $user->moonshine_user_role_id === 2);
        
        return response()->json([
            'message' => 'Тарифы обновлены',
            'data' => $this->transformExcursion($excursion, false, $includePast),
        ]);
    }

    /**
     * Обновить цены для персонала (водители/экскурсоводы)
     */
    public function updateStaffPrices(Request $request, $id)
    {
        $user = $request->user();

        if (! $user || (! $user->isSuperUser() && (int) $user->moonshine_user_role_id !== 1)) {
            abort(403, 'Недостаточно прав для изменения цен персонала.');
        }

        $validated = $request->validate([
            'staff_prices' => 'required|array',
            'staff_prices.*.staff_type' => 'required|in:driver,guide',
            'staff_prices.*.min_passengers' => 'required|integer|min:0',
            'staff_prices.*.max_passengers' => 'nullable|integer|min:0',
            'staff_prices.*.price' => 'required|numeric|min:0',
        ]);

        $excursion = Excursion::findOrFail($id);

        // Удаляем старые цены персонала для этой экскурсии
        $excursion->staffPrices()->delete();

        // Создаем новые цены
        foreach ($validated['staff_prices'] as $staffPriceData) {
            $excursion->staffPrices()->create([
                'staff_type' => $staffPriceData['staff_type'],
                'min_passengers' => $staffPriceData['min_passengers'],
                'max_passengers' => $staffPriceData['max_passengers'] ?? null,
                'price' => $staffPriceData['price'],
            ]);
        }

        $excursion->refresh()->load(['busSeats', 'assignedUsers', 'prices', 'staffPrices']);

        $user = $request->user();
        $isAdmin = $user && ($user->isSuperUser() || (int) $user->moonshine_user_role_id === 1);
        $includePast = $isAdmin || ($user && (int) $user->moonshine_user_role_id === 2);
        
        return response()->json([
            'message' => 'Цены персонала обновлены',
            'data' => $this->transformExcursion($excursion, false, $includePast),
        ]);
    }

    /**
     * Получить статистику по экскурсиям с чистой прибылью
     */
    public function statistics(Request $request)
    {
        $user = $request->user();

        if (! $user || (! $user->isSuperUser() && (int) $user->moonshine_user_role_id !== 1)) {
            abort(403, 'Недостаточно прав для просмотра статистики.');
        }

        $excursions = Excursion::with(['bookings', 'bookings.bookedBy', 'prices', 'staffPrices', 'assignedUsers'])
            ->where('is_active', true)
            ->orderBy('date_time', 'desc')
            ->get();

        $statistics = [];

        foreach ($excursions as $excursion) {
            $bookings = $excursion->bookings;
            
            // Пропускаем экскурсии без бронирований
            if ($bookings->isEmpty()) {
                continue;
            }
            
            $totalRevenue = $bookings->sum('price'); // Общая выручка от проданных билетов

            // Считаем комиссии продавцов
            $sellerCommissions = 0;
            foreach ($bookings as $booking) {
                $priceRecord = $excursion->prices->firstWhere('passenger_type', $booking->passenger_type);
                if ($priceRecord) {
                    $commissionPercent = (float) $priceRecord->seller_commission_percent;
                    $sellerCommissions += $booking->price * $commissionPercent / 100;
                } else {
                    $sellerCommissions += $booking->price * 0.10; // Дефолтная комиссия 10%
                }
            }

            // Считаем расходы на персонал (водители и экскурсоводы)
            $driverCosts = 0;
            $guideCosts = 0;
            $passengerCount = $bookings->count();

            // Цена для водителя
            $driverPrices = $excursion->staffPrices->where('staff_type', 'driver');
            $driverPrice = $driverPrices->first(function ($price) use ($passengerCount) {
                return $price->matchesPassengerCount($passengerCount);
            });
            if ($driverPrice) {
                $driverCosts = (float) $driverPrice->price;
            }

            // Цена для экскурсовода
            $guidePrices = $excursion->staffPrices->where('staff_type', 'guide');
            $guidePrice = $guidePrices->first(function ($price) use ($passengerCount) {
                return $price->matchesPassengerCount($passengerCount);
            });
            if ($guidePrice) {
                $guideCosts = (float) $guidePrice->price;
            }

            $staffCosts = $driverCosts + $guideCosts;

            // Доход = выручка от продажи билетов
            $income = $totalRevenue;
            
            // Чистая прибыль = доход - комиссии продавцов - расходы на персонал
            $netProfit = $income - $sellerCommissions - $staffCosts;

            $statistics[] = [
                'excursion' => [
                    'id' => $excursion->id,
                    'title' => $excursion->title,
                    'date_time' => $excursion->date_time?->toIso8601String(),
                ],
                'income' => round($income, 2), // Доход (выручка)
                'total_revenue' => round($totalRevenue, 2), // Для обратной совместимости
                'bookings_count' => $bookings->count(),
                'seller_commissions' => round($sellerCommissions, 2),
                'driver_costs' => round($driverCosts, 2),
                'guide_costs' => round($guideCosts, 2),
                'staff_costs' => round($staffCosts, 2),
                'net_profit' => round($netProfit, 2), // Чистая прибыль = доход - комиссии - расходы на персонал
            ];
        }

        // Считаем общую чистую прибыль напрямую, чтобы избежать проблем с округлением
        $totalNetProfit = 0;
        foreach ($statistics as $stat) {
            $totalNetProfit += (float) $stat['net_profit'];
        }

        return response()->json([
            'statistics' => $statistics,
            'total_net_profit' => round($totalNetProfit, 2),
        ]);
    }

    /**
     * Получить шаблоны расписания экскурсий из базы данных
     */
    public function schedule(Request $request)
    {
        $weekdays = [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];
        
        // Получаем все экскурсии с их расписанием из БД
        $excursions = Excursion::with('scheduleTemplate.scheduleDays')
            ->orderBy('title', 'asc')
            ->get();
        
        $schedule = $excursions->map(function ($excursion) use ($weekdays) {
            $scheduleData = [];
            
            if ($excursion->scheduleTemplate && $excursion->scheduleTemplate->scheduleDays) {
                foreach ($excursion->scheduleTemplate->scheduleDays as $scheduleDay) {
                    $time = is_string($scheduleDay->time) 
                        ? (strlen($scheduleDay->time) >= 5 ? substr($scheduleDay->time, 0, 5) : $scheduleDay->time)
                        : $scheduleDay->time->format('H:i');
                    
                    $scheduleData[] = [
                        'day_number' => $scheduleDay->weekday,
                        'day_name' => $weekdays[$scheduleDay->weekday] ?? "День {$scheduleDay->weekday}",
                        'time' => $time,
                    ];
                }
            }
            
            return [
                'id' => $excursion->id,
                'title' => $excursion->title,
                'description' => $excursion->description ?? '',
                'schedule' => $scheduleData,
            ];
        })->filter(function ($item) {
            // Фильтруем только те экскурсии, у которых есть расписание
            return !empty($item['schedule']);
        });
        
        return response()->json([
            'data' => $schedule->values(),
        ]);
    }

    /**
     * Отменить экскурсию на конкретную дату и время
     */
    public function cancelDate(Request $request, $id)
    {
        $user = $request->user();
        $isAdmin = $user->isSuperUser() || (int) $user->moonshine_user_role_id === 1;
        
        if (!$isAdmin) {
            abort(403, 'Only admins can cancel excursions');
        }

        $request->validate([
            'date_time' => 'required|date',
        ]);

        $excursion = Excursion::findOrFail($id);
        $dateTime = \Carbon\Carbon::parse($request->input('date_time'));

        // Проверяем, не отменена ли уже эта дата
        $existing = \App\Models\CancelledExcursionDate::where('excursion_id', $excursion->id)
            ->where('date_time', $dateTime->format('Y-m-d H:i:s'))
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Эта дата уже отменена',
            ], 422);
        }

        // Создаем запись об отмене
        $cancelled = \App\Models\CancelledExcursionDate::create([
            'excursion_id' => $excursion->id,
            'date_time' => $dateTime,
        ]);

        // Аннулируем все бронирования на эту дату и время
        $normalizedDate = $dateTime->format('Y-m-d');
        $normalizedTime = $dateTime->format('H:i');
        
        $bookings = \App\Models\Booking::where('excursion_id', $excursion->id)
            ->where(function($query) use ($normalizedDate, $normalizedTime, $dateTime) {
                $query->where(function($q) use ($normalizedDate, $normalizedTime) {
                    // Проверяем по excursion_date и time
                    $q->whereRaw('DATE(excursion_date) = ?', [$normalizedDate])
                      ->whereRaw('TIME(time) = ?', [$normalizedTime]);
                })->orWhere(function($q) use ($normalizedDate, $normalizedTime, $dateTime) {
                    // Или по weekday и time (если нет конкретной даты)
                    $q->whereNull('excursion_date')
                      ->where('weekday', $dateTime->isoWeekday())
                      ->whereRaw('TIME(time) = ?', [$normalizedTime]);
                });
            })
            ->get();

        $cancelledCount = 0;
        foreach ($bookings as $booking) {
            // Возвращаем деньги в кошелек
            \App\Models\WalletTransaction::create([
                'user_id' => $booking->booked_by,
                'booking_id' => $booking->id,
                'amount' => -$booking->price, // Отрицательная сумма = возврат
                'description' => "Возврат за отмененную экскурсию",
            ]);
            
            $booking->delete();
            $cancelledCount++;
        }

        return response()->json([
            'message' => 'Экскурсия отменена',
            'cancelled_bookings_count' => $cancelledCount,
            'data' => [
                'id' => $cancelled->id,
                'excursion_id' => $excursion->id,
                'date_time' => $cancelled->date_time->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Восстановить отмененную экскурсию
     */
    public function restoreDate(Request $request, $id, $cancelledDateId)
    {
        $user = $request->user();
        $isAdmin = $user->isSuperUser() || (int) $user->moonshine_user_role_id === 1;
        
        if (!$isAdmin) {
            abort(403, 'Only admins can restore excursions');
        }

        $cancelled = \App\Models\CancelledExcursionDate::where('excursion_id', $id)
            ->findOrFail($cancelledDateId);

        $cancelled->delete();

        return response()->json([
            'message' => 'Экскурсия восстановлена',
        ], 200);
    }

    /**
     * Получить список всех отмененных экскурсий
     */
    public function cancelledDates(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->isSuperUser() || (int) $user->moonshine_user_role_id === 1;
        
        if (!$isAdmin) {
            abort(403, 'Only admins can view cancelled dates');
        }

        $cancelledDates = CancelledExcursionDate::with('excursion')
            ->orderBy('date_time', 'desc')
            ->get()
            ->map(function ($cancelled) {
                return [
                    'id' => $cancelled->id,
                    'excursion_id' => $cancelled->excursion_id,
                    'excursion_title' => $cancelled->excursion->title,
                    'date_time' => $cancelled->date_time->toIso8601String(),
                    'date' => $cancelled->date_time->format('Y-m-d'),
                    'time' => $cancelled->date_time->format('H:i'),
                    'created_at' => $cancelled->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $cancelledDates,
        ], 200);
    }
}
