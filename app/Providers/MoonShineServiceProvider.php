<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\ConfiguratorContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShine;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;
use App\MoonShine\Resources\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRoleResource;
use App\MoonShine\Resources\ExcursionResource;
use App\MoonShine\Resources\BusSeatResource;
use App\MoonShine\Resources\UserResource;
use App\MoonShine\Resources\BookingResource;
use App\MoonShine\Resources\StopResource;
use App\MoonShine\Resources\WalletTransactionResource;

class MoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param  MoonShine  $core
     * @param  MoonShineConfigurator  $config
     *
     */
    public function boot(CoreContract $core, ConfiguratorContract $config): void
    {
        $core
            ->resources([
                UserResource::class,
                ExcursionResource::class,
                BusSeatResource::class,
                BookingResource::class,
                StopResource::class,
                WalletTransactionResource::class,
                MoonShineUserResource::class,
                MoonShineUserRoleResource::class,
            ])
            ->pages([
                ...$config->getPages(),
            ])
        ;
    }
}