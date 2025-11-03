<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Models\Booking;
use App\Models\Excursion;
use App\Models\Stop;
use MoonShine\Laravel\Models\MoonshineUser;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Date;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Support\Enums\Color;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

#[Icon('ticket')]
#[Group('Бронирования', 'bookings')]
#[Order(3)]
/**
 * @extends ModelResource<Booking>
 */
class BookingResource extends ModelResource
{
    protected string $model = Booking::class;

    protected string $title = 'Бронирования';
    
    protected string $column = 'customer_name';
    
    protected array $with = ['excursion', 'bookedBy', 'stop', 'busSeat'];
    
    protected bool $createInModal = true;
    
    protected bool $editInModal = true;
    
    public function getTitle(): string
    {
        return 'Бронирования';
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
                
            Text::make('Клиент', 'customer_name')
                ->sortable(),
                
            Text::make('Телефон', 'customer_phone'),
                
            Text::make('Тип пассажира', 'passenger_type'),
                
            Text::make('Цена', 'price')
                ->sortable(),
                
            BelongsTo::make('Остановка', 'stop', resource: StopResource::class),
                
            BelongsTo::make('Продавец', 'bookedBy', resource: MoonShineUserResource::class)
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
                    
                BelongsTo::make('Место в автобусе', 'busSeat', resource: BusSeatResource::class)
                    ->required(),
                    
                Text::make('Имя клиента', 'customer_name')
                    ->required(),
                    
                Text::make('Телефон клиента', 'customer_phone')
                    ->required(),
                    
                Select::make('Тип пассажира', 'passenger_type')
                    ->options([
                        'adult' => 'Взрослый',
                        'child' => 'Ребенок',
                        'senior' => 'Пенсионер',
                        'disabled' => 'Инвалид',
                    ])
                    ->default('adult'),
                    
                Text::make('Цена', 'price')
                    ->required(),
                    
                BelongsTo::make('Остановка', 'stop', resource: StopResource::class)
                    ->required(),
                    
                BelongsTo::make('Продавец', 'bookedBy', resource: MoonShineUserResource::class)
                    ->required(),
                    
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
            
            BelongsTo::make('Место в автобусе', 'busSeat', resource: BusSeatResource::class),
            
            Text::make('Имя клиента', 'customer_name'),
            
            Text::make('Телефон клиента', 'customer_phone'),
            
            Text::make('Тип пассажира', 'passenger_type'),
            
            Text::make('Цена', 'price'),
            
            BelongsTo::make('Остановка', 'stop', resource: StopResource::class),
            
            BelongsTo::make('Продавец', 'bookedBy', resource: MoonShineUserResource::class),
            
            Date::make('Дата бронирования', 'booked_at')
                ->format('d.m.Y H:i'),
        ];
    }

    /**
     * @param Booking $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'excursion_id' => 'required|exists:excursions,id',
            'bus_seat_id' => 'required|exists:bus_seats,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'passenger_type' => 'required|in:adult,child,senior,disabled',
            'price' => 'required|numeric|min:0',
            'stop_id' => 'required|exists:stops,id',
            'booked_by' => 'required|exists:moonshine_users,id',
            'booked_at' => 'nullable|date',
        ];
    }
    
    protected function search(): array
    {
        return [
            'customer_name',
            'customer_phone',
        ];
    }
    
    protected function filters(): iterable
    {
        return [
            BelongsTo::make('Экскурсия', 'excursion', resource: ExcursionResource::class),
            
            BelongsTo::make('Остановка', 'stop', resource: StopResource::class),
            
            Select::make('Тип пассажира', 'passenger_type')
                ->options([
                    'adult' => 'Взрослый',
                    'child' => 'Ребенок',
                    'senior' => 'Пенсионер',
                    'disabled' => 'Инвалид',
                ]),
                
            BelongsTo::make('Продавец', 'bookedBy', resource: MoonShineUserResource::class),
        ];
    }
}
