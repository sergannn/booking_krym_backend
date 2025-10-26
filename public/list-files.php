<?php
$uploadDir = __DIR__ . '/fake_uploads/';
$files = [];

if (is_dir($uploadDir)) {
    $items = scandir($uploadDir);
    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..' && is_file($uploadDir . $item)) {
            $files[] = [
                'name' => $item,
                'size' => filesize($uploadDir . $item),
                'date' => filemtime($uploadDir . $item),
                'path' => '/fake_uploads/' . $item
            ];
        }
    }
}

// Сортируем по дате (новые сначала)
usort($files, function($a, $b) {
    return $b['date'] - $a['date'];
});
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загруженные файлы</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .file-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .file-info {
            flex: 1;
        }
        .file-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .file-details {
            color: #666;
            font-size: 0.9rem;
        }
        .file-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .empty {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .stats {
            background: #e8f5e8;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📁 Загруженные файлы</h1>
            <p>Все файлы, загруженные через анализатор аудио</p>
        </div>
        
        <div class="content">
            <div class="stats">
                <h3>📊 Статистика</h3>
                <p><strong>Всего файлов:</strong> <?= count($files) ?></p>
                <p><strong>Общий размер:</strong> <?= number_format(array_sum(array_column($files, 'size')) / 1024 / 1024, 2) ?> MB</p>
            </div>

            <?php if (empty($files)): ?>
                <div class="empty">
                    <h3>📂 Папка пуста</h3>
                    <p>Файлы еще не загружены</p>
                    <a href="/audio-analyzer.html" class="btn btn-primary">Загрузить файлы</a>
                </div>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <div class="file-item">
                        <div class="file-info">
                            <div class="file-name"><?= htmlspecialchars($file['name']) ?></div>
                            <div class="file-details">
                                Размер: <?= number_format($file['size'] / 1024, 2) ?> KB | 
                                Загружен: <?= date('d.m.Y H:i:s', $file['date']) ?>
                            </div>
                        </div>
                        <div class="file-actions">
                            <a href="<?= $file['path'] ?>" class="btn btn-primary" download>📥 Скачать</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="/audio-analyzer.html" class="btn btn-primary">🎙️ Вернуться к анализатору</a>
            </div>
        </div>
    </div>
</body>
</html>
