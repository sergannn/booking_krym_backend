# API для управления пользователями

## Обзор

API предоставляет endpoints для создания пользователей с разными ролями и получения информации о них. Все endpoints требуют аутентификации через Laravel Sanctum.

## Аутентификация

Все endpoints требуют заголовок `Authorization: Bearer {token}` где `{token}` - это токен, полученный при логине через `/api/auth/login`.

## Endpoints

### 1. Создание пользователя

**POST** `/api/users`

Создает нового пользователя с указанной ролью.

#### Заголовки
```
Authorization: Bearer {token}
Content-Type: application/json
```

#### Тело запроса
```json
{
    "name": "Имя пользователя",
    "email": "user@example.com",
    "password": "password123",
    "role_id": 1
}
```

#### Параметры
- `name` (string, обязательный) - Имя пользователя (2-255 символов)
- `email` (string, обязательный) - Email адрес (уникальный, максимум 190 символов)
- `password` (string, обязательный) - Пароль (минимум 8 символов)
- `role_id` (integer, обязательный) - ID роли пользователя

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

#### Ошибки
- `422` - Ошибки валидации
- `500` - Внутренняя ошибка сервера

### 2. Получение информации о пользователе

**GET** `/api/users/{id}`

Получает подробную информацию о пользователе по его ID.

#### Заголовки
```
Authorization: Bearer {token}
```

#### Параметры URL
- `id` (integer) - ID пользователя

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

#### Ошибки
- `404` - Пользователь не найден
- `500` - Внутренняя ошибка сервера

### 3. Получение списка пользователей

**GET** `/api/users`

Получает список всех пользователей с возможностью фильтрации и поиска.

#### Заголовки
```
Authorization: Bearer {token}
```

#### Query параметры
- `role_id` (integer, опциональный) - Фильтр по роли
- `search` (string, опциональный) - Поиск по имени или email
- `per_page` (integer, опциональный) - Количество записей на страницу (по умолчанию 15)

#### Примеры запросов
```
GET /api/users
GET /api/users?role_id=1
GET /api/users?search=Иван
GET /api/users?per_page=10&role_id=2
```

#### Успешный ответ (200)
```json
{
    "users": [
        {
            "id": 1,
            "name": "Имя пользователя",
            "email": "user@example.com",
            "role": "Admin",
            "role_id": 1,
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
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

### 4. Получение списка ролей

**GET** `/api/users`

Получает список всех пользователей, где каждая запись содержит информацию о роли. Для получения только уникальных ролей можно обработать ответ на стороне клиента.

#### Заголовки
```
Content-Type: application/json
```

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
        },
        {
            "id": 4,
            "name": "Анна Петрова",
            "email": "anna@excursion.ru", 
            "role": "Продавец",
            "role_id": 2,
            "moonshine_user_role": {
                "id": 2,
                "name": "Продавец"
            }
        }
    ]
}
```

**Примечание:** Для получения только списка ролей обработайте ответ на стороне клиента, извлекая уникальные значения из поля `moonshine_user_role`.

## Коды ошибок

- `401` - Не авторизован (отсутствует или неверный токен)
- `404` - Ресурс не найден
- `422` - Ошибки валидации данных
- `500` - Внутренняя ошибка сервера

## Примеры использования

### Создание пользователя с ролью "Продавец"

```bash
curl -X POST http://your-domain.com/api/users \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Анна Петрова",
    "email": "anna@example.com",
    "password": "securepassword123",
    "role_id": 2
  }'
```

### Получение информации о пользователе

```bash
curl -X GET http://your-domain.com/api/users/1 \
  -H "Authorization: Bearer your-token"
```

### Получение списка ролей

```bash
curl -X GET http://your-domain.com/api/users/roles \
  -H "Authorization: Bearer your-token"
```
