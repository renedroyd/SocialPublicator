<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Publications\PublicationResource;
use App\Filament\Widgets\BotOperationsOverview;
use App\Filament\Widgets\ExecutionActivityChart;
use App\Filament\Widgets\InventoryOverview;
use App\Filament\Widgets\OperationalAlerts;
use App\Filament\Widgets\PublicationOverview;
use App\Filament\Widgets\PublicationStatsOverview;
use App\Filament\Widgets\RecentExecutions;
use App\Filament\Widgets\SystemHealthOverview;
use App\Filament\Widgets\UpcomingPublications;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Centro de operaciones';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $slug = 'dashboard';

    public function getHeaderActions(): array
    {
        return [
            Action::make('newPublication')
                ->label('Nueva publicación')
                ->icon('heroicon-o-megaphone')
                ->color('primary')
                ->url(PublicationResource::getUrl('create')),

            Action::make('publications')
                ->label('Ver publicaciones')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->url(PublicationResource::getUrl()),

            Action::make('products')
                ->label('Productos')
                ->icon('heroicon-o-cube')
                ->color('gray')
                ->url(ProductResource::getUrl()),

            Action::make('categories')
                ->label('Categorías')
                ->icon('heroicon-o-tag')
                ->color('gray')
                ->url(CategoryResource::getUrl()),
        ];
    }

    public function getWidgets(): array
    {
        return [
            PublicationStatsOverview::class,
            BotOperationsOverview::class,
            OperationalAlerts::class,
            ExecutionActivityChart::class,
            PublicationOverview::class,
            UpcomingPublications::class,
            RecentExecutions::class,
            SystemHealthOverview::class,
            InventoryOverview::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 12;
    }
}
