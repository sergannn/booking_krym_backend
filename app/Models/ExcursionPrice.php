<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcursionPrice extends Model
{
    protected $fillable = [
        'excursion_id',
        'passenger_type',
        'price',
        'seller_commission_percent',
        'partner_commission_percent',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'seller_commission_percent' => 'decimal:2',
        'partner_commission_percent' => 'decimal:2',
    ];

    public function excursion()
    {
        return $this->belongsTo(Excursion::class);
    }
}
