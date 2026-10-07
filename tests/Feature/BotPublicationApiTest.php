<?php

namespace Tests\Feature;

use App\Models\FacebookGroup;
use App\Models\Publication;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotPublicationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.bot_api.token' => 'test-token']);
        Carbon::setTestNow(Carbon::create(2026, 10, 7, 10, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_returns_selected_facebook_groups_for_a_publication(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-07',
            'time' => '12:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación de prueba',
            'destination' => 'both',
            'active' => true,
            'recurrence_type' => 'none',
        ]);

        $selectedGroup = FacebookGroup::create([
            'facebook_id' => '111111111',
            'name' => 'Grupo seleccionado',
            'url' => 'https://www.facebook.com/groups/111111111',
            'active' => true,
        ]);

        $otherGroup = FacebookGroup::create([
            'facebook_id' => '222222222',
            'name' => 'Grupo no seleccionado',
            'url' => 'https://www.facebook.com/groups/222222222',
            'active' => true,
        ]);

        $inactiveGroup = FacebookGroup::create([
            'facebook_id' => '333333333',
            'name' => 'Grupo inactivo',
            'url' => 'https://www.facebook.com/groups/333333333',
            'active' => false,
        ]);

        $publication->facebookGroups()->attach([
            $selectedGroup->id,
            $inactiveGroup->id,
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $publication->id)
            ->assertJsonPath('data.0.occurrence_id', ''.$publication->id.'_2026-10-07_12-00')
            ->assertJsonPath('data.0.destination', 'both')
            ->assertJsonPath('data.0.facebook_groups.0.id', $selectedGroup->id)
            ->assertJsonPath('data.0.facebook_groups.0.facebook_id', '111111111')
            ->assertJsonPath('data.0.facebook_groups.0.name', 'Grupo seleccionado');

        $this->assertNotSame($selectedGroup->id, $otherGroup->id);
        $this->assertNotSame($selectedGroup->id, $inactiveGroup->id);
        $this->assertCount(1, $response->json('data.0.facebook_groups'));
    }

    public function test_it_returns_a_publication_with_both_destinations(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-07',
            'time' => '12:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación multicanal',
            'destination' => 'both',
            'active' => true,
            'recurrence_type' => 'none',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $publication->id)
            ->assertJsonPath('data.0.destination', 'both')
            ->assertJsonPath('data.0.destino', 'ambos');
    }

    public function test_it_generates_hourly_occurrences_with_the_configured_interval(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-07',
            'time' => '09:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación recurrente',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'hourly',
            'interval_hours' => 3,
            'recurrence_start_time' => '09:00',
            'recurrence_end_time' => '18:00',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->assertSame(
            ['09:00', '12:00', '15:00', '18:00'],
            collect($response->json('data'))->pluck('time')->all()
        );
    }
}
