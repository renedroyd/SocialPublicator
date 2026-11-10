<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class IntegrationEventInbox extends Model
{
    use BelongsToWorkspace;

    protected $table = 'integration_event_inbox';

    protected $fillable = [
        'event_id',
        'event_type',
        'contract_version',
        'aggregate_external_id',
        'revision',
        'payload',
        'status',
        'attempts',
        'last_error',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'revision' => 'integer',
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
