<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Stop;
use App\Models\Booking;
use App\Models\Excursion;
use Illuminate\Http\Request;

class StopsController extends Controller
{
    /**
     * Получить список всех остановок
     */
    public function index()
    {
        $stops = Stop::orderBy('order')->get();
        
        return response()->json([
            'stops' => $stops
        ]);
    }

    /**
     * Получить остановки для конкретной экскурсии
     */
    public function forExcursion(Request $request, $excursionId)
    {
        $user = $request->user();

        // Для водителей и экскурсоводов показываем только остановки,
        // которые реально встречаются в бронированиях назначенной им экскурсии
        if ($user && in_array((int) $user->moonshine_user_role_id, [3, 5], true)) {
            $excursion = Excursion::with('assignedUsers')->findOrFail($excursionId);

            $isAssigned = $excursion->assignedUsers->contains('id', $user->id);
            if (! $isAssigned) {
                abort(403, 'У вас нет доступа к этой экскурсии');
            }

            $stopIds = Booking::where('excursion_id', $excursionId)
                ->whereNotNull('stop_id')
                ->pluck('stop_id')
                ->unique()
                ->values();

            $stops = Stop::whereIn('id', $stopIds)->orderBy('order')->get();

            return response()->json([
                'stops' => $stops
            ]);
        }

        // Для остальных клиентов оставляем поведение по умолчанию (все остановки)
        $stops = Stop::orderBy('order')->get();

        return response()->json([
            'stops' => $stops
        ]);
    }
}
