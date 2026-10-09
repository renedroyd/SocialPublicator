<?php

namespace Tests\Feature;

use App\Models\BotCommand;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotCommandApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bot_api.token' => 'test-token',
            'services.bot_api.command_bot_name' => 'WBot',
        ]);
    }

    public function test_bot_can_claim_a_pending_command(): void
    {
        $command = BotCommand::create([
            'bot_name' => 'WBot',
            'type' => 'retry_publication',
            'payload' => ['occurrence_id' => '1_2026-10-09_09-00'],
            'idempotency_key' => 'test-command-1',
            'status' => 'pending',
            'available_at' => now(),
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/commands/claim?bot_name=WBot');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $command->id)
            ->assertJsonPath('data.status', 'claimed')
            ->assertJsonPath('data.attempts', 1);

        $this->assertDatabaseHas('bot_commands', [
            'id' => $command->id,
            'status' => 'claimed',
            'attempts' => 1,
        ]);
    }

    public function test_bot_can_complete_a_claimed_command(): void
    {
        $command = BotCommand::create([
            'bot_name' => 'WBot',
            'type' => 'retry_publication',
            'payload' => ['occurrence_id' => '1_2026-10-09_09-00'],
            'idempotency_key' => 'test-command-2',
            'status' => 'claimed',
            'attempts' => 1,
            'claimed_at' => now(),
        ]);

        $response = $this
            ->withToken('test-token')
            ->postJson("/api/bot/commands/{$command->id}/complete", [
                'bot_name' => 'WBot',
                'result' => ['ok' => true],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.result.ok', true);
    }

    public function test_failed_command_is_requeued_until_max_attempts(): void
    {
        $command = BotCommand::create([
            'bot_name' => 'WBot',
            'type' => 'retry_publication',
            'payload' => ['occurrence_id' => '1_2026-10-09_09-00'],
            'idempotency_key' => 'test-command-3',
            'status' => 'claimed',
            'attempts' => 1,
            'max_attempts' => 3,
            'claimed_at' => now(),
        ]);

        $response = $this
            ->withToken('test-token')
            ->postJson("/api/bot/commands/{$command->id}/fail", [
                'bot_name' => 'WBot',
                'error' => 'Fallo de prueba',
                'retry' => true,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.error_message', 'Fallo de prueba');
    }

    public function test_bot_can_report_a_publication_execution(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-09',
            'time' => '09:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación de ejecución',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'none',
        ]);

        $response = $this
            ->withToken('test-token')
            ->postJson('/api/bot/executions/start', [
                'publication_id' => $publication->id,
                'occurrence_id' => $publication->id.'_2026-10-09_09-00',
                'channel' => 'whatsapp',
                'target_key' => 'Venta mayorista de cervezas.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.attempts', 1);

        $executionId = $response->json('data.id');

        $this
            ->withToken('test-token')
            ->postJson("/api/bot/executions/{$executionId}/complete", [
                'external_id' => 'message-123',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.external_id', 'message-123');

        $this->assertDatabaseHas('publication_executions', [
            'id' => $executionId,
            'status' => 'succeeded',
            'attempts' => 1,
        ]);
    }
}
