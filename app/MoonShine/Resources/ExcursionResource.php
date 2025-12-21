<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Models\Excursion;
use App\Models\BusSeat;
use App\MoonShine\Resources\ExcursionPriceResource;
use App\MoonShine\Resources\BusSeatResource;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Checkbox;
use MoonShine\UI\Fields\Number;
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
    
    protected array $with = ['prices'];
    
    protected array $savedPriceData = [];
    
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
                
            Text::make('Статус', 'is_active'),
                
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
                            
                        Textarea::make('Описание', 'description'),
                            
                        Flex::make([
                            Date::make('Дата и время', 'date_time')
                                ->required(),
                                
                            Text::make('Максимум мест', 'max_seats')
                                ->required(),
                        ]),
                        
                        Checkbox::make('Активна', 'is_active')
                            ->default(true),
                    ]),
                ])->icon('calendar'),
                
                Tab::make('Места в автобусе', [
                    HasMany::make('Места', 'busSeats', resource: BusSeatResource::class)
                        ->creatable(),
                ]),
                
                Tab::make('Цены', [
                    Box::make('Взрослый', [
                        Flex::make([
                            Number::make('Без входа', 'price_adult_without_entry')
                                ->step(0.01)
                                ->min(0),
                            Number::make('Со входом', 'price_adult_with_entry')
                                ->step(0.01)
                                ->min(0),
                        ]),
                        Flex::make([
                            Number::make('Комиссия продавца (%)', 'price_adult_seller_commission')
                                ->step(0.01)
                                ->min(0)
                                ->max(100),
                            Number::make('Комиссия партнера (%)', 'price_adult_partner_commission')
                                ->step(0.01)
                                ->min(0)
                                ->max(100),
                        ]),
                    ]),
                    
                    Box::make('Детский', [
                        Flex::make([
                            Number::make('Без входа', 'price_child_without_entry')
                                ->step(0.01)
                                ->min(0),
                            Number::make('Со входом', 'price_child_with_entry')
                                ->step(0.01)
                                ->min(0),
                        ]),
                        Flex::make([
                            Number::make('Комиссия продавца (%)', 'price_child_seller_commission')
                                ->step(0.01)
                                ->min(0)
                                ->max(100),
                            Number::make('Комиссия партнера (%)', 'price_child_partner_commission')
                                ->step(0.01)
                                ->min(0)
                                ->max(100),
                        ]),
                    ]),
                    
                    Box::make('Пенсионер', [
                        Flex::make([
                            Number::make('Без входа', 'price_senior_without_entry')
                                ->step(0.01)
                                ->min(0),
                            Number::make('Со входом', 'price_senior_with_entry')
                                ->step(0.01)
                                ->min(0),
                        ]),
                        Flex::make([
                            Number::make('Комиссия продавца (%)', 'price_senior_seller_commission')
                                ->step(0.01)
                                ->min(0)
                                ->max(100),
                            Number::make('Комиссия партнера (%)', 'price_senior_partner_commission')
                                ->step(0.01)
                                ->min(0)
                                ->max(100),
                        ]),
                    ]),
                    
                    Box::make('Инвалид', [
                        Flex::make([
                            Number::make('Без входа', 'price_disabled_without_entry')
                                ->step(0.01)
                                ->min(0),
                            Number::make('Со входом', 'price_disabled_with_entry')
                                ->step(0.01)
                                ->min(0),
                        ]),
                        Flex::make([
                            Number::make('Комиссия продавца (%)', 'price_disabled_seller_commission')
                                ->step(0.01)
                                ->min(0)
                                ->max(100),
                            Number::make('Комиссия партнера (%)', 'price_disabled_partner_commission')
                                ->step(0.01)
                                ->min(0)
                                ->max(100),
                        ]),
                    ]),
                ])->icon('currency-dollar'),
                
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
                
            Text::make('Максимум мест', 'max_seats'),
            
            Text::make('Статус', 'is_active'),
                
            Text::make('Забронировано мест', 'booked_seats_count'),
            
            Text::make('Свободно мест', 'available_seats_count'),
            
            Text::make('Цены по категориям', 'formatted_prices'),
            
            HasMany::make('Цены', 'prices', resource: \App\MoonShine\Resources\ExcursionPriceResource::class),
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
            'price' => 'nullable|numeric|min:0',
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

    /**
     * Вызывается перед отображением формы
     */
    protected function onBeforeFormRender(Model $item): void
    {
        /** @var Excursion $item */
        if ($item->exists) {
            // Загружаем цены для доступа к accessors
            $item->load('prices');
            
            // Создаем цены, если их еще нет
            if ($item->prices()->count() === 0) {
                $item->createDefaultPrices();
                $item->load('prices');
            }
            
            // Если в таблице excursions есть значения цен, используем их
            // Иначе используем значения из excursion_prices через accessors
            $types = ['adult', 'child', 'senior', 'disabled', 'special'];
            foreach ($types as $type) {
                $withoutEntryField = "price_{$type}_without_entry";
                $withEntryField = "price_{$type}_with_entry";
                $sellerCommissionField = "price_{$type}_seller_commission";
                $partnerCommissionField = "price_{$type}_partner_commission";
                
                // Если в excursions нет значений, берем из excursion_prices
                if ($item->$withoutEntryField === null) {
                    $price = $item->prices->firstWhere('passenger_type', $type);
                    if ($price) {
                        $item->$withoutEntryField = $price->price_without_entry;
                        $item->$withEntryField = $price->price_with_entry;
                        $item->$sellerCommissionField = $price->seller_commission_percent;
                        $item->$partnerCommissionField = $price->partner_commission_percent;
                    }
                }
            }
        }
    }

    /**
     * Вызывается перед сохранением экскурсии
     */
    protected function onBeforeSave(Model $item): void
    {
        /** @var Excursion $item */
        $request = request();
        
        // Устанавливаем price в null, если оно не передано (поле теперь nullable)
        if (!$request->has('price') || $request->input('price') === '' || $request->input('price') === null) {
            $item->price = null;
        }
        
        // Сохраняем значения цен для сохранения в excursion_prices и excursions
        $this->savedPriceData = [];
        $types = ['adult', 'child', 'senior', 'disabled', 'special'];
        
        foreach ($types as $type) {
            // Получаем значения из запроса или из атрибутов модели
            // MoonShine может установить значения напрямую в модель
            $withoutEntry = $request->input("price_{$type}_without_entry") 
                ?? $item->getAttribute("price_{$type}_without_entry")
                ?? null;
            $withEntry = $request->input("price_{$type}_with_entry")
                ?? $item->getAttribute("price_{$type}_with_entry")
                ?? null;
            $sellerCommission = $request->input("price_{$type}_seller_commission")
                ?? $item->getAttribute("price_{$type}_seller_commission")
                ?? 10;
            $partnerCommission = $request->input("price_{$type}_partner_commission")
                ?? $item->getAttribute("price_{$type}_partner_commission")
                ?? 10;
            
            // Преобразуем значения: пустые строки и null в null для цен
            // Для комиссий: пустые строки и null в 10 (по умолчанию)
            // Важно: 0 - это валидное значение, не преобразуем его в null
            $withoutEntryValue = ($withoutEntry === '' || $withoutEntry === null) 
                ? null 
                : (is_numeric($withoutEntry) ? (float)$withoutEntry : null);
            
            $withEntryValue = ($withEntry === '' || $withEntry === null) 
                ? null 
                : (is_numeric($withEntry) ? (float)$withEntry : null);
            
            $sellerCommissionValue = ($sellerCommission === '' || $sellerCommission === null) 
                ? 10 
                : (is_numeric($sellerCommission) ? (float)$sellerCommission : 10);
            
            $partnerCommissionValue = ($partnerCommission === '' || $partnerCommission === null) 
                ? 10 
                : (is_numeric($partnerCommission) ? (float)$partnerCommission : 10);
            
            // Сохраняем для excursion_prices
            $this->savedPriceData[$type] = [
                'price_without_entry' => $withoutEntryValue,
                'price_with_entry' => $withEntryValue,
                'seller_commission_percent' => $sellerCommissionValue,
                'partner_commission_percent' => $partnerCommissionValue,
            ];
            
            // Также сохраняем в атрибуты модели для сохранения в excursions
            // Устанавливаем значения напрямую в attributes, чтобы обойти accessors
            $item->setAttribute("price_{$type}_without_entry", $withoutEntryValue);
            $item->setAttribute("price_{$type}_with_entry", $withEntryValue);
            $item->setAttribute("price_{$type}_seller_commission", $sellerCommissionValue);
            $item->setAttribute("price_{$type}_partner_commission", $partnerCommissionValue);
        }
    }

    /**
     * Вызывается после сохранения экскурсии
     */
    protected function onAfterSave(Model $item): void
    {
        /** @var Excursion $item */
        
        // Сохраняем цены из формы в excursion_prices (используем сохраненные данные из onBeforeSave)
        // Также синхронизируем данные из excursions в excursion_prices
        if (isset($this->savedPriceData) && !empty($this->savedPriceData)) {
            $hasAnyPriceData = false;
            
            foreach ($this->savedPriceData as $type => $priceData) {
                // Всегда сохраняем, даже если значения null (чтобы синхронизировать с excursions)
                // Явно устанавливаем price => null, так как это поле не используется
                $priceData['price'] = null;
                $item->prices()->updateOrCreate(
                    ['passenger_type' => $type],
                    $priceData
                );
                
                // Проверяем, есть ли хотя бы одно непустое значение
                if ($priceData['price_without_entry'] !== null || 
                    $priceData['price_with_entry'] !== null || 
                    ($priceData['seller_commission_percent'] ?? null) !== null ||
                    ($priceData['partner_commission_percent'] ?? null) !== null) {
                    $hasAnyPriceData = true;
                }
            }
            
            // Если цены были заполнены в форме, не создаем дефолтные
            if ($hasAnyPriceData) {
                $item->load('prices');
                return;
            }
        }
        
        // Если savedPriceData пуст, но в excursions есть значения, синхронизируем их
        $types = ['adult', 'child', 'senior', 'disabled', 'special'];
        $hasValuesInExcursions = false;
        foreach ($types as $type) {
            $withoutEntry = $item->getAttribute("price_{$type}_without_entry");
            $withEntry = $item->getAttribute("price_{$type}_with_entry");
            $sellerCommission = $item->getAttribute("price_{$type}_seller_commission") ?? 10;
            $partnerCommission = $item->getAttribute("price_{$type}_partner_commission") ?? 10;
            
            if ($withoutEntry !== null || $withEntry !== null) {
                $hasValuesInExcursions = true;
                $item->prices()->updateOrCreate(
                    ['passenger_type' => $type],
                    [
                        'price' => null,
                        'price_without_entry' => $withoutEntry,
                        'price_with_entry' => $withEntry,
                        'seller_commission_percent' => $sellerCommission,
                        'partner_commission_percent' => $partnerCommission,
                    ]
                );
            }
        }
        
        if ($hasValuesInExcursions) {
            $item->load('prices');
            return;
        }
        
        // Создаем цены по умолчанию, если их еще нет и они не были заполнены в форме
        if ($item->prices()->count() === 0) {
            $item->createDefaultPrices();
            $item->load('prices');
        }
    }
}
