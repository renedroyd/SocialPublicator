<?php

namespace App\Jobs;

use App\Models\IntegrationEventInbox;
use App\Services\ExternalCatalogSyncService;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ProcessDubaiShopEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_EVENT_ATTEMPTS = 5;

    private const LEASE_MINUTES = 30;

    public int $tries = self::MAX_EVENT_ATTEMPTS;

    public array $backoff = [30, 120, 300, 600];

    public function __construct(public readonly int $eventId, public readonly ?int $workspaceId = null) {}

    public function handle(ExternalCatalogSyncService $syncService): void
    {
        $claim = DB::transaction(function (): ?array {
            $event = IntegrationEventInbox::withoutGlobalScope('workspace')
                ->lockForUpdate()
                ->find($this->eventId);

            if (! $event || in_array($event->status, ['processed', 'failed'], true)) {
                return null;
            }

            if ($event->status === 'processing'
                && $event->lease_expires_at !== null
                && $event->lease_expires_at->isFuture()) {
                return null;
            }

            if ($event->attempts >= self::MAX_EVENT_ATTEMPTS) {
                $event->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'lease_expires_at' => null,
                    'processing_token' => null,
                    'dispatch_reserved_at' => null,
                ]);

                return null;
            }

            $token = (string) Str::uuid();

            $event->update([
                'status' => 'processing',
                'attempts' => $event->attempts + 1,
                'last_error' => null,
                'dispatch_reserved_at' => null,
                'lease_expires_at' => now()->addMinutes(self::LEASE_MINUTES),
                'processing_token' => $token,
                'failed_at' => null,
            ]);

            return [
                'workspace_id' => $this->workspaceId ?? (int) $event->workspace_id,
                'processing_token' => $token,
                'attempts' => (int) $event->attempts,
            ];
        });

        if ($claim === null) {
            return;
        }

        $context = app(WorkspaceContext::class);
        $context->set($claim['workspace_id']);

        try {
            $event = IntegrationEventInbox::withoutGlobalScope('workspace')->findOrFail($this->eventId);
            $syncService->processEvent($event);

            IntegrationEventInbox::withoutGlobalScope('workspace')
                ->whereKey($this->eventId)
                ->where('processing_token', $claim['processing_token'])
                ->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'last_error' => null,
                    'lease_expires_at' => null,
                    'processing_token' => null,
                    'failed_at' => null,
                    'updated_at' => now(),
                ]);
        } catch (Throwable $exception) {
            $terminal = $claim['attempts'] >= self::MAX_EVENT_ATTEMPTS;

            IntegrationEventInbox::withoutGlobalScope('workspace')
                ->whereKey($this->eventId)
                ->where('processing_token', $claim['processing_token'])
                ->update([
                    'status' => $terminal ? 'failed' : 'pending',
                    'last_error' => Str::limit($exception->getMessage(), 4000),
                    'lease_expires_at' => null,
                    'processing_token' => null,
                    'failed_at' => $terminal ? now() : null,
                    'updated_at' => now(),
                ]);

            throw $exception;
        } finally {
            $context->clear();
        }
    }

    public function failed(?Throwable $exception): void
    {
        IntegrationEventInbox::withoutGlobalScope('workspace')
            ->whereKey($this->eventId)
            ->where('status', '!=', 'processed')
            ->where(function ($query): void {
                $query->where('status', '!=', 'processing')
                    ->orWhereNull('lease_expires_at')
                    ->orWhere('lease_expires_at', '<=', now());
            })
            ->update([
                'status' => 'failed',
                'last_error' => $exception ? Str::limit($exception->getMessage(), 4000) : 'Queue exhausted retries.',
                'lease_expires_at' => null,
                'processing_token' => null,
                'dispatch_reserved_at' => null,
                'failed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
