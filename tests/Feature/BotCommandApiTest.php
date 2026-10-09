<?php

namespace Tests\Feature;

use App\Models\BotCommand;
use App\Models\Publication;
use App\Models\PublicationExecution;
use App\Services\BotCommandService;
use Illuminate\Support\Facades\Artisan;
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

    public function test_repeating_execution_start_does_not_create_a_duplicate_or_increment_attempts(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-09',
            'time' => '09:00',
            'image' => 'publications/idempotent.jpg',
            'text' => 'Publicación idempotente',
            'destination' => 'facebook',
            'active' => true,
            'recurrence_type' => 'none',
        ]);

        $payload = [
            'publication_id' => $publication->id,
            'occurrence_id' => $publication->id.'_2026-10-09_09-00',
            'channel' => 'facebook',
            'target_key' => 'facebook:123456789',
        ];

        $first = $this
            ->withToken('test-token')
            ->postJson('/api/bot/executions/start', $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.attempts', 1);

        $executionId = $first->json('data.id');

        $second = $this
            ->withToken('test-token')
            ->postJson('/api/bot/executions/start', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $executionId)
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.attempts', 1);

        $this->assertDatabaseCount('publication_executions', 1);
    }

    public function test_stale_running_executions_are_marked_unknown_without_auto_retry(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-09',
            'time' => '09:00',
            'image' => 'publications/stale.jpg',
            'text' => 'Publicación interrumpida',
            'destination' => 'facebook',
            'active' => true,
            'recurrence_type' => 'none',
        ]);

        $stale = PublicationExecution::create([
            'publication_id' => $publication->id,
            'occurrence_id' => $publication->id.'_2026-10-09_09-00',
            'channel' => 'facebook',
            'target_key' => 'facebook:123',
            'status' => 'running',
            'attempts' => 1,
            'started_at' => now()->subMinutes(20),
        ]);

        $recent = PublicationExecution::create([
            'publication_id' => $publication->id,
            'occurrence_id' => $publication->id.'_2026-10-09_09-10',
            'channel' => 'facebook',
            'target_key' => 'facebook:456',
            'status' => 'running',
            'attempts' => 1,
            'started_at' => now()->subMinutes(5),
        ]);

        Artisan::call('publications:mark-stale-executions');

        $this->assertDatabaseHas('publication_executions', [
            'id' => $stale->id,
            'status' => 'unknown',
            'attempts' => 1,
        ]);
        $this->assertDatabaseHas('publication_executions', [
            'id' => $recent->id,
            'status' => 'running',
            'attempts' => 1,
        ]);
        $this->assertDatabaseCount('bot_commands', 0);
    }

    public function test_retrying_execution_reuses_an_active_retry_command(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-09',
            'time' => '09:00',
            'image' => 'publications/retry.jpg',
            'text' => 'Publicación para reintento',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'none',
        ]);

        $execution = PublicationExecution::create([
            'publication_id' => $publication->id,
            'occurrence_id' => $publication->id.'_2026-10-09_09-00',
            'channel' => 'whatsapp',
            'target_key' => 'Venta mayorista de cervezas.',
            'status' => 'failed',
            'attempts' => 1,
            'error_message' => 'Error simulado',
        ]);

        $service = app(BotCommandService::class);

        $firstCommand = $service->queueRetry($execution);
        $secondCommand = $service->queueRetry($execution->fresh());

        $this->assertSame($firstCommand->id, $secondCommand->id);
        $this->assertDatabaseCount('bot_commands', 1);
    }
}
