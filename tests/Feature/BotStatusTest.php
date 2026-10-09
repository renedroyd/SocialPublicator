<?php

namespace Tests\Feature;

use App\Models\BotStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_can_publish_an_authenticated_heartbeat(): void
    {
        config(['services.bot_api.token' => 'test-token']);

        $response = $this
            ->withToken('test-token')
            ->postJson('/api/bot/status', [
                'name' => 'WBot',
                'platform' => 'whatsapp',
                'status' => 'online',
                'version' => '96.0.0',
                'host' => 'bot-host',
                'message' => 'Connected',
                'metadata' => [
                    'group' => 'LaravelBot',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'WBot')
            ->assertJsonPath('data.platform', 'whatsapp')
            ->assertJsonPath('data.status', 'online');

        $this->assertDatabaseHas('bot_statuses', [
            'name' => 'WBot',
            'platform' => 'whatsapp',
            'status' => 'online',
            'version' => '96.0.0',
        ]);

        $this->assertNotNull(
            BotStatus::query()->where('name', 'WBot')->value('last_seen_at')
        );
    }

    public function test_bot_heartbeat_requires_the_api_token(): void
    {
        config(['services.bot_api.token' => 'test-token']);

        $this
            ->postJson('/api/bot/status', [
                'name' => 'WBot',
                'platform' => 'whatsapp',
                'status' => 'online',
            ])
            ->assertUnauthorized();
    }
}
