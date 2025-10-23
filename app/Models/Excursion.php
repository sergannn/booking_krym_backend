<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Excursion extends Model
{
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

    /**
     * Связь с местами в автобусе
     */
    public function busSeats(): HasMany
    {
        return $this->hasMany(BusSeat::class);
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
}
