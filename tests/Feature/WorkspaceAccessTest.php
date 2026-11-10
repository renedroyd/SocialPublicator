<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
