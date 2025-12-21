<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use App\Models\ScheduleTemplate;
use Illuminate\Database\Eloquent\Model;

#[Icon('calendar-days')]
#[Group('Экскурсии', 'excursions')]
#[Order(0)]
/**
 * @extends ModelResource<ScheduleTemplate>
 */
class ScheduleResource extends ModelResource
{
    protected string $model = ScheduleTemplate::class;
    
    protected string $title = 'Расписание экскурсий';
    
    protected string $column = 'title';
    
    protected array $with = ['scheduleDays'];
    
    public function getTitle(): string
    {
        return 'Расписание экскурсий';
    }
    
    /**
     * Получаем названия дней недели
     */
    private function getWeekdayLabels(): array
    {
        return [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];
    }
    
    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            Text::make('Название', 'title')
                ->sortable(),
                
            Text::make('Пн', 'weekday_1'),
            Text::make('Вт', 'weekday_2'),
            Text::make('Ср', 'weekday_3'),
            Text::make('Чт', 'weekday_4'),
            Text::make('Пт', 'weekday_5'),
            Text::make('Сб', 'weekday_6'),
            Text::make('Вс', 'weekday_7'),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            Text::make('Название', 'title'),
            
            HasMany::make('Дни недели', 'scheduleDays', resource: ScheduleDayResource::class),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                Text::make('Название', 'title'),
            ]),
            
            HasMany::make('Дни недели', 'scheduleDays', resource: ScheduleDayResource::class)
                ->creatable(),
        ];
    }
    
    protected function search(): array
    {
        return ['title', 'description'];
    }
}
