<?php

namespace App\Providers;

use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkspaceContext::class);
    }

    public function boot(): void
    {
        RateLimiter::for('bot-api', function (Request $request): Limit {
            $workspaceId = (string) $request->attributes->get('bot_agent_workspace_id', 'legacy');
            $instanceId = (string) $request->attributes->get('bot_agent_instance_id', 'unknown');

            return Limit::perMinute(300)->by('bot-api:'.$workspaceId.':'.$instanceId);
        });

        RateLimiter::for('social-account-status', function (Request $request): Limit {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return Limit::perMinute(120)->by('social-account-status:'.$userId);
        });

        RateLimiter::for('meta-oauth', function (Request $request): Limit {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return Limit::perMinute(10)->by('meta-oauth:'.$userId);
        });

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
