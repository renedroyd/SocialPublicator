<?php

namespace Tests\Feature;

use App\Filament\Resources\Publications\Schemas\PublicationForm;
use App\Models\User;
use App\Models\WhatsAppGroup;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceMembershipDestinationPermission;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class PublicationFormDestinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unassigned_whatsapp_groups_are_hidden_when_the_workspace_uses_social_accounts(): void
    {
        $workspace = Workspace::query()->create([
            'name' => 'Workspace multi-account',
            'slug' => 'workspace-multi-account',
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $user = User::factory()->create();
        $membership = WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMembership::ROLE_OWNER,
            'status' => 'active',
        ]);
        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'display_name' => 'Cuenta asignada',
        ]);

        $linkedGroup = WhatsAppGroup::query()->create([
            'social_account_id' => $account->id,
            'whatsapp_id' => 'linked-group@g.us',
            'name' => 'Grupo vinculado',
            'active' => true,
            'is_admin' => true,
        ]);
        $orphanGroup = WhatsAppGroup::query()->create([
            'whatsapp_id' => 'orphan-group@g.us',
            'name' => 'Grupo sin cuenta',
            'active' => true,
            'is_admin' => true,
        ]);

        $this->actingAs($user);

        $method = new ReflectionMethod(PublicationForm::class, 'authorizedDestinations');
        $method->setAccessible(true);
        $query = $method->invoke(
            null,
            WhatsAppGroup::query(),
            WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP,
        );

        $this->assertSame([$linkedGroup->id], $query->pluck('id')->all());
        $this->assertTrue($membership->canPublishToDestination(
            WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP,
            (int) $linkedGroup->id,
        ));
        $this->assertFalse($membership->canPublishToDestination(
            WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP,
            (int) $orphanGroup->id,
        ));
    }

    public function test_whatsapp_publication_form_only_offers_groups_where_the_connected_account_is_admin(): void
    {
        $workspace = Workspace::query()->create([
            'name' => 'Workspace WhatsApp',
            'slug' => 'workspace-whatsapp',
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $user = User::factory()->create();
        WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMembership::ROLE_OWNER,
            'status' => 'active',
        ]);
        $this->actingAs($user);

        $adminGroup = WhatsAppGroup::query()->create([
            'whatsapp_id' => 'admin-group@g.us',
            'name' => 'Grupo administrado',
            'active' => true,
            'is_admin' => true,
        ]);
        $memberGroup = WhatsAppGroup::query()->create([
            'whatsapp_id' => 'member-group@g.us',
            'name' => 'Grupo sin administración',
            'active' => true,
            'is_admin' => false,
        ]);
        $unknownGroup = WhatsAppGroup::query()->create([
            'whatsapp_id' => 'unknown-group@g.us',
            'name' => 'Grupo sin verificación',
            'active' => true,
            'is_admin' => null,
        ]);

        $method = new ReflectionMethod(PublicationForm::class, 'authorizedDestinations');
        $method->setAccessible(true);
        $query = $method->invoke(
            null,
            WhatsAppGroup::query(),
            WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP,
        );

        $this->assertSame(
            [$adminGroup->id],
            $query->pluck('id')->all(),
        );
        $this->assertNotContains($memberGroup->id, $query->pluck('id')->all());
        $this->assertNotContains($unknownGroup->id, $query->pluck('id')->all());
    }
}
