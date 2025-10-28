<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stop extends Model
{
    protected $fillable = [
        'name',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
