<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MoonShine\Laravel\Models\MoonshineUser;

class WalletTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'booking_id',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(MoonshineUser::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
