<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Models\ExcursionPrice;
use App\Models\ScheduleTemplate;
use Illuminate\Database\Eloquent\Builder;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Select;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

#[Icon('currency-dollar')]
#[Group('Экскурсии', 'excursions')]
#[Order(2)]
/**
 * @extends ModelResource<ExcursionPrice>
 */
class ExcursionPriceResource extends ModelResource
{
    protected string $model = ExcursionPrice::class;

    protected string $title = 'Цены экскурсий';
    
    protected string $column = 'passenger_type';
    
    protected array $with = ['excursion'];
    
    protected bool $createInModal = true;
    
    protected bool $editInModal = true;
    
    public function getTitle(): string
    {
        return 'Цены экскурсий';
    }
    
    /**
     * Показываем только цены экскурсий из расписания (через прямую связь)
     */
    public function query(): Builder
    {
        return parent::query()
            ->whereHas('excursion.scheduleTemplate');
    }
    
    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            
            Text::make('Экскурсия', 'excursion.title'),
                
            Select::make('Тип пассажира', 'passenger_type')
                ->options([
                    'adult' => 'Взрослый',
                    'child' => 'Детский',
                    'senior' => 'Пенсионер',
                    'disabled' => 'Инвалид',
                    'special' => 'Спеццена',
                ])
                ->sortable(),
            
            Text::make('Основная цена', 'price')
                ->sortable(),
                
            Text::make('Без входа', 'price_without_entry')
                ->sortable(),
                
            Text::make('Со входом', 'price_with_entry')
                ->sortable(),
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
                    
                Select::make('Тип пассажира', 'passenger_type')
                    ->options([
                        'adult' => 'Взрослый',
                        'child' => 'Детский',
                        'senior' => 'Пенсионер',
                        'disabled' => 'Инвалид',
                    ])
                    ->required(),
                    
                Text::make('Цена без входа', 'price_without_entry')
                    ->required(),
                    
                Text::make('Цена со входом', 'price_with_entry')
                    ->required(),
                    
                Text::make('Комиссия продавца (%)', 'seller_commission_percent')
                    ->required()
                    ->default(10),
                    
                Text::make('Комиссия партнера (%)', 'partner_commission_percent')
                    ->required()
                    ->default(10),
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
            
            Text::make('Экскурсия', 'excursion.title'),
            
            Select::make('Тип пассажира', 'passenger_type')
                ->options([
                    'adult' => 'Взрослый',
                    'child' => 'Детский',
                    'senior' => 'Пенсионер',
                    'disabled' => 'Инвалид',
                    'special' => 'Спеццена',
                ]),
            
            Text::make('Основная цена', 'price'),
                
            Text::make('Цена без входа', 'price_without_entry'),
            
            Text::make('Цена со входом', 'price_with_entry'),
            
            Text::make('Комиссия продавца (%)', 'seller_commission_percent'),
            
            Text::make('Комиссия партнера (%)', 'partner_commission_percent'),
        ];
    }

    /**
     * @param ExcursionPrice $item
     *
     * @return array<string, string[]|string>
     */
    protected function rules(mixed $item): array
    {
        return [
            'excursion_id' => 'required|exists:excursions,id',
            'passenger_type' => 'required|in:adult,child,senior,disabled,special',
            'price' => 'nullable|numeric|min:0',
            'price_without_entry' => 'required|numeric|min:0',
            'price_with_entry' => 'required|numeric|min:0',
            'seller_commission_percent' => 'required|numeric|min:0|max:100',
            'partner_commission_percent' => 'required|numeric|min:0|max:100',
        ];
    }
    
    protected function search(): array
    {
        return [
            'passenger_type',
        ];
    }
}

