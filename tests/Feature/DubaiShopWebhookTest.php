<?php

namespace Tests\Feature;

use App\Jobs\ProcessDubaiShopEvent;
use App\Models\Workspace;
use App\Models\WorkspaceDubaiShopIntegration;
use App\Services\Workspaces\WorkspaceContext;
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
        $workspace = Workspace::query()->first()
            ?? Workspace::query()->create(['name' => 'Legacy webhook', 'slug' => 'legacy-webhook']);
        config()->set('services.dubai_shop.workspace_id', $workspace->id);
        config()->set('services.dubai_shop.api_base_url', 'https://legacy-shop.example.test');
        config()->set('services.dubai_shop.api_token', 'legacy-api-token');
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

    public function test_workspace_hmac_key_routes_event_to_its_workspace_and_disabled_keys_are_rejected(): void
    {
        Queue::fake();
        config()->set('services.dubai_shop.webhook_keys', []);

        $workspace = Workspace::query()->create(['name' => 'Webhook workspace', 'slug' => 'webhook-workspace']);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://shop.example.test',
            'api_token' => 'workspace-api-token',
            'webhook_key_id' => 'workspace-key',
            'webhook_secret' => self::SECRET,
            'enabled' => true,
        ]);

        $payload = $this->payload();
        $timestamp = now()->timestamp;
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);

        $this->call('POST', '/api/integrations/dubai-shop/v1/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DUBAISHOP_KEY_ID' => 'workspace-key',
            'HTTP_X_DUBAISHOP_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_DUBAISHOP_SIGNATURE' => $signature,
        ], $body)->assertAccepted();

        $this->assertDatabaseHas('integration_event_inbox', [
            'event_id' => $payload['event_id'],
            'workspace_id' => $workspace->id,
        ]);

        $disabledWorkspace = Workspace::query()->create(['name' => 'Disabled webhook', 'slug' => 'disabled-webhook']);
        app(WorkspaceContext::class)->set((int) $disabledWorkspace->id);
        WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://disabled.example.test',
            'api_token' => 'disabled-token',
            'webhook_key_id' => 'disabled-key',
            'webhook_secret' => self::SECRET,
            'enabled' => false,
        ]);

        $disabledSignature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);
        $this->call('POST', '/api/integrations/dubai-shop/v1/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DUBAISHOP_KEY_ID' => 'disabled-key',
            'HTTP_X_DUBAISHOP_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_DUBAISHOP_SIGNATURE' => $disabledSignature,
        ], $body)->assertUnauthorized();
    }

    public function test_enabled_integration_without_api_credentials_rejects_hmac_webhook(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Incomplete webhook', 'slug' => 'incomplete-webhook']);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        WorkspaceDubaiShopIntegration::query()->create([
            'webhook_key_id' => 'incomplete-key',
            'webhook_secret' => self::SECRET,
            'enabled' => true,
        ]);

        $payload = $this->payload();
        $timestamp = now()->timestamp;
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);

        $this->call('POST', '/api/integrations/dubai-shop/v1/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DUBAISHOP_KEY_ID' => 'incomplete-key',
            'HTTP_X_DUBAISHOP_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_DUBAISHOP_SIGNATURE' => $signature,
        ], $body)->assertUnauthorized();
    }

    public function test_previous_hmac_key_is_accepted_only_until_its_expiration(): void
    {
        Queue::fake();
        $workspace = Workspace::query()->create(['name' => 'Rotating key', 'slug' => 'rotating-key']);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://rotating-shop.example.test',
            'api_token' => 'rotating-api-token',
            'webhook_key_id' => 'current-key',
            'webhook_secret' => 'current-secret-with-enough-entropy',
            'previous_webhook_key_id' => 'previous-key',
            'previous_webhook_secret' => self::SECRET,
            'previous_webhook_secret_expires_at' => now()->addHours(24),
            'enabled' => true,
        ]);

        $payload = $this->payload();
        $timestamp = now()->timestamp;
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);

        $this->call('POST', '/api/integrations/dubai-shop/v1/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DUBAISHOP_KEY_ID' => 'previous-key',
            'HTTP_X_DUBAISHOP_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_DUBAISHOP_SIGNATURE' => $signature,
        ], $body)->assertAccepted();

        $this->assertDatabaseHas('integration_event_inbox', ['event_id' => $payload['event_id']]);
    }

    public function test_previous_hmac_key_is_rejected_after_expiration(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Expired rotating key', 'slug' => 'expired-rotating-key']);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://expired-shop.example.test',
            'api_token' => 'expired-api-token',
            'webhook_key_id' => 'active-key',
            'webhook_secret' => 'active-secret-with-enough-entropy',
            'previous_webhook_key_id' => 'expired-key',
            'previous_webhook_secret' => self::SECRET,
            'previous_webhook_secret_expires_at' => now()->subSecond(),
            'enabled' => true,
        ]);

        $payload = $this->payload();
        $timestamp = now()->timestamp;
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);

        $this->call('POST', '/api/integrations/dubai-shop/v1/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DUBAISHOP_KEY_ID' => 'expired-key',
            'HTTP_X_DUBAISHOP_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_DUBAISHOP_SIGNATURE' => $signature,
        ], $body)->assertUnauthorized();

        $this->assertDatabaseCount('integration_event_inbox', 0);
    }

    public function test_active_hmac_key_remains_valid_during_rotation(): void
    {
        Queue::fake();
        $workspace = Workspace::query()->create(['name' => 'Active rotating key', 'slug' => 'active-rotating-key']);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://active-shop.example.test',
            'api_token' => 'active-api-token',
            'webhook_key_id' => 'active-key',
            'webhook_secret' => 'active-secret-with-enough-entropy',
            'previous_webhook_key_id' => 'previous-key',
            'previous_webhook_secret' => self::SECRET,
            'previous_webhook_secret_expires_at' => now()->addHours(24),
            'enabled' => true,
        ]);

        $payload = $this->payload();
        $timestamp = now()->timestamp;
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $secret = 'active-secret-with-enough-entropy';
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $this->call('POST', '/api/integrations/dubai-shop/v1/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DUBAISHOP_KEY_ID' => 'active-key',
            'HTTP_X_DUBAISHOP_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_DUBAISHOP_SIGNATURE' => $signature,
        ], $body)->assertAccepted();
    }
}
