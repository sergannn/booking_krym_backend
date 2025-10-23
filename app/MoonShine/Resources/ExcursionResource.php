<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Models\Excursion;
use App\Models\BusSeat;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\SwitchBoolean;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Support\Enums\Color;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

#[Icon('calendar')]
#[Group('Экскурсии', 'excursions')]
#[Order(1)]
/**
 * @extends ModelResource<Excursion>
 */
class ExcursionResource extends ModelResource
{
    protected string $model = Excursion::class;

    protected string $title = 'Экскурсии';
    
    protected string $column = 'title';
    
    protected array $with = ['busSeats'];
    
    public function getTitle(): string
    {
        return 'Экскурсии';
    }
    
    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            
            Text::make('Название', 'title')
                ->sortable(),
                
            Date::make('Дата и время', 'date_time')
                ->format('d.m.Y H:i')
                ->sortable(),
                
            Text::make('Цена', 'price')
                ->sortable(),
                
            Text::make('Статус', 'is_active')
,
                
            Text::make('Забронировано мест', 'booked_seats_count'),
        ];
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [
            Tabs::make([
                Tab::make('Основная информация', [
                    Box::make([
                        ID::make(),
                        
                        Text::make('Название', 'title')
                            ->required(),
                            
                        Textarea::make('Описание', 'description')
                            ->rows(4),
                            
                        Flex::make([
                            Date::make('Дата и время', 'date_time')
                                ->required(),
                                
                            Text::make('Цена', 'price')
                                ->required(),
                                
                            Text::make('Максимум мест', 'max_seats')
                                ->required(),
                        ]),
                        
                        SwitchBoolean::make('Активна', 'is_active')
                            ->default(true),
                    ]),
                ])->icon('calendar'),
                
                Tab::make('Места в автобусе', [
                    HasMany::make('Места', 'busSeats', resource: BusSeatResource::class)
                        ->creatable()
                        ->showOnExport()
                        ->hideOnIndex(),
                ]),
            ]),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            
            Text::make('Название', 'title'),
            
            Textarea::make('Описание', 'description'),
            
            Date::make('Дата и время', 'date_time')
                ->format('d.m.Y H:i'),
                
            Text::make('Цена', 'price'),
                
            Text::make('Максимум мест', 'max_seats'),
            
            Text::make('Статус', 'is_active')
,
                
            Text::make('Забронировано мест', 'booked_seats_count'),
            
            Text::make('Свободно мест', 'available_seats_count'),
        ];
    }

    /**
     * @param Excursion $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date_time' => 'required|date|after:now',
            'price' => 'required|numeric|min:0',
            'max_seats' => 'required|integer|min:1|max:100',
            'is_active' => 'boolean',
        ];
    }
    
    protected function search(): array
    {
        return [
            'title',
            'description',
        ];
    }
}
