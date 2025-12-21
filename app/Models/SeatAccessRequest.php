<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MoonShine\Laravel\Models\MoonshineUser;

class SeatAccessRequest extends Model
{
    protected $fillable = [
        'excursion_id',
        'user_id',
        'excursion_date',
        'seat_number',
        'status',
        'reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'excursion_date' => 'date',
        'seat_number' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function excursion(): BelongsTo
    {
        return $this->belongsTo(Excursion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(MoonshineUser::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(MoonshineUser::class, 'reviewed_by');
    }
}
