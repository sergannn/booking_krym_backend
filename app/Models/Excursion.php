<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MoonShine\Laravel\Models\MoonshineUser;

class Excursion extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'date_time',
        'price',
        'max_seats',
        'is_active',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($excursion) {
            $excursion->createBusSeats();
        });

        static::deleting(function ($excursion) {
            // Дополнительная проверка - места должны удаляться автоматически через каскадное удаление
            // Но на всякий случай проверим, что они действительно удаляются
            $excursion->busSeats()->delete();
        });
    }

    /**
     * Связь с местами в автобусе
     */
    public function busSeats(): HasMany
    {
        return $this->hasMany(BusSeat::class);
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
            ->withPivot('role_in_excursion')
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
}
