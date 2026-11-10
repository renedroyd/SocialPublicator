<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Services\Workspaces\WorkspaceContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Publication extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'created_by_user_id',
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
        'external_campaign_id',
        'external_selection_group_id',
        'external_campaign_trigger_id',
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

    public function nextExecutionAt(): ?Carbon
    {
        $startDate = $this->date?->copy()->startOfDay();

        if (! $startDate) {
            return null;
        }

        $now = now();
        $endDate = $this->end_date ? Carbon::parse($this->end_date)->endOfDay() : null;
        $time = $this->recurrence_type === 'hourly'
            ? ($this->recurrence_start_time ?: $this->time)
            : $this->time;

        if (blank($time)) {
            return null;
        }

        $firstRun = $startDate->copy()->setTimeFromTimeString($time);

        if ($this->recurrence_type === 'none') {
            return $firstRun;
        }

        if ($endDate && $endDate->lt($now)) {
            return null;
        }

        if ($this->recurrence_type === 'daily') {
            $candidate = $firstRun->copy();

            if ($candidate->lt($now)) {
                $candidate = $now->copy()->startOfDay()->setTimeFromTimeString($time);

                if ($candidate->lt($now)) {
                    $candidate->addDay();
                }
            }

            return (! $endDate || $candidate->lte($endDate)) ? $candidate : null;
        }

        if ($this->recurrence_type === 'weekly') {
            $days = collect($this->recurrence_days ?? [])
                ->map(fn ($day): int => (int) $day)
                ->all();

            for ($offset = 0; $offset <= 370; $offset++) {
                $candidate = $now->copy()->startOfDay()->addDays($offset)
                    ->setTimeFromTimeString($time);

                if ($candidate->lt($firstRun) || $candidate->lt($now)) {
                    continue;
                }

                if (! in_array($candidate->dayOfWeekIso, $days, true)) {
                    continue;
                }

                if ($endDate && $candidate->gt($endDate)) {
                    return null;
                }

                return $candidate;
            }

            return null;
        }

        if ($this->recurrence_type === 'hourly') {
            $interval = max(1, (int) ($this->interval_minutes ?: 60));
            $endTime = $this->recurrence_end_time ?: '23:59';

            for ($offset = 0; $offset <= 370; $offset++) {
                $day = $now->copy()->startOfDay()->addDays($offset);
                $candidate = $day->copy()->setTimeFromTimeString($time);
                $dailyEnd = $day->copy()->setTimeFromTimeString($endTime);

                while ($candidate->lte($dailyEnd)) {
                    if ($candidate->gte($firstRun) && $candidate->gte($now)) {
                        if ($endDate && $candidate->gt($endDate)) {
                            return null;
                        }

                        return $candidate;
                    }

                    $candidate->addMinutes($interval);
                }

                if ($endDate && $day->gt($endDate)) {
                    return null;
                }
            }
        }

        return null;
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
            ->withPivotValue('workspace_id', app(WorkspaceContext::class)->id())->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(
            PublicationDestination::class,
            'publication_publication_destination'
        )->withPivotValue('workspace_id', app(WorkspaceContext::class)->id())->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(PublicationExecution::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(PublicationSchedule::class);
    }

    public function whatsappGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            WhatsAppGroup::class,
            'whatsapp_group_publication',
            'publication_id',
            'whatsapp_group_id'
        )->withPivotValue('workspace_id', app(WorkspaceContext::class)->id())->withTimestamps();
    }

    public function facebookGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            FacebookGroup::class,
            'facebook_group_publication'
        )->withPivotValue('workspace_id', app(WorkspaceContext::class)->id())->withTimestamps();
    }

    protected static function booted(): void
    {
        static::creating(function (Publication $publication): void {
            $publication->created_by_user_id ??= auth()->id();
        });

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
