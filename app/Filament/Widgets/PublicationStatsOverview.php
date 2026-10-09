<?php

namespace App\Filament\Widgets;

use App\Models\Publication;
use App\Models\PublicationExecution;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PublicationStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $weekEnd = $today->copy()->addDays(7);

        $total = Publication::query()->count();
        $active = Publication::query()->where('active', true)->count();
        $todayCount = Publication::query()
            ->where('active', true)
            ->whereDate('date', $today)
            ->count();
        $nextSevenDays = Publication::query()
            ->where('active', true)
            ->whereDate('date', '>=', $today)
            ->whereDate('date', '<', $weekEnd)
            ->count();

        $failed24h = PublicationExecution::query()
            ->where('status', 'failed')
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        $succeeded24h = PublicationExecution::query()
            ->where('status', 'succeeded')
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        $successRate = $succeeded24h + $failed24h > 0
            ? round(($succeeded24h / ($succeeded24h + $failed24h)) * 100, 1)
            : 100;

        $dailyExecutions = collect(range(6, 0))->map(
            fn (int $days): int => PublicationExecution::query()
                ->where('status', 'succeeded')
                ->whereDate('completed_at', now()->subDays($days))
                ->count()
        )->all();

        return [
            Stat::make('Publicaciones totales', number_format($total))
                ->description($active.' activas')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('primary')
                ->chart($dailyExecutions),

            Stat::make('Programadas hoy', number_format($todayCount))
                ->description('Para la fecha actual')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Próximos 7 días', number_format($nextSevenDays))
                ->description('Publicaciones activas')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Éxito últimas 24 h', $successRate.'%')
                ->description($succeeded24h.' correctas · '.$failed24h.' fallidas')
                ->descriptionIcon(
                    $failed24h > 0
                        ? 'heroicon-m-exclamation-triangle'
                        : 'heroicon-m-check-circle'
                )
                ->color($failed24h > 0 ? 'warning' : 'success'),
        ];
    }
}
