<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\PublicationExecution;
use App\Services\PublicationExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PublicationExecutionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_execution_in_pending_status(): void
    {
        $publication = $this->createPublication();

        $execution = app(PublicationExecutionService::class)->getOrCreate([
            'publication_id' => $publication->id,
            'occurrence_id' => $publication->id.'_2026-10-07_12-00',
            'channel' => 'whatsapp',
            'target_key' => 'whatsapp',
        ]);

        $this->assertSame('pending', $execution->status);
        $this->assertDatabaseHas('publication_executions', [
            'id' => $execution->id,
            'status' => 'pending',
        ]);
    }

    public function test_it_reuses_an_existing_execution_for_the_same_occurrence_and_target(): void
    {
        $publication = $this->createPublication();
        $service = app(PublicationExecutionService::class);

        $attributes = [
            'publication_id' => $publication->id,
            'occurrence_id' => $publication->id.'_2026-10-07_12-00',
            'channel' => 'whatsapp',
            'target_key' => 'whatsapp',
        ];

        $first = $service->getOrCreate($attributes);
        $second = $service->getOrCreate($attributes);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            PublicationExecution::query()
                ->where('occurrence_id', $attributes['occurrence_id'])
                ->where('target_key', 'whatsapp')
                ->count()
        );
    }

    public function test_it_moves_an_execution_from_pending_to_running(): void
    {
        $execution = $this->createExecution();

        $execution = app(PublicationExecutionService::class)->start($execution);

        $this->assertSame('running', $execution->status);
        $this->assertNotNull($execution->started_at);
        $this->assertNull($execution->completed_at);
    }

    public function test_it_marks_a_running_execution_as_succeeded_and_stores_external_id(): void
    {
        $execution = app(PublicationExecution::class);
        $execution = $this->createExecution();
        $service = app(PublicationExecutionService::class);

        $service->start($execution);
        $execution = $service->succeed($execution, 'whatsapp-message-123');

        $this->assertSame('succeeded', $execution->status);
        $this->assertSame('whatsapp-message-123', $execution->external_id);
        $this->assertNotNull($execution->completed_at);
        $this->assertNull($execution->error_message);
    }

    public function test_it_marks_a_running_execution_as_failed_and_stores_the_error(): void
    {
        $execution = $this->createExecution();
        $service = app(PublicationExecutionService::class);

        $service->start($execution);
        $execution = $service->fail($execution, 'Facebook composer was not found.');

        $this->assertSame('failed', $execution->status);
        $this->assertSame(
            'Facebook composer was not found.',
            $execution->error_message
        );
        $this->assertNotNull($execution->completed_at);
    }

    public function test_it_rejects_invalid_status_transitions(): void
    {
        $execution = $this->createExecution();
        $service = app(PublicationExecutionService::class);

        $service->start($execution);
        $service->succeed($execution);

        $this->expectException(LogicException::class);

        $service->start($execution->refresh());
    }

    public function test_it_claims_a_pending_execution_only_once(): void
    {
        $execution = $this->createExecution();
        $service = app(PublicationExecutionService::class);

        $first = $service->claim($execution);
        $second = $service->claim($execution->refresh());

        $this->assertTrue($first['claimed']);
        $this->assertSame('running', $first['execution']->status);
        $this->assertFalse($second['claimed']);
        $this->assertSame($first['execution']->id, $second['execution']->id);
        $this->assertSame(1, $second['execution']->attempts);
    }

    public function test_it_never_reclaims_running_or_unknown_executions(): void
    {
        $execution = $this->createExecution();
        $service = app(PublicationExecutionService::class);

        $service->start($execution);
        $runningClaim = $service->claim($execution->refresh());
        $this->assertFalse($runningClaim['claimed']);
        $this->assertSame('running', $runningClaim['execution']->status);

        $service->markUnknown($execution->refresh(), 'No se pudo confirmar el envío.');
        $unknownClaim = $service->claim($execution->refresh());
        $this->assertFalse($unknownClaim['claimed']);
        $this->assertSame('unknown', $unknownClaim['execution']->status);
    }

    public function test_it_marks_uncertain_running_execution_as_unknown_and_allows_reconciliation_to_succeed(): void
    {
        $execution = $this->createExecution();
        $service = app(PublicationExecutionService::class);

        $service->start($execution);
        $execution = $service->markUnknown($execution, 'El proveedor no confirmó el resultado.');

        $this->assertSame('unknown', $execution->status);
        $this->assertSame('El proveedor no confirmó el resultado.', $execution->error_message);

        $execution = $service->succeed($execution, 'external-post-123');
        $this->assertSame('succeeded', $execution->status);
        $this->assertSame('external-post-123', $execution->external_id);
    }


    public function test_it_requires_verified_absence_before_retrying_an_unknown_execution(): void
    {
        $execution = $this->createExecution();
        $service = app(PublicationExecutionService::class);

        $service->start($execution);
        $execution = $service->markUnknown($execution, 'El resultado externo no se pudo confirmar.');

        try {
            $service->retryUnknown($execution, false);
            $this->fail('An unknown execution must not be retried without explicit confirmation.');
        } catch (LogicException) {
            $this->assertSame('unknown', $execution->fresh()->status);
        }

        $execution = $service->retryUnknown($execution->refresh(), true);

        $this->assertSame('running', $execution->status);
        $this->assertSame(2, $execution->attempts);
        $this->assertNull($execution->external_id);
        $this->assertNull($execution->error_message);
    }

    private function createExecution(): PublicationExecution
    {
        $publication = $this->createPublication();

        return PublicationExecution::create([
            'publication_id' => $publication->id,
            'occurrence_id' => $publication->id.'_2026-10-07_12-00',
            'channel' => 'whatsapp',
            'target_key' => 'whatsapp',
            'status' => 'pending',
        ]);
    }

    private function createPublication(): Publication
    {
        return Publication::create([
            'date' => '2026-10-07',
            'time' => '12:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación de prueba',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'none',
        ]);
    }
}
