<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcursionPrice extends Model
{
    protected $fillable = [
        'excursion_id',
        'passenger_type',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function excursion()
    {
        return $this->belongsTo(Excursion::class);
    }
}
