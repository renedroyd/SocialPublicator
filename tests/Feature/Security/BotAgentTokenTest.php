<?php

namespace Tests\Feature\Security;

use App\Models\BotAgent;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BotAgentTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_token_is_bound_to_its_instance_and_authorized_accounts(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Scoped agent', 'slug' => 'scoped-agent']);
        config([
            'services.bot_api.token' => '',
            'services.bot_api.workspace_id' => null,
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $allowedAccount = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'display_name' => 'Cuenta autorizada',
        ]);
        $otherAccount = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'display_name' => 'Cuenta no autorizada',
        ]);

        $token = 'one-time-agent-token';
        BotAgent::query()->create([
            'name' => 'Agente de prueba',
            'instance_id' => 'agent-test-01',
            'token_hash' => hash('sha256', $token),
            'social_account_ids' => [$allowedAccount->id],
            'is_active' => true,
        ]);
        app(WorkspaceContext::class)->clear();

        $this->withToken($token)->postJson('/api/bot/whatsapp/connection/state', [
            'social_account_id' => $otherAccount->id,
            'agent_instance_id' => 'agent-test-01',
            'status' => 'connected',
        ])->assertForbidden();

        $this->withToken($token)->postJson('/api/bot/whatsapp/groups/sync', [
            'agent_instance_id' => 'agent-test-01',
            'groups' => [],
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/bot/whatsapp/connection/state', [
            'social_account_id' => $allowedAccount->id,
            'agent_instance_id' => 'another-agent',
            'status' => 'connected',
        ])->assertForbidden();

        $this->withToken($token)->postJson('/api/bot/whatsapp/connection/state', [
            'social_account_id' => $allowedAccount->id,
            'agent_instance_id' => 'agent-test-01',
            'status' => 'connected',
        ])->assertOk();
    }

    public function test_inactive_agent_token_is_rejected(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Inactive agent', 'slug' => 'inactive-agent']);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        $token = 'inactive-agent-token';
        BotAgent::query()->create([
            'name' => 'Agente inactivo',
            'instance_id' => 'agent-inactive',
            'token_hash' => hash('sha256', $token),
            'social_account_ids' => [],
            'is_active' => false,
        ]);
        app(WorkspaceContext::class)->clear();

        config(['services.bot_api.token' => 'legacy-token']);

        $this->withToken($token)->postJson('/api/bot/status', [
            'name' => 'WBot',
            'instance_id' => 'agent-inactive',
            'platform' => 'whatsapp',
            'status' => 'online',
        ])->assertUnauthorized();
    }

    public function test_legacy_token_cannot_impersonate_a_non_default_instance(): void
    {
        config([
            'services.bot_api.token' => 'legacy-token',
            'services.bot_api.command_bot_name' => 'WBot',
        ]);

        $this->withToken('legacy-token')->postJson('/api/bot/status', [
            'name' => 'WBot',
            'instance_id' => 'agent-other',
            'platform' => 'whatsapp',
            'status' => 'online',
        ])->assertForbidden();
    }

    public function test_cli_issues_a_token_once_and_persists_only_its_hash(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Token issue', 'slug' => 'token-issue']);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        BotAgent::query()->create([
            'name' => 'Agente de token',
            'instance_id' => 'agent-token-issue',
            'social_account_ids' => [],
            'is_active' => true,
        ]);
        app(WorkspaceContext::class)->clear();

        $exitCode = Artisan::call('bot-agent:issue-token', [
            'workspace_id' => $workspace->id,
            'instance_id' => 'agent-token-issue',
        ]);
        $output = Artisan::output();
        preg_match('/\b[a-zA-Z0-9]{64}\b/', $output, $matches);
        $token = $matches[0] ?? null;

        $this->assertSame(0, $exitCode);
        $this->assertNotNull($token);
        $this->assertSame(
            hash('sha256', $token),
            BotAgent::withoutGlobalScopes()->where('instance_id', 'agent-token-issue')->value('token_hash'),
        );
    }

    public function test_authenticated_agent_identity_overrides_conflicting_session_payload_identity(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Agent identity', 'slug' => 'agent-identity']);
        config([
            'services.bot_api.token' => '',
            'services.bot_api.workspace_id' => null,
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'display_name' => 'Cuenta de identidad',
        ]);
        $token = 'agent-identity-token';
        BotAgent::query()->create([
            'name' => 'Agente de identidad',
            'instance_id' => 'agent-identity-01',
            'token_hash' => hash('sha256', $token),
            'social_account_ids' => [$account->id],
            'is_active' => true,
        ]);
        app(WorkspaceContext::class)->clear();

        $this->withToken($token)->postJson('/api/bot/whatsapp/connection/state', [
            'social_account_id' => $account->id,
            'instance_id' => 'agent-identity-01',
            'agent_instance_id' => 'attacker-selected-instance',
            'status' => 'connected',
        ])->assertOk();

        $this->assertDatabaseHas('social_account_connections', [
            'social_account_id' => $account->id,
            'agent_instance_id' => 'agent-identity-01',
            'connection_type' => 'wppconnect_qr',
        ]);
    }

    public function test_scoped_agent_cannot_use_legacy_facebook_group_sync_alias_without_account_id(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Facebook sync alias', 'slug' => 'facebook-sync-alias']);
        config([
            'services.bot_api.token' => '',
            'services.bot_api.workspace_id' => null,
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PROFILE,
            'display_name' => 'Perfil de Facebook',
        ]);
        $token = 'facebook-alias-token';
        BotAgent::query()->create([
            'name' => 'Agente Facebook',
            'instance_id' => 'agent-facebook-01',
            'token_hash' => hash('sha256', $token),
            'social_account_ids' => [$account->id],
            'is_active' => true,
        ]);
        app(WorkspaceContext::class)->clear();

        $this->withToken($token)->postJson('/api/bot/facebook/grupos/sincronizar', [
            'agent_instance_id' => 'agent-facebook-01',
            'groups' => [['facebook_id' => 'group-1', 'name' => 'Grupo de prueba']],
        ])->assertStatus(422)
            ->assertJsonPath('message', 'A social_account_id is required for agent group synchronization.');
    }

}
