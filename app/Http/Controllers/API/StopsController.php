<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Stop;
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
    public function forExcursion($excursionId)
    {
        // Остановки одинаковые для всех экскурсий
        $stops = Stop::orderBy('order')->get();
        
        return response()->json([
            'stops' => $stops
        ]);
    }
}
