<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\WorkspaceMembership;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MetaOAuthController extends Controller
{
    public function connect(Request $request, int $socialAccount): Response
    {
        $socialAccount = SocialAccount::query()->findOrFail($socialAccount);
        $membership = $this->authorizeAccount($request, $socialAccount);
        abort_unless($socialAccount->account_type === SocialAccount::TYPE_FACEBOOK_PROFILE, 422);

        $appId = (string) config('services.meta.app_id');
        abort_if($appId === '' || (string) config('services.meta.app_secret') === '', 503);

        $state = Str::random(64);
        Cache::put($this->stateKey($state), [
            'user_id' => $request->user()->id,
            'workspace_id' => $membership->workspace_id,
            'social_account_id' => $socialAccount->id,
        ], now()->addMinutes(10));

        $query = http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', config('services.meta.permissions', [])),
        ]);

        return redirect()->away('https://www.facebook.com/'.$this->graphVersion().'/dialog/oauth?'.$query);
    }

    public function callback(Request $request): Response
    {
        $state = (string) $request->query('state');
        $pending = $state !== '' ? Cache::pull($this->stateKey($state)) : null;

        abort_unless(is_array($pending), 419);
        abort_unless((int) $pending['user_id'] === (int) $request->user()->id, 403);
        abort_unless((int) $pending['workspace_id'] === (int) app(WorkspaceContext::class)->id(), 403);

        $account = SocialAccount::query()->findOrFail($pending['social_account_id']);
        $this->authorizeAccount($request, $account);

        if ($request->filled('error')) {
            $account->update([
                'status' => SocialAccount::STATUS_DISCONNECTED,
                'last_error_at' => now(),
                'last_error_message' => 'La autorización de Meta fue cancelada o denegada.',
            ]);

            return $this->back()->with('warning', 'La autorización de Meta no se completó.');
        }

        $code = (string) $request->query('code');
        abort_if($code === '', 422);

        try {
            $base = 'https://graph.facebook.com/'.$this->graphVersion();
            $appId = (string) config('services.meta.app_id');
            $secret = (string) config('services.meta.app_secret');

            $short = Http::acceptJson()->get($base.'/oauth/access_token', [
                'client_id' => $appId,
                'client_secret' => $secret,
                'redirect_uri' => $this->redirectUri(),
                'code' => $code,
            ])->throw()->json();

            $long = Http::acceptJson()->get($base.'/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $secret,
                'fb_exchange_token' => $short['access_token'] ?? '',
            ])->throw()->json();

            $token = (string) ($long['access_token'] ?? '');
            abort_if($token === '', 502);

            $profile = Http::acceptJson()->get($base.'/me', [
                'fields' => 'id,name',
                'access_token' => $token,
                'appsecret_proof' => hash_hmac('sha256', $token, $secret),
            ])->throw()->json();

            $pages = Http::acceptJson()->get($base.'/me/accounts', [
                'fields' => 'id,name,access_token,tasks',
                'access_token' => $token,
                'appsecret_proof' => hash_hmac('sha256', $token, $secret),
            ])->throw()->json('data', []);

            abort_unless(is_array($profile) && filled($profile['id'] ?? null), 502);

            DB::transaction(function () use ($account, $profile, $long, $token, $pages): void {
                $expiresAt = isset($long['expires_in'])
                    ? now()->addSeconds((int) $long['expires_in'])
                    : null;

                $account->update([
                    'external_account_id' => (string) $profile['id'],
                    'display_name' => (string) ($profile['name'] ?? $account->display_name),
                    'status' => SocialAccount::STATUS_CONNECTED,
                    'connected_at' => now(),
                    'last_verified_at' => now(),
                    'last_error_at' => null,
                    'last_error_message' => null,
                ]);

                $account->connections()->updateOrCreate(
                    ['connection_type' => SocialAccountConnection::TYPE_META_OAUTH, 'provider' => 'meta'],
                    [
                        'status' => 'connected',
                        'credentials' => ['access_token' => $token, 'token_type' => 'user'],
                        'expires_at' => $expiresAt,
                        'connected_at' => now(),
                        'last_verified_at' => now(),
                        'revoked_at' => null,
                        'last_error_code' => null,
                        'last_error_message' => null,
                    ],
                );

                foreach (is_array($pages) ? $pages : [] as $page) {
                    if (! is_array($page) || empty($page['id']) || empty($page['access_token'])) {
                        continue;
                    }

                    $pageAccount = SocialAccount::query()->firstOrNew([
                        'platform' => SocialAccount::PLATFORM_FACEBOOK,
                        'account_type' => SocialAccount::TYPE_FACEBOOK_PAGE,
                        'external_account_id' => (string) $page['id'],
                    ]);
                    $pageAccount->fill([
                        'parent_account_id' => $account->id,
                        'display_name' => (string) ($page['name'] ?? 'Página de Facebook'),
                        'status' => SocialAccount::STATUS_CONNECTED,
                        'capabilities' => ['pages_api' => true],
                        'metadata' => ['tasks' => array_values(array_filter($page['tasks'] ?? [], 'is_string'))],
                        'connected_at' => now(),
                        'last_verified_at' => now(),
                    ]);
                    $pageAccount->save();

                    $pageAccount->connections()->updateOrCreate(
                        ['connection_type' => SocialAccountConnection::TYPE_META_OAUTH, 'provider' => 'meta'],
                        [
                            'status' => 'connected',
                            'credentials' => ['access_token' => (string) $page['access_token'], 'token_type' => 'page'],
                            'expires_at' => $expiresAt,
                            'connected_at' => now(),
                            'last_verified_at' => now(),
                            'revoked_at' => null,
                            'last_error_code' => null,
                            'last_error_message' => null,
                        ],
                    );
                }
            });
        } catch (ConnectionException $exception) {
            Log::warning('Meta OAuth transport failure.', [
                'social_account_id' => $account->id,
                'exception_type' => $exception::class,
            ]);
            $account->update([
                'status' => SocialAccount::STATUS_ERROR,
                'last_error_at' => now(),
                'last_error_message' => 'No fue posible conectar con los servicios de Meta.',
            ]);

            return $this->back()->with('danger', 'No se pudo completar la conexión con Meta.');
        } catch (\Throwable $exception) {
            if (app()->runningUnitTests()) {
                throw $exception;
            }

            Log::warning('Meta OAuth callback failed.', [
                'social_account_id' => $account->id,
                'exception_type' => $exception::class,
            ]);

            $account->update([
                'status' => SocialAccount::STATUS_ERROR,
                'last_error_at' => now(),
                'last_error_message' => 'Meta rechazó la autorización o la respuesta no fue válida.',
            ]);

            return $this->back()->with('danger', 'Meta no pudo completar la autorización. Revisa los permisos de la aplicación.');
        }

        return $this->back()->with('success', 'Perfil de Facebook conectado y páginas autorizadas sincronizadas.');
    }

    private function authorizeAccount(Request $request, SocialAccount $account): WorkspaceMembership
    {
        $membership = $request->user()->activeWorkspaceMemberships()
            ->where('workspace_id', $account->workspace_id)
            ->first();

        abort_unless($membership && $membership->canUseSocialAccount($account, 'manage'), 403);

        return $membership;
    }

    private function stateKey(string $state): string
    {
        return 'meta_oauth_state:'.hash('sha256', $state);
    }

    private function redirectUri(): string
    {
        return (string) config('services.meta.redirect_uri') ?: route('social-accounts.meta.callback');
    }

    private function graphVersion(): string
    {
        $version = (string) config('services.meta.graph_version', 'v26.0');

        return preg_match('/^v[0-9]+\.[0-9]+$/', $version) ? $version : 'v26.0';
    }

    private function back()
    {
        return redirect()->route('filament.admin.resources.social-accounts.index');
    }
}
