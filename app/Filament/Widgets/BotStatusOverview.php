<?php

namespace App\Filament\Widgets;

use App\Models\BotStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BotStatusOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $bots = BotStatus::query()
            ->orderBy('platform')
            ->orderBy('name')
            ->get();

        if ($bots->isEmpty()) {
            return [
                Stat::make('Estado del bot', 'Sin reporte')
                    ->description('El bot todavía no ha enviado heartbeat')
                    ->descriptionIcon('heroicon-m-signal')
                    ->color('gray'),

                Stat::make('Último heartbeat', '—')
                    ->description('Esperando conexión')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color('gray'),

                Stat::make('Versión', '—')
                    ->description('No reportada')
                    ->descriptionIcon('heroicon-m-code-bracket')
                    ->color('gray'),

                Stat::make('Incidencias', '0')
                    ->description('Sin errores registrados')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('success'),
            ];
        }

        $online = $bots->filter(
            fn (BotStatus $bot): bool => $bot->isOnline()
        )->count();

        $latest = $bots->sortByDesc('last_seen_at')->first();
        $errors = $bots->whereIn('status', ['error', 'offline'])->count();

        $state = $online === $bots->count()
            ? 'Online'
            : ($online > 0 ? 'Degradado' : 'Offline');

        $stateColor = match ($state) {
            'Online' => 'success',
            'Degradado' => 'warning',
            default => 'danger',
        };

        return [
            Stat::make('Estado del bot', $state)
                ->description($online.' de '.$bots->count().' bots activos')
                ->descriptionIcon('heroicon-m-signal')
                ->color($stateColor),

            Stat::make(
                'Último heartbeat',
                $latest?->last_seen_at?->diffForHumans() ?? '—'
            )
                ->description($latest?->name ?? 'Sin bot registrado')
                ->descriptionIcon('heroicon-m-clock')
                ->color($latest?->isOnline() ? 'success' : 'danger'),

            Stat::make('Versión', $latest?->version ?: 'No reportada')
                ->description($latest?->platform ?? '—')
                ->descriptionIcon('heroicon-m-code-bracket')
                ->color('info'),

            Stat::make('Incidencias', number_format($errors))
                ->description('Bots offline o con error')
                ->descriptionIcon(
                    $errors > 0
                        ? 'heroicon-m-exclamation-triangle'
                        : 'heroicon-m-check-circle'
                )
                ->color($errors > 0 ? 'danger' : 'success'),
        ];
    }
}
