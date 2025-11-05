# Архитектурные диаграммы проекта Excursion Booking System

## 1. Общая архитектура системы

```mermaid
graph TB
    subgraph Frontend[Flutter Frontend]
        A[UI Layer<br/>features/*]
        B[State Management<br/>Riverpod Providers]
        C[Data Layer<br/>Repositories]
        D[API Client<br/>HTTP Client]
    end
    
    subgraph Backend[Laravel Backend]
        E[API Routes<br/>routes/api.php]
        F[Controllers<br/>app/Http/Controllers/API]
        G[Models<br/>app/Models]
        H[Database<br/>SQLite/MySQL]
    end
    
    subgraph AdminPanel[Laravel MoonShine Admin]
        I[Admin Interface]
        J[Admin Resources]
    end
    
    A --> B
    B --> C
    C --> D
    D -->|HTTPS REST API| E
    E --> F
    F --> G
    G --> H
    I --> J
    J --> G
    G --> H
    
    style Frontend fill:#e1f5ff
    style Backend fill:#fff4e1
    style AdminPanel fill:#f0e1ff
```

## 2. Схема базы данных (ER Diagram)

```mermaid
erDiagram
    MOONSHINE_USERS ||--o{ BOOKINGS : "creates"
    MOONSHINE_USERS ||--o{ WALLET_TRANSACTIONS : "has"
    MOONSHINE_USERS ||--o{ EXCURSION_USER : "assigned"
    MOONSHINE_USERS }o--|| MOONSHINE_USER_ROLES : "has"
    
    EXCURSIONS ||--o{ BOOKINGS : "has"
    EXCURSIONS ||--o{ BUS_SEATS : "contains"
    EXCURSIONS ||--o{ EXCURSION_PRICES : "has"
    EXCURSIONS ||--o{ EXCURSION_USER : "assigned"
    
    BUS_SEATS ||--o| BOOKINGS : "booked"
    BOOKINGS ||--o{ WALLET_TRANSACTIONS : "generates"
    STOPS ||--o{ BOOKINGS : "used_in"
    
    MOONSHINE_USERS {
        int id PK
        string name
        string email
        string password
        int role_id FK
    }
    
    MOONSHINE_USER_ROLES {
        int id PK
        string name
    }
    
    EXCURSIONS {
        int id PK
        string title
        string description
        string date_time
        decimal price
        int max_seats
        bool is_active
    }
    
    BUS_SEATS {
        int id PK
        int excursion_id FK
        int seat_number
        string status
        int booked_by FK
        string booked_at
    }
    
    BOOKINGS {
        int id PK
        int excursion_id FK
        int bus_seat_id FK
        int booked_by FK
        int stop_id FK
        string customer_name
        string customer_phone
        string passenger_type
        decimal price
        string created_at
    }
    
    EXCURSION_PRICES {
        int id PK
        int excursion_id FK
        string passenger_type
        decimal price
        decimal seller_commission
        decimal partner_commission
    }
    
    EXCURSION_USER {
        int excursion_id FK
        int user_id FK
        string role_in_excursion
    }
    
    WALLET_TRANSACTIONS {
        int id PK
        int user_id FK
        int booking_id FK
        decimal amount
        string description
        string created_at
    }
    
    STOPS {
        int id PK
        string name
        string address
        float latitude
        float longitude
    }
```

## 3. Схема API Endpoints

```mermaid
graph TB
    subgraph Auth["Authentication"]
        A1["POST /api/auth/login"]
        A2["POST /api/auth/logout"]
        A3["GET /api/auth/me"]
    end
    
    subgraph Users["Users Management"]
        U1["GET /api/users"]
        U2["GET /api/users/id"]
        U3["POST /api/users"]
        U4["DELETE /api/users/id"]
        U5["GET /api/users/roles"]
    end
    
    subgraph Excursions["Excursions"]
        E1["GET /api/excursions"]
        E2["GET /api/excursions/id"]
        E3["POST /api/excursions"]
        E4["POST /api/excursions/id/assign"]
        E5["DELETE /api/excursions/id/assign/user_id"]
        E6["PUT /api/excursions/id/prices"]
    end
    
    subgraph Bookings["Bookings"]
        B1["GET /api/bookings"]
        B2["POST /api/bookings"]
        B3["DELETE /api/bookings/id"]
    end
    
    subgraph Wallet["Wallet and Sales"]
        W1["GET /api/users/id/wallet"]
        W2["GET /api/users/id/sales"]
        W3["GET /api/users/id/profit"]
    end
    
    subgraph Stops["Stops"]
        S1["GET /api/stops"]
        S2["GET /api/excursions/id/stops"]
    end
    
    style Auth fill:#e1f5ff
    style Users fill:#fff4e1
    style Excursions fill:#f0e1ff
    style Bookings fill:#e1ffe1
    style Wallet fill:#ffe1f5
    style Stops fill:#f5ffe1
```

