<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Models\WorkspaceDubaiShopIntegration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VerifyDubaiShopWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $body = $request->getContent();

        if (strlen($body) > 1_048_576) {
            return response()->json(['message' => 'Payload too large.'], 413);
        }

        $keyId = (string) $request->header('X-DubaiShop-Key-Id');
        $timestamp = (string) $request->header('X-DubaiShop-Timestamp');
        $signature = (string) $request->header('X-DubaiShop-Signature');
        $signatureVersion = (string) $request->header('X-DubaiShop-Signature-Version', 'v1');
        $installationId = (string) $request->header('X-DubaiShop-Installation-Id');

        if ($keyId === '' || ! ctype_digit($timestamp)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $integration = WorkspaceDubaiShopIntegration::withoutGlobalScope('workspace')
            ->where('webhook_key_id', $keyId)
            ->first();

        if ($integration) {
            if (! $integration->isReady()) {
                return response()->json(['message' => 'Invalid webhook signature.'], 401);
            }

            if ($signatureVersion === 'v2') {
                if (! Str::isUuid($installationId) || ! hash_equals((string) $integration->installation_id, $installationId)) {
                    return response()->json(['message' => 'Invalid installation identity.'], 401);
                }
            } elseif ($signatureVersion !== 'v1' || filled($integration->installation_id)) {
                return response()->json(['message' => 'Unsupported webhook signature version.'], 401);
            }

            $secret = $integration->webhook_secret;
            $workspaceId = (int) $integration->workspace_id;
            $tolerance = max(1, (int) $integration->webhook_tolerance_seconds);
        } else {
            if ($signatureVersion !== 'v1') {
                return response()->json(['message' => 'Unknown installation identity.'], 401);
            }

            // Compatibility for existing single-workspace installations using env-based keys.
            $keys = config('services.dubai_shop.webhook_keys', []);
            $keyConfig = is_array($keys) ? ($keys[$keyId] ?? null) : null;
            $secret = is_array($keyConfig) ? ($keyConfig['secret'] ?? null) : $keyConfig;
            $workspaceId = is_array($keyConfig)
                ? (int) ($keyConfig['workspace_id'] ?? config('services.dubai_shop.workspace_id', 0))
                : (int) config('services.dubai_shop.workspace_id', 0);

            if ($workspaceId < 1) {
                $workspaceIds = Workspace::query()->withoutGlobalScopes()->limit(2)->pluck('id');
                $workspaceId = $workspaceIds->count() === 1 ? (int) $workspaceIds->first() : 0;
            }

            if (
                ! is_string($secret)
                || $secret === ''
                || $workspaceId < 1
                || blank(config('services.dubai_shop.api_base_url'))
                || blank(config('services.dubai_shop.api_token'))
                || WorkspaceDubaiShopIntegration::withoutGlobalScope('workspace')
                    ->where('workspace_id', $workspaceId)
                    ->exists()
            ) {
                return response()->json(['message' => 'Invalid webhook signature.'], 401);
            }

            $tolerance = max(1, (int) config('services.dubai_shop.webhook_tolerance_seconds', 300));
        }

        if (abs(now()->timestamp - (int) $timestamp) > $tolerance) {
            return response()->json(['message' => 'Webhook timestamp outside the accepted window.'], 401);
        }

        $signedPayload = $signatureVersion === 'v2'
            ? $timestamp.'.'.$installationId.'.'.$body
            : $timestamp.'.'.$body;
        $expected = 'sha256='.hash_hmac('sha256', $signedPayload, $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $request->attributes->set('dubai_shop_webhook_key_id', $keyId);
        $request->attributes->set('dubai_shop_installation_id', $signatureVersion === 'v2' ? $installationId : null);
        $request->attributes->set('dubai_shop_workspace_id', $workspaceId);

        return $next($request);
    }
}
