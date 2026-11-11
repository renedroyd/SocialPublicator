<?php

namespace App\Services;

use App\Models\PublicationDestination;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use Illuminate\Support\Facades\DB;
use LogicException;

class SocialAccountConnectionService
{
    public function revoke(SocialAccount $account, string $connectionType): SocialAccountConnection
    {
        return DB::transaction(function () use ($account, $connectionType): SocialAccountConnection {
            $connection = $account->connections()
                ->where('connection_type', $connectionType)
                ->lockForUpdate()
                ->firstOrFail();

            $connection->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'last_error_code' => 'workspace_admin_revoked',
                'last_error_message' => 'El acceso fue revocado desde SocialPublicator.',
            ]);

            $account->update([
                'status' => SocialAccount::STATUS_DISCONNECTED,
                'last_error_at' => null,
                'last_error_message' => null,
            ]);

            if (
                $connectionType === SocialAccountConnection::TYPE_META_OAUTH
                && $account->account_type === SocialAccount::TYPE_FACEBOOK_PROFILE
            ) {
                $pages = $account->childAccounts()
                    ->where('account_type', SocialAccount::TYPE_FACEBOOK_PAGE)
                    ->get();

                foreach ($pages as $page) {
                    $page->update([
                        'status' => SocialAccount::STATUS_DISCONNECTED,
                        'last_error_at' => null,
                        'last_error_message' => null,
                    ]);

                    $page->connections()
                        ->where('connection_type', SocialAccountConnection::TYPE_META_OAUTH)
                        ->where('provider', 'meta')
                        ->update([
                            'status' => 'revoked',
                            'revoked_at' => now(),
                            'last_error_code' => 'parent_profile_revoked',
                            'last_error_message' => 'El perfil principal de Meta fue desconectado desde SocialPublicator.',
                        ]);

                    PublicationDestination::query()
                        ->where('social_account_id', $page->id)
                        ->update(['active' => false]);
                }
            }

            return $connection->refresh();
        });
    }

    public function reactivate(SocialAccount $account, string $connectionType): SocialAccountConnection
    {
        if ($connectionType === SocialAccountConnection::TYPE_META_OAUTH) {
            throw new LogicException('Las conexiones Meta OAuth deben reautorizarse mediante el flujo oficial de Meta.');
        }

        return DB::transaction(function () use ($account, $connectionType): SocialAccountConnection {
            $connection = $account->connections()
                ->where('connection_type', $connectionType)
                ->lockForUpdate()
                ->firstOrFail();

            if ($connection->status !== 'revoked') {
                throw new LogicException('Solo se puede reactivar una conexión revocada.');
            }

            $connection->update([
                'status' => 'pending',
                'revoked_at' => null,
                'connected_at' => null,
                'last_verified_at' => null,
                'last_error_code' => null,
                'last_error_message' => null,
            ]);

            $account->update([
                'status' => SocialAccount::STATUS_PENDING,
                'last_error_at' => null,
                'last_error_message' => null,
            ]);

            return $connection->refresh();
        });
    }
}
