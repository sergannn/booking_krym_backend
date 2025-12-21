<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffPrice extends Model
{
    protected $fillable = [
        'excursion_id',
        'staff_type',
        'min_passengers',
        'max_passengers',
        'price',
    ];

    protected $casts = [
        'min_passengers' => 'integer',
        'max_passengers' => 'integer',
        'price' => 'decimal:2',
    ];

    public function excursion()
    {
        return $this->belongsTo(Excursion::class);
    }

    /**
     * Проверяет, попадает ли количество пассажиров в диапазон
     */
    public function matchesPassengerCount(int $passengerCount): bool
    {
        if ($passengerCount < $this->min_passengers) {
            return false;
        }
        
        if ($this->max_passengers !== null && $passengerCount > $this->max_passengers) {
            return false;
        }
        
        return true;
    }
}


