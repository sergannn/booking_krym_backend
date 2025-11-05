# Архитектурные диаграммы проекта

## 📊 Просмотр диаграмм

### 1. Интерактивные Mermaid диаграммы

Откройте в браузере:
```
https://excursion.panfilius.ru/diagrams.html
```

Или локально:
```
http://localhost/diagrams.html
```

На странице доступны 9 интерактивных диаграмм:
- Общая архитектура системы
- База данных (ER Diagram)
- API Endpoints
- Поток бронирования
- Поток отмены бронирования
- Роли и права доступа
- Расчет прибыли
- Взаимодействие Frontend ↔ Backend
- Структура проекта

### 2. ER диаграмма базы данных (автоматическая генерация)

Сгенерировать ER диаграмму из моделей Laravel:

```bash
php artisan generate:erd public/database-erd.png --format=png
```

Или в формате SVG:
```bash
php artisan generate:erd public/database-erd.svg --format=svg
```

Просмотр:
```
https://excursion.panfilius.ru/database-erd.png
```

## 📁 Файлы диаграмм

- `public/diagrams.html` - Интерактивная страница с Mermaid диаграммами
- `public/database-erd.png` - Автоматически сгенерированная ER диаграмма
- `PROJECT_DIAGRAMS.md` - Markdown файл с исходным кодом диаграмм
- `DIAGRAM_TOOLS.md` - Инструкции по использованию инструментов

## 🔧 Команды для генерации

### Обновление ER диаграммы
```bash
php artisan generate:erd public/database-erd.png --format=png
```

### Доступные форматы
- `png` - PNG изображение
- `svg` - SVG векторная графика
- `pdf` - PDF документ

### Просмотр всех опций
```bash
php artisan generate:erd --help
```

