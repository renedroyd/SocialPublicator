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

    public function test_it_does_not_generate_a_recurring_publication_before_its_start_date(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-08',
            'time' => '09:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación futura',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'hourly',
            'interval_hours' => 2,
            'recurrence_start_time' => '09:00',
            'recurrence_end_time' => '17:00',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_stops_generating_a_recurring_publication_after_its_end_date(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-01',
            'time' => '09:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación finalizada',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'hourly',
            'interval_hours' => 2,
            'recurrence_start_time' => '09:00',
            'recurrence_end_time' => '17:00',
            'end_date' => '2026-10-06',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_respects_the_daily_recurrence_time_window(): void
    {
        Publication::create([
            'date' => '2026-10-01',
            'time' => '09:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación con ventana horaria',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'hourly',
            'interval_hours' => 3,
            'recurrence_start_time' => '11:00',
            'recurrence_end_time' => '20:00',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.time', '11:00')
            ->assertJsonPath('data.3.time', '20:00');
    }

    public function test_it_keeps_past_occurrences_in_the_daily_schedule(): void
    {
        Publication::create([
            'date' => '2026-10-07',
            'time' => '09:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación pasada',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'none',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.time', '09:00');
    }

    public function test_it_keeps_past_recurring_occurrences_in_the_daily_schedule(): void
    {
        Publication::create([
            'date' => '2026-10-01',
            'time' => '09:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación recurrente pasada',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'hourly',
            'interval_hours' => 3,
            'recurrence_start_time' => '09:00',
            'recurrence_end_time' => '15:00',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->assertSame(
            ['09:00', '12:00', '15:00'],
            collect($response->json('data'))->pluck('time')->all()
        );
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

    public function test_it_generates_one_occurrence_per_day_for_daily_recurrence(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-01',
            'time' => '14:30',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación diaria',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'daily',
            'end_date' => '2026-10-10',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $publication->id)
            ->assertJsonPath('data.0.date', '2026-10-07')
            ->assertJsonPath('data.0.time', '14:30')
            ->assertJsonPath('data.0.occurrence_id', $publication->id.'_2026-10-07_14-30')
            ->assertJsonPath('data.0.recurrence_type', 'daily')
            ->assertJsonPath('data.0.tipo_recurrencia', 'diaria');
    }

    public function test_it_does_not_generate_daily_recurrence_after_its_end_date(): void
    {
        Publication::create([
            'date' => '2026-10-01',
            'time' => '14:30',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación diaria finalizada',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'daily',
            'end_date' => '2026-10-06',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_does_not_generate_daily_recurrence_before_its_start_date(): void
    {
        Publication::create([
            'date' => '2026-10-08',
            'time' => '14:30',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación diaria futura',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'daily',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
