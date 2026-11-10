<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class SocialAccount extends Model
{
    use BelongsToWorkspace;

    public const PLATFORM_WHATSAPP = 'whatsapp';

    public const PLATFORM_FACEBOOK = 'facebook';

    public const TYPE_WHATSAPP = 'whatsapp_account';

    public const TYPE_FACEBOOK_PROFILE = 'facebook_profile';

    public const TYPE_FACEBOOK_PAGE = 'facebook_page';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_ERROR = 'error';

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $fillable = [
        'parent_account_id',
        'platform',
        'account_type',
        'external_account_id',
        'display_name',
        'username',
        'avatar_url',
        'status',
        'capabilities',
        'metadata',
        'created_by_user_id',
        'connected_at',
        'last_verified_at',
        'last_error_at',
        'last_error_message',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'metadata' => 'array',
            'connected_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SocialAccount $account): void {
            $account->created_by_user_id ??= auth()->id();
        });

        static::saving(function (SocialAccount $account): void {
            $valid = match ($account->platform) {
                self::PLATFORM_WHATSAPP => $account->account_type === self::TYPE_WHATSAPP,
                self::PLATFORM_FACEBOOK => in_array($account->account_type, [
                    self::TYPE_FACEBOOK_PROFILE,
                    self::TYPE_FACEBOOK_PAGE,
                ], true),
                default => false,
            };

            if (! $valid) {
                throw ValidationException::withMessages([
                    'account_type' => 'El tipo de cuenta no corresponde a la plataforma seleccionada.',
                ]);
            }

            if (! in_array($account->status, [self::STATUS_PENDING, self::STATUS_CONNECTED, self::STATUS_DISCONNECTED, self::STATUS_ERROR], true)) {
                throw ValidationException::withMessages(['status' => 'Estado de cuenta social no válido.']);
            }

            if ($account->account_type === self::TYPE_FACEBOOK_PAGE) {
                $parent = static::query()
                    ->withoutGlobalScope('workspace')
                    ->find($account->parent_account_id);

                if (
                    ! $parent
                    || $parent->platform !== self::PLATFORM_FACEBOOK
                    || $parent->account_type !== self::TYPE_FACEBOOK_PROFILE
                    || (int) $parent->workspace_id !== (int) app(\App\Services\Workspaces\WorkspaceContext::class)->id()
                ) {
                    throw ValidationException::withMessages([
                        'parent_account_id' => 'Una página de Facebook debe pertenecer a un perfil conectado del mismo workspace.',
                    ]);
                }
            } elseif ($account->parent_account_id !== null) {
                throw ValidationException::withMessages([
                    'parent_account_id' => 'Solo las páginas de Facebook pueden tener una cuenta principal.',
                ]);
            }
        });

        static::created(function (SocialAccount $account): void {
            WorkspaceAuditLog::record(
                $account->workspace,
                'social_account.created',
                auth()->user(),
                $account,
                [
                    'platform' => $account->platform,
                    'account_type' => $account->account_type,
                    'status' => $account->status,
                ],
            );
        });

        static::updated(function (SocialAccount $account): void {
            WorkspaceAuditLog::record(
                $account->workspace,
                'social_account.updated',
                auth()->user(),
                $account,
                ['changes' => $account->getChanges()],
            );
        });
    }

    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_account_id');
    }

    public function childAccounts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_account_id');
    }

    public function connections(): HasMany
    {
        return $this->hasMany(SocialAccountConnection::class);
    }

    public function facebookGroups(): HasMany
    {
        return $this->hasMany(FacebookGroup::class);
    }

    public function whatsappGroups(): HasMany
    {
        return $this->hasMany(WhatsAppGroup::class);
    }

    public function publicationDestinations(): HasMany
    {
        return $this->hasMany(PublicationDestination::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
