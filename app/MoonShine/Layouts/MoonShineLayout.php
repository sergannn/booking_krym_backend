<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\ColorManager\ColorManager;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Laravel\Components\Layout\{Locales, Notifications, Profile, Search};
use MoonShine\UI\Components\{Breadcrumbs,
    Components,
    Layout\Flash,
    Layout\Div,
    Layout\Body,
    Layout\Burger,
    Layout\Content,
    Layout\Footer,
    Layout\Head,
    Layout\Favicon,
    Layout\Assets,
    Layout\Meta,
    Layout\Header,
    Layout\Html,
    Layout\Layout,
    Layout\Logo,
    Layout\Menu,
    Layout\Sidebar,
    Layout\ThemeSwitcher,
    Layout\TopBar,
    Layout\Wrapper,
    When};
use App\MoonShine\Resources\ExcursionResource;
use MoonShine\MenuManager\MenuItem;
use App\MoonShine\Resources\BusSeatResource;
use App\MoonShine\Resources\UserResource;
use App\MoonShine\Resources\BookingResource;
use App\MoonShine\Resources\StopResource;
use App\MoonShine\Resources\WalletTransactionResource;

final class MoonShineLayout extends AppLayout
{
    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        return [
            ...parent::menu(),
            MenuItem::make('Экскурсии', ExcursionResource::class),
            MenuItem::make('Бронирования', BookingResource::class),
            MenuItem::make('Места в автобусе', BusSeatResource::class),
            MenuItem::make('Остановки', StopResource::class),
            MenuItem::make('Транзакции кошелька', WalletTransactionResource::class),
            MenuItem::make('Пользователи', UserResource::class),
        ];
    }

    /**
     * @param ColorManager $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }

    public function build(): Layout
    {
        return parent::build();
    }
}
