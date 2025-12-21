<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Models\ScheduleDay;
use App\Models\ScheduleTemplate;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Select;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;

/**
 * @extends ModelResource<ScheduleDay>
 */
class ScheduleDayResource extends ModelResource
{
    protected string $model = ScheduleDay::class;

    protected string $title = 'Дни недели';
    
    protected string $column = 'weekday';
    
    protected bool $createInModal = true;
    
    protected bool $editInModal = true;
    
    public function getTitle(): string
    {
        return 'Дни недели';
    }
    
    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            
            BelongsTo::make('Шаблон расписания', 'scheduleTemplate', resource: ScheduleResource::class)
                ->sortable(),
                
            Text::make('День недели', 'weekday')->sortable(),
            
            Text::make('Время', 'time')->sortable(),
        ];
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                
                BelongsTo::make('Шаблон расписания', 'scheduleTemplate', resource: ScheduleResource::class)
                    ->required(),
                    
                Select::make('День недели', 'weekday')
                    ->options([
                        1 => 'Понедельник',
                        2 => 'Вторник',
                        3 => 'Среда',
                        4 => 'Четверг',
                        5 => 'Пятница',
                        6 => 'Суббота',
                        7 => 'Воскресенье',
                    ])
                    ->required(),
                    
                Text::make('Время', 'time')
                    ->setAttribute('type', 'time')
                    ->required(),
            ])
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            
            BelongsTo::make('Шаблон расписания', 'scheduleTemplate', resource: ScheduleResource::class),
            
            Text::make('День недели', 'weekday'),
            
            Text::make('Время', 'time'),
        ];
    }

    /**
     * @param ScheduleDay $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'schedule_template_id' => 'required|exists:schedule_templates,id',
            'weekday' => 'required|integer|min:1|max:7',
            'time' => 'required|date_format:H:i',
        ];
    }
}
