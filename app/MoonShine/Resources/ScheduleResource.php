<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Support\Facades\Config;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

#[Icon('calendar-days')]
#[Group('Экскурсии', 'excursions')]
#[Order(0)]
class ScheduleResource extends ModelResource
{
    protected string $title = 'Расписание экскурсий';
    
    protected string $column = 'title';
    
    public function getTitle(): string
    {
        return 'Расписание экскурсий';
    }
    
    /**
     * Переопределяем для получения данных из конфига
     */
    public function paginate(): LengthAwarePaginator
    {
        $templates = Config::get('excursion_schedule.templates', []);
        
        $items = collect($templates)->map(function ($template, $index) {
            return (object) [
                'id' => $index + 1,
                'title' => $template['title'] ?? '',
                'description' => $template['description'] ?? '',
                'schedule' => $template['schedule'] ?? [],
            ];
        });
        
        $page = request()->get('page', 1);
        $perPage = $this->perPage();
        $total = $items->count();
        
        $items = $items->slice(($page - 1) * $perPage, $perPage);
        
        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
    
    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        $weekdays = [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];
        
        return [
            Text::make('Название', 'title')
                ->sortable(),
                
            Textarea::make('Описание', 'description'),
            
            Text::make('Расписание', function ($item) use ($weekdays) {
                $schedule = $item->schedule ?? [];
                if (empty($schedule)) {
                    return 'Расписание не задано';
                }
                
                $html = '<div class="space-y-1">';
                foreach ($schedule as $day => $time) {
                    $weekday = $weekdays[$day] ?? "День $day";
                    $html .= "<div><strong>$weekday:</strong> $time</div>";
                }
                $html .= '</div>';
                return $html;
            })->html(),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        $weekdays = [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];
        
        return [
            Box::make([
                Text::make('Название', 'title'),
                
                Textarea::make('Описание', 'description'),
            ]),
            
            Box::make([
                Text::make('Расписание по дням недели', function ($item) use ($weekdays) {
                    $schedule = $item->schedule ?? [];
                    if (empty($schedule)) {
                        return 'Расписание не задано';
                    }
                    
                    $html = '<table class="table-auto w-full border-collapse">';
                    $html .= '<thead><tr class="bg-gray-100"><th class="border p-2 text-left">День недели</th><th class="border p-2 text-left">Время отправления</th></tr></thead>';
                    $html .= '<tbody>';
                    
                    foreach ($weekdays as $dayNum => $dayName) {
                        $time = $schedule[$dayNum] ?? null;
                        $html .= '<tr>';
                        $html .= "<td class='border p-2'>$dayName</td>";
                        $html .= "<td class='border p-2'>" . ($time ?: '<span class="text-gray-400">—</span>') . "</td>";
                        $html .= '</tr>';
                    }
                    
                    $html .= '</tbody></table>';
                    return $html;
                })->html(),
            ]),
        ];
    }
    
    /**
     * Отключаем создание, редактирование и удаление
     */
    public function isCreatable(): bool
    {
        return false;
    }
    
    public function isEditable(): bool
    {
        return false;
    }
    
    public function isDeletable(): bool
    {
        return false;
    }
    
    protected function search(): array
    {
        return ['title', 'description'];
    }
}

