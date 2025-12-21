<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivedBooking extends Model
{
    protected $fillable = [
        'original_booking_id',
        'original_excursion_id',
        'excursion_id',
        'bus_seat_id',
        'booked_by',
        'price',
        'customer_name',
        'customer_phone',
        'passenger_type',
        'stop_id',
        'booked_at',
        'payload',
        'wallet_transactions',
        'archived_reason',
        'archived_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'booked_at' => 'datetime',
        'payload' => 'array',
        'wallet_transactions' => 'array',
        'archived_at' => 'datetime',
    ];
}






