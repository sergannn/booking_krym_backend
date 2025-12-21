<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MoonShine\Laravel\Models\MoonshineUser;

class Excursion extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_template_id',
        'title',
        'description',
        'date_time',
        'price',
        'max_seats',
        'is_active',
        // Поля цен для взрослых
        'price_adult_without_entry',
        'price_adult_with_entry',
        'price_adult_seller_commission',
        'price_adult_partner_commission',
        // Поля цен для детей
        'price_child_without_entry',
        'price_child_with_entry',
        'price_child_seller_commission',
        'price_child_partner_commission',
        // Поля цен для пенсионеров
        'price_senior_without_entry',
        'price_senior_with_entry',
        'price_senior_seller_commission',
        'price_senior_partner_commission',
        // Поля цен для инвалидов
        'price_disabled_without_entry',
        'price_disabled_with_entry',
        'price_disabled_seller_commission',
        'price_disabled_partner_commission',
        // Поля цен для спеццены
        'price_special_without_entry',
        'price_special_with_entry',
        'price_special_seller_commission',
        'price_special_partner_commission',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        // Поля цен для взрослых
        'price_adult_without_entry' => 'decimal:2',
        'price_adult_with_entry' => 'decimal:2',
        'price_adult_seller_commission' => 'decimal:2',
        'price_adult_partner_commission' => 'decimal:2',
        // Поля цен для детей
        'price_child_without_entry' => 'decimal:2',
        'price_child_with_entry' => 'decimal:2',
        'price_child_seller_commission' => 'decimal:2',
        'price_child_partner_commission' => 'decimal:2',
        // Поля цен для пенсионеров
        'price_senior_without_entry' => 'decimal:2',
        'price_senior_with_entry' => 'decimal:2',
        'price_senior_seller_commission' => 'decimal:2',
        'price_senior_partner_commission' => 'decimal:2',
        // Поля цен для инвалидов
        'price_disabled_without_entry' => 'decimal:2',
        'price_disabled_with_entry' => 'decimal:2',
        'price_disabled_seller_commission' => 'decimal:2',
        'price_disabled_partner_commission' => 'decimal:2',
        // Поля цен для спеццены
        'price_special_without_entry' => 'decimal:2',
        'price_special_with_entry' => 'decimal:2',
        'price_special_seller_commission' => 'decimal:2',
        'price_special_partner_commission' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($excursion) {
            $excursion->createBusSeats();
            $excursion->createDefaultPrices();
        });

        static::deleting(function ($excursion) {
            // Дополнительная проверка - места должны удаляться автоматически через каскадное удаление
            // Но на всякий случай проверим, что они действительно удаляются
            $excursion->busSeats()->delete();
        });
    }

    /**
     * Связь с шаблоном расписания
     */
    public function scheduleTemplate(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class);
    }

    /**
     * Связь с местами в автобусе
     */
    public function busSeats(): HasMany
    {
        return $this->hasMany(BusSeat::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ExcursionPrice::class);
    }

    /**
     * Связь с ценами для персонала (водители/экскурсоводы)
     */
    public function staffPrices(): HasMany
    {
        return $this->hasMany(StaffPrice::class);
    }

    /**
     * Связь с бронированиями
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Связь с назначенными пользователями (водители/гиды)
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(MoonshineUser::class, 'excursion_user', 'excursion_id', 'user_id')
            ->withPivot('role_in_excursion', 'excursion_date', 'time')
            ->withTimestamps();
    }

    /**
     * Назначенные водители
     */
    public function drivers(): BelongsToMany
    {
        return $this->assignedUsers()->wherePivot('role_in_excursion', 'driver');
    }

    /**
     * Назначенные гиды
     */
    public function guides(): BelongsToMany
    {
        return $this->assignedUsers()->wherePivot('role_in_excursion', 'guide');
    }

    /**
     * Количество забронированных мест
     */
    public function getBookedSeatsCountAttribute(): int
    {
        return $this->busSeats()->whereIn('status', ['booked', 'reserved'])->count();
    }

    /**
     * Количество свободных мест
     */
    public function getAvailableSeatsCountAttribute(): int
    {
        return $this->max_seats - $this->booked_seats_count;
    }

    /**
     * Создание мест в автобусе для экскурсии
     */
    public function createBusSeats(): void
    {
        if ($this->busSeats()->count() > 0) {
            return; // Места уже созданы
        }

        $now = now();
        $seats = [];
        
        for ($i = 1; $i <= $this->max_seats; $i++) {
            $seats[] = [
                'excursion_id' => $this->id,
                'seat_number' => $i,
                'status' => 'available',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        BusSeat::insert($seats);
    }

    /**
     * Создание цен по умолчанию для всех типов пассажиров
     */
    public function createDefaultPrices(): void
    {
        if ($this->prices()->count() > 0) {
            return; // Цены уже созданы
        }

        $types = ['adult', 'child', 'senior', 'disabled', 'special'];
        $now = now();
        $prices = [];

        foreach ($types as $type) {
            $prices[] = [
                'excursion_id' => $this->id,
                'passenger_type' => $type,
                'price' => null, // Основная цена не используется
                'price_without_entry' => $this->price,
                'price_with_entry' => $this->price,
                'seller_commission_percent' => 10,
                'partner_commission_percent' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ExcursionPrice::insert($prices);
    }

    public function priceForType(string $passengerType): ?float
    {
        $price = $this->prices
            ->firstWhere('passenger_type', $passengerType);

        return $price?->price;
    }

    /**
     * Получить отформатированное отображение всех цен
     */
    public function getFormattedPricesAttribute(): string
    {
        $passengerTypeLabels = [
            'adult' => 'Взрослый',
            'child' => 'Детский',
            'senior' => 'Пенсионер',
            'disabled' => 'Инвалид',
            'special' => 'Спеццена',
        ];

        $types = ['adult', 'child', 'senior', 'disabled', 'special'];
        $result = [];

        foreach ($types as $type) {
            $typeLabel = $passengerTypeLabels[$type] ?? $type;
            
            // Сначала проверяем значения в самой модели (из таблицы excursions)
            $withoutEntryField = "price_{$type}_without_entry";
            $withEntryField = "price_{$type}_with_entry";
            
            $priceWithout = null;
            $priceWith = null;
            
            // Проверяем прямые атрибуты модели
            if (isset($this->attributes[$withoutEntryField]) && $this->attributes[$withoutEntryField] !== null) {
                $priceWithout = (float)$this->attributes[$withoutEntryField];
            } elseif (isset($this->attributes[$withEntryField]) && $this->attributes[$withEntryField] !== null) {
                // Если нет without_entry, но есть with_entry, используем его
            }
            
            if (isset($this->attributes[$withEntryField]) && $this->attributes[$withEntryField] !== null) {
                $priceWith = (float)$this->attributes[$withEntryField];
            }
            
            // Если в excursions нет значений, берем из excursion_prices
            if ($priceWithout === null || $priceWith === null) {
                $this->loadMissing('prices');
                $price = $this->prices->firstWhere('passenger_type', $type);
                if ($price) {
                    if ($priceWithout === null) {
                        $priceWithout = $price->price_without_entry ? (float)$price->price_without_entry : null;
                    }
                    if ($priceWith === null) {
                        $priceWith = $price->price_with_entry ? (float)$price->price_with_entry : null;
                    }
                }
            }
            
            $priceWithoutFormatted = $priceWithout 
                ? number_format($priceWithout, 2, '.', ' ') . ' ₽'
                : '-';
            $priceWithFormatted = $priceWith 
                ? number_format($priceWith, 2, '.', ' ') . ' ₽'
                : '-';
            
            $result[] = "$typeLabel: Без входа - $priceWithoutFormatted, Со входом - $priceWithFormatted";
        }

        if (empty($result) || (count($result) === 4 && strpos(implode('', $result), '-') !== false && strpos(implode('', $result), '₽') === false)) {
            return 'Цены не настроены';
        }

        return implode(' | ', $result);
    }

    /**
     * Accessors для получения цен по типам пассажиров
     * ВАЖНО: Accessors используются только для чтения, не для записи
     * При сохранении используются прямые атрибуты модели
     */
    private function getPriceAttributeForType(string $type, string $field): ?float
    {
        // Сначала проверяем, есть ли значение в самой модели (из таблицы excursions)
        $directField = "price_{$type}_{$field}";
        if (isset($this->attributes[$directField]) && $this->attributes[$directField] !== null) {
            return (float)$this->attributes[$directField];
        }
        
        // Если нет, берем из excursion_prices
        $this->loadMissing('prices');
        $price = $this->prices->firstWhere('passenger_type', $type);
        return $price ? (float)$price->$field : null;
    }

    public function getPriceAdultWithoutEntryAttribute(): ?float
    {
        // Если значение установлено напрямую в модели, используем его
        if (isset($this->attributes['price_adult_without_entry']) && $this->attributes['price_adult_without_entry'] !== null) {
            return (float)$this->attributes['price_adult_without_entry'];
        }
        return $this->getPriceAttributeForType('adult', 'price_without_entry');
    }

    public function getPriceAdultWithEntryAttribute(): ?float
    {
        if (isset($this->attributes['price_adult_with_entry']) && $this->attributes['price_adult_with_entry'] !== null) {
            return (float)$this->attributes['price_adult_with_entry'];
        }
        return $this->getPriceAttributeForType('adult', 'price_with_entry');
    }

    public function getPriceAdultSellerCommissionAttribute(): ?float
    {
        if (isset($this->attributes['price_adult_seller_commission']) && $this->attributes['price_adult_seller_commission'] !== null) {
            return (float)$this->attributes['price_adult_seller_commission'];
        }
        return $this->getPriceAttributeForType('adult', 'seller_commission_percent');
    }

    public function getPriceAdultPartnerCommissionAttribute(): ?float
    {
        if (isset($this->attributes['price_adult_partner_commission']) && $this->attributes['price_adult_partner_commission'] !== null) {
            return (float)$this->attributes['price_adult_partner_commission'];
        }
        return $this->getPriceAttributeForType('adult', 'partner_commission_percent');
    }

    public function getPriceChildWithoutEntryAttribute(): ?float
    {
        if (isset($this->attributes['price_child_without_entry']) && $this->attributes['price_child_without_entry'] !== null) {
            return (float)$this->attributes['price_child_without_entry'];
        }
        return $this->getPriceAttributeForType('child', 'price_without_entry');
    }

    public function getPriceChildWithEntryAttribute(): ?float
    {
        if (isset($this->attributes['price_child_with_entry']) && $this->attributes['price_child_with_entry'] !== null) {
            return (float)$this->attributes['price_child_with_entry'];
        }
        return $this->getPriceAttributeForType('child', 'price_with_entry');
    }

    public function getPriceChildSellerCommissionAttribute(): ?float
    {
        if (isset($this->attributes['price_child_seller_commission']) && $this->attributes['price_child_seller_commission'] !== null) {
            return (float)$this->attributes['price_child_seller_commission'];
        }
        return $this->getPriceAttributeForType('child', 'seller_commission_percent');
    }

    public function getPriceChildPartnerCommissionAttribute(): ?float
    {
        if (isset($this->attributes['price_child_partner_commission']) && $this->attributes['price_child_partner_commission'] !== null) {
            return (float)$this->attributes['price_child_partner_commission'];
        }
        return $this->getPriceAttributeForType('child', 'partner_commission_percent');
    }

    public function getPriceSeniorWithoutEntryAttribute(): ?float
    {
        if (isset($this->attributes['price_senior_without_entry']) && $this->attributes['price_senior_without_entry'] !== null) {
            return (float)$this->attributes['price_senior_without_entry'];
        }
        return $this->getPriceAttributeForType('senior', 'price_without_entry');
    }

    public function getPriceSeniorWithEntryAttribute(): ?float
    {
        if (isset($this->attributes['price_senior_with_entry']) && $this->attributes['price_senior_with_entry'] !== null) {
            return (float)$this->attributes['price_senior_with_entry'];
        }
        return $this->getPriceAttributeForType('senior', 'price_with_entry');
    }

    public function getPriceSeniorSellerCommissionAttribute(): ?float
    {
        if (isset($this->attributes['price_senior_seller_commission']) && $this->attributes['price_senior_seller_commission'] !== null) {
            return (float)$this->attributes['price_senior_seller_commission'];
        }
        return $this->getPriceAttributeForType('senior', 'seller_commission_percent');
    }

    public function getPriceSeniorPartnerCommissionAttribute(): ?float
    {
        if (isset($this->attributes['price_senior_partner_commission']) && $this->attributes['price_senior_partner_commission'] !== null) {
            return (float)$this->attributes['price_senior_partner_commission'];
        }
        return $this->getPriceAttributeForType('senior', 'partner_commission_percent');
    }

    public function getPriceDisabledWithoutEntryAttribute(): ?float
    {
        if (isset($this->attributes['price_disabled_without_entry']) && $this->attributes['price_disabled_without_entry'] !== null) {
            return (float)$this->attributes['price_disabled_without_entry'];
        }
        return $this->getPriceAttributeForType('disabled', 'price_without_entry');
    }

    public function getPriceDisabledWithEntryAttribute(): ?float
    {
        if (isset($this->attributes['price_disabled_with_entry']) && $this->attributes['price_disabled_with_entry'] !== null) {
            return (float)$this->attributes['price_disabled_with_entry'];
        }
        return $this->getPriceAttributeForType('disabled', 'price_with_entry');
    }

    public function getPriceDisabledSellerCommissionAttribute(): ?float
    {
        if (isset($this->attributes['price_disabled_seller_commission']) && $this->attributes['price_disabled_seller_commission'] !== null) {
            return (float)$this->attributes['price_disabled_seller_commission'];
        }
        return $this->getPriceAttributeForType('disabled', 'seller_commission_percent');
    }

    public function getPriceDisabledPartnerCommissionAttribute(): ?float
    {
        if (isset($this->attributes['price_disabled_partner_commission']) && $this->attributes['price_disabled_partner_commission'] !== null) {
            return (float)$this->attributes['price_disabled_partner_commission'];
        }
        return $this->getPriceAttributeForType('disabled', 'partner_commission_percent');
    }

    public function getPriceSpecialWithoutEntryAttribute(): ?float
    {
        if (isset($this->attributes['price_special_without_entry']) && $this->attributes['price_special_without_entry'] !== null) {
            return (float)$this->attributes['price_special_without_entry'];
        }
        return $this->getPriceAttributeForType('special', 'price_without_entry');
    }

    public function getPriceSpecialWithEntryAttribute(): ?float
    {
        if (isset($this->attributes['price_special_with_entry']) && $this->attributes['price_special_with_entry'] !== null) {
            return (float)$this->attributes['price_special_with_entry'];
        }
        return $this->getPriceAttributeForType('special', 'price_with_entry');
    }

    public function getPriceSpecialSellerCommissionAttribute(): ?float
    {
        if (isset($this->attributes['price_special_seller_commission']) && $this->attributes['price_special_seller_commission'] !== null) {
            return (float)$this->attributes['price_special_seller_commission'];
        }
        return $this->getPriceAttributeForType('special', 'seller_commission_percent');
    }

    public function getPriceSpecialPartnerCommissionAttribute(): ?float
    {
        if (isset($this->attributes['price_special_partner_commission']) && $this->attributes['price_special_partner_commission'] !== null) {
            return (float)$this->attributes['price_special_partner_commission'];
        }
        return $this->getPriceAttributeForType('special', 'partner_commission_percent');
    }
}
