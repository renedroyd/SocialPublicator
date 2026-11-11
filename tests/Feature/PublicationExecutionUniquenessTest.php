<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\PublicationExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationExecutionUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_multiple_distinct_targets_for_one_occurrence_but_not_duplicate_target_claims(): void
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

        $occurrence = $publication->id.'_2026-10-07_12-00';

        foreach (['wa-one@g.us', 'wa-two@g.us', 'fb-one', 'fb-two'] as $targetKey) {
            PublicationExecution::create([
                'publication_id' => $publication->id,
                'occurrence_id' => $occurrence,
                'channel' => str_starts_with($targetKey, 'fb-') ? 'facebook' : 'whatsapp',
                'target_key' => $targetKey,
                'status' => 'pending',
            ]);
        }

        $this->assertSame(4, PublicationExecution::query()->where('occurrence_id', $occurrence)->count());

        $this->expectException(\\Illuminate\\Database\\QueryException::class);
        PublicationExecution::create([
            'publication_id' => $publication->id,
            'occurrence_id' => $occurrence,
            'channel' => 'whatsapp',
            'target_key' => 'wa-one@g.us',
            'status' => 'pending',
        ]);
    }

    public function test_it_allows_the_same_target_for_different_occurrences(): void
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

        $baseAttributes = [
            'publication_id' => $publication->id,
            'channel' => 'whatsapp',
            'target_key' => 'whatsapp',
            'status' => 'succeeded',
        ];

        PublicationExecution::create([
            ...$baseAttributes,
            'occurrence_id' => $publication->id.'_2026-10-07_09-00',
        ]);

        $secondExecution = PublicationExecution::create([
            ...$baseAttributes,
            'occurrence_id' => $publication->id.'_2026-10-07_12-00',
        ]);

        $this->assertDatabaseCount('publication_executions', 2);
        $this->assertSame(
            $publication->id.'_2026-10-07_12-00',
            $secondExecution->occurrence_id
        );
    }
}