## 4. Поток данных: Бронирование места

```mermaid
sequenceDiagram
    participant UI as Flutter UI
    participant Repo as BookingsRepository
    participant API as Laravel API
    participant DB as Database
    participant Wallet as WalletService
    
    UI->>Repo: bookSeats(payload)
    Repo->>API: POST /api/bookings
    API->>DB: Проверка доступности места
    DB-->>API: Место доступно
    API->>DB: Создание Booking
    API->>DB: Обновление BusSeat (booked)
    API->>Wallet: Создание транзакции
    Wallet->>DB: WalletTransaction (amount = price)
    API-->>Repo: BookingResponse
    Repo-->>UI: Booking created
    UI->>UI: Обновление UI
    UI->>UI: Генерация PDF билета
```

## 5. Поток данных: Отмена бронирования

```mermaid
sequenceDiagram
    participant UI as Flutter UI
    participant Repo as BookingsRepository
    participant API as Laravel API
    participant DB as Database
    participant Wallet as WalletService
    
    UI->>UI: Диалог причины отмены
    UI->>Repo: cancelBooking(id, reason)
    Repo->>API: DELETE /api/bookings/{id}?reason=...
    API->>DB: Проверка времени (24ч guard)
    alt До экскурсии < 24 часов
        API-->>Repo: 422 Error
        Repo-->>UI: Ошибка отмены
    else До экскурсии >= 24 часов
        API->>DB: Проверка валидации reason
        API->>DB: Освобождение места
        API->>Wallet: Обратная транзакция
        Wallet->>DB: WalletTransaction (amount = -price, description с причиной)
        API->>DB: Удаление Booking
        API-->>Repo: Success
        Repo-->>UI: Booking cancelled
        UI->>UI: Обновление UI
    end
```

## 6. Архитектура ролей и прав доступа

```mermaid
graph TB
    subgraph Roles["Роли пользователей"]
        A["Admin<br/>role_id: 1"]
        S["Seller<br/>role_id: 2"]
        D["Driver<br/>role_id: 3"]
        P["Partner<br/>role_id: 4"]
    end
    
    subgraph AdminPerms["Права администратора"]
        A1["Создание экскурсий"]
        A2["Управление тарифами"]
        A3["Назначение сотрудников"]
        A4["Управление пользователями"]
        A5["Просмотр всех данных"]
    end
    
    subgraph SellerPerms["Права продавца"]
        S1["Бронирование мест"]
        S2["Отмена бронирований"]
        S3["Просмотр прибыли 10 процентов"]
        S4["Генерация билетов"]
    end
    
    subgraph DriverPerms["Права водителя"]
        D1["Просмотр назначенных экскурсий"]
        D2["Просмотр расписания"]
    end
    
    subgraph PartnerPerms["Права партнера"]
        P1["Просмотр прибыли с комиссией"]
        P2["Просмотр статистики"]
    end
    
    A --> A1
    A --> A2
    A --> A3
    A --> A4
    A --> A5
    
    S --> S1
    S --> S2
    S --> S3
    S --> S4
    
    D --> D1
    D --> D2
    
    P --> P1
    P --> P2
    
    style A fill:#ff6b6b
    style S fill:#4ecdc4
    style D fill:#95e1d3
    style P fill:#f38181
```

## 7. Схема расчета прибыли

```mermaid
graph LR
    subgraph Booking[Бронирование]
        B1[Цена билета<br/>pricePerSeat]
        B2[Тип пассажира<br/>adult/child/senior/disabled]
    end
    
    subgraph Tariff[Тариф]
        T1[Базовая цена<br/>from ExcursionPrice]
        T2[Комиссия продавца<br/>seller_commission_percent]
        T3[Комиссия партнера<br/>partner_commission_percent]
    end
    
    subgraph Calculation[Расчет прибыли]
        C1[Продавец:<br/>price * 10%]
        C2[Партнер:<br/>price * partner_commission%]
    end
    
    subgraph Wallet[Кошелек]
        W1[WalletTransaction<br/>amount = price]
        W2[Profit calculation<br/>commission from price]
    end
    
    B1 --> T1
    B2 --> T1
    T1 --> T2
    T1 --> T3
    T2 --> C1
    T3 --> C2
    C1 --> W1
    C2 --> W2
    
    style Booking fill:#e1f5ff
    style Tariff fill:#fff4e1
    style Calculation fill:#f0e1ff
    style Wallet fill:#e1ffe1
```

