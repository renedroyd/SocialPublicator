<?php

namespace Tests\Feature\Security;

use App\Models\BotAgent;
use App\Models\BotCommand;
use App\Models\Publication;
use App\Models\PublicationExecution;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\WhatsAppGroup;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotAgentExecutionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bot_api.token' => '',
            'services.bot_api.workspace_id' => null,
            'services.bot_api.command_bot_name' => 'WBot',
        ]);
    }

    public function test_agent_can_only_start_and_complete_executions_for_assigned_accounts(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Execution scope', 'slug' => 'execution-scope']);
        $context = app(WorkspaceContext::class);
        $context->set((int) $workspace->id);

        $allowedAccount = $this->createWhatsAppAccount('Cuenta permitida', 'allowed-account');
        $deniedAccount = $this->createWhatsAppAccount('Cuenta denegada', 'denied-account');
        $this->connectAccountToAgent($allowedAccount, 'agent-execution-01');
        $this->connectAccountToAgent($deniedAccount, 'agent-execution-01');

        $allowedGroup = $this->createGroup($allowedAccount, 'allowed-group@g.us');
        $deniedGroup = $this->createGroup($deniedAccount, 'denied-group@g.us');
        $publication = $this->createPublication('Publicación de prueba');

        $deniedExecution = PublicationExecution::query()->create([
            'publication_id' => $publication->id,
            'occurrence_id' => 'denied-existing-execution',
            'channel' => 'whatsapp',
            'target_key' => $deniedGroup->whatsapp_id,
            'agent_instance_id' => 'agent-execution-01',
            'status' => 'running',
            'attempts' => 1,
            'started_at' => now(),
        ]);

        $token = 'agent-execution-scope-token';
        BotAgent::query()->create([
            'name' => 'Agente de ejecución',
            'instance_id' => 'agent-execution-01',
            'token_hash' => hash('sha256', $token),
            'social_account_ids' => [$allowedAccount->id],
            'is_active' => true,
        ]);
        $context->clear();

        $this->withToken($token)->postJson('/api/bot/executions/start', [
            'publication_id' => $publication->id,
            'occurrence_id' => 'denied-new-execution',
            'channel' => 'whatsapp',
            'target_key' => $deniedGroup->whatsapp_id,
            'instance_id' => 'agent-execution-01',
        ])->assertForbidden();

        $this->withToken($token)->postJson('/api/bot/executions/'.$deniedExecution->id.'/complete', [
            'instance_id' => 'agent-execution-01',
            'external_id' => 'must-not-complete',
        ])->assertForbidden();

        $started = $this->withToken($token)->postJson('/api/bot/executions/start', [
            'publication_id' => $publication->id,
            'occurrence_id' => 'allowed-new-execution',
            'channel' => 'whatsapp',
            'target_key' => $allowedGroup->whatsapp_id,
            'instance_id' => 'agent-execution-01',
        ])->assertOk()
            ->assertJsonPath('data.agent_instance_id', 'agent-execution-01');

        $executionId = $started->json('data.id');

        $this->withToken($token)->postJson('/api/bot/executions/'.$executionId.'/complete', [
            'instance_id' => 'agent-execution-01',
            'external_id' => 'allowed-message-id',
        ])->assertOk()->assertJsonPath('data.status', 'succeeded');
    }

    public function test_agent_claims_only_commands_for_assigned_accounts_and_cannot_complete_legacy_commands(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Command scope', 'slug' => 'command-scope']);
        $context = app(WorkspaceContext::class);
        $context->set((int) $workspace->id);

        $allowedAccount = $this->createWhatsAppAccount('Cuenta de comandos permitida', 'command-allowed');
        $deniedAccount = $this->createWhatsAppAccount('Cuenta de comandos denegada', 'command-denied');
        $allowedGroup = $this->createGroup($allowedAccount, 'command-allowed@g.us');
        $deniedGroup = $this->createGroup($deniedAccount, 'command-denied@g.us');
        $publication = $this->createPublication('Publicación de comandos');

        $deniedExecution = $this->createExecution($publication, $deniedGroup, 'command-denied-execution');
        $allowedExecution = $this->createExecution($publication, $allowedGroup, 'command-allowed-execution');

        BotCommand::query()->create([
            'bot_name' => 'WBot',
            'agent_instance_id' => 'agent-command-01',
            'type' => 'retry_publication',
            'payload' => ['execution_id' => $deniedExecution->id],
            'idempotency_key' => 'command-denied-account',
            'status' => 'pending',
            'available_at' => now(),
        ]);
        $allowedCommand = BotCommand::query()->create([
            'bot_name' => 'WBot',
            'agent_instance_id' => 'agent-command-01',
            'type' => 'retry_publication',
            'payload' => ['execution_id' => $allowedExecution->id],
            'idempotency_key' => 'command-allowed-account',
            'status' => 'pending',
            'available_at' => now(),
        ]);

        $legacyCommand = BotCommand::query()->create([
            'bot_name' => 'WBot',
            'agent_instance_id' => null,
            'type' => 'retry_publication',
            'payload' => ['execution_id' => $allowedExecution->id],
            'idempotency_key' => 'command-legacy-assignment',
            'status' => 'claimed',
            'attempts' => 1,
            'claimed_at' => now(),
        ]);

        $token = 'agent-command-scope-token';
        BotAgent::query()->create([
            'name' => 'Agente de comandos',
            'instance_id' => 'agent-command-01',
            'token_hash' => hash('sha256', $token),
            'social_account_ids' => [$allowedAccount->id],
            'is_active' => true,
        ]);
        $context->clear();

        $this->withToken($token)->getJson('/api/bot/commands/claim?bot_name=WBot&instance_id=agent-command-01')
            ->assertOk()
            ->assertJsonPath('data.id', $allowedCommand->id)
            ->assertJsonPath('data.payload.execution_id', $allowedExecution->id);

        $this->withToken($token)->postJson('/api/bot/commands/'.$legacyCommand->id.'/complete', [
            'bot_name' => 'WBot',
            'instance_id' => 'agent-command-01',
            'result' => ['ok' => true],
        ])->assertForbidden();
    }

    private function createWhatsAppAccount(string $name, string $externalId): SocialAccount
    {
        return SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'external_account_id' => $externalId,
            'display_name' => $name,
        ]);
    }

    private function connectAccountToAgent(SocialAccount $account, string $instanceId): void
    {
        $account->connections()->create([
            'connection_type' => SocialAccountConnection::TYPE_WPP_CONNECT_QR,
            'provider' => 'wppconnect',
            'status' => 'connected',
            'agent_instance_id' => $instanceId,
            'session_reference' => 'wppconnect:'.hash('sha256', $instanceId.':'.$account->id),
        ]);
    }

    private function createGroup(SocialAccount $account, string $whatsappId): WhatsAppGroup
    {
        return WhatsAppGroup::query()->create([
            'social_account_id' => $account->id,
            'whatsapp_id' => $whatsappId,
            'name' => $whatsappId,
            'active' => true,
        ]);
    }

    private function createPublication(string $text): Publication
    {
        return Publication::query()->create([
            'date' => now()->toDateString(),
            'time' => '10:00',
            'image' => 'publications/security-test.jpg',
            'text' => $text,
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'none',
        ]);
    }

    private function createExecution(Publication $publication, WhatsAppGroup $group, string $occurrenceId): PublicationExecution
    {
        return PublicationExecution::query()->create([
            'publication_id' => $publication->id,
            'occurrence_id' => $occurrenceId,
            'channel' => 'whatsapp',
            'target_key' => $group->whatsapp_id,
            'agent_instance_id' => 'agent-command-01',
            'status' => 'failed',
            'attempts' => 1,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}
