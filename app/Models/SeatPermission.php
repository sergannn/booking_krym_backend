<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MoonShine\Laravel\Models\MoonshineUser;

class SeatPermission extends Model
{
    protected $fillable = [
        'excursion_id',
        'user_id',
        'excursion_date',
        'seat_number',
    ];

    protected $casts = [
        'excursion_date' => 'date',
        'seat_number' => 'integer',
    ];

    public function excursion(): BelongsTo
    {
        return $this->belongsTo(Excursion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(MoonshineUser::class, 'user_id');
    }
}
