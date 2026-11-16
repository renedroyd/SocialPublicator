<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDubaiShopEvent;
use App\Models\IntegrationEventInbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecoverDubaiShopInbox extends Command
{
    protected $signature = 'social-publicator:recover-dubai-inbox {--limit=100 : Maximum events to redispatch per run}';

    protected $description = 'Recover pending or expired Dubai Shop webhook events that were not processed';

    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $now = now();
        $reservationCutoff = $now->copy()->subMinutes(5);

        $ids = IntegrationEventInbox::withoutGlobalScope('workspace')
            ->where(function ($query) use ($now, $reservationCutoff): void {
                $query->where(function ($pending) use ($reservationCutoff): void {
                    $pending->where('status', 'pending')
                        ->where(function ($reserved) use ($reservationCutoff): void {
                            $reserved->whereNull('dispatch_reserved_at')
                                ->orWhere('dispatch_reserved_at', '<=', $reservationCutoff);
                        });
                })->orWhere(function ($expired) use ($now): void {
                    $expired->where('status', 'processing')
                        ->whereNotNull('lease_expires_at')
                        ->where('lease_expires_at', '<=', $now);
                });
            })
            ->orderBy('received_at')
            ->limit($limit)
            ->pluck('id');

        $dispatched = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            $reservation = DB::transaction(function () use ($id, $now, $reservationCutoff): ?array {
                $event = IntegrationEventInbox::withoutGlobalScope('workspace')
                    ->lockForUpdate()
                    ->find($id);

                if (! $event || $event->status === 'processed') {
                    return null;
                }

                $pendingAndAvailable = $event->status === 'pending'
                    && ($event->dispatch_reserved_at === null
                        || $event->dispatch_reserved_at->lte($reservationCutoff));
                $leaseExpired = $event->status === 'processing'
                    && $event->lease_expires_at !== null
                    && $event->lease_expires_at->lte($now);

                if (! $pendingAndAvailable && ! $leaseExpired) {
                    return null;
                }

                $event->update([
                    'status' => 'pending',
                    'dispatch_reserved_at' => $now,
                    'lease_expires_at' => null,
                    'processing_token' => null,
                ]);

                return [
                    'id' => (int) $event->id,
                    'workspace_id' => (int) $event->workspace_id,
                ];
            });

            if ($reservation === null) {
                $skipped++;

                continue;
            }

            try {
                ProcessDubaiShopEvent::dispatch($reservation['id'], $reservation['workspace_id']);
                $dispatched++;
            } catch (Throwable $exception) {
                IntegrationEventInbox::withoutGlobalScope('workspace')
                    ->whereKey($reservation['id'])
                    ->where('status', 'pending')
                    ->update([
                        'dispatch_reserved_at' => null,
                        'last_error' => 'Inbox recovery dispatch failed: '.mb_substr($exception->getMessage(), 0, 3500),
                        'updated_at' => now(),
                    ]);

                report($exception);
                $skipped++;
            }
        }

        $this->info("Eventos redispatchados: {$dispatched}; omitidos: {$skipped}.");

        return self::SUCCESS;
    }
}
