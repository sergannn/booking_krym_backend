<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MoonShine\Laravel\Models\MoonshineUser;

class Booking extends Model
{
    protected $fillable = [
        'excursion_id',
        'bus_seat_id',
        'booked_by',
        'price',
        'customer_name',
        'customer_phone',
        'passenger_type',
        'stop_id',
        'booked_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'booked_at' => 'datetime',
    ];

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

    public function stop()
    {
        return $this->belongsTo(Stop::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
