# API Документация - Экскурсии

## Базовый URL
```
https://excursion.panfilius.ru/api
```

## Аутентификация

API использует Laravel Sanctum для аутентификации. Для доступа к защищенным эндпоинтам необходимо получить токен.

### Получение токена
```http
POST /api/auth/login
Content-Type: application/json

{
    "email": "anna@excursion.ru",
    "password": "password"
}
```

**Ответ:**
```json
{
    "token": "20|IfR5NzLPMSTiVetLUvbY2hSljEPRqMUCVdfNmbb64a451ae7",
    "user": {
        "id": 4,
        "name": "Анна Петрова",
        "email": "anna@excursion.ru",
        "role": "Продавец",
        "role_id": 2,
        "is_super_user": false
    }
}
```

### Использование токена
Добавьте заголовок `Authorization` с полученным токеном:
```http
Authorization: Bearer 20|IfR5NzLPMSTiVetLUvbY2hSljEPRqMUCVdfNmbb64a451ae7
```

---

## Эндпоинты

### 1. Получение списка экскурсий

**Публичный эндпоинт** (не требует аутентификации)

```http
GET /api/excursions
```

**Ответ:**
```json
{
    "data": [
        {
            "id": 1,
            "title": "Обзорная экскурсия по городу",
            "description": "Познакомьтесь с историей и достопримечательностями нашего города",
            "date_time": "2024-12-25T10:00:00.000000Z",
            "price": "1500.00",
            "max_seats": 50,
            "booked_seats_count": 36,
            "available_seats_count": 14,
            "bus_seats": [
                {
                    "id": 1,
                    "seat_number": 1,
                    "status": "booked",
                    "booked_by": 4,
                    "booked_at": "2024-12-20T15:30:00.000000Z"
                },
                {
                    "id": 2,
                    "seat_number": 2,
                    "status": "available",
                    "booked_by": null,
                    "booked_at": null
                }
                // ... остальные места
            ]
        }
    ]
}
```

### 2. Получение конкретной экскурсии

**Публичный эндпоинт** (не требует аутентификации)

```http
GET /api/excursions/{id}
```

**Пример:**
```http
GET /api/excursions/1
```

**Ответ:** Аналогичен ответу из списка экскурсий, но содержит только одну экскурсию.

### 3. Создание экскурсии

**Защищенный эндпоинт** (требует аутентификации и права администратора)

```http
POST /api/excursions
Authorization: Bearer {token}
Content-Type: application/json

{
    "title": "Новая экскурсия",
    "description": "Описание экскурсии",
    "date_time": "2024-12-25 10:00:00",
    "price": 2000,
    "max_seats": 100,
    "is_active": true
}
```

**Параметры:**
- `title` (обязательный) - Название экскурсии (максимум 255 символов)
- `description` (необязательный) - Описание экскурсии
- `date_time` (обязательный) - Дата и время экскурсии (формат: YYYY-MM-DD HH:MM:SS)
- `price` (обязательный) - Цена экскурсии (число, минимум 0)
- `max_seats` (обязательный) - Максимальное количество мест (целое число, от 1 до 100)
- `is_active` (необязательный) - Активна ли экскурсия (по умолчанию true)

**Ответ:**
```json
{
    "message": "Экскурсия создана",
    "data": {
        "id": 5,
        "title": "Новая экскурсия",
        "description": "Описание экскурсии",
        "date_time": "2024-12-25T10:00:00.000000Z",
        "price": "2000.00",
        "max_seats": 100,
        "booked_seats_count": 0,
        "available_seats_count": 100,
        "bus_seats": [
            {
                "id": 101,
                "seat_number": 1,
                "status": "available",
                "booked_by": null,
                "booked_at": null
            }
            // ... все 100 мест
        ]
    }
}
```

**Важно:** При создании экскурсии автоматически создаются все места в автобусе (от 1 до max_seats) со статусом "available".

---

## Бронирование мест

### 4. Создание бронирования

**Защищенный эндпоинт** (требует аутентификации)

```http
POST /api/bookings
Authorization: Bearer {token}
Content-Type: application/json

{
    "excursion_id": 1,
    "seat_number": 5
}
```

### 5. Получение бронирований пользователя

**Защищенный эндпоинт** (требует аутентификации)

```http
GET /api/bookings
Authorization: Bearer {token}
```

### 6. Отмена бронирования

**Защищенный эндпоинт** (требует аутентификации)

