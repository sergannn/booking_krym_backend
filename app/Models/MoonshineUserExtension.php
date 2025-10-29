<?php

namespace App\Models;

use MoonShine\Laravel\Models\MoonshineUser as BaseMoonshineUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MoonshineUserExtension extends BaseMoonshineUser
{
    /**
     * Связь с транзакциями кошелька
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Связь с бронированиями (продажи)
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'booked_by');
    }

    /**
     * Связь с назначенными экскурсиями
     */
    public function assignedExcursions(): BelongsToMany
    {
        return $this->belongsToMany(Excursion::class, 'excursion_user', 'user_id', 'excursion_id')
            ->withPivot('role_in_excursion')
            ->withTimestamps();
    }

    /**
     * Назначенные экскурсии как водитель
     */
    public function driverExcursions(): BelongsToMany
    {
        return $this->assignedExcursions()->wherePivot('role_in_excursion', 'driver');
    }

    /**
     * Назначенные экскурсии как гид
     */
    public function guideExcursions(): BelongsToMany
    {
        return $this->assignedExcursions()->wherePivot('role_in_excursion', 'guide');
    }

    /**
     * Получить баланс кошелька
     */
    public function getWalletBalanceAttribute(): float
    {
        return $this->walletTransactions()->sum('amount');
    }
}


