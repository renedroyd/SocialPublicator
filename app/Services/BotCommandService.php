<?php

namespace App\Services;

use App\Models\BotCommand;
use App\Models\FacebookGroup;
use App\Models\PublicationExecution;
use App\Models\SocialAccountConnection;
use App\Models\WhatsAppGroup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class BotCommandService
{
    public function queueRetry(PublicationExecution $execution): BotCommand
    {
        $botName = (string) config('services.bot_api.command_bot_name', 'WBot');

        return DB::transaction(function () use ($execution, $botName): BotCommand {
            $execution = PublicationExecution::query()
                ->lockForUpdate()
                ->findOrFail($execution->id);

            if (! in_array($execution->status, ['failed', 'pending'], true)) {
                throw new LogicException(
                    "La ejecución {$execution->id} no puede reintentarse desde {$execution->status}."
                );
            }

            $agentInstanceId = $this->resolveAgentInstanceId($execution);

            $activeCommandQuery = BotCommand::query()
                ->where('bot_name', $botName)
                ->where('type', 'retry_publication')
                ->whereIn('status', ['pending', 'claimed'])
                ->where('payload->execution_id', $execution->id);

            if ($agentInstanceId === null) {
                $activeCommandQuery->whereNull('agent_instance_id');
            } else {
                $activeCommandQuery->where('agent_instance_id', $agentInstanceId);
            }

            $activeCommand = $activeCommandQuery->latest('id')->first();

            if ($activeCommand !== null) {
                return $activeCommand;
            }

            return BotCommand::query()->create([
                'bot_name' => $botName,
                'agent_instance_id' => $agentInstanceId,
                'type' => 'retry_publication',
                'payload' => [
                    'execution_id' => $execution->id,
                    'publication_id' => $execution->publication_id,
                    'occurrence_id' => $execution->occurrence_id,
                    'channel' => $execution->channel,
                    'target_key' => $execution->target_key,
                    'facebook_group_id' => $execution->facebook_group_id,
                ],
                'idempotency_key' => 'retry_'.$execution->id.'_'.Str::uuid(),
                'status' => 'pending',
                'available_at' => now(),
                'max_attempts' => 3,
            ]);
        });
    }

    public function claim(
        string $botName,
        ?string $instanceId = null,
        ?array $authorizedAccountIds = null,
    ): ?BotCommand {
        return DB::transaction(function () use ($botName, $instanceId, $authorizedAccountIds): ?BotCommand {
            $query = BotCommand::query()
                ->where('bot_name', $botName)
                ->where('status', 'pending')
                ->where(function ($query): void {
                    $query
                        ->whereNull('available_at')
                        ->orWhere('available_at', '<=', now());
                });

            if ($instanceId === null) {
                $query->whereNull('agent_instance_id');
            } elseif ($instanceId === $botName) {
                $query->where(function ($query) use ($instanceId): void {
                    $query->whereNull('agent_instance_id')
                        ->orWhere('agent_instance_id', $instanceId);
                });
            } else {
                $query->where('agent_instance_id', $instanceId);
            }

            $skippedCommandIds = [];

            while (true) {
                $candidateQuery = clone $query;

                if ($skippedCommandIds !== []) {
                    $candidateQuery->whereNotIn('id', $skippedCommandIds);
                }

                $command = $candidateQuery
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($command === null) {
                    return null;
                }

                if (
                    is_array($authorizedAccountIds)
                    && ! $this->commandBelongsToAuthorizedAccount($command, $authorizedAccountIds)
                ) {
                    $skippedCommandIds[] = $command->id;

                    continue;
                }

                $command->update([
                    'status' => 'claimed',
                    'attempts' => $command->attempts + 1,
                    'claimed_at' => now(),
                    'error_message' => null,
                ]);

                return $command->refresh();
            }
        });
    }

    public function complete(
        BotCommand $command,
        string $botName,
        array $result = [],
        ?string $instanceId = null,
        ?array $authorizedAccountIds = null,
    ): BotCommand {
        return DB::transaction(function () use ($command, $botName, $result, $instanceId, $authorizedAccountIds): BotCommand {
            $command = BotCommand::query()
                ->lockForUpdate()
                ->findOrFail($command->id);

            $this->assertClaimedByAgent($command, $botName, $instanceId, $authorizedAccountIds);

            $command->update([
                'status' => 'succeeded',
                'completed_at' => now(),
                'result' => $result,
                'error_message' => null,
            ]);

            return $command->refresh();
        });
    }

    public function fail(
        BotCommand $command,
        string $botName,
        string $error,
        bool $retry = true,
        ?string $instanceId = null,
        ?array $authorizedAccountIds = null,
    ): BotCommand {
        return DB::transaction(function () use ($command, $botName, $error, $retry, $instanceId, $authorizedAccountIds): BotCommand {
            $command = BotCommand::query()
                ->lockForUpdate()
                ->findOrFail($command->id);

            $this->assertClaimedByAgent($command, $botName, $instanceId, $authorizedAccountIds);

            $canRetry = $retry && $command->attempts < $command->max_attempts;

            $command->update([
                'status' => $canRetry ? 'pending' : 'failed',
                'available_at' => $canRetry ? now()->addSeconds(30) : null,
                'claimed_at' => null,
                'completed_at' => $canRetry ? null : now(),
                'error_message' => $error,
            ]);

            return $command->refresh();
        });
    }

    private function resolveAgentInstanceId(PublicationExecution $execution): ?string
    {
        $group = $this->executionTarget($execution);
        $connectionType = $this->connectionTypeForChannel($execution->channel);

        if (! $group?->social_account_id || ! $connectionType) {
            return null;
        }

        return SocialAccountConnection::query()
            ->where('social_account_id', $group->social_account_id)
            ->where('connection_type', $connectionType)
            ->where('status', 'connected')
            ->value('agent_instance_id');
    }

    private function executionTarget(PublicationExecution $execution): WhatsAppGroup|FacebookGroup|null
    {
        if ($execution->channel === 'whatsapp') {
            return WhatsAppGroup::query()
                ->where(function ($query) use ($execution): void {
                    $query->where('whatsapp_id', $execution->target_key)
                        ->orWhere('name', $execution->target_key);
                })
                ->first();
        }

        if ($execution->channel === 'facebook') {
            return $execution->facebookGroup
                ?? ($execution->facebook_group_id
                    ? FacebookGroup::query()->find($execution->facebook_group_id)
                    : FacebookGroup::query()->where('facebook_id', $execution->target_key)->first());
        }

        return null;
    }

    private function connectionTypeForChannel(string $channel): ?string
    {
        return match ($channel) {
            'whatsapp' => SocialAccountConnection::TYPE_WPP_CONNECT_QR,
            'facebook' => SocialAccountConnection::TYPE_BROWSER_SESSION,
            default => null,
        };
    }

    private function commandBelongsToAuthorizedAccount(BotCommand $command, array $authorizedAccountIds): bool
    {
        if ($command->type !== 'retry_publication') {
            return false;
        }

        $executionId = $command->payload['execution_id'] ?? null;
        $execution = $executionId ? PublicationExecution::query()->find($executionId) : null;
        $group = $execution ? $this->executionTarget($execution) : null;

        return $group !== null
            && $group->social_account_id !== null
            && in_array((int) $group->social_account_id, $authorizedAccountIds, true);
    }

    private function assertClaimedByAgent(
        BotCommand $command,
        string $botName,
        ?string $instanceId,
        ?array $authorizedAccountIds,
    ): void {
        if ($command->bot_name !== $botName || $command->status !== 'claimed') {
            throw new LogicException(
                "El comando {$command->id} no está reclamado por {$botName}."
            );
        }

        $allowedInstanceIds = $instanceId === $botName
            ? [null, $botName]
            : [$instanceId];

        if (! in_array($command->agent_instance_id, $allowedInstanceIds, true)) {
            throw new AuthorizationException('El comando está asignado a otra instancia de WBot.');
        }

        if (
            is_array($authorizedAccountIds)
            && ! $this->commandBelongsToAuthorizedAccount($command, $authorizedAccountIds)
        ) {
            throw new AuthorizationException('El agente no está autorizado para la cuenta social del comando.');
        }
    }
}
