<?php

namespace App\Filament\Widgets;

use App\Models\Publication;
use Filament\Widgets\ChartWidget;

class PublicationOverview extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['md' => 4, 'xl' => 4];

    protected ?string $heading = 'Canales de publicación';

    protected ?string $description = 'Distribución de publicaciones configuradas.';

    protected function getData(): array
    {
        $counts = Publication::query()
            ->selectRaw('destination, COUNT(*) as aggregate')
            ->groupBy('destination')
            ->pluck('aggregate', 'destination');

        return [
            'datasets' => [
                [
                    'label' => 'Publicaciones',
                    'data' => [
                        (int) ($counts['whatsapp'] ?? 0),
                        (int) ($counts['facebook'] ?? 0),
                        (int) ($counts['both'] ?? 0),
                    ],
                ],
            ],
            'labels' => [
                'WhatsApp',
                'Facebook',
                'Ambos',
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
