<?php

namespace Tests\\Feature\\Api;

use App\\Models\\FacebookGroup;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class FacebookGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.bot_api.token' => 'test-bot-token']);
    }

    public function test_generic_group_names_are_not_inserted_into_the_catalog(): void
    {
        $response = $this->withToken('test-bot-token')->postJson(
            '/api/bot/facebook/grupos/sincronizar',
            [
                'grupos' => [
                    [
                        'facebook_id' => '123456789',
                        'nombre' => 'Grupo Facebook 123456789',
                        'url' => 'https://www.facebook.com/groups/123456789',
                    ],
                ],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('ignored_generic_names', 1);

        $this->assertDatabaseMissing('facebook_groups', [
            'facebook_id' => '123456789',
        ]);
    }

    public function test_generic_name_does_not_overwrite_an_existing_real_group_name(): void
    {
        $group = FacebookGroup::create([
            'facebook_id' => '987654321',
            'name' => 'Venta mayorista Cuba',
            'url' => 'https://www.facebook.com/groups/987654321',
            'active' => true,
        ]);

        $response = $this->withToken('test-bot-token')->postJson(
            '/api/bot/facebook/grupos/sincronizar',
            [
                'grupos' => [
                    [
                        'facebook_id' => '987654321',
                        'nombre' => 'Grupo Facebook 987654321',
                        'url' => 'https://www.facebook.com/groups/987654321',
                    ],
                ],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('ignored_generic_names', 1);

        $this->assertDatabaseHas('facebook_groups', [
            'id' => $group->id,
            'facebook_id' => '987654321',
            'name' => 'Venta mayorista Cuba',
        ]);
    }

    public function test_real_group_names_are_synchronized_normally(): void
    {
        $response = $this->withToken('test-bot-token')->postJson(
            '/api/bot/facebook/grupos/sincronizar',
            [
                'grupos' => [
                    [
                        'facebook_id' => '1122334455',
                        'nombre' => 'Grupo real de ventas',
                        'url' => 'https://www.facebook.com/groups/1122334455',
                    ],
                ],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('ignored_generic_names', 0);

        $this->assertDatabaseHas('facebook_groups', [
            'facebook_id' => '1122334455',
            'name' => 'Grupo real de ventas',
        ]);
    }
}
