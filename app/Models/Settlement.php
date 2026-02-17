<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MoonShine\Laravel\Models\MoonshineUser;

class Settlement extends Model
{
    protected $fillable = [
        'seller_id',
        'total_amount',
        'notes',
        'settlement_date',
        'date_from',
        'date_to',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'settlement_date' => 'date',
        'date_from' => 'date',
        'date_to' => 'date',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(MoonshineUser::class, 'seller_id');
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'settlement_booking')
            ->withTimestamps();
    }
}
