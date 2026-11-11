<?php

namespace Tests\Feature;

use App\Jobs\PublishMetaPagePublication;
use App\Models\Publication;
use App\Models\PublicationDestination;
use App\Models\PublicationExecution;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\PublicationExecutionService;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetaPagePublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_publication_is_dispatched_to_each_connected_meta_page(): void
    {
        $this->travelTo(now()->startOfMinute());
        $fixtures = $this->createPagePublication();

        Queue::fake();

        Artisan::call('meta-pages:dispatch');

        Queue::assertPushed(PublishMetaPagePublication::class, 1);
        $this->assertDatabaseHas('publication_executions', [
            'publication_id' => $fixtures['publication']->id,
            'occurrence_id' => $fixtures['publication']->id.'_'.now()->format('Y-m-d_H-i'),
            'channel' => 'facebook',
            'target_key' => 'meta_page_destination:'.$fixtures['destination']->id,
            'status' => 'running',
        ]);
    }

    public function test_page_job_publishes_photo_using_encrypted_page_token_and_tracks_external_post_id(): void
    {
        $fixtures = $this->createPagePublication();
        Storage::fake('public');
        Storage::disk('public')->put('publications/test.jpg', 'image-bytes');
        $fixtures['publication']->update(['image' => 'publications/test.jpg']);

        $execution = $this->startExecution($fixtures);
        config([
            'services.meta.app_secret' => 'test-meta-secret',
            'services.meta.graph_version' => 'v26.0',
        ]);
        Http::fake([
            'https://graph.facebook.com/v26.0/page-100/photos' => Http::response([
                'id' => 'photo-100',
                'post_id' => 'page-post-100',
            ]),
        ]);

        app(PublishMetaPagePublication::class, ['executionId' => $execution->id])
            ->handle(app(PublicationExecutionService::class), app(WorkspaceContext::class));

        $execution->refresh();
        $this->assertSame('succeeded', $execution->status);
        $this->assertSame('page-post-100', $execution->external_id);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://graph.facebook.com/v26.0/page-100/photos'
                && $request['access_token'] === 'encrypted-page-token'
                && $request['appsecret_proof'] === hash_hmac('sha256', 'encrypted-page-token', 'test-meta-secret')
                && $request['caption'] === 'Publicación oficial de prueba'
                && str_contains($request['url'], '/storage/publications/test.jpg');
        });
    }

    public function test_uncertain_meta_transport_failure_marks_execution_unknown_without_retrying(): void
    {
        $fixtures = $this->createPagePublication();
        $execution = $this->startExecution($fixtures);
        config(['services.meta.app_secret' => 'test-meta-secret']);
        Http::fake([
            '*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out'),
        ]);

        app(PublishMetaPagePublication::class, ['executionId' => $execution->id])
            ->handle(app(PublicationExecutionService::class), app(WorkspaceContext::class));

        $this->assertSame('unknown', $execution->fresh()->status);
        $this->assertStringContainsString('verifica la página', $execution->fresh()->error_message);
    }

    public function test_meta_api_validation_error_marks_execution_failed(): void
    {
        $fixtures = $this->createPagePublication();
        $execution = $this->startExecution($fixtures);
        config(['services.meta.app_secret' => 'test-meta-secret']);
        Http::fake([
            '*' => Http::response([
                'error' => ['message' => 'Permissions error'],
            ], 400),
        ]);

        app(PublishMetaPagePublication::class, ['executionId' => $execution->id])
            ->handle(app(PublicationExecutionService::class), app(WorkspaceContext::class));

        $this->assertSame('failed', $execution->fresh()->status);
        $this->assertSame('Permissions error', $execution->fresh()->error_message);
    }

    private function createPagePublication(): array
    {
        $workspace = Workspace::query()->create([
            'name' => 'Meta Page workspace',
            'slug' => 'meta-page-'.str()->random(8),
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $user = User::factory()->create();
        WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMembership::ROLE_OWNER,
            'status' => 'active',
        ]);

        $profile = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PROFILE,
            'external_account_id' => 'profile-100',
            'display_name' => 'Perfil de prueba',
            'status' => SocialAccount::STATUS_CONNECTED,
        ]);

        $page = SocialAccount::query()->create([
            'parent_account_id' => $profile->id,
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PAGE,
            'external_account_id' => 'page-100',
            'display_name' => 'Página de prueba',
            'status' => SocialAccount::STATUS_CONNECTED,
            'capabilities' => ['pages_api' => true],
        ]);

        SocialAccountConnection::query()->create([
            'social_account_id' => $page->id,
            'connection_type' => SocialAccountConnection::TYPE_META_OAUTH,
            'provider' => 'meta',
            'status' => 'connected',
            'credentials' => ['access_token' => 'encrypted-page-token', 'token_type' => 'page'],
            'expires_at' => now()->addDays(30),
        ]);

        $destination = PublicationDestination::query()->create([
            'social_account_id' => $page->id,
            'platform' => 'facebook',
            'external_id' => 'page-100',
            'name' => 'Página de prueba',
            'url' => 'https://www.facebook.com/page-100',
            'active' => true,
            'metadata' => ['destination_type' => 'facebook_page', 'delivery' => 'meta_graph_api'],
        ]);

        $publication = Publication::query()->create([
            'created_by_user_id' => $user->id,
            'date' => now()->toDateString(),
            'time' => now()->format('H:i'),
            'image' => 'publications/test.jpg',
            'text' => 'Publicación oficial de prueba',
            'destination' => 'facebook',
            'active' => true,
            'recurrence_type' => 'none',
        ]);
        $publication->destinations()->attach($destination->id);

        return compact('workspace', 'profile', 'page', 'destination', 'publication');
    }

    private function startExecution(array $fixtures): PublicationExecution
    {
        $execution = app(PublicationExecutionService::class)->getOrCreate([
            'publication_id' => $fixtures['publication']->id,
            'occurrence_id' => $fixtures['publication']->id.'_'.now()->format('Y-m-d_H-i'),
            'channel' => 'facebook',
            'target_key' => 'meta_page_destination:'.$fixtures['destination']->id,
            'agent_instance_id' => null,
        ]);

        return app(PublicationExecutionService::class)->start($execution);
    }
}
