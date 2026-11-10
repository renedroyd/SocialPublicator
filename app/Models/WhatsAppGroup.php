<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WhatsAppGroup extends Model
{
    use BelongsToWorkspace;

    protected $table = 'whatsapp_groups';

    protected $fillable = [
        'social_account_id',
        'whatsapp_id',
        'name',
        'active',
        'last_synchronized_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_synchronized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (WhatsAppGroup $group): void {
            WorkspaceMembershipDestinationPermission::query()
                ->withoutGlobalScope('workspace')
                ->where('workspace_id', $group->workspace_id)
                ->where('destination_type', WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP)
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
            'whatsapp_group_publication',
            'whatsapp_group_id',
            'publication_id'
        )->withPivotValue('workspace_id', app(WorkspaceContext::class)->id())->withTimestamps();
    }
}
