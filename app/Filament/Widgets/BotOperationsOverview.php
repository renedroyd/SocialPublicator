<?php

namespace App\Filament\Widgets;

use App\Models\BotStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BotOperationsOverview extends BaseWidget
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
                    ->description('Esperando el primer heartbeat')
                    ->descriptionIcon('heroicon-m-signal')
                    ->color('gray'),
                Stat::make('WhatsApp', 'Sin datos')
                    ->description('Aún no hay estado de conexión')
                    ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                    ->color('gray'),
                Stat::make('Facebook', 'Sin datos')
                    ->description('Aún no hay estado de conexión')
                    ->descriptionIcon('heroicon-m-globe-alt')
                    ->color('gray'),
                Stat::make('Publicaciones', '0')
                    ->description('Sin actividad reportada')
                    ->descriptionIcon('heroicon-m-megaphone')
                    ->color('gray'),
                Stat::make('Próxima publicación', '—')
                    ->description('Sin programación reportada')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color('gray'),
                Stat::make('Última incidencia', 'Ninguna')
                    ->description('El bot aún no ha reportado errores')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('success'),
            ];
        }

        $latest = $bots->sortByDesc('last_seen_at')->first();
        $online = $bots->filter(fn (BotStatus $bot): bool => $bot->isOnline())->count();

        $whatsapp = $this->connectionState($latest?->metadata['whatsapp'] ?? null);
        $facebook = $this->connectionState($latest?->metadata['facebook'] ?? null);

        $stats = is_array($latest?->metadata['stats'] ?? null)
            ? $latest->metadata['stats']
            : [];

        $successful = (int) ($stats['successful'] ?? 0);
        $failed = (int) ($stats['failed'] ?? 0);
        $retries = (int) ($stats['retries'] ?? 0);

        $lastPublication = $latest?->metadata['lastPublication'] ?? null;
        $nextPublication = $latest?->metadata['nextPublication'] ?? null;
        $lastError = $latest?->metadata['lastError'] ?? $latest?->message;
        $lastHeartbeat = $latest?->last_seen_at?->diffForHumans() ?? 'Sin heartbeat';

        $botState = $online === $bots->count()
            ? 'En línea'
            : ($online > 0 ? 'Degradado' : 'Sin conexión');

        return [
            Stat::make('Estado del bot', $botState)
                ->description($online.' de '.$bots->count().' bots activos')
                ->descriptionIcon('heroicon-m-signal')
                ->color(match ($botState) {
                    'En línea' => 'success',
                    'Degradado' => 'warning',
                    default => 'danger',
                }),

            Stat::make('WhatsApp', $this->humanState($whatsapp))
                ->description('Último reporte: '.$lastHeartbeat)
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color($this->stateColor($whatsapp)),

            Stat::make('Facebook', $this->humanState($facebook))
                ->description('Último reporte: '.$lastHeartbeat)
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color($this->stateColor($facebook)),

            Stat::make('Publicaciones', number_format($successful))
                ->description($failed.' fallidas · '.$retries.' reintentos')
                ->descriptionIcon($failed > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($failed > 0 ? 'warning' : 'success'),

            Stat::make('Próxima publicación', $this->displayValue($nextPublication))
                ->description($lastPublication ? 'Última: '.$this->displayValue($lastPublication) : 'Sin publicación registrada')
                ->descriptionIcon('heroicon-m-clock')
                ->color($nextPublication ? 'info' : 'gray'),

            Stat::make('Última incidencia', $this->displayValue($lastError, 55))
                ->description($lastError ? ($latest?->metadata['lastErrorAt'] ?? 'Error reportado') : 'Sin incidencias reportadas')
                ->descriptionIcon($lastError ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($lastError ? 'danger' : 'success'),
        ];
    }

    private function connectionState(mixed $state): string
    {
        if (! is_string($state) || $state === '') {
            return 'unknown';
        }

        return strtolower($state);
    }

    private function humanState(string $state): string
    {
        return match ($state) {
            'connected', 'online' => 'Conectado',
            'starting' => 'Iniciando',
            'disconnected', 'offline' => 'Desconectado',
            'error' => 'Error',
            'disabled' => 'No configurado',
            default => 'Sin reporte',
        };
    }

    private function stateColor(string $state): string
    {
        return match ($state) {
            'connected', 'online' => 'success',
            'starting' => 'warning',
            'error', 'disconnected', 'offline' => 'danger',
            'disabled', 'unknown' => 'gray',
            default => 'gray',
        };
    }

    private function displayValue(mixed $value, int $limit = 45): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $value = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);

        return mb_strlen($value) > $limit
            ? mb_substr($value, 0, $limit - 1).'…'
            : $value;
    }
}
