<?php

namespace Tests\\Feature;

use App\\Models\\Workspace;
use App\\Models\\WorkspaceDubaiShopIntegration;
use App\\Services\\Workspaces\\WorkspaceContext;
use Illuminate\\Database\\QueryException;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\Queue;
use Illuminate\\Support\\Str;
use Tests\\TestCase;

class DubaiShopInstallationIdentityTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'installation-identity-test-secret-with-entropy';

    private function eventPayload(): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'event_type' => 'inventory.changed',
            'contract_version' => 'v1',
            'aggregate_external_id' => (string) Str::uuid(),
            'revision' => 1,
            'occurred_at' => now()->toISOString(),
            'data' => ['availability' => ['warehouses' => []]],
        ];
    }

    public function test_database_rejects_pairing_one_installation_to_two_workspaces(): void
    {
        $installationId = (string) Str::uuid();
        $first = Workspace::query()->create(['name' => 'First', 'slug' => 'first']);
        $second = Workspace::query()->create(['name' => 'Second', 'slug' => 'second']);

        app(WorkspaceContext::class)->set((int) $first->id);
        WorkspaceDubaiShopIntegration::query()->create([
            'installation_id' => $installationId,
            'api_base_url' => 'https://first.example.test',
            'api_token' => 'first-token',
            'webhook_key_id' => 'first-key',
            'webhook_secret' => self::SECRET,
            'enabled' => true,
        ]);

        app(WorkspaceContext::class)->set((int) $second->id);

        $this->expectException(QueryException::class);
        WorkspaceDubaiShopIntegration::query()->create([
            'installation_id' => $installationId,
            'api_base_url' => 'https://second.example.test',
            'api_token' => 'second-token',
            'webhook_key_id' => 'second-key',
            'webhook_secret' => self::SECRET,
            'enabled' => true,
        ]);
    }

    public function test_paired_installation_identity_cannot_be_changed(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Immutable', 'slug' => 'immutable']);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $integration = WorkspaceDubaiShopIntegration::query()->create([
            'installation_id' => (string) Str::uuid(),
            'api_base_url' => 'https://shop.example.test',
            'api_token' => 'api-token',
            'webhook_key_id' => 'immutable-key',
            'webhook_secret' => self::SECRET,
            'enabled' => true,
        ]);

        $this->expectException(\\RuntimeException::class);
        $integration->update(['installation_id' => (string) Str::uuid()]);
    }

    public function test_v2_webhook_signature_is_bound_to_the_paired_installation(): void
    {
        Queue::fake();
        $workspace = Workspace::query()->create(['name' => 'Bound webhook', 'slug' => 'bound-webhook']);
        $installationId = (string) Str::uuid();
        app(WorkspaceContext::class)->set((int) $workspace->id);

        WorkspaceDubaiShopIntegration::query()->create([
            'installation_id' => $installationId,
            'api_base_url' => 'https://shop.example.test',
            'api_token' => 'api-token',
            'webhook_key_id' => 'bound-key',
            'webhook_secret' => self::SECRET,
            'enabled' => true,
        ]);

        $payload = $this->eventPayload();
        $timestamp = (string) now()->timestamp;
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$installationId.'.'.$body, self::SECRET);

        $this->call('POST', '/api/integrations/dubai-shop/v1/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DUBAISHOP_KEY_ID' => 'bound-key',
            'HTTP_X_DUBAISHOP_TIMESTAMP' => $timestamp,
            'HTTP_X_DUBAISHOP_SIGNATURE_VERSION' => 'v2',
            'HTTP_X_DUBAISHOP_INSTALLATION_ID' => $installationId,
            'HTTP_X_DUBAISHOP_SIGNATURE' => $signature,
        ], $body)->assertAccepted();

        $this->assertDatabaseHas('integration_event_inbox', [
            'event_id' => $payload['event_id'],
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_v2_webhook_rejects_a_different_installation_identity(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Wrong identity', 'slug' => 'wrong-identity']);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        WorkspaceDubaiShopIntegration::query()->create([
            'installation_id' => (string) Str::uuid(),
            'api_base_url' => 'https://shop.example.test',
            'api_token' => 'api-token',
            'webhook_key_id' => 'wrong-identity-key',
            'webhook_secret' => self::SECRET,
            'enabled' => true,
        ]);

        $wrongInstallationId = (string) Str::uuid();
        $payload = $this->eventPayload();
        $timestamp = (string) now()->timestamp;
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$wrongInstallationId.'.'.$body, self::SECRET);

        $this->call('POST', '/api/integrations/dubai-shop/v1/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DUBAISHOP_KEY_ID' => 'wrong-identity-key',
            'HTTP_X_DUBAISHOP_TIMESTAMP' => $timestamp,
            'HTTP_X_DUBAISHOP_SIGNATURE_VERSION' => 'v2',
            'HTTP_X_DUBAISHOP_INSTALLATION_ID' => $wrongInstallationId,
            'HTTP_X_DUBAISHOP_SIGNATURE' => $signature,
        ], $body)->assertUnauthorized();

        $this->assertDatabaseMissing('integration_event_inbox', ['event_id' => $payload['event_id']]);
    }
}
