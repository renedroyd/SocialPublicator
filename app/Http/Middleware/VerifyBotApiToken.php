<?php

namespace App\Http\Middleware;

use App\Models\BotAgent;
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

            $workspaceId = config('services.bot_api.workspace_id');

            if ($workspaceId) {
                $request->attributes->set('bot_agent_workspace_id', (int) $workspaceId);
                $request->attributes->set('bot_agent_instance_id', $legacyInstanceId);
                $request->attributes->set('bot_agent_social_account_ids', null);
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

        $accountId = $request->input('social_account_id');
        $allowedAccountIds = array_map('intval', $agent->social_account_ids ?? []);

        if (filled($accountId) && ! in_array((int) $accountId, $allowedAccountIds, true)) {
            return response()->json(['message' => 'This agent is not authorized for the requested social account.'], 403);
        }

        $agent->forceFill(['last_seen_at' => now()])->saveQuietly();

        $request->attributes->set('bot_agent_workspace_id', (int) $agent->workspace_id);
        $request->attributes->set('bot_agent_instance_id', $agent->instance_id);
        $request->attributes->set('bot_agent_social_account_ids', $allowedAccountIds);

        return $next($request);
    }
}
