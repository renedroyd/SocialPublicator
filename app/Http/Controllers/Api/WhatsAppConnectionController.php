<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WhatsAppConnectionController extends Controller
{
    public function state(Request $request): JsonResponse
    {
        $data = $request->validate([
            'social_account_id' => ['required', 'integer'],
            'agent_instance_id' => ['required', 'string', 'max:191'],
            'session_name' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'in:qr,pending,connected,disconnected,error'],
            'qr' => ['nullable', 'string', 'max:500000'],
            'phone' => ['nullable', 'string', 'max:191'],
            'error' => ['nullable', 'string', 'max:500'],
        ]);

        $account = SocialAccount::query()->findOrFail($data['social_account_id']);
        abort_unless(
            $account->platform === SocialAccount::PLATFORM_WHATSAPP
                && $account->account_type === SocialAccount::TYPE_WHATSAPP,
            422,
        );

        $cacheKey = $this->qrCacheKey($account);
        if ($data['status'] === 'qr' && filled($data['qr'])) {
            Cache::put($cacheKey, $data['qr'], now()->addSeconds(60));
        } else {
            Cache::forget($cacheKey);
        }

        $connected = $data['status'] === 'connected';
        $disconnected = $data['status'] === 'disconnected';
        $hasError = $data['status'] === 'error';

        $account->update([
            'status' => $connected
                ? SocialAccount::STATUS_CONNECTED
                : ($disconnected ? SocialAccount::STATUS_DISCONNECTED : ($hasError ? SocialAccount::STATUS_ERROR : SocialAccount::STATUS_PENDING)),
            'external_account_id' => $connected && filled($data['phone'] ?? null) ? $data['phone'] : $account->external_account_id,
            'display_name' => $connected && filled($data['phone'] ?? null) ? $data['phone'] : $account->display_name,
            'connected_at' => $connected ? ($account->connected_at ?? now()) : $account->connected_at,
            'last_verified_at' => $connected ? now() : $account->last_verified_at,
            'last_error_at' => $hasError ? now() : null,
            'last_error_message' => $hasError ? ($data['error'] ?? 'WBot informó un error de conexión.') : null,
        ]);

        $account->connections()->updateOrCreate(
            [
                'connection_type' => SocialAccountConnection::TYPE_WPP_CONNECT_QR,
                'provider' => 'wppconnect',
            ],
            [
                'status' => $connected ? 'connected' : ($disconnected ? 'revoked' : ($hasError ? 'error' : 'pending')),
                'agent_instance_id' => $data['agent_instance_id'],
                'session_reference' => 'wppconnect:'.hash('sha256', $data['agent_instance_id'].':'.$account->id.':'.($data['session_name'] ?? 'whatsapp-bot')),
                'provider_connection_id' => $data['session_name'] ?? 'whatsapp-bot',
                'credentials' => null,
                'expires_at' => null,
                'connected_at' => $connected ? now() : null,
                'last_verified_at' => $connected ? now() : null,
                'revoked_at' => $disconnected ? now() : null,
                'last_error_code' => $hasError ? 'agent_connection_error' : null,
                'last_error_message' => $hasError ? ($data['error'] ?? 'Error de conexión informado por WBot.') : null,
            ],
        );

        return response()->json([
            'success' => true,
            'status' => $data['status'],
            'social_account_id' => $account->id,
            'qr_expires_in' => $data['status'] === 'qr' ? 60 : null,
        ]);
    }

    private function qrCacheKey(SocialAccount $account): string
    {
        return 'social-account-whatsapp-qr:'.$account->workspace_id.':'.$account->id;
    }
}
