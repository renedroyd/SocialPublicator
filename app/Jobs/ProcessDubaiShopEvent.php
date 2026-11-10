<?php

namespace App\Jobs;

use App\Models\IntegrationEventInbox;
use App\Services\ExternalCatalogSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class ProcessDubaiShopEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 120, 300, 600];

    public function __construct(public readonly int $eventId) {}

    public function handle(ExternalCatalogSyncService $syncService): void
    {
        $event = IntegrationEventInbox::query()->findOrFail($this->eventId);

        if ($event->status === 'processed') {
            return;
        }

        $event->update([
            'status' => 'processing',
            'attempts' => $event->attempts + 1,
            'last_error' => null,
        ]);

        try {
            $syncService->processEvent($event);

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $event->update([
                'status' => 'failed',
                'last_error' => Str::limit($exception->getMessage(), 4000),
            ]);

            throw $exception;
        }
    }
}