## 8. Схема взаимодействия Frontend и Backend

```mermaid
graph TB
    subgraph FlutterApp["Flutter Application"]
        F1["Features Layer<br/>admin/seller/driver/partner"]
        F2["State Management<br/>Riverpod"]
        F3["Repositories"]
        F4["API Client<br/>ApiClient"]
        F5["Models"]
    end
    
    subgraph LaravelAPI["Laravel API"]
        L1["Routes<br/>api.php"]
        L2["Controllers<br/>API Controllers"]
        L3["Middleware<br/>auth:sanctum"]
        L4["Models"]
        L5["Database"]
    end
    
    subgraph Auth["Authentication Flow"]
        A1["Login Request"]
        A2["Token Generation"]
        A3["Token Storage"]
        A4["Authenticated Requests"]
    end
    
    F1 --> F2
    F2 --> F3
    F3 --> F4
    F4 -->|HTTPS| L1
    L1 --> L3
    L3 --> L2
    L2 --> L4
    L4 --> L5
    
    F4 --> A1
    A1 -->|"POST /api/auth/login"| L2
    L2 --> A2
    A2 --> A3
    A3 --> A4
    A4 --> F4
    
    style FlutterApp fill:#e1f5ff
    style LaravelAPI fill:#fff4e1
    style Auth fill:#f0e1ff
```

## 9. Структура директорий проекта

```mermaid
graph TD
    ROOT["excursion.panfilius.ru/"]
    
    subgraph Laravel["Laravel Backend"]
        APP["app/"]
        HTTP["Http/Controllers/API/"]
        MODELS["Models/"]
        SERVICES["Services/"]
        ROUTES["routes/api.php"]
        DB["database/migrations/"]
        
        APP --> HTTP
        APP --> MODELS
        APP --> SERVICES
    end
    
    subgraph Flutter["Flutter Frontend"]
        FLUTTER_DIR["flutter_app/"]
        LIB["lib/src/"]
        FEATURES["features/"]
        DATA["data/"]
        CORE["core/"]
        ADMIN["admin/"]
        SELLER["seller/"]
        DRIVER["driver/"]
        PARTNER["partner/"]
        REPOS["repositories/"]
        MODELS_DART["models/"]
        API_CLIENT["api/"]
        
        FLUTTER_DIR --> LIB
        LIB --> FEATURES
        LIB --> DATA
        LIB --> CORE
        FEATURES --> ADMIN
        FEATURES --> SELLER
        FEATURES --> DRIVER
        FEATURES --> PARTNER
        DATA --> REPOS
        DATA --> MODELS_DART
        CORE --> API_CLIENT
    end
    
    subgraph Tests["Tests"]
        TEST_FLUTTER["flutter_app/test/"]
        TEST_LARAVEL["tests/"]
    end
    
    ROOT --> Laravel
    ROOT --> Flutter
    ROOT --> Tests
    
    style Laravel fill:#fff4e1
    style Flutter fill:#e1f5ff
    style Tests fill:#f0e1ff
```

## Инструменты для генерации диаграмм

### Используемые инструменты:
1. **Mermaid** - уже используется в проекте (ARCHITECTURE.md)
   - Поддерживается GitHub, GitLab, VS Code
   - Можно рендерить в HTML

### Дополнительные инструменты для Laravel:
1. **Laravel ER Diagram Generator** (composer пакет):
   ```bash
   composer require --dev beyondcode/laravel-erd-generator
   php artisan generate:erd
   ```

2. **Laravel Visual Modeler**:
   ```bash
   composer require --dev laravel-modeler/laravel-modeler
   ```

3. **PlantUML** (универсальный):
   - Установить PlantUML
   - Создать .puml файлы
   - Генерировать PNG/SVG

### Рекомендации:
- **Mermaid** - для документации в README/MD файлах (уже используется)
- **Laravel ER Generator** - для автоматической генерации ER диаграмм из миграций
- **PlantUML** - для более сложных UML диаграмм (sequence, class diagrams)

