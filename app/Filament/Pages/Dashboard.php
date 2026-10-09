<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BotOperationsOverview;
use App\Filament\Widgets\ExecutionActivityChart;
use App\Filament\Widgets\PublicationOverview;
use App\Filament\Widgets\PublicationStatsOverview;
use App\Filament\Widgets\UpcomingPublications;
use App\Filament\Widgets\RecentExecutions;
use App\Filament\Widgets\InventoryOverview;
use App\Filament\Widgets\OperationalAlerts;
use App\Filament\Widgets\SystemHealthOverview;
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
            BotOperationsOverview::class,
            SystemHealthOverview::class,
            OperationalAlerts::class,
            ExecutionActivityChart::class,
            PublicationOverview::class,
            UpcomingPublications::class,
            RecentExecutions::class,
            InventoryOverview::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 12;
    }
}
