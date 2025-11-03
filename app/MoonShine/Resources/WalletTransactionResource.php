<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Models\WalletTransaction;
use App\Models\Booking;
use MoonShine\Laravel\Models\MoonshineUser;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Date;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Support\Enums\Color;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

#[Icon('currency-dollar')]
#[Group('Финансы', 'finance')]
#[Order(5)]
/**
 * @extends ModelResource<WalletTransaction>
 */
class WalletTransactionResource extends ModelResource
{
    protected string $model = WalletTransaction::class;

    protected string $title = 'Транзакции кошелька';
    
    protected string $column = 'description';
    
    protected array $with = ['user', 'booking'];
    
    protected bool $createInModal = true;
    
    protected bool $editInModal = true;
    
    public function getTitle(): string
    {
        return 'Транзакции кошелька';
    }
    
    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            
            BelongsTo::make('Пользователь', 'user', resource: MoonShineUserResource::class)
                ->sortable(),
                
            Text::make('Сумма', 'amount')
                ->sortable(),
                
            Text::make('Описание', 'description')
                ->sortable(),
                
            BelongsTo::make('Бронирование', 'booking', resource: BookingResource::class)
                ->nullable(),
                
            Date::make('Дата', 'created_at')
                ->format('d.m.Y H:i'),
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
                
                BelongsTo::make('Пользователь', 'user', resource: MoonShineUserResource::class)
                    ->required(),
                    
                Text::make('Сумма', 'amount')
                    ->required(),
                    
                Text::make('Описание', 'description')
                    ->required(),
                    
                BelongsTo::make('Бронирование', 'booking', resource: BookingResource::class)
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
            
            BelongsTo::make('Пользователь', 'user', resource: MoonShineUserResource::class),
            
            Text::make('Сумма', 'amount'),
            
            Text::make('Описание', 'description'),
            
            BelongsTo::make('Бронирование', 'booking', resource: BookingResource::class),
            
            Date::make('Дата', 'created_at')
                ->format('d.m.Y H:i'),
        ];
    }

    /**
     * @param WalletTransaction $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'user_id' => 'required|exists:moonshine_users,id',
            'amount' => 'required|numeric',
            'description' => 'required|string|max:255',
            'booking_id' => 'nullable|exists:bookings,id',
        ];
    }
    
    protected function search(): array
    {
        return [
            'description',
        ];
    }
    
    protected function filters(): iterable
    {
        return [
            BelongsTo::make('Пользователь', 'user', resource: MoonShineUserResource::class),
            
            BelongsTo::make('Бронирование', 'booking', resource: BookingResource::class),
        ];
    }
}


