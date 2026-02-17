<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancelledExcursionDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'excursion_id',
        'date_time',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    /**
     * Связь с экскурсией
     */
    public function excursion(): BelongsTo
    {
        return $this->belongsTo(Excursion::class);
    }
}
