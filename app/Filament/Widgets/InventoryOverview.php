<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InventoryOverview extends BaseWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $total = Product::query()->count();
        $active = Product::query()->where('activo', true)->count();
        $outOfStock = Product::query()->where('activo', true)->where('stock', '<=', 0)->count();
        $lowStock = Product::query()
            ->where('activo', true)
            ->where('stock', '>', 0)
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->count();

        $inventoryValue = (float) Product::query()
            ->where('activo', true)
            ->selectRaw('COALESCE(SUM(stock * precio_costo), 0) as total')
            ->value('total');

        return [
            Stat::make('Productos', number_format($total))
                ->description($active.' activos')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Stock agotado', number_format($outOfStock))
                ->description($outOfStock > 0 ? 'Requiere reposición' : 'Sin productos agotados')
                ->descriptionIcon($outOfStock > 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->color($outOfStock > 0 ? 'danger' : 'success'),

            Stat::make('Stock bajo', number_format($lowStock))
                ->description($lowStock > 0 ? 'Por debajo del mínimo' : 'Niveles normales')
                ->descriptionIcon($lowStock > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($lowStock > 0 ? 'warning' : 'success'),

            Stat::make('Valor de inventario', number_format($inventoryValue, 2).' USD')
                ->description('Según precio de costo')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),
        ];
    }
}