```http
DELETE /api/bookings/{id}
Authorization: Bearer {token}
```

---

## Статусы мест

- `available` - Свободно
- `booked` - Забронировано
- `reserved` - Зарезервировано

---

## Коды ответов

- `200` - Успешный запрос
- `201` - Ресурс создан
- `400` - Ошибка валидации
- `401` - Не авторизован
- `403` - Недостаточно прав
- `404` - Ресурс не найден
- `422` - Ошибка валидации данных
- `500` - Внутренняя ошибка сервера

---

## Примеры использования

### cURL

#### Получение списка экскурсий
```bash
curl -X GET https://excursion.panfilius.ru/api/excursions
```

#### Создание экскурсии
```bash
curl -X POST https://excursion.panfilius.ru/api/excursions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "title": "Тестовая экскурсия",
    "description": "Описание тестовой экскурсии",
    "date_time": "2024-12-25 10:00:00",
    "price": 1500,
    "max_seats": 50,
    "is_active": true
  }'
```

#### Получение конкретной экскурсии
```bash
curl -X GET https://excursion.panfilius.ru/api/excursions/1
```

### JavaScript (fetch)

```javascript
// Получение списка экскурсий
const response = await fetch('https://excursion.panfilius.ru/api/excursions');
const data = await response.json();
console.log(data);

// Создание экскурсии
const createExcursion = async (excursionData, token) => {
  const response = await fetch('https://excursion.panfilius.ru/api/excursions', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify(excursionData)
  });
  return await response.json();
};
```

### PHP (Guzzle)

```php
use GuzzleHttp\Client;

$client = new Client();

// Получение списка экскурсий
$response = $client->get('https://excursion.panfilius.ru/api/excursions');
$data = json_decode($response->getBody(), true);

// Создание экскурсии
$response = $client->post('https://excursion.panfilius.ru/api/excursions', [
    'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $token
    ],
    'json' => [
        'title' => 'Новая экскурсия',
        'description' => 'Описание',
        'date_time' => '2024-12-25 10:00:00',
        'price' => 2000,
        'max_seats' => 100,
        'is_active' => true
    ]
]);
```

---

## Управление пользователями

### Создание пользователя

**POST** `/api/users`

Создает нового пользователя с указанной ролью.

#### Тело запроса
```json
{
    "name": "Имя пользователя",
    "email": "user@example.com",
    "password": "password123",
    "role_id": 1
}
```

#### Успешный ответ (201)
```json
{
    "message": "Пользователь успешно создан",
    "user": {
        "id": 1,
        "name": "Имя пользователя",
        "email": "user@example.com",
        "role": "Admin",
        "role_id": 1,
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    }
}
```

### Получение информации о пользователе

**GET** `/api/users/{id}`

Получает подробную информацию о пользователе по его ID.

#### Успешный ответ (200)
```json
{
    "user": {
        "id": 1,
        "name": "Имя пользователя",
        "email": "user@example.com",
        "role": "Admin",
        "role_id": 1,
        "avatar": null,
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    }
}
```

### Получение списка пользователей

**GET** `/api/users`

Получает список всех пользователей с возможностью фильтрации и поиска.

#### Query параметры
- `role_id` (integer, опциональный) - Фильтр по роли
- `search` (string, опциональный) - Поиск по имени или email
- `per_page` (integer, опциональный) - Количество записей на страницу (по умолчанию 15)

#### Успешный ответ (200)
```json
{
    "users": [
        {
            "id": 1,
            "name": "Admin",
            "email": "admin@excursion.ru",
            "role": "Admin",
            "role_id": 1,
            "moonshine_user_role": {
                "id": 1,
                "name": "Admin"
            }
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 1
    }
}
```

**Примечание:** Для получения списка ролей используйте endpoint `/api/users` и извлеките уникальные значения из поля `moonshine_user_role`.

---

## Особенности

1. **Автоматическое создание мест** - При создании экскурсии автоматически создаются все места в автобусе
2. **Каскадное удаление** - При удалении экскурсии все связанные места и бронирования удаляются автоматически
3. **Валидация данных** - Все входящие данные проходят валидацию
4. **Права доступа** - Создание экскурсий доступно только администраторам
5. **Активные экскурсии** - По умолчанию возвращаются только активные экскурсии
6. **Управление пользователями** - Создание и получение информации о пользователях с разными ролями

---

## Поддержка

При возникновении проблем обращайтесь к администратору системы.
