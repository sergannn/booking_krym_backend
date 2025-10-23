<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MoonShine\Laravel\Models\MoonshineUser;

class BusSeat extends Model
{
    protected $fillable = [
        'excursion_id',
        'seat_number',
        'status',
        'booked_by',
        'booked_at',
    ];

    protected $casts = [
        'booked_at' => 'datetime',
    ];

    /**
     * Связь с экскурсией
     */
    public function excursion(): BelongsTo
    {
        return $this->belongsTo(Excursion::class);
    }

    /**
     * Связь с пользователем, который забронировал место
     */
    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(MoonshineUser::class, 'booked_by');
    }

    /**
     * Проверка, свободно ли место
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Проверка, забронировано ли место
     */
    public function isBooked(): bool
    {
        return $this->status === 'booked';
    }

    /**
     * Проверка, зарезервировано ли место
     */
    public function isReserved(): bool
    {
        return $this->status === 'reserved';
    }
}
