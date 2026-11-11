<?php

namespace Tests\Feature;

use App\Models\PublicationDestination;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaPageAccessRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_absent_from_meta_oauth_sync_are_disabled(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Revocation', 'slug' => 'revocation']);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        $user = User::factory()->create();
        WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMembership::ROLE_OWNER,
            'status' => 'active',
        ]);

        $profile = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PROFILE,
            'external_account_id' => 'profile-revocation',
            'display_name' => 'Perfil',
            'status' => SocialAccount::STATUS_CONNECTED,
        ]);
        $page = SocialAccount::query()->create([
            'parent_account_id' => $profile->id,
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PAGE,
            'external_account_id' => 'page-revocation',
            'display_name' => 'Página',
            'status' => SocialAccount::STATUS_CONNECTED,
        ]);
        $connection = SocialAccountConnection::query()->create([
            'social_account_id' => $page->id,
            'connection_type' => SocialAccountConnection::TYPE_META_OAUTH,
            'provider' => 'meta',
            'status' => 'connected',
            'credentials' => ['access_token' => 'page-token', 'token_type' => 'page'],
        ]);
        $destination = PublicationDestination::query()->create([
            'social_account_id' => $page->id,
            'platform' => 'facebook',
            'external_id' => 'page-revocation',
            'name' => 'Página',
            'active' => true,
        ]);

        config([
            'services.meta.app_id' => 'app-id',
            'services.meta.app_secret' => 'app-secret',
            'services.meta.redirect_uri' => route('social-accounts.meta.callback'),
        ]);
        Http::fake([
            'https://graph.facebook.com/v26.0/oauth/access_token' => Http::sequence()
                ->push(['access_token' => 'short-token', 'expires_in' => 3600])
                ->push(['access_token' => 'long-token', 'expires_in' => 5184000]),
            'https://graph.facebook.com/v26.0/me?*' => Http::response([
                'id' => 'profile-revocation',
                'name' => 'Perfil',
            ]),
            'https://graph.facebook.com/v26.0/me/accounts?*' => Http::response(['data' => []]),
        ]);

        $this->actingAs($user)->withSession(['active_workspace_id' => $workspace->id]);
        $start = $this->get(route('social-accounts.meta.connect', $profile));
        parse_str((string) parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->get(route('social-accounts.meta.callback', [
            'state' => $query['state'],
            'code' => 'authorization-code',
        ]))->assertRedirect(route('filament.admin.resources.social-accounts.index'));

        app(WorkspaceContext::class)->set((int) $workspace->id);
        $this->assertSame(SocialAccount::STATUS_DISCONNECTED, $page->fresh()->status);
        $this->assertSame('revoked', $connection->fresh()->status);
        $this->assertNotNull($connection->fresh()->revoked_at);
        $this->assertFalse($destination->fresh()->active);
    }
}
