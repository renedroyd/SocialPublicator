<?php

namespace Tests\Feature;

use App\Filament\Widgets\SystemHealthOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class SystemHealthOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_includes_dubai_inbox_campaign_and_connection_health_stats(): void
    {
        $widget = new SystemHealthOverview;
        $method = new ReflectionMethod(SystemHealthOverview::class, 'getStats');
        $method->setAccessible(true);

        $stats = $method->invoke($widget);

        $this->assertCount(9, $stats);
    }
}
