<?php

namespace Tests\Feature;

use App\Jobs\ProcessDubaiShopEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DubaiShopWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret-with-sufficient-entropy';

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'event_id' => (string) Str::uuid(),
            'event_type' => 'inventory.changed',
            'contract_version' => 'v1',
            'aggregate_external_id' => (string) Str::uuid(),
            'revision' => 3,
            'occurred_at' => now()->toISOString(),
            'data' => [
                'item_external_id' => (string) Str::uuid(),
                'availability' => ['warehouses' => []],
            ],
        ], $overrides);
    }

    private function sendSigned(array $payload, ?int $timestamp = null, ?string $signatureOverride = null)
    {
        config()->set('services.dubai_shop.webhook_keys', ['test-key' => self::SECRET]);
        config()->set('services.dubai_shop.webhook_tolerance_seconds', 300);

        $timestamp ??= now()->timestamp;
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = $signatureOverride
            ?? 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);

        return $this->call(
            'POST',
            '/api/integrations/dubai-shop/v1/events',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_DUBAISHOP_KEY_ID' => 'test-key',
                'HTTP_X_DUBAISHOP_TIMESTAMP' => (string) $timestamp,
                'HTTP_X_DUBAISHOP_SIGNATURE' => $signature,
            ],
            $body,
        );
    }

    public function test_valid_signed_event_is_stored_and_queued(): void
    {
        Queue::fake();
        $payload = $this->payload();

        $response = $this->sendSigned($payload);

        $response->assertAccepted()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('event_id', $payload['event_id']);

        $this->assertDatabaseHas('integration_event_inbox', [
            'event_id' => $payload['event_id'],
            'event_type' => 'inventory.changed',
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessDubaiShopEvent::class);
    }

    public function test_duplicate_event_is_acknowledged_without_duplicate_job(): void
    {
        Queue::fake();
        $payload = $this->payload();

        $this->sendSigned($payload)->assertAccepted();
        $this->sendSigned($payload)
            ->assertAccepted()
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('integration_event_inbox', 1);
        Queue::assertPushed(ProcessDubaiShopEvent::class, 1);
    }

    public function test_invalid_signature_is_rejected_without_storing_event(): void
    {
        $payload = $this->payload();

        $this->sendSigned($payload, signatureOverride: 'sha256=invalid')
            ->assertUnauthorized();

        $this->assertDatabaseCount('integration_event_inbox', 0);
    }

    public function test_stale_webhook_timestamp_is_rejected(): void
    {
        $payload = $this->payload();

        $this->sendSigned($payload, now()->subMinutes(10)->timestamp)
            ->assertUnauthorized();

        $this->assertDatabaseCount('integration_event_inbox', 0);
    }

    public function test_unknown_event_type_is_rejected_after_signature_validation(): void
    {
        $payload = $this->payload(['event_type' => 'unknown.event']);

        $this->sendSigned($payload)->assertUnprocessable();

        $this->assertDatabaseCount('integration_event_inbox', 0);
    }
}
