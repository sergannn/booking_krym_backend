<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleDay extends Model
{
    protected $fillable = [
        'schedule_template_id',
        'weekday',
        'time',
    ];

    protected $casts = [
        'weekday' => 'integer',
    ];

    public function scheduleTemplate(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class);
    }
}
