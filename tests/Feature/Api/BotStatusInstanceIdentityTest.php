<?php

namespace Tests\Feature\Api;

use App\Models\BotStatus;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotStatusInstanceIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.bot_api.token' => 'test-bot-token']);
    }

    public function test_multiple_agent_instances_with_the_same_name_keep_separate_status_records(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Agent identity', 'slug' => 'agent-identity']);
        config(['services.bot_api.workspace_id' => $workspace->id]);

        foreach (['wbot-whatsapp-01', 'wbot-whatsapp-02'] as $instanceId) {
            $this->withToken('test-bot-token')->postJson('/api/bot/status', [
                'name' => 'WBot',
                'instance_id' => $instanceId,
                'platform' => 'whatsapp',
                'status' => 'online',
            ])->assertOk()->assertJsonPath('data.instance_id', $instanceId);
        }

        app(WorkspaceContext::class)->set((int) $workspace->id);
        $this->assertSame(2, BotStatus::query()->count());
        $this->assertDatabaseHas('bot_statuses', [
            'workspace_id' => $workspace->id,
            'name' => 'WBot',
            'instance_id' => 'wbot-whatsapp-01',
        ]);
        $this->assertDatabaseHas('bot_statuses', [
            'workspace_id' => $workspace->id,
            'name' => 'WBot',
            'instance_id' => 'wbot-whatsapp-02',
        ]);
    }

    public function test_legacy_heartbeat_uses_name_as_instance_identity(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Legacy agent', 'slug' => 'legacy-agent']);
        config(['services.bot_api.workspace_id' => $workspace->id]);

        $payload = [
            'name' => 'WBot',
            'platform' => 'whatsapp',
            'status' => 'online',
        ];

        $this->withToken('test-bot-token')->postJson('/api/bot/status', $payload)
            ->assertOk()->assertJsonPath('data.instance_id', 'WBot');
        $this->withToken('test-bot-token')->postJson('/api/bot/status', $payload)
            ->assertOk();

        app(WorkspaceContext::class)->set((int) $workspace->id);
        $this->assertSame(1, BotStatus::query()->count());
    }
}
