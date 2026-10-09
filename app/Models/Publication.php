<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Publication extends Model
{
    protected $fillable = [
        'date',
        'time',
        'image',
        'text',
        'destination',
        'active',
        'recurrence_type',
        'recurrence_days',
        'interval_hours',
        'interval_minutes',
        'recurrence_start_time',
        'recurrence_end_time',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'active' => 'boolean',
            'end_date' => 'date',
            'interval_hours' => 'integer',
            'interval_minutes' => 'integer',
            'recurrence_days' => 'array',
        ];
    }

    public function isRecurring(): bool
    {
        return $this->recurrence_type !== 'none';
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'publication_product'
        )
            ->withPivot([
                'quantity',
                'published_price',
                'sort_order',
            ])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(
            PublicationDestination::class,
            'publication_publication_destination'
        )->withTimestamps();
    }

    public function executions(): HasMany
    {
        return $this->hasMany(PublicationExecution::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(PublicationSchedule::class);
    }

    public function facebookGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            FacebookGroup::class,
            'facebook_group_publication'
        )->withTimestamps();
    }

    protected static function booted(): void
    {
        static::saving(function (Publication $publication): void {
            if (
                $publication->recurrence_type === 'hourly' &&
                filled($publication->recurrence_start_time)
            ) {
                $publication->time = $publication->recurrence_start_time;
            }
        });
    }
}
