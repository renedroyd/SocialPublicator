<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        $keys = config('services.dubai_shop.webhook_keys', []);
        $keyConfig = is_array($keys) ? ($keys[$keyId] ?? null) : null;
        $secret = is_array($keyConfig) ? ($keyConfig['secret'] ?? null) : $keyConfig;

        if (! is_string($secret) || $secret === '' || ! ctype_digit($timestamp)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $tolerance = max(1, (int) config('services.dubai_shop.webhook_tolerance_seconds', 300));

        if (abs(now()->timestamp - (int) $timestamp) > $tolerance) {
            return response()->json(['message' => 'Webhook timestamp outside the accepted window.'], 401);
        }

        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $request->attributes->set('dubai_shop_webhook_key_id', $keyId);

        if (is_array($keyConfig) && isset($keyConfig['workspace_id'])) {
            $request->attributes->set('dubai_shop_workspace_id', (int) $keyConfig['workspace_id']);
        }

        return $next($request);
    }
}
