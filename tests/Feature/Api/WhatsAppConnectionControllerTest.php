<?php

namespace Tests\Feature\Api;

use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WhatsAppConnectionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bot_api.token' => 'test-bot-token',
        ]);
    }

    public function test_agent_qr_is_cached_temporarily_and_never_persisted(): void
    {
        $workspace = Workspace::query()->create(['name' => 'WhatsApp workspace', 'slug' => 'whatsapp-workspace']);
        config(['services.bot_api.workspace_id' => $workspace->id]);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'display_name' => 'WhatsApp principal',
        ]);
        app(WorkspaceContext::class)->clear();

        $response = $this->withToken('test-bot-token')->postJson('/api/bot/whatsapp/connection/state', [
            'social_account_id' => $account->id,
            'agent_instance_id' => 'wbot-local-01',
            'status' => 'qr',
            'qr' => 'data:image/png;base64,temporary-qr',
        ]);

        $response->assertOk()->assertJsonPath('status', 'qr');

        app(WorkspaceContext::class)->set((int) $workspace->id);
        $connection = $account->connections()->firstOrFail();
        $this->assertSame('pending', $connection->status);
        $this->assertNull($connection->credentials);
        $this->assertStringStartsWith('wppconnect:', $connection->session_reference);
        $this->assertSame('data:image/png;base64,temporary-qr', Cache::get(
            'social-account-whatsapp-qr:'.$workspace->id.':'.$account->id,
        ));
        $this->assertStringNotContainsString('temporary-qr', json_encode($connection->toArray()));
    }

    public function test_agent_connection_state_marks_account_connected_and_clears_qr(): void
    {
        $workspace = Workspace::query()->create(['name' => 'WhatsApp connected', 'slug' => 'whatsapp-connected']);
        config(['services.bot_api.workspace_id' => $workspace->id]);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'display_name' => 'WhatsApp principal',
        ]);
        Cache::put('social-account-whatsapp-qr:'.$workspace->id.':'.$account->id, 'expired-qr', now()->addMinute());
        app(WorkspaceContext::class)->clear();

        $this->withToken('test-bot-token')->postJson('/api/bot/whatsapp/connection/state', [
            'social_account_id' => $account->id,
            'agent_instance_id' => 'wbot-local-01',
            'status' => 'connected',
            'phone' => '15551234567@c.us',
        ])->assertOk()->assertJsonPath('status', 'connected');

        app(WorkspaceContext::class)->set((int) $workspace->id);
        $this->assertSame(SocialAccount::STATUS_CONNECTED, $account->fresh()->status);
        $this->assertSame('15551234567@c.us', $account->fresh()->external_account_id);
        $this->assertSame('connected', $account->connections()->firstOrFail()->status);
        $this->assertNull(Cache::get('social-account-whatsapp-qr:'.$workspace->id.':'.$account->id));
        $this->assertSame(1, SocialAccountConnection::query()->count());
    }
}
