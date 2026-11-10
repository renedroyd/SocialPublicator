<?php

namespace Tests\Feature;

use App\Services\CampaignPublicationDispatcher;
use Tests\TestCase;

class CampaignPublicationDispatchCommandTest extends TestCase
{
    public function test_campaign_dispatch_is_disabled_by_default_even_with_api_credentials(): void
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
            ->expectsOutput('Campaign dispatch is disabled or Dubai Shop API credentials are missing; no publications were queued.')
            ->assertSuccessful();
    }

    public function test_campaign_dispatch_requires_api_credentials_when_explicitly_enabled(): void
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
            ->assertSuccessful()
            ->expectsOutput('Campaign dispatch is disabled or Dubai Shop API credentials are missing; no publications were queued.');
    }
}
