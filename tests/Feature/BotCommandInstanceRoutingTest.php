<?php

namespace Tests\Feature;

use App\Models\BotCommand;
use App\Models\Publication;
use App\Models\PublicationExecution;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\WhatsAppGroup;
use App\Models\Workspace;
use App\Services\BotCommandService;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotCommandInstanceRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.bot_api.command_bot_name' => 'WBot']);
    }

    public function test_agent_instances_only_claim_commands_assigned_to_them(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Agent routing', 'slug' => 'agent-routing']);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $first = BotCommand::query()->create([
            'bot_name' => 'WBot',
            'agent_instance_id' => 'wbot-wa-01',
            'type' => 'retry_publication',
            'payload' => ['execution_id' => 1],
            'idempotency_key' => 'agent-command-1',
            'status' => 'pending',
            'available_at' => now(),
        ]);
        BotCommand::query()->create([
            'bot_name' => 'WBot',
            'agent_instance_id' => 'wbot-wa-02',
            'type' => 'retry_publication',
            'payload' => ['execution_id' => 2],
            'idempotency_key' => 'agent-command-2',
            'status' => 'pending',
            'available_at' => now(),
        ]);

        $service = app(BotCommandService::class);

        $this->assertNull($service->claim('WBot', 'wbot-wa-03'));
        $this->assertSame($first->id, $service->claim('WBot', 'wbot-wa-01')?->id);
        $this->assertSame('wbot-wa-01', $first->fresh()->agent_instance_id);
        $this->assertSame('claimed', $first->fresh()->status);
    }

    public function test_legacy_unassigned_commands_are_claimed_only_by_the_default_instance(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Legacy command', 'slug' => 'legacy-command']);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $command = BotCommand::query()->create([
            'bot_name' => 'WBot',
            'type' => 'retry_publication',
            'payload' => ['execution_id' => 1],
            'idempotency_key' => 'legacy-agent-command',
            'status' => 'pending',
            'available_at' => now(),
        ]);

        $service = app(BotCommandService::class);

        $this->assertNull($service->claim('WBot', 'wbot-wa-01'));
        $this->assertSame($command->id, $service->claim('WBot', 'WBot')?->id);
    }

    public function test_retry_command_is_assigned_to_the_connected_destination_agent(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Retry routing', 'slug' => 'retry-routing']);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'display_name' => 'WhatsApp agent',
        ]);
        WhatsAppGroup::query()->create([
            'social_account_id' => $account->id,
            'whatsapp_id' => '120363000000010@g.us',
            'name' => 'Grupo agent',
            'active' => true,
        ]);
        SocialAccountConnection::query()->create([
            'social_account_id' => $account->id,
            'connection_type' => SocialAccountConnection::TYPE_WPP_CONNECT_QR,
            'provider' => 'wppconnect',
            'status' => 'connected',
            'agent_instance_id' => 'wbot-wa-10',
            'session_reference' => 'wppconnect:opaque-reference',
            'provider_connection_id' => 'whatsapp-main',
        ]);

        $publication = Publication::query()->create([
            'date' => '2026-11-10',
            'time' => '12:00',
            'image' => 'publications/retry-routing.jpg',
            'text' => 'Retry routing',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'none',
        ]);
        $execution = PublicationExecution::query()->create([
            'publication_id' => $publication->id,
            'occurrence_id' => 'retry-routing-occurrence',
            'channel' => 'whatsapp',
            'target_key' => '120363000000010@g.us',
            'status' => 'failed',
            'attempts' => 1,
        ]);

        $command = app(BotCommandService::class)->queueRetry($execution);

        $this->assertSame('wbot-wa-10', $command->agent_instance_id);
    }
}
