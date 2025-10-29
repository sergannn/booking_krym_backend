<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Database\Eloquent\Model;
use MoonShine\Laravel\Models\MoonshineUser;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\Password;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Models\MoonshineUserRole;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

#[Icon('users')]
#[Group('Пользователи', 'users')]
#[Order(0)]
/**
 * @extends ModelResource<MoonshineUser>
 */
class UserResource extends ModelResource
{
    protected string $model = MoonshineUser::class;

    protected string $title = 'Пользователи';
    
    protected array $with = ['moonshineUserRole'];
    
    public function getTitle(): string
    {
        return 'Пользователи';
    }
    
    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Имя', 'name')->sortable(),
            Email::make('Email', 'email')->sortable(),
            BelongsTo::make('Роль', 'moonshineUserRole', resource: MoonShineUserRoleResource::class)
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
                Text::make('Имя', 'name')->required(),
                Email::make('Email', 'email')->required(),
                Password::make('Пароль', 'password')
                    ->required()
                    ->eye(),
                BelongsTo::make('Роль', 'moonshineUserRole', resource: MoonShineUserRoleResource::class)
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
        ];
    }

    /**
     * @param User $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:moonshine_users,email,' . ($item?->id ?? 'NULL'),
            'password' => 'required|string|min:6',
            'moonshine_user_role_id' => 'required|exists:moonshine_user_roles,id',
        ];
    }

    protected function onCreating(Model $item): void
    {
        if ($item->password) {
            $item->password = bcrypt($item->password);
        }
    }

    protected function onUpdating(Model $item): void
    {
        if ($item->isDirty('password') && $item->password) {
            $item->password = bcrypt($item->password);
        }
    }
}
