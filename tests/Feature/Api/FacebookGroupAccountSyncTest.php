<?php

namespace Tests\Feature\Api;

use App\Models\FacebookGroup;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacebookGroupAccountSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_facebook_group_sync_associates_groups_with_the_profile_account(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Facebook sync', 'slug' => 'facebook-sync']);
        config([
            'services.bot_api.token' => 'test-bot-token',
            'services.bot_api.workspace_id' => $workspace->id,
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_FACEBOOK,
            'account_type' => SocialAccount::TYPE_FACEBOOK_PROFILE,
            'display_name' => 'Perfil de grupos',
        ]);
        app(WorkspaceContext::class)->clear();

        $this->withToken('test-bot-token')->postJson('/api/bot/facebook/groups/sync', [
            'social_account_id' => $account->id,
            'groups' => [[
                'facebook_id' => 'fb-group-991',
                'name' => 'Grupo real',
                'url' => 'https://www.facebook.com/groups/fb-group-991',
            ]],
        ])->assertOk()->assertJsonPath('total', 1);

        app(WorkspaceContext::class)->set((int) $workspace->id);
        $group = FacebookGroup::query()->where('facebook_id', 'fb-group-991')->firstOrFail();
        $this->assertSame($account->id, $group->social_account_id);
    }
}
