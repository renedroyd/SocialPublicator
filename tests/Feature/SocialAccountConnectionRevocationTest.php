<?php

namespace Tests\Feature;

use App\Models\BotAgent;
use App\Models\PublicationDestination;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\SocialAccountConnectionService;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialAccountConnectionRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoked_whatsapp_connection_rejects_agent_state_until_reactivated(): void
    {
        $workspace = $this->workspace();
        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'display_name' => 'WhatsApp de prueba',
        ]);
        $connection = SocialAccountConnection::query()->create([
            'social_account_id' => $account->id,
            'connection_type' => SocialAccountConnection::TYPE_WPP_CONNECT_QR,
            'provider' => 'wppconnect',
            'status' => 'connected',
            'agent_instance_id' => 'wbot-wa-01',
            'session_reference' => 'wppconnect:opaque',
            'provider_connection_id' => 'whatsapp-bot',
        ]);
        $this->agent($workspace, $account, 'health-token');

        $service = app(SocialAccountConnectionService::class);
        $service->revoke($account, SocialAccountConnection::TYPE_WPP_CONNECT_QR);

        $this->withToken('health-token')
            ->postJson('/api/bot/whatsapp/connection/state', [
                'social_account_id' => $account->id,
                'agent_instance_id' => 'wbot-wa-01',
                'session_name' => 'whatsapp-bot',
                'status' => 'connected',
                'phone' => '+15550000000',
            ])
            ->assertStatus(423);

        $service->reactivate($account->fresh(), SocialAccountConnection::TYPE_WPP_CONNECT_QR);

        $this->withToken('health-token')
            ->postJson('/api/bot/whatsapp/connection/state', [
                'social_account_id' => $account->id,
                'agent_instance_id' => 'wbot-wa-01',
                'session_name' => 'whatsapp-bot',
                'status' => 'connected',
                'phone' => '+15550000000',
            ])
            ->assertOk();

        $this->assertSame('connected', $connection->fresh()->status);
        $this->assertNull($connection->fresh()->revoked_at);
    }

    public function test_revoking_meta_profile_disables_child_pages_and_destinations_without_erasing_tokens(): void
    {
        $this->workspace();
        $profile = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PROFILE,
            'external_account_id' => 'profile-900',
            'display_name' => 'Perfil de Meta',
            'status' => SocialAccount::STATUS_CONNECTED,
        ]);
        $profileConnection = SocialAccountConnection::query()->create([
            'social_account_id' => $profile->id,
            'connection_type' => SocialAccountConnection::TYPE_META_OAUTH,
            'provider' => 'meta',
            'status' => 'connected',
            'credentials' => ['access_token' => 'encrypted-user-token', 'token_type' => 'user'],
        ]);
        $page = SocialAccount::query()->create([
            'parent_account_id' => $profile->id,
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PAGE,
            'external_account_id' => 'page-900',
            'display_name' => 'Página de prueba',
            'status' => SocialAccount::STATUS_CONNECTED,
        ]);
        $pageConnection = SocialAccountConnection::query()->create([
            'social_account_id' => $page->id,
            'connection_type' => SocialAccountConnection::TYPE_META_OAUTH,
            'provider' => 'meta',
            'status' => 'connected',
            'credentials' => ['access_token' => 'encrypted-page-token', 'token_type' => 'page'],
        ]);
        $destination = PublicationDestination::query()->create([
            'social_account_id' => $page->id,
            'platform' => 'facebook',
            'external_id' => 'page-900',
            'name' => 'Página de prueba',
            'active' => true,
        ]);

        app(SocialAccountConnectionService::class)->revoke(
            $profile,
            SocialAccountConnection::TYPE_META_OAUTH,
        );

        $this->assertSame('revoked', $profileConnection->fresh()->status);
        $this->assertSame('revoked', $pageConnection->fresh()->status);
        $this->assertSame('encrypted-user-token', $profileConnection->fresh()->credentials['access_token']);
        $this->assertSame('encrypted-page-token', $pageConnection->fresh()->credentials['access_token']);
        $this->assertSame(SocialAccount::STATUS_DISCONNECTED, $page->fresh()->status);
        $this->assertFalse($destination->fresh()->active);
    }

    private function workspace(): Workspace
    {
        $workspace = Workspace::query()->create([
            'name' => 'Revocation workspace',
            'slug' => 'revocation-'.str()->random(8),
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        $user = User::factory()->create();
        WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMembership::ROLE_OWNER,
            'status' => 'active',
        ]);

        return $workspace;
    }

    private function agent(Workspace $workspace, SocialAccount $account, string $token): BotAgent
    {
        return BotAgent::query()->create([
            'name' => 'WhatsApp agent',
            'instance_id' => 'wbot-wa-01',
            'token_hash' => hash('sha256', $token),
            'social_account_ids' => [$account->id],
            'is_active' => true,
        ]);
    }
}
