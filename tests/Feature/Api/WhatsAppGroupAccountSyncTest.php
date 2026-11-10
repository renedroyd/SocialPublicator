<?php

namespace Tests\Feature\Api;

use App\Models\SocialAccount;
use App\Models\WhatsAppGroup;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppGroupAccountSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_group_sync_links_groups_to_the_whatsapp_account(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Group sync', 'slug' => 'group-sync']);
        config([
            'services.bot_api.token' => 'test-bot-token',
            'services.bot_api.workspace_id' => $workspace->id,
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);
        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'display_name' => 'WhatsApp de pruebas',
        ]);
        app(WorkspaceContext::class)->clear();

        $this->withToken('test-bot-token')->postJson('/api/bot/whatsapp/groups/sync', [
            'social_account_id' => $account->id,
            'groups' => [[
                'whatsapp_id' => '120363000000099@g.us',
                'name' => 'Grupo vinculado',
            ]],
        ])->assertOk()->assertJsonPath('total', 1);

        app(WorkspaceContext::class)->set((int) $workspace->id);
        $group = WhatsAppGroup::query()->where('whatsapp_id', '120363000000099@g.us')->firstOrFail();
        $this->assertSame($account->id, $group->social_account_id);
    }
}
