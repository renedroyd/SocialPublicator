<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Publication;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use App\Services\Workspaces\AcceptWorkspaceInvitation;
use App\Services\Workspaces\WorkspaceContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
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
}
