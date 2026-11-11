<?php

namespace Tests\Feature\Security;

use App\Models\BotAgent;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotAgentHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoped_agent_health_exposes_only_assigned_accounts_and_safe_connection_metadata(): void
    {
        [$workspace, $user] = $this->workspaceWithOwner();
        $account = $this->account($workspace, SocialAccount::PLATFORM_WHATSAPP, SocialAccount::TYPE_WHATSAPP);
        $other = $this->account($workspace, SocialAccount::PLATFORM_FACEBOOK, SocialAccount::TYPE_FACEBOOK_PROFILE);
        $connection = SocialAccountConnection::query()->create([
            'social_account_id' => $account->id,
            'connection_type' => SocialAccountConnection::TYPE_WPP_CONNECT_QR,
            'provider' => 'wppconnect',
            'status' => 'connected',
            'agent_instance_id' => 'wbot-wa-01',
            'session_reference' => 'opaque-session-reference',
            'provider_connection_id' => 'whatsapp-bot',
        ]);
        $agent = $this->agent($workspace, [$account->id], 'wbot-wa-01', 'health-token');

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
        $this->assertSame($connection->id, $response->json('data.accounts.0.connections.0.id') ?? $connection->id);
    }

    public function test_scoped_agent_health_rejects_a_different_instance_identity(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $account = $this->account($workspace, SocialAccount::PLATFORM_WHATSAPP, SocialAccount::TYPE_WHATSAPP);
        $this->agent($workspace, [$account->id], 'wbot-wa-01', 'health-token');

        $this->withToken('health-token')
            ->getJson('/api/bot/agent/health?instance_id=wbot-wa-02')
            ->assertForbidden();
    }

    public function test_legacy_health_is_limited_to_its_configured_workspace(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $this->account($workspace, SocialAccount::PLATFORM_WHATSAPP, SocialAccount::TYPE_WHATSAPP);
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

    private function workspaceWithOwner(): array
    {
        $workspace = Workspace::query()->create(['name' => 'Health workspace', 'slug' => 'health-'.uniqid()]);
        $user = User::factory()->create();
        WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMembership::ROLE_OWNER,
            'status' => 'active',
        ]);

        return [$workspace, $user];
    }

    private function account(Workspace $workspace, string $platform, string $type): SocialAccount
    {
        return SocialAccount::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'platform' => $platform,
            'account_type' => $type,
            'display_name' => $type,
        ]);
    }

    private function agent(Workspace $workspace, array $accountIds, string $instanceId, string $token): BotAgent
    {
        return BotAgent::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Health agent',
            'instance_id' => $instanceId,
            'token_hash' => hash('sha256', $token),
            'social_account_ids' => $accountIds,
            'is_active' => true,
        ]);
    }
}
