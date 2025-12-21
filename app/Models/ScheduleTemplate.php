<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleTemplate extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'id',
        'title',
        'description',
    ];
    
    protected $appends = [
        'weekday_1',
        'weekday_2',
        'weekday_3',
        'weekday_4',
        'weekday_5',
        'weekday_6',
        'weekday_7',
    ];
    
    public function scheduleDays(): HasMany
    {
        return $this->hasMany(ScheduleDay::class);
    }
    
    public function excursions(): HasMany
    {
        return $this->hasMany(Excursion::class);
    }

    private function getWeekdayTime(int $weekday): string
    {
        if (!$this->relationLoaded('scheduleDays')) {
            $this->load('scheduleDays');
        }
        $scheduleDay = $this->scheduleDays->firstWhere('weekday', $weekday);
        if ($scheduleDay && $scheduleDay->time) {
            return is_string($scheduleDay->time)
                ? $scheduleDay->time
                : $scheduleDay->time->format('H:i');
        }
        return '';
    }

    public function getWeekday1Attribute(): string
    {
        return $this->getWeekdayTime(1);
    }

    public function getWeekday2Attribute(): string
    {
        return $this->getWeekdayTime(2);
    }

    public function getWeekday3Attribute(): string
    {
        return $this->getWeekdayTime(3);
    }

    public function getWeekday4Attribute(): string
    {
        return $this->getWeekdayTime(4);
    }

    public function getWeekday5Attribute(): string
    {
        return $this->getWeekdayTime(5);
    }

    public function getWeekday6Attribute(): string
    {
        return $this->getWeekdayTime(6);
    }

    public function getWeekday7Attribute(): string
    {
        return $this->getWeekdayTime(7);
    }
}



