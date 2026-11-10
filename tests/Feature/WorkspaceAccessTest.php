<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\PublicationDestination;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\Publication;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceMembershipDestinationPermission;
use App\Models\WorkspaceMembershipSocialAccountPermission;
use App\Services\Workspaces\AcceptWorkspaceInvitation;
use App\Services\Workspaces\WorkspaceContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_permissions_follow_role_and_explicit_overrides(): void
    {
        $workspace = Workspace::query()->create([
            'name' => 'Equipo editorial',
            'slug' => 'equipo-editorial',
        ]);

        $editor = WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => User::factory()->create()->id,
            'role' => WorkspaceMembership::ROLE_EDITOR,
            'status' => 'active',
        ]);

        $this->assertTrue($editor->hasPermission('publications.publish'));
        $this->assertFalse($editor->hasPermission('accounts.manage'));

        $editor->update(['permissions' => ['accounts.manage']]);

        $this->assertTrue($editor->fresh()->hasPermission('accounts.manage'));
        $this->assertTrue($editor->fresh()->hasPermission('accounts.view'));
        $this->assertTrue($editor->fresh()->hasPermission('accounts.create'));
        $this->assertFalse($editor->fresh()->hasPermission('publications.publish'));
    }

    public function test_inactive_membership_cannot_use_permissions_even_with_owner_role(): void
    {
        $workspace = Workspace::query()->create([
            'name' => 'Equipo suspendido',
            'slug' => 'equipo-suspendido',
        ]);

        $membership = WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => User::factory()->create()->id,
            'role' => WorkspaceMembership::ROLE_OWNER,
            'status' => 'suspended',
        ]);

        $this->assertFalse($membership->hasPermission('publications.view'));
        $this->assertFalse($membership->canManageMembers());
        $this->assertFalse($membership->isOwner());
    }

    public function test_workspace_invitation_is_usable_only_before_expiration_or_revocation(): void
    {
        $workspace = Workspace::query()->create([
            'name' => 'Equipo de prueba',
            'slug' => 'equipo-prueba',
        ]);

        $invitation = WorkspaceInvitation::query()->create([
            'workspace_id' => $workspace->id,
            'email' => 'editor@example.test',
            'role' => WorkspaceMembership::ROLE_EDITOR,
            'token_hash' => hash('sha256', 'one-time-secret'),
            'expires_at' => now()->addDay(),
        ]);

        $this->assertTrue($invitation->isUsable());

        $invitation->update(['revoked_at' => now()]);

        $this->assertFalse($invitation->fresh()->isUsable());
    }

    public function test_users_without_an_active_workspace_membership_cannot_access_filament(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_workspace_owned_records_are_invisible_outside_the_active_workspace(): void
    {
        $first = Workspace::query()->create(['name' => 'Workspace Uno', 'slug' => 'workspace-uno']);
        $second = Workspace::query()->create(['name' => 'Workspace Dos', 'slug' => 'workspace-dos']);
        $context = app(WorkspaceContext::class);

        $context->set((int) $first->id);
        Category::query()->create(['nombre' => 'Bebidas', 'activo' => true]);

        $context->set((int) $second->id);
        Category::query()->create(['nombre' => 'Bebidas', 'activo' => true]);

        $this->assertSame(1, Category::query()->count());
        $this->assertSame('Bebidas', Category::query()->firstOrFail()->nombre);

        $context->clear();
        $this->assertSame(0, Category::query()->count());
    }

    public function test_creating_workspace_owned_records_without_context_is_rejected_when_multiple_workspaces_exist(): void
    {
        Workspace::query()->create(['name' => 'Workspace Uno', 'slug' => 'workspace-uno']);
        Workspace::query()->create(['name' => 'Workspace Dos', 'slug' => 'workspace-dos']);

        $this->expectException(LogicException::class);

        Category::query()->create(['nombre' => 'Sin contexto', 'activo' => true]);
    }

    public function test_invited_user_can_access_filament_and_accepts_the_invitation_once(): void
    {
        $workspace = Workspace::query()->create([
            'name' => 'Workspace invitado',
            'slug' => 'workspace-invitado',
        ]);
        $user = User::factory()->create(['email' => 'editor@example.test']);
        $token = str_repeat('a', 64);

        $invitation = WorkspaceInvitation::query()->create([
            'workspace_id' => $workspace->id,
            'email' => $user->email,
            'role' => WorkspaceMembership::ROLE_EDITOR,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));

        $membership = app(AcceptWorkspaceInvitation::class)->accept($user, $token);

        $this->assertSame($workspace->id, $membership->workspace_id);
        $this->assertSame(WorkspaceMembership::ROLE_EDITOR, $membership->role);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertFalse($invitation->fresh()->isUsable());
    }

    public function test_invitation_cannot_be_accepted_by_a_different_email_address(): void
    {
        $workspace = Workspace::query()->create([
            'name' => 'Workspace privado',
            'slug' => 'workspace-privado',
        ]);
        $user = User::factory()->create(['email' => 'other@example.test']);
        $token = str_repeat('b', 64);

        WorkspaceInvitation::query()->create([
            'workspace_id' => $workspace->id,
            'email' => 'invited@example.test',
            'role' => WorkspaceMembership::ROLE_EDITOR,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        $this->expectException(AuthorizationException::class);

        app(AcceptWorkspaceInvitation::class)->accept($user, $token);
    }

    public function test_workspace_database_constraints_reject_cross_workspace_publication_products(): void
    {
        $first = Workspace::query()->create(['name' => 'Workspace Uno', 'slug' => 'workspace-uno']);
        $second = Workspace::query()->create(['name' => 'Workspace Dos', 'slug' => 'workspace-dos']);
        $context = app(WorkspaceContext::class);

        $context->set((int) $first->id);
        $category = Category::query()->create(['nombre' => 'Bebidas', 'activo' => true]);
        $localProduct = Product::query()->create([
            'categoria_id' => $category->id,
            'sku' => 'LOCAL-001',
            'nombre' => 'Producto propio',
            'unidad' => 'unidad',
            'precio_costo' => 1,
            'precio_mayorista' => 2,
            'precio_minorista' => 3,
            'stock' => 10,
            'stock_minimo' => 0,
            'stock_maximo' => 20,
            'activo' => true,
        ]);
        $publication = Publication::query()->create([
            'date' => now()->toDateString(),
            'time' => '12:00:00',
            'image' => 'publication.jpg',
            'text' => 'Publicación del primer espacio',
            'destination' => 'whatsapp',
            'active' => true,
        ]);

        $context->set((int) $second->id);
        $otherCategory = Category::query()->create(['nombre' => 'Alimentos', 'activo' => true]);
        $foreignProduct = Product::query()->create([
            'categoria_id' => $otherCategory->id,
            'sku' => 'OTHER-001',
            'nombre' => 'Producto ajeno',
            'unidad' => 'unidad',
            'precio_costo' => 1,
            'precio_mayorista' => 2,
            'precio_minorista' => 3,
            'stock' => 10,
            'stock_minimo' => 0,
            'stock_maximo' => 20,
            'activo' => true,
        ]);

        $context->set((int) $first->id);
        $publication->products()->attach($localProduct->id);

        $this->assertSame(
            [$localProduct->id],
            $publication->products()->pluck('products.id')->all(),
        );

        $this->expectException(QueryException::class);

        $publication->products()->attach($foreignProduct->id);
    }

    public function test_social_account_credentials_are_encrypted_and_browser_sessions_are_referenced_only(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Cuentas seguras', 'slug' => 'cuentas-seguras']);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PROFILE,
            'external_account_id' => 'profile-100',
            'display_name' => 'Perfil de prueba',
            'status' => SocialAccount::STATUS_PENDING,
        ]);

        $oauth = SocialAccountConnection::query()->create([
            'social_account_id' => $account->id,
            'connection_type' => SocialAccountConnection::TYPE_META_OAUTH,
            'provider' => 'meta',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'sensitive-access-token',
                'refresh_token' => 'sensitive-refresh-token',
            ],
        ]);

        $storedCredentials = DB::table('social_account_connections')
            ->where('id', $oauth->id)
            ->value('credentials');

        $this->assertIsString($storedCredentials);
        $this->assertStringNotContainsString('sensitive-access-token', $storedCredentials);
        $this->assertSame('sensitive-access-token', $oauth->fresh()->credentials['access_token']);
        $this->assertArrayNotHasKey('credentials', $oauth->toArray());

        $browserSession = SocialAccountConnection::query()->create([
            'social_account_id' => $account->id,
            'connection_type' => SocialAccountConnection::TYPE_BROWSER_SESSION,
            'provider' => 'puppeteer',
            'status' => 'connected',
            'agent_instance_id' => 'wbot-agent-01',
            'session_reference' => 'opaque-facebook-session-01',
        ]);

        $this->assertArrayNotHasKey('session_reference', $browserSession->toArray());
        $this->assertNull($browserSession->fresh()->credentials);
    }

    public function test_editor_needs_both_account_and_destination_grants_to_publish(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Permisos sociales', 'slug' => 'permisos-sociales']);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $membership = WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => User::factory()->create()->id,
            'role' => WorkspaceMembership::ROLE_EDITOR,
            'status' => 'active',
        ]);

        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PAGE,
            'parent_account_id' => SocialAccount::query()->create([
                'platform' => SocialAccount::PLATFORM_FACEBOOK,
                'account_type' => SocialAccount::TYPE_FACEBOOK_PROFILE,
                'external_account_id' => 'profile-200',
                'display_name' => 'Perfil principal',
            ])->id,
            'external_account_id' => 'page-200',
            'display_name' => 'Página autorizada',
        ]);

        $destination = PublicationDestination::query()->create([
            'social_account_id' => $account->id,
            'platform' => 'facebook',
            'external_id' => 'page-200',
            'name' => 'Página autorizada',
            'active' => true,
        ]);

        WorkspaceMembershipDestinationPermission::query()->create([
            'membership_id' => $membership->id,
            'destination_type' => WorkspaceMembershipDestinationPermission::DESTINATION_PUBLICATION,
            'destination_id' => $destination->id,
            'permissions' => ['publish'],
        ]);

        $this->assertTrue($membership->fresh()->canUseDestination(
            WorkspaceMembershipDestinationPermission::DESTINATION_PUBLICATION,
            $destination->id,
            'publish',
        ));
        $this->assertFalse($membership->fresh()->canPublishToDestination(
            WorkspaceMembershipDestinationPermission::DESTINATION_PUBLICATION,
            $destination->id,
        ));

        WorkspaceMembershipSocialAccountPermission::query()->create([
            'membership_id' => $membership->id,
            'social_account_id' => $account->id,
            'permissions' => ['publish'],
        ]);

        $freshMembership = $membership->fresh();

        $this->assertTrue($freshMembership->canUseSocialAccount($account, 'publish'));
        $this->assertTrue($freshMembership->canPublishToDestination(
            WorkspaceMembershipDestinationPermission::DESTINATION_PUBLICATION,
            $destination->id,
        ));
    }

    public function test_account_permissions_cannot_cross_workspace_boundaries(): void
    {
        $first = Workspace::query()->create(['name' => 'Cuenta A', 'slug' => 'cuenta-a']);
        $second = Workspace::query()->create(['name' => 'Cuenta B', 'slug' => 'cuenta-b']);

        $membership = WorkspaceMembership::query()->create([
            'workspace_id' => $first->id,
            'user_id' => User::factory()->create()->id,
            'role' => WorkspaceMembership::ROLE_EDITOR,
            'status' => 'active',
        ]);

        app(WorkspaceContext::class)->set((int) $second->id);
        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'external_account_id' => 'wa-300',
            'display_name' => 'WhatsApp externo',
        ]);

        app(WorkspaceContext::class)->set((int) $first->id);

        $this->expectException(QueryException::class);

        WorkspaceMembershipSocialAccountPermission::query()->create([
            'membership_id' => $membership->id,
            'social_account_id' => $account->id,
            'permissions' => ['view'],
        ]);
    }


    public function test_destination_grants_reject_destinations_from_another_workspace(): void
    {
        $first = Workspace::query()->create(['name' => 'Destino A', 'slug' => 'destino-a']);
        $second = Workspace::query()->create(['name' => 'Destino B', 'slug' => 'destino-b']);

        $membership = WorkspaceMembership::query()->create([
            'workspace_id' => $first->id,
            'user_id' => User::factory()->create()->id,
            'role' => WorkspaceMembership::ROLE_EDITOR,
            'status' => 'active',
        ]);

        app(WorkspaceContext::class)->set((int) $second->id);
        $destination = PublicationDestination::query()->create([
            'platform' => 'facebook',
            'external_id' => 'foreign-page-1',
            'name' => 'Página ajena',
            'active' => true,
        ]);

        app(WorkspaceContext::class)->set((int) $first->id);

        $this->expectException(LogicException::class);

        WorkspaceMembershipDestinationPermission::query()->create([
            'membership_id' => $membership->id,
            'destination_type' => WorkspaceMembershipDestinationPermission::DESTINATION_PUBLICATION,
            'destination_id' => $destination->id,
            'permissions' => ['view'],
        ]);
    }

}
