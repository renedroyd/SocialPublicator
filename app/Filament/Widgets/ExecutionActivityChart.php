<?php

namespace App\Filament\Widgets;

use App\Models\PublicationExecution;
use Filament\Widgets\ChartWidget;

class ExecutionActivityChart extends ChartWidget
{
    protected static ?int $sort = 4;

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
                    'label' => '✓ Exitosas',
                    'data' => $datasets['succeeded'],
                    'borderColor' => '#16A34A',
                    'backgroundColor' => '#16A34A',
                    'pointBackgroundColor' => '#16A34A',
                    'pointBorderColor' => '#16A34A',
                    'pointStyle' => 'circle',
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'borderWidth' => 2,
                    'tension' => 0.3,
                    'fill' => false,
                ],
                [
                    'label' => '✕ Fallidas',
                    'data' => $datasets['failed'],
                    'borderColor' => '#DC2626',
                    'backgroundColor' => '#DC2626',
                    'pointBackgroundColor' => '#DC2626',
                    'pointBorderColor' => '#DC2626',
                    'pointStyle' => 'triangle',
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'borderWidth' => 2,
                    'tension' => 0.3,
                    'fill' => false,
                ],
                [
                    'label' => '◷ Pendientes',
                    'data' => $datasets['pending'],
                    'borderColor' => '#D97706',
                    'backgroundColor' => '#D97706',
                    'pointBackgroundColor' => '#D97706',
                    'pointBorderColor' => '#D97706',
                    'pointStyle' => 'rectRot',
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'borderWidth' => 2,
                    'tension' => 0.3,
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 16,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
