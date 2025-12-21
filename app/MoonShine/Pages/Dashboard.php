<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\Booking;
use App\Models\Excursion;
use App\Models\ExcursionUser;
use App\Models\Stop;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Metrics\Wrapped\ValueMetric;

#[\MoonShine\MenuManager\Attributes\SkipMenu]
class Dashboard extends Page
{
    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            '#' => $this->getTitle()
        ];
    }

    public function getTitle(): string
    {
        return $this->title ?: 'Панель управления';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $totalExcursions = Excursion::count();
        $activeExcursions = Excursion::where('is_active', true)->count();
        $totalBookings = Booking::count();
        $todayBookings = Booking::whereDate('created_at', today())->count();
        $totalUsers = MoonshineUser::count();
        $totalStops = Stop::count();
        $totalAssignments = ExcursionUser::count();

        return [
            Grid::make([
                Column::make([
                    Box::make([
                        ValueMetric::make('Всего экскурсий')
                            ->value($totalExcursions)
                            ->icon('document-text'),
                    ]),
                ])->columnSpan(4),
                
                Column::make([
                    Box::make([
                        ValueMetric::make('Активных экскурсий')
                            ->value($activeExcursions)
                            ->icon('check-circle'),
                    ]),
                ])->columnSpan(4),
                
                Column::make([
                    Box::make([
                        ValueMetric::make('Всего бронирований')
                            ->value($totalBookings)
                            ->icon('ticket'),
                    ]),
                ])->columnSpan(4),
            ]),
            
            Grid::make([
                Column::make([
                    Box::make([
                        ValueMetric::make('Бронирований сегодня')
                            ->value($todayBookings)
                            ->icon('calendar'),
                    ]),
                ])->columnSpan(4),
                
                Column::make([
                    Box::make([
                        ValueMetric::make('Пользователей')
                            ->value($totalUsers)
                            ->icon('users'),
                    ]),
                ])->columnSpan(4),
                
                Column::make([
                    Box::make([
                        ValueMetric::make('Остановок')
                            ->value($totalStops)
                            ->icon('map-pin'),
                    ]),
                ])->columnSpan(4),
            ]),
            
            Grid::make([
                Column::make([
                    Box::make([
                        ValueMetric::make('Назначений персонала')
                            ->value($totalAssignments)
                            ->icon('user-group'),
                    ]),
                ])->columnSpan(4),
            ]),
        ];
    }
}
