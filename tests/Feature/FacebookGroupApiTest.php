<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacebookGroupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_facebook_groups_using_the_english_contract(): void
    {
        config(['services.bot_api.token' => 'test-token']);

        $response = $this
            ->withToken('test-token')
            ->postJson('/api/bot/facebook/groups/sync', [
                'groups' => [
                    [
                        'facebook_id' => '123456789',
                        'name' => 'Grupo de prueba',
                        'url' => 'https://www.facebook.com/groups/123456789',
                    ],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.facebook_id', '123456789')
            ->assertJsonPath('data.0.name', 'Grupo de prueba');

        $this->assertDatabaseHas('facebook_groups', [
            'facebook_id' => '123456789',
            'name' => 'Grupo de prueba',
            'active' => 1,
        ]);
    }

    public function test_it_keeps_accepting_the_legacy_sync_payload(): void
    {
        config(['services.bot_api.token' => 'test-token']);

        $response = $this
            ->withToken('test-token')
            ->postJson('/api/bot/facebook/groups/sync', [
                'grupos' => [
                    [
                        'facebook_id' => '987654321',
                        'nombre' => 'Grupo legado',
                        'url' => 'https://www.facebook.com/groups/987654321',
                    ],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.facebook_id', '987654321')
            ->assertJsonPath('data.0.name', 'Grupo legado');
    }
}
