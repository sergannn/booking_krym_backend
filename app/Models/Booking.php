<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MoonShine\Laravel\Models\MoonshineUser;

class Booking extends Model
{
    protected $fillable = [
        'excursion_id',
        'weekday',
        'time',
        'excursion_date',
        'bus_seat_id',
        'booked_by',
        'price',
        'customer_name',
        'customer_phone',
        'passenger_type',
        'with_entry',
        'stop_id',
        'booked_at',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'price' => 'decimal:2',
        'with_entry' => 'boolean',
        'booked_at' => 'datetime',
        'excursion_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        // При удалении бронирования освобождаем место
        static::deleting(function ($booking) {
            if ($booking->busSeat) {
                $booking->busSeat->update([
                    'status' => 'available',
                    'booked_by' => null,
                    'booked_at' => null,
                ]);
            }
        });
    }

    public function excursion()
    {
        return $this->belongsTo(Excursion::class);
    }

    public function busSeat()
    {
        return $this->belongsTo(BusSeat::class);
    }

    public function bookedBy()
    {
        return $this->belongsTo(MoonshineUser::class, 'booked_by');
    }

    public function bookedByUser()
    {
        return $this->belongsTo(MoonshineUser::class, 'booked_by');
    }

    public function stop()
    {
        return $this->belongsTo(Stop::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function settlements()
    {
        return $this->belongsToMany(Settlement::class, 'settlement_booking')
            ->withTimestamps();
    }

    /**
     * Дата/время экскурсии для отображения
     * ВАЖНО: Этот аксессор переопределяет стандартный доступ к excursion_date
     * Для получения реального значения из БД используйте getRawOriginal('excursion_date')
     */
    public function getExcursionDateAttribute($value): ?\Carbon\Carbon
    {
        // 1. Если есть реальное значение excursion_date из БД - используем его
        $rawExcursionDate = $this->getRawOriginal('excursion_date');
        if ($rawExcursionDate) {
            $dateOnly = is_string($rawExcursionDate) ? substr($rawExcursionDate, 0, 10) : $rawExcursionDate;
            $normalizedTime = is_string($this->time)
                ? substr($this->time, 0, 5)
                : ($this->time ? $this->time->format('H:i') : '00:00');
            return \Carbon\Carbon::parse($dateOnly . ' ' . $normalizedTime);
        }

        // 2. Если у экскурсии есть date_time — используем его
        if ($this->relationLoaded('excursion') && $this->excursion?->date_time) {
            return $this->excursion->date_time;
        }

        // 3. Если есть weekday и time — строим ближайшую дату
        if ($this->weekday && $this->time) {
            $startDate = now()->startOfDay();
            $normalizedTime = is_string($this->time)
                ? substr($this->time, 0, 5)
                : $this->time->format('H:i');

            for ($i = 0; $i < 60; $i++) {
                $date = $startDate->copy()->addDays($i);
                if ($date->isoWeekday() == $this->weekday) {
                    return \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $normalizedTime);
                }
            }
        }

        // 4. Фолбэк — дата бронирования
        return $this->booked_at;
    }
}
