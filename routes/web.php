<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// Маршрут для страницы задач
Route::get('/tasks', function () {
    $tasksPath = public_path('tasks.html');
    if (file_exists($tasksPath)) {
        return response(file_get_contents($tasksPath), 200)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
    return response('Tasks file not found', 404);
});

// Маршрут для тестирования цветов схемы рассадки
Route::get('/test-seating-colors', function () {
    $colorsPath = public_path('test-seating-colors.html');
    if (file_exists($colorsPath)) {
        return response(file_get_contents($colorsPath), 200)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
    return response('Test seating colors file not found', 404);
});

// Маршрут для скачивания APK
Route::get('/download', function () {
    $apkPath = public_path('apk');
    
    // Сначала проверяем latest.apk (самый быстрый вариант)
    $latestApkPath = $apkPath . '/latest.apk';
    if (file_exists($latestApkPath)) {
        return response()->download($latestApkPath, 'excursion.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
    
    // Если latest.apk нет, ищем все APK файлы в public/apk
    $apkFiles = glob($apkPath . '/*.apk');
    
    // Если нет в public/apk, проверяем build директорию Flutter
    if (empty($apkFiles)) {
        $flutterBuildPath = base_path('flutter_app/build/app/outputs/flutter-apk');
        $apkFiles = glob($flutterBuildPath . '/*.apk');
    }
    
    // Если файлы найдены, берем последний по времени модификации
    if (!empty($apkFiles)) {
        // Сортируем по времени модификации (новые первыми)
        usort($apkFiles, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $latestApk = $apkFiles[0];
        $fileName = basename($latestApk);
        
        return response()->download($latestApk, $fileName, [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
    
    // Если APK не найден
    return response()->json([
        'error' => 'APK file not found',
        'message' => 'Please build the APK first using: ./build_apk.sh or cd flutter_app && flutter build apk',
        'checked_paths' => [
            public_path('apk'),
            base_path('flutter_app/build/app/outputs/flutter-apk'),
        ]
    ], 404);
});
