<?php

namespace Tests\Feature\Security;

use App\Models\BotAgent;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotAgentHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoped_agent_health_exposes_only_assigned_accounts_and_safe_connection_metadata(): void
    {
        $workspace = $this->workspace();
        $account = $this->account(SocialAccount::PLATFORM_WHATSAPP, SocialAccount::TYPE_WHATSAPP);
        $other = $this->account(SocialAccount::PLATFORM_FACEBOOK, SocialAccount::TYPE_FACEBOOK_PROFILE);
        SocialAccountConnection::query()->create([
            'social_account_id' => $account->id,
            'connection_type' => SocialAccountConnection::TYPE_WPP_CONNECT_QR,
            'provider' => 'wppconnect',
            'status' => 'connected',
            'agent_instance_id' => 'wbot-wa-01',
            'session_reference' => 'opaque-session-reference',
            'provider_connection_id' => 'whatsapp-bot',
        ]);
        $this->agent([$account->id], 'wbot-wa-01', 'health-token');

        $response = $this->withToken('health-token')
            ->getJson('/api/bot/agent/health?instance_id=wbot-wa-01')
            ->assertOk()
            ->assertJsonPath('data.workspace_id', $workspace->id)
            ->assertJsonPath('data.instance_id', 'wbot-wa-01')
            ->assertJsonPath('data.authorization_mode', 'scoped')
            ->assertJsonPath('data.authorized_social_account_ids.0', $account->id)
            ->assertJsonPath('data.accounts.0.id', $account->id)
            ->assertJsonPath('data.accounts.0.connections.0.agent_instance_id', 'wbot-wa-01');

        $this->assertCount(1, $response->json('data.accounts'));
        $this->assertArrayNotHasKey('session_reference', $response->json('data.accounts.0.connections.0'));
        $this->assertArrayNotHasKey('provider_connection_id', $response->json('data.accounts.0.connections.0'));
        $this->assertArrayNotHasKey('credentials', $response->json('data.accounts.0.connections.0'));
        $this->assertNotSame($other->id, $response->json('data.accounts.0.id'));
    }

    public function test_scoped_agent_health_rejects_a_different_instance_identity(): void
    {
        $this->workspace();
        $account = $this->account(SocialAccount::PLATFORM_WHATSAPP, SocialAccount::TYPE_WHATSAPP);
        $this->agent([$account->id], 'wbot-wa-01', 'health-token');

        $this->withToken('health-token')
            ->getJson('/api/bot/agent/health?instance_id=wbot-wa-02')
            ->assertForbidden();
    }

    public function test_legacy_health_is_rejected_when_workspace_has_social_accounts(): void
    {
        $workspace = Workspace::query()->create([
            'name' => 'Managed health workspace',
            'slug' => 'managed-health-workspace',
        ]);
        config(['services.bot_api.workspace_id' => $workspace->id]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'display_name' => 'Cuenta gestionada',
        ]);

        app(WorkspaceContext::class)->clear();

        $this->withToken('test-bot-token')
            ->getJson('/api/bot/agent/health?instance_id=WBot')
            ->assertForbidden();
    }

    public function test_legacy_health_is_limited_to_its_configured_workspace(): void
    {
        $workspace = $this->workspace();
        $this->account(SocialAccount::PLATFORM_WHATSAPP, SocialAccount::TYPE_WHATSAPP);
        config([
            'services.bot_api.token' => 'legacy-health-token',
            'services.bot_api.workspace_id' => $workspace->id,
            'services.bot_api.command_bot_name' => 'WBot',
        ]);

        $response = $this->withToken('legacy-health-token')
            ->getJson('/api/bot/agent/health?instance_id=WBot')
            ->assertOk()
            ->assertJsonPath('data.authorization_mode', 'legacy_workspace')
            ->assertJsonPath('data.workspace_id', $workspace->id);

        $this->assertCount(1, $response->json('data.accounts'));
    }

    private function workspace(): Workspace
    {
        $workspace = Workspace::query()->create([
            'name' => 'Health workspace',
            'slug' => 'health-'.str()->random(10),
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        return $workspace;
    }

    private function account(string $platform, string $type): SocialAccount
    {
        return SocialAccount::query()->create([
            'platform' => $platform,
            'account_type' => $type,
            'display_name' => $type,
        ]);
    }

    private function agent(array $accountIds, string $instanceId, string $token): BotAgent
    {
        return BotAgent::query()->create([
            'name' => 'Health agent',
            'instance_id' => $instanceId,
            'token_hash' => hash('sha256', $token),
            'social_account_ids' => $accountIds,
            'is_active' => true,
        ]);
    }
}
