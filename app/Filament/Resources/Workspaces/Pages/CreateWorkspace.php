<?php

namespace App\Filament\Resources\Workspaces\Pages;

use App\Filament\Resources\Workspaces\WorkspaceResource;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateWorkspace extends CreateRecord
{
    protected static string $resource = WorkspaceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Workspace {
            $workspace = Workspace::query()->create([
                'name' => $data['name'],
                'slug' => Str::slug($data['slug'] ?: $data['name']),
                'created_by_user_id' => auth()->id(),
            ]);

            WorkspaceMembership::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => auth()->id(),
                'role' => WorkspaceMembership::ROLE_OWNER,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return $workspace;
        });
    }

    protected function afterCreate(): void
    {
        session()->put('active_workspace_id', $this->record->id);

        Notification::make()
            ->title('Espacio de trabajo creado')
            ->body('Ahora puedes invitar miembros y configurar las cuentas sociales de este espacio.')
            ->success()
            ->send();
    }
}
