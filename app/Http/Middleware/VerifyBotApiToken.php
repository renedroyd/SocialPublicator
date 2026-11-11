<?php

namespace App\Http\Middleware;

use App\Models\BotAgent;
use App\Models\SocialAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBotApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $providedToken = (string) $request->bearerToken();

        if ($providedToken === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $configuredToken = (string) config('services.bot_api.token');
        $legacyInstanceId = (string) config('services.bot_api.command_bot_name', 'WBot');
        $requestInstanceId = $request->input('instance_id')
            ?? $request->query('instance_id')
            ?? $request->input('agent_instance_id');

        if ($configuredToken !== '' && hash_equals($configuredToken, $providedToken)) {
            if (filled($requestInstanceId) && ! hash_equals($legacyInstanceId, (string) $requestInstanceId)) {
                return response()->json(['message' => 'This legacy token is restricted to the default WBot instance.'], 403);
            }

            $request->attributes->set('bot_agent_instance_id', $legacyInstanceId);
            $request->attributes->set('bot_agent_auth_mode', 'legacy_workspace');
            $request->attributes->set('bot_agent_social_account_ids', null);

            $workspaceId = config('services.bot_api.workspace_id');

            if ($workspaceId) {
                $request->attributes->set('bot_agent_workspace_id', (int) $workspaceId);
            }

            return $next($request);
        }

        $agent = BotAgent::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $providedToken))
            ->where('is_active', true)
            ->first();

        if (! $agent) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! filled($requestInstanceId) || ! hash_equals($agent->instance_id, (string) $requestInstanceId)) {
            return response()->json(['message' => 'Agent instance identity does not match this token.'], 403);
        }

        $allowedAccountIds = array_map('intval', $agent->social_account_ids ?? []);
        $allowedAccountIds = SocialAccount::withoutGlobalScopes()
            ->where('workspace_id', $agent->workspace_id)
            ->whereIn('id', $allowedAccountIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $isGroupSync = $request->is('api/bot/whatsapp/groups/sync')
            || $request->is('api/bot/facebook/groups/sync')
            || $request->is('api/bot/facebook/grupos/sincronizar');

        if ($isGroupSync && ! filled($request->input('social_account_id'))) {
            return response()->json(['message' => 'A social_account_id is required for agent group synchronization.'], 422);
        }

        $accountId = $request->input('social_account_id');

        if (filled($accountId) && ! in_array((int) $accountId, $allowedAccountIds, true)) {
            return response()->json(['message' => 'This agent is not authorized for the requested social account.'], 403);
        }

        if (! $agent->last_seen_at || $agent->last_seen_at->lt(now()->subMinute())) {
            $agent->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        $request->attributes->set('bot_agent_workspace_id', (int) $agent->workspace_id);
        $request->attributes->set('bot_agent_instance_id', $agent->instance_id);
        $request->attributes->set('bot_agent_auth_mode', 'scoped');
        $request->attributes->set('bot_agent_social_account_ids', $allowedAccountIds);

        return $next($request);
    }
}
