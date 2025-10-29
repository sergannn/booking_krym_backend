<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Models\Stop;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Support\Enums\Color;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

#[Icon('map-pin')]
#[Group('Справочники', 'reference')]
#[Order(4)]
/**
 * @extends ModelResource<Stop>
 */
class StopResource extends ModelResource
{
    protected string $model = Stop::class;

    protected string $title = 'Остановки';
    
    protected string $column = 'name';
    
    protected array $with = ['bookings'];
    
    public function getTitle(): string
    {
        return 'Остановки';
    }
    
    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            
            Text::make('Название', 'name')
                ->sortable(),
                
            Text::make('Порядок', 'order')
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
                
                Text::make('Название', 'name')
                    ->required(),
                    
                Text::make('Порядок', 'order')
                    ->required(),
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
            
            Text::make('Название', 'name'),
            
            Text::make('Порядок', 'order'),
            
            HasMany::make('Бронирования', 'bookings', resource: BookingResource::class),
        ];
    }

    /**
     * @param Stop $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'name' => 'required|string|max:255',
            'order' => 'required|integer|min:1',
        ];
    }
    
    protected function search(): array
    {
        return [
            'name',
        ];
    }
    
    protected function filters(): iterable
    {
        return [
            Text::make('Порядок', 'order'),
        ];
    }
}
