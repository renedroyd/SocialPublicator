<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspaceContext
{
    public function handle(Request $request, Closure $next, string $source = 'web'): Response
    {
        if ($source === 'web' && ! $request->user()) {
            return $next($request);
        }

        $workspaceId = null;

        if ($request->user()) {
            $memberships = $request->user()
                ->activeWorkspaceMemberships()
                ->with('workspace')
                ->get();

            $selectedId = $request->session()->get('active_workspace_id');

            $membership = $memberships->firstWhere('workspace_id', (int) $selectedId)
                ?? $memberships->first();

            if (! $membership) {
                return response()->json(['message' => 'No active workspace membership.'], 403);
            }

            $workspaceId = (int) $membership->workspace_id;
            $request->session()->put('active_workspace_id', $workspaceId);
        } else {
            $configuredId = match ($source) {
                'bot' => config('services.bot_api.workspace_id'),
                'dubai_shop' => $request->attributes->get('dubai_shop_workspace_id')
                    ?? config('services.dubai_shop.workspace_id'),
                default => null,
            };

            if ($configuredId) {
                $workspaceId = (int) $configuredId;
            } else {
                $workspaces = Workspace::query()->limit(2)->pluck('id');

                if ($workspaces->count() === 1) {
                    $workspaceId = (int) $workspaces->first();
                }
            }
        }

        if (! $workspaceId || ! Workspace::query()->whereKey($workspaceId)->exists()) {
            return response()->json([
                'message' => 'Workspace context is missing or invalid. Configure a workspace for this integration.',
            ], 403);
        }

        $context = app(WorkspaceContext::class);
        $context->set($workspaceId);

        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
