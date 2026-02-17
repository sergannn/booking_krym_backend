<?php

namespace App\Models;

use MoonShine\Laravel\Models\MoonshineUser as BaseMoonshineUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoonshineUserExtension extends BaseMoonshineUser
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'moonshine_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'moonshine_user_role_id',
        'password',
        'name',
        'avatar',
        'plain_password',
        'bus_id',
    ];

    /**
     * The attributes that should be visible in serialization.
     *
     * @var array<int, string>
     */
    protected $visible = [
        'id',
        'name',
        'email',
        'moonshine_user_role_id',
        'avatar',
        'created_at',
        'updated_at',
        'plain_password', // Добавляем для доступа через API
    ];

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

    /**
     * Связь с расчетами (settlements)
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class, 'seller_id');
    }

    /**
     * Связь с автобусом (для водителей)
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class, 'bus_id');
    }
}


