<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotAgentHealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $workspaceId = $request->attributes->get('bot_agent_workspace_id')
            ?? app(WorkspaceContext::class)->id();
        abort_unless($workspaceId, 403, 'The bot agent has no workspace context.');

        $instanceId = (string) $request->attributes->get('bot_agent_instance_id');
        abort_if($instanceId === '', 403, 'The bot agent identity is missing.');

        $authorizedIds = $request->attributes->get('bot_agent_social_account_ids');
        $query = SocialAccount::query()->where('workspace_id', $workspaceId);

        if (is_array($authorizedIds)) {
            $query->whereIn('id', $authorizedIds);
        }

        $accounts = $query
            ->with(['connections' => fn ($connections) => $connections
                ->select([
                    'id',
                    'social_account_id',
                    'connection_type',
                    'provider',
                    'status',
                    'agent_instance_id',
                    'connected_at',
                    'last_verified_at',
                    'revoked_at',
                    'last_error_at',
                ])
                ->whereIn('connection_type', [
                    'wppconnect_qr',
                    'browser_session',
                ])])
            ->orderBy('id')
            ->get([
                'id',
                'platform',
                'account_type',
                'display_name',
                'status',
            ])
            ->map(fn (SocialAccount $account): array => [
                'id' => (int) $account->id,
                'platform' => $account->platform,
                'account_type' => $account->account_type,
                'display_name' => $account->display_name,
                'status' => $account->status,
                'connections' => $account->connections
                    ->map(fn ($connection): array => [
                        'type' => $connection->connection_type,
                        'provider' => $connection->provider,
                        'status' => $connection->status,
                        'agent_instance_id' => $connection->agent_instance_id,
                        'connected_at' => $connection->connected_at?->toISOString(),
                        'last_verified_at' => $connection->last_verified_at?->toISOString(),
                        'revoked_at' => $connection->revoked_at?->toISOString(),
                        'last_error_at' => $connection->last_error_at?->toISOString(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'workspace_id' => (int) $workspaceId,
                'instance_id' => $instanceId,
                'authorization_mode' => is_array($authorizedIds) ? 'scoped' : 'legacy_workspace',
                'authorized_social_account_ids' => is_array($authorizedIds)
                    ? array_values(array_map('intval', $authorizedIds))
                    : null,
                'accounts' => $accounts,
                'checked_at' => now()->toISOString(),
            ],
        ]);
    }
}
