<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Models\BusSeat;
use App\Models\Excursion;
use MoonShine\Laravel\Models\MoonshineUser;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Date;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Support\Enums\Color;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

// #[Icon('cube')]
#[Group('Экскурсии', 'excursions')]
#[Order(2)]
/**
 * @extends ModelResource<BusSeat>
 */
class BusSeatResource extends ModelResource
{
    protected string $model = BusSeat::class;

    protected string $title = 'Места в автобусе';
    
    protected string $column = 'seat_number';
    
    protected array $with = ['excursion', 'bookedBy'];
    
    protected bool $createInModal = true;
    
    protected bool $editInModal = true;
    
    public function getTitle(): string
    {
        return 'Места в автобусе';
    }
    
    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            
            BelongsTo::make('Экскурсия', 'excursion', resource: ExcursionResource::class)
                ->sortable(),
                
            Text::make('Номер места', 'seat_number')
                ->sortable(),
                
            Text::make('Статус', 'status')
,
                
            BelongsTo::make('Забронировал', 'bookedBy', resource: MoonShineUserResource::class)
                ->nullable(),
                
            Date::make('Дата бронирования', 'booked_at'),
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
                
                BelongsTo::make('Экскурсия', 'excursion', resource: ExcursionResource::class)
                    ->required(),
                    
                Text::make('Номер места', 'seat_number')
                    ->required(),
                    
                Select::make('Статус', 'status')
                    ->options([
                        'available' => 'Свободно',
                        'booked' => 'Забронировано',
                        'reserved' => 'Зарезервировано',
                    ])
                    ->default('available'),
                    
                BelongsTo::make('Забронировал', 'bookedBy', resource: MoonShineUserResource::class)
                    ->nullable(),
                    
                Date::make('Дата бронирования', 'booked_at')
                    ->nullable(),
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
            
            BelongsTo::make('Экскурсия', 'excursion', resource: ExcursionResource::class),
            
            Text::make('Номер места', 'seat_number'),
            
            Text::make('Статус', 'status')
,
                
            BelongsTo::make('Забронировал', 'bookedBy', resource: MoonShineUserResource::class),
            
            Date::make('Дата бронирования', 'booked_at')
                ->format('d.m.Y H:i'),
        ];
    }

    /**
     * @param BusSeat $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'excursion_id' => 'required|exists:excursions,id',
            'seat_number' => 'required|integer|min:1|max:100',
            'status' => 'required|in:available,booked,reserved',
            'booked_by' => 'nullable|exists:moonshine_users,id',
            'booked_at' => 'nullable|date',
        ];
    }
    
    protected function search(): array
    {
        return [
            'seat_number',
        ];
    }
    
    protected function filters(): iterable
    {
        return [
            BelongsTo::make('Экскурсия', 'excursion', resource: ExcursionResource::class),
            
            Select::make('Статус', 'status')
                ->options([
                    'available' => 'Свободно',
                    'booked' => 'Забронировано',
                    'reserved' => 'Зарезервировано',
                ]),
        ];
    }
}
