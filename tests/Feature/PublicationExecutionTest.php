<?php

namespace Tests\Feature;

use App\Models\FacebookGroup;
use App\Models\Publication;
use App\Models\PublicationExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class PublicationExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_tracks_a_whatsapp_execution_by_occurrence_and_target(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-07',
            'time' => '12:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación de prueba',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'none',
        ]);

        $execution = PublicationExecution::create([
            'publication_id' => $publication->id,
            'occurrence_id' => $publication->id.'_2026-10-07_12-00',
            'channel' => 'whatsapp',
            'target_key' => 'whatsapp',
            'status' => 'succeeded',
            'completed_at' => now(),
            'external_id' => 'whatsapp-message-123',
        ]);

        $this->assertSame(
            $execution->id,
            $publication->executions()->firstOrFail()->id
        );

        $this->assertNull($execution->facebook_group_id);
        $this->assertSame('succeeded', $execution->status);
    }

    public function test_it_tracks_each_facebook_group_as_a_separate_execution_target(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-07',
            'time' => '12:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación de prueba',
            'destination' => 'facebook',
            'active' => true,
            'recurrence_type' => 'none',
        ]);

        $group = FacebookGroup::create([
            'facebook_id' => '123456789',
            'name' => 'Grupo de prueba',
            'url' => 'https://www.facebook.com/groups/123456789',
            'active' => true,
        ]);

        $execution = PublicationExecution::create([
            'publication_id' => $publication->id,
            'occurrence_id' => $publication->id.'_2026-10-07_12-00',
            'channel' => 'facebook',
            'target_key' => 'facebook:'.$group->id,
            'facebook_group_id' => $group->id,
            'status' => 'pending',
        ]);

        $this->assertTrue($execution->facebookGroup->is($group));
        $this->assertSame(
            'facebook:'.$group->id,
            $execution->target_key
        );
    }

    public function test_it_prevents_duplicate_execution_for_the_same_target(): void
    {
        $publication = Publication::create([
            'date' => '2026-10-07',
            'time' => '12:00',
            'image' => 'publications/test.jpg',
            'text' => 'Publicación de prueba',
            'destination' => 'whatsapp',
            'active' => true,
            'recurrence_type' => 'none',
        ]);

        $attributes = [
            'publication_id' => $publication->id,
            'occurrence_id' => $publication->id.'_2026-10-07_12-00',
            'channel' => 'whatsapp',
            'target_key' => 'whatsapp',
            'status' => 'pending',
        ];

        PublicationExecution::create($attributes);

        $this->expectException(QueryException::class);

        PublicationExecution::create($attributes);
    }
}
