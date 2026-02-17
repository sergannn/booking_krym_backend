<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bus extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'model',
        'capacity',
        'license_plate',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
    ];

    /**
     * Водители, привязанные к этому автобусу
     */
    public function drivers(): HasMany
    {
        return $this->hasMany(MoonshineUserExtension::class, 'bus_id');
    }
}
