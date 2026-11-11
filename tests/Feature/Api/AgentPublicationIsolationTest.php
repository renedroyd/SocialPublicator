<?php

namespace Tests\Feature\Api;

use App\Models\BotAgent;
use App\Models\FacebookGroup;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\WhatsAppGroup;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentPublicationIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.bot_api.token' => 'test-bot-token']);
    }

    public function test_publication_feed_returns_only_groups_assigned_to_the_requesting_agent(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Publication agents', 'slug' => 'publication-agents']);
        config(['services.bot_api.workspace_id' => $workspace->id]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $accountA = $this->createWhatsAppAccount($workspace, 'agent-wa-a');
        $accountB = $this->createWhatsAppAccount($workspace, 'agent-wa-b');
        $tokenA = 'token-agent-wa-a';
        BotAgent::query()->create([
            'name' => 'Agent A',
            'instance_id' => 'agent-wa-a',
            'token_hash' => hash('sha256', $tokenA),
            'social_account_ids' => [$accountA->id],
            'is_active' => true,
        ]);

        $groupA = WhatsAppGroup::query()->create([
            'social_account_id' => $accountA->id,
            'whatsapp_id' => 'group-a@g.us',
            'name' => 'Grupo A',
            'active' => true,
            'is_admin' => true,
        ]);
        $groupB = WhatsAppGroup::query()->create([
            'social_account_id' => $accountB->id,
            'whatsapp_id' => 'group-b@g.us',
            'name' => 'Grupo B',
            'active' => true,
            'is_admin' => true,
        ]);
        $nonAdminGroup = WhatsAppGroup::query()->create([
            'social_account_id' => $accountA->id,
            'whatsapp_id' => 'group-a-member@g.us',
            'name' => 'Grupo A sin administración',
            'active' => true,
            'is_admin' => false,
        ]);

        $publication = Publication::query()->create([
            'date' => now()->toDateString(),
            'time' => '23:59',
            'image' => 'publications/agents.jpg',
            'text' => 'Publicación multicuenta',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'none',
        ]);
        $publication->whatsappGroups()->attach([$groupA->id, $groupB->id, $nonAdminGroup->id]);
        app(WorkspaceContext::class)->clear();

        $response = $this->withToken($tokenA)
            ->getJson('/api/bot/publications?instance_id=agent-wa-a');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(
            [$groupA->id],
            collect($response->json('data.0.whatsapp_groups'))->pluck('id')->all(),
        );
    }

    public function test_two_agents_receive_only_their_own_selected_whatsapp_and_facebook_destinations(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Multi-account agents', 'slug' => 'multi-account-agents']);
        config(['services.bot_api.workspace_id' => $workspace->id]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $whatsappA = $this->createWhatsAppAccount($workspace, 'agent-a');
        $whatsappB = $this->createWhatsAppAccount($workspace, 'agent-b');
        $facebookA = $this->createFacebookAccount($workspace, 'agent-a');
        $facebookB = $this->createFacebookAccount($workspace, 'agent-b');

        $tokenA = 'token-multi-agent-a';
        $tokenB = 'token-multi-agent-b';
        BotAgent::query()->create([
            'name' => 'Agent A',
            'instance_id' => 'agent-a',
            'token_hash' => hash('sha256', $tokenA),
            'social_account_ids' => [$whatsappA->id, $facebookA->id],
            'is_active' => true,
        ]);
        BotAgent::query()->create([
            'name' => 'Agent B',
            'instance_id' => 'agent-b',
            'token_hash' => hash('sha256', $tokenB),
            'social_account_ids' => [$whatsappB->id, $facebookB->id],
            'is_active' => true,
        ]);

        $waGroupA = WhatsAppGroup::query()->create([
            'social_account_id' => $whatsappA->id,
            'whatsapp_id' => 'wa-a@g.us',
            'name' => 'WhatsApp A',
            'active' => true,
            'is_admin' => true,
        ]);
        $waGroupB = WhatsAppGroup::query()->create([
            'social_account_id' => $whatsappB->id,
            'whatsapp_id' => 'wa-b@g.us',
            'name' => 'WhatsApp B',
            'active' => true,
            'is_admin' => true,
        ]);
        $fbGroupA = \\App\\Models\\FacebookGroup::query()->create([
            'social_account_id' => $facebookA->id,
            'facebook_id' => 'fb-a',
            'name' => 'Facebook A',
            'url' => 'https://www.facebook.com/groups/fb-a',
            'active' => true,
        ]);
        $fbGroupB = \\App\\Models\\FacebookGroup::query()->create([
            'social_account_id' => $facebookB->id,
            'facebook_id' => 'fb-b',
            'name' => 'Facebook B',
            'url' => 'https://www.facebook.com/groups/fb-b',
            'active' => true,
        ]);

        $publication = Publication::query()->create([
            'date' => now()->toDateString(),
            'time' => '23:59',
            'image' => 'publications/multi-account.jpg',
            'text' => 'Publicación para dos cuentas',
            'destination' => 'both',
            'active' => true,
            'recurrence_type' => 'none',
        ]);
        $publication->whatsappGroups()->attach([$waGroupA->id, $waGroupB->id]);
        $publication->facebookGroups()->attach([$fbGroupA->id, $fbGroupB->id]);
        app(WorkspaceContext::class)->clear();

        $responseA = $this->withToken($tokenA)->getJson('/api/bot/publications?instance_id=agent-a');
        $responseA->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame([$waGroupA->id], collect($responseA->json('data.0.whatsapp_groups'))->pluck('id')->all());
        $this->assertSame([$fbGroupA->id], collect($responseA->json('data.0.facebook_groups'))->pluck('id')->all());

        $responseB = $this->withToken($tokenB)->getJson('/api/bot/publications?instance_id=agent-b');
        $responseB->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame([$waGroupB->id], collect($responseB->json('data.0.whatsapp_groups'))->pluck('id')->all());
        $this->assertSame([$fbGroupB->id], collect($responseB->json('data.0.facebook_groups'))->pluck('id')->all());
    }

    public function test_execution_start_and_completion_require_the_destination_owner_agent(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Execution agents', 'slug' => 'execution-agents']);
        config(['services.bot_api.workspace_id' => $workspace->id]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $account = $this->createWhatsAppAccount($workspace, 'agent-wa-owner');
        $ownerToken = 'token-agent-wa-owner';
        $otherToken = 'token-agent-wa-other';
        BotAgent::query()->create([
            'name' => 'Owner agent',
            'instance_id' => 'agent-wa-owner',
            'token_hash' => hash('sha256', $ownerToken),
            'social_account_ids' => [$account->id],
            'is_active' => true,
        ]);
        BotAgent::query()->create([
            'name' => 'Other agent',
            'instance_id' => 'agent-wa-other',
            'token_hash' => hash('sha256', $otherToken),
            'social_account_ids' => [$account->id],
            'is_active' => true,
        ]);
        $group = WhatsAppGroup::query()->create([
            'social_account_id' => $account->id,
            'whatsapp_id' => 'owned-group@g.us',
            'name' => 'Grupo propiedad del agente',
            'active' => true,
        ]);
        $publication = Publication::query()->create([
            'date' => now()->toDateString(),
            'time' => '23:59',
            'image' => 'publications/agent-owner.jpg',
            'text' => 'Ejecución protegida',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'none',
        ]);
        app(WorkspaceContext::class)->clear();

        $payload = [
            'publication_id' => $publication->id,
            'occurrence_id' => 'agent-owned-occurrence',
            'channel' => 'whatsapp',
            'target_key' => $group->whatsapp_id,
        ];

        $this->withToken($ownerToken)
            ->postJson('/api/bot/executions/start', $payload)
            ->assertForbidden();

        $this->withToken($otherToken)
            ->postJson('/api/bot/executions/start', [...$payload, 'instance_id' => 'agent-wa-other'])
            ->assertForbidden();

        $started = $this->withToken($ownerToken)
            ->postJson('/api/bot/executions/start', [...$payload, 'instance_id' => 'agent-wa-owner'])
            ->assertOk()
            ->assertJsonPath('data.agent_instance_id', 'agent-wa-owner');

        $executionId = $started->json('data.id');

        $this->withToken($otherToken)
            ->postJson("/api/bot/executions/{$executionId}/complete", [
                'instance_id' => 'agent-wa-other',
                'external_id' => 'wrong-agent-message',
            ])
            ->assertForbidden();

        $this->withToken($ownerToken)
            ->postJson("/api/bot/executions/{$executionId}/complete", [
                'instance_id' => 'agent-wa-owner',
                'external_id' => 'owned-message',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');
    }

    private function createFacebookAccount(Workspace $workspace, string $agentId): SocialAccount
    {
        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PROFILE,
            'display_name' => 'Facebook '.$agentId,
        ]);

        SocialAccountConnection::query()->create([
            'social_account_id' => $account->id,
            'connection_type' => SocialAccountConnection::TYPE_BROWSER_SESSION,
            'provider' => 'puppeteer',
            'status' => 'connected',
            'agent_instance_id' => $agentId,
            'session_reference' => 'browser:'.$agentId,
            'provider_connection_id' => 'facebook-'.$agentId,
        ]);

        return $account;
    }

    private function createWhatsAppAccount(Workspace $workspace, string $agentId): SocialAccount
    {
        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'display_name' => 'Cuenta '.$agentId,
        ]);

        SocialAccountConnection::query()->create([
            'social_account_id' => $account->id,
            'connection_type' => SocialAccountConnection::TYPE_WPP_CONNECT_QR,
            'provider' => 'wppconnect',
            'status' => 'connected',
            'agent_instance_id' => $agentId,
            'session_reference' => 'wppconnect:'.$agentId,
            'provider_connection_id' => 'session-'.$agentId,
        ]);

        return $account;
    }
}
