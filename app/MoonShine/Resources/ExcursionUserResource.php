<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\ExcursionUser;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Select;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;

class ExcursionUserResource extends ModelResource
{
    protected string $model = ExcursionUser::class;

    protected string $title = 'Назначения персонала';

    protected string $column = 'id';

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Экскурсия', 'excursion', resource: ExcursionResource::class)
                ->sortable(),
            BelongsTo::make('Сотрудник', 'user', resource: MoonShineUserResource::class)
                ->sortable(),
            Select::make('Роль', 'role_in_excursion')
                ->options([
                    'driver' => 'Водитель',
                    'guide' => 'Экскурсовод',
                ]),
            Date::make('Дата экскурсии', 'excursion_date')
                ->format('d.m.Y')
                ->sortable(),
            Text::make('Время', 'time'),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                BelongsTo::make('Экскурсия', 'excursion', resource: ExcursionResource::class)
                    ->required(),
                BelongsTo::make('Сотрудник', 'user', resource: MoonShineUserResource::class)
                    ->required(),
                Select::make('Роль', 'role_in_excursion')
                    ->options([
                        'driver' => 'Водитель',
                        'guide' => 'Экскурсовод',
                    ])
                    ->required(),
                Date::make('Дата экскурсии', 'excursion_date')
                    ->format('d.m.Y'),
                Text::make('Время', 'time')
                    ->placeholder('HH:MM'),
            ])
        ];
    }

    protected function detailFields(): iterable
    {
        return $this->indexFields();
    }

    protected function rules(mixed $item): array
    {
        return [
            'excursion_id' => 'required|exists:excursions,id',
            'user_id' => 'required|exists:moonshine_users,id',
            'role_in_excursion' => 'required|in:driver,guide',
            'excursion_date' => 'nullable|date',
            'time' => 'nullable|string|max:5',
        ];
    }
}


