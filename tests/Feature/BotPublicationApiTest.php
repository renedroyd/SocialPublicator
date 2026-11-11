<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\BotPublicationController;
use App\Models\FacebookGroup;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\User;
use App\Models\WhatsAppGroup;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceMembershipDestinationPermission;
use App\Models\WorkspaceMembershipSocialAccountPermission;
use App\Services\Workspaces\WorkspaceContext;
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

    public function test_it_returns_all_selected_whatsapp_and_facebook_groups_for_multidestination_delivery(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-07',
            'time' => '12:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación multigrupo',
            'destination' => 'both',
            'active' => true,
            'recurrence_type' => 'none',
        ]);

        $whatsappGroups = collect([
            ['whatsapp_id' => 'wa-one@g.us', 'name' => 'WhatsApp uno'],
            ['whatsapp_id' => 'wa-two@g.us', 'name' => 'WhatsApp dos'],
        ])->map(fn (array $attributes) => WhatsAppGroup::create($attributes));

        $facebookGroups = collect([
            ['facebook_id' => 'fb-one', 'name' => 'Facebook uno', 'url' => 'https://www.facebook.com/groups/fb-one'],
            ['facebook_id' => 'fb-two', 'name' => 'Facebook dos', 'url' => 'https://www.facebook.com/groups/fb-two'],
        ])->map(fn (array $attributes) => FacebookGroup::create([...$attributes, 'active' => true]));

        $publication->whatsappGroups()->attach($whatsappGroups->pluck('id'));
        $publication->facebookGroups()->attach($facebookGroups->pluck('id'));

        $response = $this->withToken('test-token')->getJson('/api/bot/publications');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.whatsapp_groups')
            ->assertJsonCount(2, 'data.0.facebook_groups');

        $this->assertEqualsCanonicalizing(
            $whatsappGroups->pluck('whatsapp_id')->all(),
            collect($response->json('data.0.whatsapp_groups'))->pluck('whatsapp_id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            $facebookGroups->pluck('facebook_id')->all(),
            collect($response->json('data.0.facebook_groups'))->pluck('facebook_id')->all(),
        );
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

    public function test_it_generates_weekly_recurrence_only_on_selected_weekdays(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-01',
            'time' => '14:30',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación semanal',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'weekly',
            'recurrence_days' => ['1', '3', '5'],
            'end_date' => '2026-10-31',
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
            ->assertJsonPath('data.0.recurrence_type', 'weekly')
            ->assertJsonPath('data.0.tipo_recurrencia', 'semanal')
            ->assertJsonPath('data.0.recurrence_days', ['1', '3', '5']);
    }

    public function test_it_does_not_generate_weekly_recurrence_on_unselected_day(): void
    {
        Publication::create([
            'date' => '2026-10-01',
            'time' => '14:30',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación semanal fuera de día',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'weekly',
            'recurrence_days' => ['1', '5'],
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_generates_minute_based_occurrences(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-01',
            'time' => '09:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación cada 15 minutos',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'hourly',
            'interval_minutes' => 15,
            'recurrence_start_time' => '09:00',
            'recurrence_end_time' => '10:00',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.interval_minutes', 15)
            ->assertJsonPath('data.0.intervalo_minutos', 15);

        $this->assertSame(
            ['09:00', '09:15', '09:30', '09:45', '10:00'],
            collect($response->json('data'))->pluck('time')->all()
        );
    }

    public function test_it_supports_a_one_minute_recurrence(): void
    {
        Publication::create([
            'date' => '2026-10-01',
            'time' => '09:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación cada minuto',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'hourly',
            'interval_minutes' => 1,
            'recurrence_start_time' => '09:00',
            'recurrence_end_time' => '09:03',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response->assertOk()->assertJsonCount(4, 'data');

        $this->assertSame(
            ['09:00', '09:01', '09:02', '09:03'],
            collect($response->json('data'))->pluck('time')->all()
        );
    }

    public function test_it_supports_minute_intervals_that_cross_an_hour(): void
    {
        Publication::create([
            'date' => '2026-10-01',
            'time' => '09:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación cada 90 minutos',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'hourly',
            'interval_minutes' => 90,
            'recurrence_start_time' => '09:00',
            'recurrence_end_time' => '13:00',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response->assertOk()->assertJsonCount(3, 'data');

        $this->assertSame(
            ['09:00', '10:30', '12:00'],
            collect($response->json('data'))->pluck('time')->all()
        );
    }

    public function test_it_keeps_legacy_hour_intervals_working(): void
    {
        Publication::create([
            'date' => '2026-10-01',
            'time' => '09:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación heredada',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'hourly',
            'interval_hours' => 2,
            'recurrence_start_time' => '09:00',
            'recurrence_end_time' => '13:00',
        ]);

        $response = $this
            ->withToken('test-token')
            ->getJson('/api/bot/publications');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.interval_minutes', 120)
            ->assertJsonPath('data.0.intervalo_minutos', 120);

        $this->assertSame(
            ['09:00', '11:00', '13:00'],
            collect($response->json('data'))->pluck('time')->all()
        );
    }

    public function test_it_only_returns_destinations_the_publication_creator_can_still_use(): void
    {
        $workspace = Workspace::query()->create([
            'name' => 'Workspace de publicaciones',
            'slug' => 'workspace-publicaciones',
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $user = User::factory()->create();
        $membership = WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMembership::ROLE_EDITOR,
            'status' => 'active',
        ]);

        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'external_account_id' => 'wa-profile-1',
            'display_name' => 'WhatsApp principal',
        ]);

        SocialAccountConnection::query()->create([
            'social_account_id' => $account->id,
            'connection_type' => SocialAccountConnection::TYPE_WPP_CONNECT_QR,
            'provider' => 'wppconnect',
            'status' => 'connected',
            'agent_instance_id' => 'WBot',
            'session_reference' => 'wppconnect:legacy-agent',
            'provider_connection_id' => 'whatsapp-bot',
        ]);

        $allowedGroup = WhatsAppGroup::query()->create([
            'social_account_id' => $account->id,
            'whatsapp_id' => 'allowed-group@g.us',
            'name' => 'Grupo permitido',
            'active' => true,
            'is_admin' => true,
        ]);

        $revokedGroup = WhatsAppGroup::query()->create([
            'social_account_id' => $account->id,
            'whatsapp_id' => 'revoked-group@g.us',
            'name' => 'Grupo sin permiso',
            'active' => true,
            'is_admin' => true,
        ]);

        WorkspaceMembershipSocialAccountPermission::query()->create([
            'membership_id' => $membership->id,
            'social_account_id' => $account->id,
            'permissions' => ['publish'],
        ]);

        WorkspaceMembershipDestinationPermission::query()->create([
            'membership_id' => $membership->id,
            'destination_type' => WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP,
            'destination_id' => $allowedGroup->id,
            'permissions' => ['publish'],
        ]);

        $publication = Publication::query()->create([
            'created_by_user_id' => $user->id,
            'date' => '2026-10-07',
            'time' => '12:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación con permisos granulares',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'none',
        ]);

        $publication->whatsappGroups()->attach([$allowedGroup->id, $revokedGroup->id]);

        request()->attributes->set('bot_agent_social_account_ids', [$account->id]);
        request()->attributes->set('bot_agent_instance_id', 'WBot');
        request()->attributes->set('bot_agent_workspace_id', $workspace->id);
        request()->query->set('instance_id', 'WBot');

        $payload = app(BotPublicationController::class)->index()->getData(true);

        $this->assertCount(1, $payload['data']);
        $this->assertSame(
            [$allowedGroup->id],
            collect($payload['data'][0]['whatsapp_groups'])->pluck('id')->all(),
        );
    }
}
