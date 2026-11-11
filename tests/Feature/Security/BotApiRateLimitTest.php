<?php

namespace Tests\Feature\Security;

use App\Models\BotAgent;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class BotApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_api_rate_limit_is_applied_per_authenticated_agent_instance(): void
    {
        config([
            'services.bot_api.token' => '',
            'services.bot_api.workspace_id' => null,
        ]);

        RateLimiter::for('bot-api', function (Request $request): Limit {
            return Limit::perMinute(2)
                ->by('security-test:'.$request->attributes->get('bot_agent_instance_id'));
        });

        $workspace = Workspace::query()->create(['name' => 'Rate limit', 'slug' => 'rate-limit']);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        $token = 'rate-limit-agent-token';
        BotAgent::query()->create([
            'name' => 'Agente con límite',
            'instance_id' => 'rate-limit-agent-01',
            'token_hash' => hash('sha256', $token),
            'social_account_ids' => [],
            'is_active' => true,
        ]);
        app(WorkspaceContext::class)->clear();

        $payload = [
            'name' => 'Agente con límite',
            'instance_id' => 'rate-limit-agent-01',
            'platform' => 'whatsapp',
            'status' => 'online',
        ];

        $this->withToken($token)->postJson('/api/bot/status', $payload)->assertOk();
        $this->withToken($token)->postJson('/api/bot/status', $payload)->assertOk();
        $this->withToken($token)->postJson('/api/bot/status', $payload)->assertStatus(429);
    }
}
