<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PublicationDestination extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'social_account_id',
        'platform',
        'external_id',
        'name',
        'url',
        'active',
        'last_synced_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (PublicationDestination $destination): void {
            WorkspaceMembershipDestinationPermission::query()
                ->withoutGlobalScope('workspace')
                ->where('workspace_id', $destination->workspace_id)
                ->where('destination_type', WorkspaceMembershipDestinationPermission::DESTINATION_PUBLICATION)
                ->where('destination_id', $destination->id)
                ->delete();
        });
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function publications(): BelongsToMany
    {
        return $this->belongsToMany(
            Publication::class,
            'publication_publication_destination'
        )->withPivotValue('workspace_id', app(WorkspaceContext::class)->id())->withTimestamps();
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query
            ->where('platform', $platform)
            ->where('active', true);
    }
}
