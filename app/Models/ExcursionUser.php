<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MoonShine\Laravel\Models\MoonshineUser;

class ExcursionUser extends Model
{
    protected $table = 'excursion_user';

    protected $fillable = [
        'excursion_id',
        'user_id',
        'role_in_excursion',
        'excursion_date',
        'time',
    ];

    protected $casts = [
        'excursion_date' => 'date',
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


