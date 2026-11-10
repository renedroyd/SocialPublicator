<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SocialAccountConnection extends Model
{
    use BelongsToWorkspace;

    public const TYPE_META_OAUTH = 'meta_oauth';

    public const TYPE_BROWSER_SESSION = 'browser_session';

    public const TYPE_WPP_CONNECT_QR = 'wppconnect_qr';

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $fillable = [
        'social_account_id',
        'connection_type',
        'provider',
        'status',
        'agent_instance_id',
        'session_reference',
        'provider_connection_id',
        'credentials',
        'metadata',
        'created_by_user_id',
        'expires_at',
        'connected_at',
        'last_verified_at',
        'revoked_at',
        'last_error_code',
        'last_error_message',
    ];

    protected $hidden = [
        'credentials',
        'session_reference',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SocialAccountConnection $connection): void {
            $connection->created_by_user_id ??= auth()->id();
        });

        static::saving(function (SocialAccountConnection $connection): void {
            $providers = match ($connection->connection_type) {
                self::TYPE_META_OAUTH => ['meta'],
                self::TYPE_BROWSER_SESSION => ['puppeteer'],
                self::TYPE_WPP_CONNECT_QR => ['wppconnect'],
                default => [],
            };

            if ($providers === [] || ! in_array($connection->provider, $providers, true)) {
                throw new LogicException('Método o proveedor de conexión social no permitido.');
            }

            if (! in_array($connection->status, ['pending', 'connected', 'expired', 'revoked', 'error'], true)) {
                throw new LogicException('Estado de conexión social no válido.');
            }

            if ($connection->connection_type === self::TYPE_META_OAUTH) {
                if (empty($connection->credentials)) {
                    throw new LogicException('Las conexiones OAuth deben almacenar sus tokens cifrados.');
                }

                return;
            }

            if (
                ! is_string($connection->session_reference)
                || $connection->session_reference === ''
                || ! is_string($connection->agent_instance_id)
                || $connection->agent_instance_id === ''
            ) {
                throw new LogicException('Las sesiones administradas por WBot requieren agente y referencia opaca.');
            }

            if (! empty($connection->credentials)) {
                throw new LogicException('Los perfiles de navegador y las sesiones WhatsApp no deben copiarse a la base de datos de SocialPublicator.');
            }
        });

        static::created(function (SocialAccountConnection $connection): void {
            WorkspaceAuditLog::record(
                $connection->workspace,
                'social_account.connection.created',
                auth()->user(),
                $connection,
                [
                    'social_account_id' => $connection->social_account_id,
                    'connection_type' => $connection->connection_type,
                    'provider' => $connection->provider,
                    'status' => $connection->status,
                ],
            );
        });

        static::updated(function (SocialAccountConnection $connection): void {
            WorkspaceAuditLog::record(
                $connection->workspace,
                'social_account.connection.updated',
                auth()->user(),
                $connection,
                [
                    'social_account_id' => $connection->social_account_id,
                    'connection_type' => $connection->connection_type,
                    'status' => $connection->status,
                ],
            );
        });
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
