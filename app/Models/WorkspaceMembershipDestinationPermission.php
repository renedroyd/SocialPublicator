<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WorkspaceMembershipDestinationPermission extends Model
{
    use BelongsToWorkspace;

    public const DESTINATION_FACEBOOK_GROUP = 'facebook_group';

    public const DESTINATION_WHATSAPP_GROUP = 'whatsapp_group';

    public const DESTINATION_PUBLICATION = 'publication_destination';

    protected $fillable = [
        'membership_id',
        'destination_type',
        'destination_id',
        'permissions',
    ];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $grant): void {
            $permissions = $grant->permissions ?? [];

            if ($permissions === [] || array_diff($permissions, ['view', 'publish']) !== []) {
                throw new LogicException('Los permisos por destino solo pueden permitir consulta y publicación.');
            }

            if (in_array('publish', $permissions, true) && in_array('view', $permissions, true) === false) {
                $grant->permissions = [...$permissions, 'view'];
            }

            $destinationClass = match ($grant->destination_type) {
                self::DESTINATION_FACEBOOK_GROUP => FacebookGroup::class,
                self::DESTINATION_WHATSAPP_GROUP => WhatsAppGroup::class,
                self::DESTINATION_PUBLICATION => PublicationDestination::class,
                default => null,
            };

            if ($destinationClass === null) {
                throw new LogicException('Tipo de destino no permitido para una autorización.');
            }

            $destination = (new $destinationClass)->newQuery()
                ->withoutGlobalScope('workspace')
                ->find($grant->destination_id);

            if (! $destination || (int) $destination->workspace_id !== (int) app(\App\Services\Workspaces\WorkspaceContext::class)->id()) {
                throw new LogicException('No se puede asignar un destino de otro workspace.');
            }
        });

        static::created(function (self $grant): void {
            WorkspaceAuditLog::record(
                $grant->workspace,
                'destination.permission.granted',
                auth()->user(),
                $grant,
                [
                    'membership_id' => $grant->membership_id,
                    'destination_type' => $grant->destination_type,
                    'destination_id' => $grant->destination_id,
                    'permissions' => $grant->permissions,
                ],
            );
        });

        static::updated(function (self $grant): void {
            WorkspaceAuditLog::record(
                $grant->workspace,
                'destination.permission.updated',
                auth()->user(),
                $grant,
                ['permissions' => $grant->permissions],
            );
        });

        static::deleted(function (self $grant): void {
            WorkspaceAuditLog::record(
                $grant->workspace,
                'destination.permission.revoked',
                auth()->user(),
                $grant,
            );
        });
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMembership::class, 'membership_id');
    }
}
