<?php

namespace Tests\Feature\Api;

use App\Models\Publication;
use App\Models\PublicationExecution;
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

        $groupA = WhatsAppGroup::query()->create([
            'social_account_id' => $accountA->id,
            'whatsapp_id' => 'group-a@g.us',
            'name' => 'Grupo A',
            'active' => true,
        ]);
        $groupB = WhatsAppGroup::query()->create([
            'social_account_id' => $accountB->id,
            'whatsapp_id' => 'group-b@g.us',
            'name' => 'Grupo B',
            'active' => true,
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
        $publication->whatsappGroups()->attach([$groupA->id, $groupB->id]);
        app(WorkspaceContext::class)->clear();

        $response = $this->withToken('test-bot-token')
            ->getJson('/api/bot/publications?instance_id=agent-wa-a');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(
            [$groupA->id],
            collect($response->json('data.0.whatsapp_groups'))->pluck('id')->all(),
        );
    }

    public function test_execution_start_and_completion_require_the_destination_owner_agent(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Execution agents', 'slug' => 'execution-agents']);
        config(['services.bot_api.workspace_id' => $workspace->id]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $account = $this->createWhatsAppAccount($workspace, 'agent-wa-owner');
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

        $this->withToken('test-bot-token')
            ->postJson('/api/bot/executions/start', $payload)
            ->assertForbidden();

        $this->withToken('test-bot-token')
            ->postJson('/api/bot/executions/start', [...$payload, 'instance_id' => 'agent-wa-other'])
            ->assertForbidden();

        $started = $this->withToken('test-bot-token')
            ->postJson('/api/bot/executions/start', [...$payload, 'instance_id' => 'agent-wa-owner'])
            ->assertOk()
            ->assertJsonPath('data.agent_instance_id', 'agent-wa-owner');

        $executionId = $started->json('data.id');

        $this->withToken('test-bot-token')
            ->postJson("/api/bot/executions/{$executionId}/complete", [
                'instance_id' => 'agent-wa-other',
                'external_id' => 'wrong-agent-message',
            ])
            ->assertForbidden();

        $this->withToken('test-bot-token')
            ->postJson("/api/bot/executions/{$executionId}/complete", [
                'instance_id' => 'agent-wa-owner',
                'external_id' => 'owned-message',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');
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
