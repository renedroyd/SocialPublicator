<?php

namespace App\Services\Workspaces;

use App\Models\Workspace;

class WorkspaceContext
{
    private ?int $workspaceId = null;

    public function set(int $workspaceId): void
    {
        $this->workspaceId = $workspaceId;
    }

    public function clear(): void
    {
        $this->workspaceId = null;
    }

    public function id(): ?int
    {
        if ($this->workspaceId !== null) {
            return $this->workspaceId;
        }

        // Backward compatibility for single-workspace CLI jobs and deployments.
        $workspaces = Workspace::query()->limit(2)->pluck('id');

        return $workspaces->count() === 1 ? (int) $workspaces->first() : null;
    }

    public function hasExplicitContext(): bool
    {
        return $this->workspaceId !== null;
    }
}
