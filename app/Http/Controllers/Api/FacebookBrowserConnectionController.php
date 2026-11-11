<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacebookBrowserConnectionController extends Controller
{
    public function state(Request $request): JsonResponse
    {
        $data = $request->validate([
            'social_account_id' => ['required', 'integer'],
            'agent_instance_id' => ['required', 'string', 'max:191'],
            'status' => ['required', 'in:pending,connected,disconnected,error'],
            'error' => ['nullable', 'string', 'max:500'],
        ]);

        $agentInstanceId = (string) ($request->attributes->get('bot_agent_instance_id') ?? $data['agent_instance_id']);
        $account = SocialAccount::query()->findOrFail($data['social_account_id']);
        abort_unless(
            $account->platform === SocialAccount::PLATFORM_FACEBOOK
                && $account->account_type === SocialAccount::TYPE_FACEBOOK_PROFILE,
            422,
        );

        $connection = $account->connections()
            ->where('connection_type', SocialAccountConnection::TYPE_BROWSER_SESSION)
            ->where('provider', 'puppeteer')
            ->first();

        abort_if(
            in_array($connection?->last_error_code, ['workspace_admin_revoked', 'parent_profile_revoked'], true),
            423,
            'La conexión fue revocada desde SocialPublicator. Reactívala desde el panel antes de volver a conectar.',
        );

        $connected = $data['status'] === 'connected';
        $disconnected = $data['status'] === 'disconnected';
        $hasError = $data['status'] === 'error';

        $account->update([
            'status' => $connected
                ? SocialAccount::STATUS_CONNECTED
                : ($disconnected ? SocialAccount::STATUS_DISCONNECTED : ($hasError ? SocialAccount::STATUS_ERROR : SocialAccount::STATUS_PENDING)),
            'connected_at' => $connected ? ($account->connected_at ?? now()) : $account->connected_at,
            'last_verified_at' => $connected ? now() : $account->last_verified_at,
            'last_error_at' => $hasError ? now() : null,
            'last_error_message' => $hasError ? ($data['error'] ?? 'WBot informó un error de sesión de Facebook.') : null,
        ]);

        $account->connections()->updateOrCreate(
            [
                'connection_type' => SocialAccountConnection::TYPE_BROWSER_SESSION,
                'provider' => 'puppeteer',
            ],
            [
                'status' => $connected ? 'connected' : ($disconnected ? 'revoked' : ($hasError ? 'error' : 'pending')),
                'agent_instance_id' => $agentInstanceId,
                'session_reference' => 'puppeteer:'.hash('sha256', $agentInstanceId.':'.$account->id),
                'provider_connection_id' => 'facebook-browser-session',
                'credentials' => null,
                'expires_at' => null,
                'connected_at' => $connected ? now() : null,
                'last_verified_at' => $connected ? now() : null,
                'revoked_at' => $disconnected ? now() : null,
                'last_error_code' => $hasError ? 'agent_session_error' : null,
                'last_error_message' => $hasError ? ($data['error'] ?? 'Error de sesión informado por WBot.') : null,
            ],
        );

        return response()->json([
            'success' => true,
            'status' => $data['status'],
            'social_account_id' => $account->id,
        ]);
    }
}
