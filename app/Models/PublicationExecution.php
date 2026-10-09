<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationExecution extends Model
{
    protected $fillable = [
        'publication_id',
        'occurrence_id',
        'channel',
        'target_key',
        'facebook_group_id',
        'status',
        'attempts',
        'started_at',
        'completed_at',
        'external_id',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function facebookGroup(): BelongsTo
    {
        return $this->belongsTo(FacebookGroup::class);
    }
}
