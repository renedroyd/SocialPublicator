<?php

namespace App\Filament\Widgets;

use App\Models\BotStatus;
use App\Models\PublicationExecution;
use App\Models\PublicationSchedule;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationalAlerts extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected static ?string $heading = 'Centro de alertas';

    protected static ?string $description = 'Incidencias que requieren atención del operador.';

    protected function getStats(): array
    {
        $failed24h = PublicationExecution::query()
            ->where('status', 'failed')
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        $stuck = PublicationExecution::query()
            ->where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(10))
            ->count();

        $pendingOverdue = PublicationExecution::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(10))
            ->count();

        $overdueSchedules = PublicationSchedule::query()
            ->where('active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<', now()->subMinutes(5))
            ->count();

        $offlineBots = BotStatus::query()
            ->get()
            ->filter(fn (BotStatus $bot): bool => ! $bot->isOnline())
            ->count();

        $critical = $failed24h + $stuck + $pendingOverdue + $overdueSchedules + $offlineBots;

        return [
            Stat::make('Fallos recientes', number_format($failed24h))
                ->description($failed24h > 0 ? 'Ejecuciones fallidas en 24 horas' : 'Sin fallos recientes')
                ->descriptionIcon($failed24h > 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->color($failed24h > 0 ? 'danger' : 'success'),

            Stat::make('Ejecuciones atascadas', number_format($stuck))
                ->description($stuck > 0 ? 'En ejecución durante más de 10 minutos' : 'Sin ejecuciones atascadas')
                ->descriptionIcon($stuck > 0 ? 'heroicon-m-pause-circle' : 'heroicon-m-check-circle')
                ->color($stuck > 0 ? 'danger' : 'success'),

            Stat::make('Pendientes antiguas', number_format($pendingOverdue))
                ->description($pendingOverdue > 0 ? 'Pendientes desde hace más de 10 minutos' : 'Cola de ejecuciones normal')
                ->descriptionIcon($pendingOverdue > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                ->color($pendingOverdue > 0 ? 'warning' : 'success'),

            Stat::make('Horarios vencidos', number_format($overdueSchedules))
                ->description($overdueSchedules > 0 ? 'Recurrencias activas fuera de hora' : 'Programación al día')
                ->descriptionIcon($overdueSchedules > 0 ? 'heroicon-m-calendar-days' : 'heroicon-m-check-circle')
                ->color($overdueSchedules > 0 ? 'warning' : 'success'),

            Stat::make('Bots sin conexión', number_format($offlineBots))
                ->description($offlineBots > 0 ? 'Sin heartbeat válido' : 'Todos los bots reportando')
                ->descriptionIcon($offlineBots > 0 ? 'heroicon-m-signal-slash' : 'heroicon-m-signal')
                ->color($offlineBots > 0 ? 'danger' : 'success'),

            Stat::make('Nivel operativo', $critical === 0 ? 'Normal' : 'Atención')
                ->description($critical === 0 ? 'No hay incidencias detectadas' : $critical.' indicadores requieren revisión')
                ->descriptionIcon($critical === 0 ? 'heroicon-m-shield-check' : 'heroicon-m-exclamation-triangle')
                ->color($critical === 0 ? 'success' : 'warning'),
        ];
    }
}
