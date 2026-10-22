<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BotOperationsOverview;
use App\Filament\Widgets\OperationalAlerts;
use App\Filament\Widgets\SystemHealthOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Salud del bot';

    protected static ?string $navigationLabel = 'Salud del bot';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static ?string $slug = 'dashboard';

    public function getWidgets(): array
    {
        return [
            BotOperationsOverview::class,
            OperationalAlerts::class,
            SystemHealthOverview::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 12;
    }
}
