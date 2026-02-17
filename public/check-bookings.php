<?php
/**
 * Скрипт для проверки бронирований на экскурсию "Дегустация в Массандре"
 * Дата: 19 января 2026 (понедельник)
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\Excursion;
use Carbon\Carbon;

// Ищем экскурсию "Дегустация в Массандре"
$excursion = Excursion::where('title', 'LIKE', '%Дегустация%Массандр%')
    ->orWhere('title', 'LIKE', '%Массандр%Дегустация%')
    ->first();

if (!$excursion) {
    echo "❌ Экскурсия 'Дегустация в Массандре' не найдена.\n";
    echo "Доступные экскурсии:\n";
    $allExcursions = Excursion::select('id', 'title')->get();
    foreach ($allExcursions as $exc) {
        echo "  - ID: {$exc->id}, Название: {$exc->title}\n";
    }
    exit;
}

echo "✅ Найдена экскурсия: {$excursion->title} (ID: {$excursion->id})\n\n";

// Ищем бронирования на 19 января 2026
$targetDate = '2026-01-19';
$targetDayOfWeek = Carbon::parse($targetDate)->dayOfWeek; // 1 = понедельник

echo "📅 Ищем бронирования на: {$targetDate} (понедельник)\n\n";

// Ищем бронирования
$bookings = Booking::with(['busSeat', 'bookedByUser', 'stop'])
    ->where('excursion_id', $excursion->id)
    ->where(function($query) use ($targetDate, $targetDayOfWeek) {
        // Проверяем конкретную дату
        $query->whereDate('excursion_date', $targetDate)
              // Или проверяем по дню недели
              ->orWhere(function($q) use ($targetDayOfWeek) {
                  $q->where('weekday', $targetDayOfWeek);
              });
    })
    ->get();

echo "📊 Найдено бронирований: " . $bookings->count() . "\n\n";

if ($bookings->isEmpty()) {
    echo "ℹ️  Бронирований на эту дату не найдено.\n";
    exit;
}

// Группируем по продавцам
$bySeller = [];
foreach ($bookings as $booking) {
    $sellerId = $booking->booked_by ?? 0;
    $sellerName = $booking->bookedByUser ? $booking->bookedByUser->name : 'Неизвестно';
    $sellerColor = $booking->bookedByUser ? $booking->bookedByUser->color : null;
    
    if (!isset($bySeller[$sellerId])) {
        $bySeller[$sellerId] = [
            'name' => $sellerName,
            'color' => $sellerColor,
            'bookings' => []
        ];
    }
    
    $bySeller[$sellerId]['bookings'][] = [
        'id' => $booking->id,
        'seat' => $booking->busSeat ? $booking->busSeat->seat_number : 'N/A',
        'customer' => $booking->customer_name,
        'phone' => $booking->customer_phone,
        'date' => $booking->excursion_date,
        'time' => $booking->time,
    ];
}

// Выводим результаты
echo "👥 Продавцы, забронировавшие места:\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($bySeller as $sellerId => $data) {
    $colorDisplay = $data['color'] ? "✅ Цвет: {$data['color']}" : "❌ Цвет не задан";
    echo "📌 Продавец: {$data['name']} (ID: {$sellerId})\n";
    echo "   {$colorDisplay}\n";
    echo "   Количество бронирований: " . count($data['bookings']) . "\n";
    echo "   Места: ";
    
    $seats = array_map(function($b) {
        return $b['seat'];
    }, $data['bookings']);
    echo implode(', ', $seats) . "\n\n";
}

echo "\n📋 Детальная информация о бронированиях:\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($bookings as $booking) {
    $sellerName = $booking->bookedByUser ? $booking->bookedByUser->name : 'Неизвестно';
    $sellerColor = $booking->bookedByUser ? $booking->bookedByUser->color : null;
    $seatNumber = $booking->busSeat ? $booking->busSeat->seat_number : 'N/A';
    
    echo "Бронирование #{$booking->id}\n";
    echo "  Место: №{$seatNumber}\n";
    echo "  Клиент: {$booking->customer_name} ({$booking->customer_phone})\n";
    echo "  Продавец: {$sellerName}";
    if ($sellerColor) {
        echo " [Цвет: {$sellerColor}]";
    } else {
        echo " [❌ Цвет не задан]";
    }
    echo "\n";
    echo "  Дата: {$booking->excursion_date}\n";
    echo "  Время: {$booking->time}\n";
    echo "\n";
}

echo "\n✅ Проверка завершена!\n";