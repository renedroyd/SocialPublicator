<?php

namespace App\Http\Controllers;

use App\Models\TikTokAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TikTokOAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $clientKey = (string) config('services.tiktok.client_key');
        $redirectUri = (string) config('services.tiktok.redirect_uri');

        abort_if($clientKey === '' || $redirectUri === '', 503, 'Configura TIKTOK_CLIENT_KEY y TIKTOK_REDIRECT_URI.');

        $state = bin2hex(random_bytes(32));
        $request->session()->put('tiktok_oauth_state', $state);

        $query = http_build_query([
            'client_key' => $clientKey,
            'response_type' => 'code',
            'scope' => 'user.info.basic,video.publish',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return redirect()->away('https://www.tiktok.com/v2/auth/authorize/?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = (string) $request->session()->pull('tiktok_oauth_state', '');
        $receivedState = (string) $request->query('state', '');

        abort_if($expectedState === '' || ! hash_equals($expectedState, $receivedState), 419, 'Estado OAuth de TikTok inválido.');

        if ($request->filled('error')) {
            return redirect('/admin')->with('error', 'TikTok no autorizó la conexión.');
        }

        $code = (string) $request->query('code', '');
        abort_if($code === '', 422, 'TikTok no devolvió un código de autorización.');

        $tokenResponse = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.tiktok.redirect_uri'),
        ])->throw()->json();

        $accessToken = (string) ($tokenResponse['access_token'] ?? '');
        $openId = (string) ($tokenResponse['open_id'] ?? '');

        if ($accessToken === '' || $openId === '') {
            throw new RuntimeException('TikTok no devolvió el token o el identificador de cuenta esperado.');
        }

        $profile = Http::withToken($accessToken)
            ->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,display_name',
            ])->throw()->json('data.user', []);

        TikTokAccount::updateOrCreate(
            ['open_id' => $openId],
            [
                'username' => $profile['display_name'] ?? null,
                'access_token' => $accessToken,
                'refresh_token' => (string) ($tokenResponse['refresh_token'] ?? ''),
                'access_token_expires_at' => now()->addSeconds((int) ($tokenResponse['expires_in'] ?? 0)),
                'refresh_token_expires_at' => now()->addSeconds((int) ($tokenResponse['refresh_expires_in'] ?? 0)),
            ],
        );

        return redirect('/admin')->with('success', 'Cuenta de TikTok conectada correctamente.');
    }
}
