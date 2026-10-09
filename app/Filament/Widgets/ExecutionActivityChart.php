<?php

namespace App\Filament\Widgets;

use App\Models\PublicationExecution;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ExecutionActivityChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['md' => 8, 'xl' => 8];

    protected ?string $heading = 'Actividad de publicaciones';

    protected ?string $description = 'Ejecuciones de los últimos 14 días por estado.';

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $labels = [];
        $datasets = [
            'succeeded' => [],
            'failed' => [],
            'pending' => [],
        ];

        foreach (range(13, 0) as $daysAgo) {
            $date = now()->subDays($daysAgo);
            $labels[] = $date->format('d/m');

            foreach (array_keys($datasets) as $status) {
                $datasets[$status][] = PublicationExecution::query()
                    ->where('status', $status)
                    ->whereDate(
                        $status === 'pending' ? 'created_at' : 'updated_at',
                        $date
                    )
                    ->count();
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Exitosas',
                    'data' => $datasets['succeeded'],
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Fallidas',
                    'data' => $datasets['failed'],
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Pendientes',
                    'data' => $datasets['pending'],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
