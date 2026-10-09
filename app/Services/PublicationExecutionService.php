<?php

namespace App\Services;

use App\Models\PublicationExecution;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

class PublicationExecutionService
{
    public function getOrCreate(array $attributes): PublicationExecution
    {
        $attributes['status'] ??= 'pending';

        try {
            return PublicationExecution::create($attributes);
        } catch (QueryException $exception) {
            $execution = PublicationExecution::query()
                ->where('occurrence_id', $attributes['occurrence_id'])
                ->where('target_key', $attributes['target_key'])
                ->first();

            if ($execution !== null) {
                return $execution;
            }

            throw $exception;
        }
    }

    public function start(PublicationExecution $execution): PublicationExecution
    {
        return DB::transaction(function () use ($execution): PublicationExecution {
            $execution = PublicationExecution::query()
                ->lockForUpdate()
                ->findOrFail($execution->id);

            if (! in_array($execution->status, ['pending', 'failed'], true)) {
                throw new LogicException(
                    "Execution {$execution->id} cannot be started from status {$execution->status}."
                );
            }

            $execution->update([
                'status' => 'running',
                'started_at' => now(),
                'completed_at' => null,
                'error_message' => null,
                'attempts' => DB::raw('attempts + 1'),
            ]);

            return $execution->refresh();
        });
    }

    public function succeed(
        PublicationExecution $execution,
        ?string $externalId = null
    ): PublicationExecution {
        return DB::transaction(function () use ($execution, $externalId): PublicationExecution {
            $execution = PublicationExecution::query()
                ->lockForUpdate()
                ->findOrFail($execution->id);

            if ($execution->status !== 'running') {
                throw new LogicException(
                    "Execution {$execution->id} cannot succeed from status {$execution->status}."
                );
            }

            $execution->update([
                'status' => 'succeeded',
                'completed_at' => now(),
                'external_id' => $externalId,
                'error_message' => null,
            ]);

            return $execution->refresh();
        });
    }

    public function fail(
        PublicationExecution $execution,
        string $errorMessage
    ): PublicationExecution {
        return DB::transaction(function () use ($execution, $errorMessage): PublicationExecution {
            $execution = PublicationExecution::query()
                ->lockForUpdate()
                ->findOrFail($execution->id);

            if ($execution->status !== 'running') {
                throw new LogicException(
                    "Execution {$execution->id} cannot fail from status {$execution->status}."
                );
            }

            $execution->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $errorMessage,
            ]);

            return $execution->refresh();
        });
    }
}
