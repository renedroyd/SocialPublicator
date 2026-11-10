<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FacebookGroup extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'social_account_id',
        'facebook_id',
        'name',
        'url',
        'active',
        'last_synchronized_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_synchronized_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (FacebookGroup $group): void {
            WorkspaceMembershipDestinationPermission::query()
                ->withoutGlobalScope('workspace')
                ->where('workspace_id', $group->workspace_id)
                ->where('destination_type', WorkspaceMembershipDestinationPermission::DESTINATION_FACEBOOK_GROUP)
                ->where('destination_id', $group->id)
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
            'facebook_group_publication'
        )->withPivotValue('workspace_id', app(WorkspaceContext::class)->id())->withTimestamps();
    }
}
