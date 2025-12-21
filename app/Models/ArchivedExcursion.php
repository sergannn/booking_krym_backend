<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivedExcursion extends Model
{
    protected $fillable = [
        'original_excursion_id',
        'title',
        'description',
        'date_time',
        'is_active',
        'payload',
        'archived_reason',
        'archived_at',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'is_active' => 'boolean',
        'payload' => 'array',
        'archived_at' => 'datetime',
    ];
}






