<?php

namespace App\Services;

use App\Models\BotCommand;
use App\Models\FacebookGroup;
use App\Models\PublicationExecution;
use App\Models\SocialAccountConnection;
use App\Models\WhatsAppGroup;
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

    public function claim(string $botName, ?string $instanceId = null): ?BotCommand
    {
        return DB::transaction(function () use ($botName, $instanceId): ?BotCommand {
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

            $command = $query
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($command === null) {
                return null;
            }

            $command->update([
                'status' => 'claimed',
                'attempts' => $command->attempts + 1,
                'claimed_at' => now(),
                'error_message' => null,
            ]);

            return $command->refresh();
        });
    }

    public function complete(
        BotCommand $command,
        string $botName,
        array $result = [],
        ?string $instanceId = null,
    ): BotCommand {
        return DB::transaction(function () use ($command, $botName, $result, $instanceId): BotCommand {
            $command = BotCommand::query()
                ->lockForUpdate()
                ->findOrFail($command->id);

            $this->assertClaimedByAgent($command, $botName, $instanceId);

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
    ): BotCommand {
        return DB::transaction(function () use ($command, $botName, $error, $retry, $instanceId): BotCommand {
            $command = BotCommand::query()
                ->lockForUpdate()
                ->findOrFail($command->id);

            $this->assertClaimedByAgent($command, $botName, $instanceId);

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
        $group = null;
        $connectionType = null;

        if ($execution->channel === 'whatsapp') {
            $group = WhatsAppGroup::query()
                ->where(function ($query) use ($execution): void {
                    $query->where('whatsapp_id', $execution->target_key)
                        ->orWhere('name', $execution->target_key);
                })
                ->first();
            $connectionType = SocialAccountConnection::TYPE_WPP_CONNECT_QR;
        } elseif ($execution->channel === 'facebook') {
            $group = $execution->facebookGroup
                ?? FacebookGroup::query()->find($execution->facebook_group_id);
            $connectionType = SocialAccountConnection::TYPE_BROWSER_SESSION;
        }

        if (! $group?->social_account_id || ! $connectionType) {
            return null;
        }

        return SocialAccountConnection::query()
            ->where('social_account_id', $group->social_account_id)
            ->where('connection_type', $connectionType)
            ->where('status', 'connected')
            ->value('agent_instance_id');
    }

    private function assertClaimedByAgent(
        BotCommand $command,
        string $botName,
        ?string $instanceId,
    ): void {
        if ($command->bot_name !== $botName || $command->status !== 'claimed') {
            throw new LogicException(
                "El comando {$command->id} no está reclamado por {$botName}."
            );
        }

        if ($command->agent_instance_id !== null && $command->agent_instance_id !== $instanceId) {
            throw new LogicException(
                "El comando {$command->id} está asignado a otra instancia de WBot."
            );
        }
    }
}
