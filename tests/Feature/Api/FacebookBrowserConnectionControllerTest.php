<?php

namespace Tests\Feature\Api;

use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacebookBrowserConnectionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_session_state_is_saved_without_browser_credentials(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Facebook browser', 'slug' => 'facebook-browser']);
        config([
            'services.bot_api.token' => 'test-bot-token',
            'services.bot_api.workspace_id' => $workspace->id,
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PROFILE,
            'display_name' => 'Perfil Facebook',
        ]);
        app(WorkspaceContext::class)->clear();

        $this->withToken('test-bot-token')->postJson('/api/bot/facebook/connection/state', [
            'social_account_id' => $account->id,
            'agent_instance_id' => 'wbot-local-01',
            'status' => 'connected',
        ])->assertOk()->assertJsonPath('status', 'connected');

        app(WorkspaceContext::class)->set((int) $workspace->id);
        $connection = $account->connections()->firstOrFail();
        $this->assertSame('connected', $connection->status);
        $this->assertSame('puppeteer', $connection->provider);
        $this->assertNull($connection->credentials);
        $this->assertStringStartsWith('puppeteer:', $connection->session_reference);
        $this->assertArrayNotHasKey('credentials', $connection->toArray());
        $this->assertSame(SocialAccount::STATUS_CONNECTED, $account->fresh()->status);
        $this->assertSame(1, SocialAccountConnection::query()->count());
    }
}
