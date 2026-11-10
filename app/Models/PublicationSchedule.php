<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationSchedule extends Model
{
    use \App\Models\Concerns\BelongsToWorkspace;

    protected $fillable = [
        'publication_id',
        'frequency',
        'interval',
        'weekdays',
        'month_day',
        'end_type',
        'end_date',
        'max_occurrences',
        'occurrences_count',
        'next_run_at',
        'last_run_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'end_date' => 'date',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }
}