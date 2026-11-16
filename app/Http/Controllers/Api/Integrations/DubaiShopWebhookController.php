<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDubaiShopEvent;
use App\Models\IntegrationEventInbox;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class DubaiShopWebhookController extends Controller
{
    private const EVENT_TYPES = [
        'catalog.item_published',
        'catalog.item_unpublished',
        'catalog.item_updated',
        'inventory.changed',
        'price.changed',
        'warehouse.updated',
    ];

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'event_id' => ['required', 'uuid'],
            'event_type' => ['required', 'string', Rule::in(self::EVENT_TYPES)],
            'contract_version' => ['required', 'in:v1'],
            'aggregate_external_id' => ['required', 'uuid'],
            'revision' => ['required', 'integer', 'min:1'],
            'occurred_at' => ['required', 'date'],
            'data' => ['required', 'array'],
        ]);

        try {
            $event = IntegrationEventInbox::query()->firstOrCreate(
                ['event_id' => $payload['event_id']],
                [
                    'event_type' => $payload['event_type'],
                    'contract_version' => $payload['contract_version'],
                    'aggregate_external_id' => $payload['aggregate_external_id'],
                    'revision' => $payload['revision'],
                    'payload' => $payload,
                    'status' => 'pending',
                    'received_at' => now(),
                ],
            );
        } catch (QueryException $exception) {
            $event = IntegrationEventInbox::query()
                ->where('event_id', $payload['event_id'])
                ->first();

            if (! $event) {
                throw $exception;
            }
        }

        if ($event->wasRecentlyCreated) {
            $event->update(['dispatch_reserved_at' => now()]);

            try {
                ProcessDubaiShopEvent::dispatch($event->id, (int) $event->workspace_id)->afterCommit();
            } catch (Throwable $exception) {
                $event->update(['dispatch_reserved_at' => null]);
                Log::warning('Dubai Shop event was persisted but immediate queue dispatch failed; inbox recovery will retry it.', [
                    'event_id' => $event->event_id,
                    'inbox_id' => $event->id,
                    'workspace_id' => $event->workspace_id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json([
            'accepted' => true,
            'duplicate' => ! $event->wasRecentlyCreated,
            'event_id' => $event->event_id,
        ], 202);
    }
}
