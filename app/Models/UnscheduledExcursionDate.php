<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnscheduledExcursionDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'excursion_id',
        'date_time',
        'deleted_at',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Scope для получения только неудаленных записей
     */
    public function scopeNotDeleted($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope для получения только удаленных записей
     */
    public function scopeDeleted($query)
    {
        return $query->whereNotNull('deleted_at');
    }

    /**
     * Пометить запись как удаленную
     */
    public function markAsDeleted()
    {
        $this->deleted_at = now();
        $this->save();
    }

    public function excursion(): BelongsTo
    {
        return $this->belongsTo(Excursion::class);
    }
}
