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
                Stat::make('Bot', 'Sin reporte')
                    ->description('Esperando heartbeat')
                    ->descriptionIcon('heroicon-m-signal')
                    ->color('gray'),
                Stat::make('WhatsApp', '—')
                    ->description('Sin datos')
                    ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                    ->color('gray'),
                Stat::make('Facebook', '—')
                    ->description('Sin datos')
                    ->descriptionIcon('heroicon-m-globe-alt')
                    ->color('gray'),
                Stat::make('Publicaciones', '0')
                    ->description('Sin actividad reportada')
                    ->descriptionIcon('heroicon-m-megaphone')
                    ->color('gray'),
                Stat::make('Próxima', '—')
                    ->description('Sin programación reportada')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color('gray'),
                Stat::make('Incidencias', '0')
                    ->description('Sin errores')
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

        $botState = $online === $bots->count()
            ? 'Online'
            : ($online > 0 ? 'Degradado' : 'Offline');

        return [
            Stat::make('Estado del bot', $botState)
                ->description($online.' de '.$bots->count().' bots activos')
                ->descriptionIcon('heroicon-m-signal')
                ->color(match ($botState) {
                    'Online' => 'success',
                    'Degradado' => 'warning',
                    default => 'danger',
                }),

            Stat::make('WhatsApp', $this->humanState($whatsapp))
                ->description($latest?->last_seen_at?->diffForHumans() ?? 'Sin heartbeat')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color($this->stateColor($whatsapp)),

            Stat::make('Facebook', $this->humanState($facebook))
                ->description($latest?->platform ?? 'Sin datos')
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

            Stat::make('Último error', $this->displayValue($lastError, 55))
                ->description($lastError ? ($latest?->metadata['lastErrorAt'] ?? 'Error reportado') : 'Sistema sin errores')
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
            default => 'Desconocido',
        };
    }

    private function stateColor(string $state): string
    {
        return match ($state) {
            'connected', 'online' => 'success',
            'starting' => 'warning',
            'error', 'disconnected', 'offline' => 'danger',
            'disabled' => 'gray',
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
