<?php

use App\Models\PublicationExecution;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('publications:mark-stale-executions', function (): int {
    $cutoff = now()->subMinutes(15);

    $count = PublicationExecution::query()
        ->where('status', 'running')
        ->whereNotNull('started_at')
        ->where('started_at', '<=', $cutoff)
        ->whereNull('completed_at')
        ->update([
            'status' => 'unknown',
            'completed_at' => now(),
            'error_message' => 'La ejecución quedó interrumpida. El resultado externo es desconocido; verifica Facebook/WhatsApp antes de reintentar.',
            'updated_at' => now(),
        ]);

    $this->info("Ejecuciones marcadas con resultado desconocido: {$count}");

    return self::SUCCESS;
})->purpose('Marca ejecuciones atascadas sin reintentarlas automáticamente');

Schedule::command('publications:mark-stale-executions')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('social-publicator:select-campaign-items')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('social-publicator:sync-dubai-catalog')
    ->hourly()
    ->withoutOverlapping()
    ->when(fn (): bool => filled(config('services.dubai_shop.api_base_url'))
        && filled(config('services.dubai_shop.api_token')));
