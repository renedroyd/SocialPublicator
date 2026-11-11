<?php

namespace Tests\Feature\Api;

use App\Models\WhatsAppGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.bot_api.token' => 'test-bot-token']);
    }

    public function test_it_synchronizes_whatsapp_group_ids_and_names(): void
    {
        $response = $this->withToken('test-bot-token')->postJson(
            '/api/bot/whatsapp/groups/sync',
            [
                'groups' => [
                    [
                        'whatsapp_id' => '120363000000001@g.us',
                        'name' => 'Grupo de clientes',
                    ],
                ],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.whatsapp_id', '120363000000001@g.us')
            ->assertJsonPath('data.0.name', 'Grupo de clientes');

        $this->assertDatabaseHas('whatsapp_groups', [
            'whatsapp_id' => '120363000000001@g.us',
            'name' => 'Grupo de clientes',
            'active' => true,
        ]);
    }

    public function test_it_persists_verified_admin_status_and_does_not_erase_it_when_older_agents_omit_the_field(): void
    {
        $response = $this->withToken('test-bot-token')->postJson(
            '/api/bot/whatsapp/groups/sync',
            [
                'groups' => [
                    [
                        'whatsapp_id' => '120363000000003@g.us',
                        'name' => 'Grupo administrador',
                        'is_admin' => true,
                    ],
                    [
                        'whatsapp_id' => '120363000000004@g.us',
                        'name' => 'Grupo sin administración',
                        'is_admin' => false,
                    ],
                ],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.0.is_admin', true);

        $this->assertDatabaseHas('whatsapp_groups', [
            'whatsapp_id' => '120363000000003@g.us',
            'is_admin' => true,
        ]);
        $this->assertDatabaseHas('whatsapp_groups', [
            'whatsapp_id' => '120363000000004@g.us',
            'is_admin' => false,
        ]);

        $this->withToken('test-bot-token')->postJson(
            '/api/bot/whatsapp/groups/sync',
            [
                'groups' => [
                    [
                        'whatsapp_id' => '120363000000003@g.us',
                        'name' => 'Grupo administrador actualizado',
                    ],
                ],
            ],
        )->assertOk();

        $this->assertDatabaseHas('whatsapp_groups', [
            'whatsapp_id' => '120363000000003@g.us',
            'name' => 'Grupo administrador actualizado',
            'is_admin' => true,
        ]);
    }

    public function test_it_updates_the_name_for_an_existing_whatsapp_group(): void
    {
        $group = WhatsAppGroup::create([
            'whatsapp_id' => '120363000000002@g.us',
            'name' => 'Nombre anterior',
            'active' => true,
        ]);

        $response = $this->withToken('test-bot-token')->postJson(
            '/api/bot/whatsapp/groups/sync',
            [
                'groups' => [
                    [
                        'whatsapp_id' => '120363000000002@g.us',
                        'name' => 'Nombre actualizado',
                    ],
                ],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('total', 1);

        $this->assertDatabaseHas('whatsapp_groups', [
            'id' => $group->id,
            'name' => 'Nombre actualizado',
        ]);
    }
}
