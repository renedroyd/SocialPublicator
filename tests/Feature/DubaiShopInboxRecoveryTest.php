<?php

namespace Tests\Feature;

use App\Jobs\ProcessDubaiShopEvent;
use App\Models\IntegrationEventInbox;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DubaiShopInboxRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $workspace = Workspace::query()->create([
            'name' => 'Inbox recovery workspace',
            'slug' => 'inbox-recovery-workspace',
        ]);

        app(WorkspaceContext::class)->set((int) $workspace->id);
    }

    private function createEvent(array $overrides = []): IntegrationEventInbox
    {
        return IntegrationEventInbox::query()->create(array_merge([
            'event_id' => (string) Str::uuid(),
            'event_type' => 'inventory.changed',
            'contract_version' => 'v1',
            'aggregate_external_id' => (string) Str::uuid(),
            'revision' => 1,
            'payload' => ['data' => ['availability' => ['warehouses' => []]]],
            'status' => 'pending',
            'attempts' => 0,
            'received_at' => now(),
        ], $overrides));
    }

    public function test_recovery_dispatches_pending_event_and_reservation_prevents_duplicate_dispatch(): void
    {
        Queue::fake();
        $event = $this->createEvent();

        Artisan::call('social-publicator:recover-dubai-inbox');
        Artisan::call('social-publicator:recover-dubai-inbox');

        Queue::assertPushed(ProcessDubaiShopEvent::class, 1);

        $this->assertNotNull($event->fresh()->dispatch_reserved_at);
        $this->assertSame('pending', $event->fresh()->status);
    }

    public function test_recovery_redispatches_an_event_with_an_expired_processing_lease(): void
    {
        Queue::fake();
        $event = $this->createEvent([
            'status' => 'processing',
            'attempts' => 1,
            'processing_token' => (string) Str::uuid(),
            'lease_expires_at' => now()->subMinute(),
        ]);

        Artisan::call('social-publicator:recover-dubai-inbox');

        Queue::assertPushed(ProcessDubaiShopEvent::class, 1);
        $event->refresh();

        $this->assertSame('pending', $event->status);
        $this->assertNull($event->processing_token);
        $this->assertNull($event->lease_expires_at);
        $this->assertNotNull($event->dispatch_reserved_at);
    }

    public function test_recovery_also_handles_legacy_processing_rows_without_a_lease(): void
    {
        Queue::fake();
        $event = $this->createEvent([
            'status' => 'processing',
            'attempts' => 1,
            'processing_token' => null,
            'lease_expires_at' => null,
        ]);

        Artisan::call('social-publicator:recover-dubai-inbox');

        Queue::assertPushed(ProcessDubaiShopEvent::class, 1);
        $this->assertSame('pending', $event->fresh()->status);
    }

    public function test_recovery_does_not_dispatch_an_event_with_an_active_processing_lease(): void
    {
        Queue::fake();
        $this->createEvent([
            'status' => 'processing',
            'attempts' => 1,
            'processing_token' => (string) Str::uuid(),
            'lease_expires_at' => now()->addMinutes(10),
        ]);

        Artisan::call('social-publicator:recover-dubai-inbox');

        Queue::assertNothingPushed();
    }

    public function test_recovery_does_not_automatically_retry_terminal_failures(): void
    {
        Queue::fake();
        $this->createEvent([
            'status' => 'failed',
            'attempts' => 5,
            'failed_at' => now(),
        ]);

        Artisan::call('social-publicator:recover-dubai-inbox');

        Queue::assertNothingPushed();
    }
}
