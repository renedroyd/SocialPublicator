<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaOAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_connects_a_facebook_profile_and_stores_page_tokens_encrypted(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Meta workspace', 'slug' => 'meta-workspace']);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        $user = User::factory()->create();
        WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMembership::ROLE_OWNER,
            'status' => 'active',
        ]);
        $otherWorkspace = Workspace::query()->create(['name' => 'Otro workspace', 'slug' => 'otro-workspace']);
        WorkspaceMembership::query()->create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMembership::ROLE_OWNER,
            'status' => 'active',
        ]);
        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PROFILE,
            'display_name' => 'Perfil pendiente',
        ]);

        config([
            'services.meta.app_id' => 'meta-app-id',
            'services.meta.app_secret' => 'meta-app-secret',
            'services.meta.redirect_uri' => route('social-accounts.meta.callback'),
            'services.meta.graph_version' => 'v26.0',
        ]);

        Http::fake(function (HttpRequest $request) {
            $url = $request->url();

            if (str_contains($url, '/oauth/access_token')) {
                return str_contains($url, 'code=one-time-code')
                    ? Http::response(['access_token' => 'short-user-token', 'expires_in' => 3600])
                    : Http::response(['access_token' => 'long-user-token', 'expires_in' => 5184000]);
            }

            if (str_contains($url, '/me/accounts')) {
                return Http::response([
                    'data' => [[
                        'id' => 'page-42',
                        'name' => 'Página de prueba',
                        'access_token' => 'page-secret-token',
                        'tasks' => ['CREATE_CONTENT'],
                    ]],
                ]);
            }

            if (str_contains($url, '/me')) {
                return Http::response(['id' => 'profile-42', 'name' => 'Perfil de prueba']);
            }

            return Http::response([], 404);
        });

        $whatsappAccount = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'display_name' => 'WhatsApp de prueba',
        ]);

        $this->actingAs($user)->withSession(['active_workspace_id' => $workspace->id]);
        $this->get(route('social-accounts.facebook.session', $account))->assertOk();
        $this->get(route('social-accounts.whatsapp.qr', $whatsappAccount))->assertOk();

        $start = $this->get(route('social-accounts.meta.connect', $account));
        $start->assertRedirect();
        parse_str((string) parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('meta-app-id', $query['client_id']);
        $this->assertNotEmpty($query['state']);

        $this->get(route('social-accounts.meta.callback', [
            'state' => $query['state'],
            'code' => 'one-time-code',
        ]))->assertRedirect(route('filament.admin.resources.social-accounts.index'));

        app(WorkspaceContext::class)->set((int) $workspace->id);

        $this->assertDatabaseHas('social_accounts', [
            'id' => $account->id,
            'external_account_id' => 'profile-42',
            'status' => 'connected',
        ]);

        $page = SocialAccount::query()->where('external_account_id', 'page-42')->firstOrFail();
        $this->assertSame($account->id, $page->parent_account_id);
        $this->assertDatabaseHas('publication_destinations', [
            'social_account_id' => $page->id,
            'platform' => 'facebook',
            'external_id' => 'page-42',
            'active' => true,
        ]);
        $this->assertSame('page-secret-token', $page->connections()->firstOrFail()->credentials['access_token']);
        $this->assertSame('long-user-token', $account->connections()->firstOrFail()->credentials['access_token']);
        $this->assertNotNull($account->connections()->firstOrFail()->expires_at);
        $this->assertNotNull($page->connections()->firstOrFail()->expires_at);
        $this->assertArrayNotHasKey('credentials', $account->connections()->firstOrFail()->toArray());
        $this->assertSame(2, SocialAccountConnection::query()->count());
    }

    public function test_oauth_state_is_single_use(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Meta state', 'slug' => 'meta-state']);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        $user = User::factory()->create();
        WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMembership::ROLE_OWNER,
            'status' => 'active',
        ]);
        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PROFILE,
            'display_name' => 'Perfil',
        ]);
        config(['services.meta.app_id' => 'app', 'services.meta.app_secret' => 'secret']);

        $this->actingAs($user)->withSession(['active_workspace_id' => $workspace->id]);
        $start = $this->get(route('social-accounts.meta.connect', $account));
        parse_str((string) parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->get(route('social-accounts.meta.callback', [
            'state' => $query['state'],
            'error' => 'access_denied',
        ]))->assertRedirect();

        $this->get(route('social-accounts.meta.callback', [
            'state' => $query['state'],
            'error' => 'access_denied',
        ]))->assertStatus(419);
    }
}
