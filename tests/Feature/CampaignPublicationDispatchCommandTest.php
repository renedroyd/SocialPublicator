<?php

namespace Tests\Feature;

use App\Models\Workspace;
use App\Models\WorkspaceDubaiShopIntegration;
use App\Services\CampaignPublicationDispatcher;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignPublicationDispatchCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_dispatch_is_inactive_when_no_workspace_connection_exists(): void
    {
        config([
            'services.dubai_shop.campaign_dispatch_enabled' => false,
            'services.dubai_shop.api_base_url' => 'https://dubai.example.test',
            'services.dubai_shop.api_token' => 'test-token',
        ]);

        $dispatcher = \Mockery::mock(CampaignPublicationDispatcher::class);
        $dispatcher->shouldNotReceive('dispatchPending');
        $this->app->instance(CampaignPublicationDispatcher::class, $dispatcher);

        $this->artisan('social-publicator:dispatch-campaign-publications')
            ->expectsOutput('No workspace has Dubai Shop campaign dispatch enabled; no publications were queued.')
            ->assertSuccessful();
    }

    public function test_campaign_dispatch_does_not_run_when_workspace_integration_is_not_ready(): void
    {
        config([
            'services.dubai_shop.campaign_dispatch_enabled' => true,
            'services.dubai_shop.api_base_url' => null,
            'services.dubai_shop.api_token' => null,
        ]);

        $dispatcher = \Mockery::mock(CampaignPublicationDispatcher::class);
        $dispatcher->shouldNotReceive('dispatchPending');
        $this->app->instance(CampaignPublicationDispatcher::class, $dispatcher);

        $this->artisan('social-publicator:dispatch-campaign-publications')
            ->expectsOutput('No workspace has Dubai Shop campaign dispatch enabled; no publications were queued.')
            ->assertSuccessful();
    }

    public function test_dispatch_runs_separately_for_each_workspace_with_campaigns_enabled(): void
    {
        $first = Workspace::query()->create(['name' => 'Campañas A', 'slug' => 'campanas-a']);
        $second = Workspace::query()->create(['name' => 'Campañas B', 'slug' => 'campanas-b']);
        $context = app(WorkspaceContext::class);

        $context->set((int) $first->id);
        WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://first.example.test',
            'api_token' => 'first-token',
            'enabled' => true,
            'campaign_dispatch_enabled' => true,
        ]);

        $context->set((int) $second->id);
        WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://second.example.test',
            'api_token' => 'second-token',
            'enabled' => true,
            'campaign_dispatch_enabled' => true,
        ]);
        $context->clear();

        $dispatcher = \Mockery::mock(CampaignPublicationDispatcher::class);
        $dispatcher->shouldReceive('dispatchPending')
            ->twice()
            ->with(100)
            ->andReturn(['triggers' => 0, 'selections' => 0, 'skipped' => 0, 'blocked' => 0, 'errors' => 0]);
        $this->app->instance(CampaignPublicationDispatcher::class, $dispatcher);

        $this->artisan('social-publicator:dispatch-campaign-publications')
            ->assertSuccessful();
    }
}
