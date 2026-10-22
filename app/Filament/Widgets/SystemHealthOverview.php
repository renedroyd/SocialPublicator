<?php

namespace App\Filament\Widgets;

use App\Models\BotStatus;
use App\Models\PublicationExecution;
use App\Models\PublicationSchedule;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemHealthOverview extends BaseWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Salud del sistema';

    protected ?string $description = 'Estado operativo de base de datos, cola, programación y ejecuciones.';

    protected function getStats(): array
    {
        return [
            $this->databaseStat(),
            $this->queueStat(),
            $this->schedulerStat(),
            $this->executionsStat(),
            $this->botHeartbeatStat(),
            $this->failedJobsStat(),
        ];
    }

    private function databaseStat(): Stat
    {
        try {
            DB::connection()->getPdo();

            return Stat::make('Base de datos', 'Operativa')
                ->description('Conexión disponible')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success');
        } catch (Throwable $exception) {
            report($exception);

            return Stat::make('Base de datos', 'Error')
                ->description('No se pudo comprobar la conexión')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger');
        }
    }

    private function queueStat(): Stat
    {
        try {
            if (! Schema::hasTable('jobs')) {
                return Stat::make('Cola', 'No disponible')
                    ->description('La tabla jobs no existe')
                    ->descriptionIcon('heroicon-m-minus-circle')
                    ->color('gray');
            }

            $pending = DB::table('jobs')->count();
            $reserved = DB::table('jobs')->whereNotNull('reserved_at')->count();

            return Stat::make('Cola', number_format($pending))
                ->description($reserved.' en ejecución · '.config('queue.default').' driver')
                ->descriptionIcon('heroicon-m-queue-list')
                ->color($pending > 0 ? 'info' : 'success');
        } catch (Throwable $exception) {
            report($exception);

            return Stat::make('Cola', 'Error')
                ->description('No se pudo consultar jobs')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger');
        }
    }

    private function schedulerStat(): Stat
    {
        try {
            $active = PublicationSchedule::query()->where('active', true)->count();
            $overdue = PublicationSchedule::query()
                ->where('active', true)
                ->whereNotNull('next_run_at')
                ->where('next_run_at', '<', now())
                ->count();

            if ($active === 0) {
                return Stat::make('Programación', 'Sin horarios')
                    ->description('No hay recurrencias activas')
                    ->descriptionIcon('heroicon-m-calendar')
                    ->color('gray');
            }

            return Stat::make('Programación', $overdue > 0 ? 'Atención' : 'OK')
                ->description($active.' activas · '.$overdue.' vencidas')
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-calendar-days')
                ->color($overdue > 0 ? 'warning' : 'success');
        } catch (Throwable $exception) {
            report($exception);

            return Stat::make('Programación', 'Error')
                ->description('No se pudo consultar horarios')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger');
        }
    }

    private function executionsStat(): Stat
    {
        try {
            $running = PublicationExecution::query()->where('status', 'running')->count();
            $pending = PublicationExecution::query()->where('status', 'pending')->count();
            $failed24h = PublicationExecution::query()
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count();

            return Stat::make('Ejecuciones', number_format($running + $pending))
                ->description($running.' ejecutando · '.$pending.' pendientes · '.$failed24h.' fallidas/24h')
                ->descriptionIcon($failed24h > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-play-circle')
                ->color($failed24h > 0 ? 'warning' : ($running > 0 ? 'info' : 'success'));
        } catch (Throwable $exception) {
            report($exception);

            return Stat::make('Ejecuciones', 'Error')
                ->description('No se pudo consultar actividad')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger');
        }
    }

    private function botHeartbeatStat(): Stat
    {
        try {
            $bots = BotStatus::query()->get();
            $online = $bots->filter(fn (BotStatus $bot): bool => $bot->isOnline())->count();
            $stale = max(0, $bots->count() - $online);

            if ($bots->isEmpty()) {
                return Stat::make('Heartbeat', 'Sin bots')
                    ->description('Esperando primer reporte')
                    ->descriptionIcon('heroicon-m-signal')
                    ->color('gray');
            }

            return Stat::make('Heartbeat', $online.'/'.$bots->count())
                ->description($stale > 0 ? $stale.' sin conexión reciente' : 'Todos reportando')
                ->descriptionIcon($stale > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-signal')
                ->color($stale > 0 ? 'warning' : 'success');
        } catch (Throwable $exception) {
            report($exception);

            return Stat::make('Heartbeat', 'Error')
                ->description('No se pudo consultar bots')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger');
        }
    }

    private function failedJobsStat(): Stat
    {
        try {
            if (! Schema::hasTable('failed_jobs')) {
                return Stat::make('Jobs fallidos', '0')
                    ->description('Tabla no disponible')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('gray');
            }

            $failed = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count();

            return Stat::make('Jobs fallidos', number_format($failed))
                ->description($failed > 0 ? 'Revisar últimas 24 horas' : 'Sin fallos recientes')
                ->descriptionIcon($failed > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($failed > 0 ? 'danger' : 'success');
        } catch (Throwable $exception) {
            report($exception);

            return Stat::make('Jobs fallidos', 'Error')
                ->description('No se pudo consultar failed_jobs')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger');
        }
    }
}
