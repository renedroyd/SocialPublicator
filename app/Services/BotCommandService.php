<?php

namespace App\Services;

use App\Models\BotCommand;
use App\Models\PublicationExecution;
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

            // Reuse an outstanding retry command instead of queueing duplicates
            // when an operator double-clicks or repeats the action.
            $activeCommand = BotCommand::query()
                ->where('bot_name', $botName)
                ->where('type', 'retry_publication')
                ->whereIn('status', ['pending', 'claimed'])
                ->where('payload->execution_id', $execution->id)
                ->latest('id')
                ->first();

            if ($activeCommand !== null) {
                return $activeCommand;
            }

            return BotCommand::query()->create([
                'bot_name' => $botName,
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

    public function claim(string $botName): ?BotCommand
    {
        return DB::transaction(function () use ($botName): ?BotCommand {
            $command = BotCommand::query()
                ->where('bot_name', $botName)
                ->where('status', 'pending')
                ->where(function ($query): void {
                    $query
                        ->whereNull('available_at')
                        ->orWhere('available_at', '<=', now());
                })
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
        array $result = []
    ): BotCommand {
        return DB::transaction(function () use ($command, $botName, $result): BotCommand {
            $command = BotCommand::query()
                ->lockForUpdate()
                ->findOrFail($command->id);

            $this->assertClaimedByBot($command, $botName);

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
        bool $retry = true
    ): BotCommand {
        return DB::transaction(function () use ($command, $botName, $error, $retry): BotCommand {
            $command = BotCommand::query()
                ->lockForUpdate()
                ->findOrFail($command->id);

            $this->assertClaimedByBot($command, $botName);

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

    private function assertClaimedByBot(BotCommand $command, string $botName): void
    {
        if ($command->bot_name !== $botName || $command->status !== 'claimed') {
            throw new LogicException(
                "El comando {$command->id} no está reclamado por {$botName}."
            );
        }
    }
}
