<?php

namespace App\Jobs;

use App\Models\PublicationDestination;
use App\Models\PublicationExecution;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceMembershipDestinationPermission;
use App\Services\PublicationExecutionService;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PublishMetaPagePublication implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $executionId)
    {
    }

    public function handle(
        PublicationExecutionService $executions,
        WorkspaceContext $context,
    ): void {
        $unscopedExecution = PublicationExecution::withoutGlobalScope('workspace')
            ->findOrFail($this->executionId);
        $context->set((int) $unscopedExecution->workspace_id);

        try {
            $execution = PublicationExecution::query()
                ->with('publication')
                ->findOrFail($this->executionId);

            if ($execution->status !== 'running') {
                return;
            }

            $destinationId = $this->destinationId($execution->target_key);
            $destination = $destinationId
                ? PublicationDestination::query()->with(['socialAccount.connections'])->find($destinationId)
                : null;

            if ($destination === null) {
                $this->failExecution($execution, $executions, 'El destino de página de Facebook ya no existe.');

                return;
            }

            $publication = $execution->publication;
            if (
                $publication->active === false
                || $destination->active === false
                || $publication->destinations()->whereKey($destination->id)->exists() === false
            ) {
                $this->failExecution($execution, $executions, 'La página ya no está seleccionada para esta publicación.');

                return;
            }

            $account = $destination->socialAccount;
            if (
                $destination->platform !== 'facebook'
                || $account === null
                || $account->account_type !== SocialAccount::TYPE_FACEBOOK_PAGE
                || $account->status !== SocialAccount::STATUS_CONNECTED
            ) {
                $this->failExecution($execution, $executions, 'La cuenta de página de Facebook ya no está conectada.');

                return;
            }

            if ($publication->created_by_user_id !== null) {
                $membership = WorkspaceMembership::query()
                    ->where('workspace_id', $publication->workspace_id)
                    ->where('user_id', $publication->created_by_user_id)
                    ->where('status', 'active')
                    ->first();

                if (
                    $membership === null
                    || $membership->canPublishToDestination(
                        WorkspaceMembershipDestinationPermission::DESTINATION_PUBLICATION,
                        (int) $destination->id,
                    ) === false
                ) {
                    $this->failExecution($execution, $executions, 'El creador ya no tiene autorización para publicar en esta página.');

                    return;
                }
            }

            $connection = $account->connections->first(
                fn (SocialAccountConnection $connection): bool => $connection->connection_type === SocialAccountConnection::TYPE_META_OAUTH
                    && $connection->provider === 'meta'
                    && $connection->status === 'connected'
                    && $connection->revoked_at === null,
            );

            $token = (string) ($connection?->credentials['access_token'] ?? '');
            $tokenType = (string) ($connection?->credentials['token_type'] ?? '');
            $appSecret = (string) config('services.meta.app_secret');

            if (
                $connection === null
                || $token === ''
                || $tokenType !== 'page'
                || $appSecret === ''
                || ($connection->expires_at && $connection->expires_at->isPast())
            ) {
                $this->failExecution($execution, $executions, 'La conexión OAuth de la página está vencida o incompleta.');

                return;
            }

            $pageId = (string) $account->external_account_id;
            $version = (string) config('services.meta.graph_version', 'v26.0');
            if (preg_match('/^v[0-9]+\\.[0-9]+$/', $version) !== 1) {
                $version = 'v26.0';
            }

            $baseUrl = "https://graph.facebook.com/{$version}/{$pageId}";
            $payload = [
                'access_token' => $token,
                'appsecret_proof' => hash_hmac('sha256', $token, $appSecret),
            ];

            if (filled($publication->image)) {
                $imageUrl = str_starts_with($publication->image, 'https://')
                    || str_starts_with($publication->image, 'http://')
                    ? $publication->image
                    : url(Storage::disk('public')->url($publication->image));

                $endpoint = $baseUrl.'/photos';
                $payload['url'] = $imageUrl;
                $payload['caption'] = (string) $publication->text;
            } else {
                $endpoint = $baseUrl.'/feed';
                $payload['message'] = (string) $publication->text;
            }

            try {
                $response = Http::acceptJson()
                    ->asForm()
                    ->timeout(45)
                    ->post($endpoint, $payload);

                if ($response->serverError()) {
                    $this->markUnknown($execution, 'Meta devolvió un error de servidor; verifica la página antes de reintentar.');

                    return;
                }

                if ($response->failed()) {
                    $message = (string) data_get($response->json(), 'error.message', 'Meta rechazó la publicación.');
                    $this->failExecution($execution, $executions, mb_substr(str_replace($token, '[redacted]', $message), 0, 500));

                    return;
                }

                $externalId = (string) (
                    $response->json('post_id')
                    ?? $response->json('id')
                    ?? ''
                );

                if ($externalId === '') {
                    $this->markUnknown($execution, 'Meta aceptó la solicitud sin devolver un identificador verificable.');

                    return;
                }

                $executions->succeed($execution, $externalId);
            } catch (ConnectionException $exception) {
                Log::warning('Meta Page publication transport result is unknown.', [
                    'execution_id' => $execution->id,
                    'exception_type' => $exception::class,
                ]);
                $this->markUnknown($execution, 'Se perdió la conexión con Meta durante la publicación; verifica la página antes de reintentar.');
            } catch (Throwable $exception) {
                Log::warning('Meta Page publication request failed.', [
                    'execution_id' => $execution->id,
                    'exception_type' => $exception::class,
                ]);
                $this->failExecution($execution, $executions, 'No fue posible completar la publicación oficial de Facebook.');
            }
        } finally {
            $context->clear();
        }
    }

    private function destinationId(string $targetKey): ?int
    {
        $prefix = 'meta_page_destination:';
        if (str_starts_with($targetKey, $prefix) === false) {
            return null;
        }

        $destinationId = substr($targetKey, strlen($prefix));

        return ctype_digit($destinationId) && (int) $destinationId > 0
            ? (int) $destinationId
            : null;
    }

    private function failExecution(
        PublicationExecution $execution,
        PublicationExecutionService $executions,
        string $message,
    ): void {
        if ($execution->fresh()->status === 'running') {
            $executions->fail($execution, $message);
        }
    }

    private function markUnknown(PublicationExecution $execution, string $message): void
    {
        if ($execution->fresh()->status !== 'running') {
            return;
        }

        $execution->update([
            'status' => 'unknown',
            'completed_at' => now(),
            'error_message' => $message,
        ]);
    }
}
