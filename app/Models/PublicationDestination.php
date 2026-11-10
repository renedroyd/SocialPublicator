<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PublicationDestination extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
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
