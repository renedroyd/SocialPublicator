<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BotStatusOverview;
use App\Filament\Widgets\ExecutionActivityChart;
use App\Filament\Widgets\PublicationOverview;
use App\Filament\Widgets\PublicationStatsOverview;
use App\Filament\Widgets\UpcomingPublications;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Centro de operaciones';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $slug = 'dashboard';

    public function getWidgets(): array
    {
        return [
            PublicationStatsOverview::class,
            BotStatusOverview::class,
            ExecutionActivityChart::class,
            PublicationOverview::class,
            UpcomingPublications::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 12;
    }
}
