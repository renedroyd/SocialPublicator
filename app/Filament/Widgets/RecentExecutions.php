<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Publications\PublicationResource;
use App\Models\BotCommand;
use App\Jobs\PublishMetaPagePublication;
use App\Models\PublicationDestination;
use App\Models\PublicationExecution;
use App\Models\WorkspaceMembershipDestinationPermission;
use App\Services\PublicationExecutionService;
use App\Services\Workspaces\WorkspaceContext;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Services\BotCommandService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentExecutions extends TableWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Actividad reciente';

    protected static ?string $description = 'Historial operativo por publicación, canal, destino e intentos de entrega.';

    protected ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PublicationExecution::query()
                    ->with(['publication', 'facebookGroup'])
                    ->latest('created_at')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Registrada')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('publication.text')
                    ->label('Publicación')
                    ->limit(70)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state, PublicationExecution $record): string => $this->isMetaPageExecution($record)
                            ? 'Facebook · Página (API)'
                            : match ($state) {
                                'whatsapp' => 'WhatsApp',
                                'facebook' => 'Facebook · Grupos',
                                default => ucfirst($state ?? '—'),
                            }
                    ),

                TextColumn::make('target_key')
                    ->label('Destino')
                    ->formatStateUsing(fn (?string $state): string => $this->formatTargetLabel($state))
                    ->limit(45)
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('occurrence_id')
                    ->label('Ocurrencia')
                    ->limit(32)
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'succeeded' => 'success',
                            'failed' => 'danger',
                            'pending' => 'warning',
                            'running' => 'info',
                            'unknown' => 'warning',
                            default => 'gray',
                        }
                    )
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'succeeded' => 'Exitosa',
                            'failed' => 'Fallida',
                            'pending' => 'Pendiente',
                            'running' => 'Ejecutando',
                            'unknown' => 'Resultado desconocido',
                            default => ucfirst($state ?? '—'),
                        }
                    ),

                TextColumn::make('attempts')
                    ->label('Intentos')
                    ->badge()
                    ->color(fn (?int $state): string => ($state ?? 0) > 1 ? 'warning' : 'gray')
                    ->sortable(),

                TextColumn::make('started_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i:s')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('completed_at')
                    ->label('Completada')
                    ->dateTime('d/m/Y H:i:s')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('external_id')
                    ->label('ID externo')
                    ->limit(28)
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(60)
                    ->placeholder('—')
                    ->color('danger')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'succeeded' => 'Exitosas',
                        'failed' => 'Fallidas',
                        'pending' => 'Pendientes',
                        'running' => 'Ejecutando',
                        'unknown' => 'Resultado desconocido',
                    ]),

                SelectFilter::make('publication_id')
                    ->label('Publicación')
                    ->relationship('publication', 'text')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('channel')
                    ->label('Canal')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'facebook' => 'Facebook',
                    ]),
            ])
            ->recordActions([
                Action::make('retryMetaPage')
                    ->label('Reintentar página')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (PublicationExecution $record): bool => $this->canManageExecutions()
                        && $this->isMetaPageExecution($record)
                        && in_array($record->status, ['pending', 'failed'], true))
                    ->requiresConfirmation()
                    ->modalDescription('Se volverá a enviar esta publicación a la página oficial mediante Meta Graph API.')
                    ->action(function (PublicationExecution $record): void {
                        DB::transaction(function () use ($record): void {
                            $fresh = PublicationExecution::query()
                                ->lockForUpdate()
                                ->findOrFail($record->id);
                            $started = app(PublicationExecutionService::class)->start($fresh);
                            PublishMetaPagePublication::dispatch($started->id)->afterCommit();
                        });

                        Notification::make()->title('Reintento de página encolado')->success()->send();
                    }),

                Action::make('resolveMetaPagePublished')
                    ->label('Confirmar publicada')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PublicationExecution $record): bool => $this->canManageExecutions()
                        && $this->isMetaPageExecution($record)
                        && $record->status === 'unknown')
                    ->form([
                        TextInput::make('external_id')
                            ->label('ID externo de la publicación en Meta')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (PublicationExecution $record, array $data): void {
                        app(PublicationExecutionService::class)->succeed(
                            $record,
                            trim((string) $data['external_id']),
                        );

                        Notification::make()->title('Ejecución reconciliada como exitosa')->success()->send();
                    }),

                Action::make('retryMetaPageUnknown')
                    ->label('Reintentar tras verificar')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn (PublicationExecution $record): bool => $this->canManageExecutions()
                        && $this->isMetaPageExecution($record)
                        && $record->status === 'unknown')
                    ->form([
                        TextInput::make('confirmation')
                            ->label('Escribe CONFIRMO QUE NO EXISTE')
                            ->helperText('Antes de continuar, revisa la página en Facebook y confirma que Meta no creó la publicación.')
                            ->required()
                            ->rule('in:CONFIRMO QUE NO EXISTE'),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription('Esta operación puede crear una publicación duplicada si no verificaste que el envío anterior no llegó a Facebook.')
                    ->action(function (PublicationExecution $record): void {
                        DB::transaction(function () use ($record): void {
                            $fresh = PublicationExecution::query()
                                ->lockForUpdate()
                                ->findOrFail($record->id);
                            $started = app(PublicationExecutionService::class)->retryUnknown($fresh, true);
                            PublishMetaPagePublication::dispatch($started->id)->afterCommit();
                        });

                        Notification::make()->title('Reintento verificado encolado')->success()->send();
                    }),

                Action::make('retry')
                    ->label('Reintentar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(
                        fn (PublicationExecution $record): bool => $this->canManageExecutions()
                            && $this->isMetaPageExecution($record) === false
                            && in_array($record->status, ['failed', 'pending'], true)
                    )
                    ->requiresConfirmation()
                    ->action(
                        fn (PublicationExecution $record): BotCommand => app(
                            BotCommandService::class
                        )->queueRetry($record)
                    ),

                Action::make('viewPublication')
                    ->label('Ver publicación')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(
                        fn (PublicationExecution $record): string => PublicationResource::getUrl(
                            'edit',
                            ['record' => $record->publication_id],
                        )
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25]);
    }

    private function isMetaPageExecution(PublicationExecution $record): bool
    {
        return str_starts_with($record->target_key, 'meta_page_destination:');
    }

    private function formatTargetLabel(?string $targetKey): string
    {
        $prefix = 'meta_page_destination:';
        if ($targetKey === null || $targetKey === '' || str_starts_with($targetKey, $prefix) === false) {
            return $targetKey ?? '—';
        }

        $destinationId = substr($targetKey, strlen($prefix));
        if (ctype_digit($destinationId) === false) {
            return 'Página Meta';
        }

        $membership = $this->currentWorkspaceMembership();
        if (
            $membership === null
            || $membership->canUseDestination(
                WorkspaceMembershipDestinationPermission::DESTINATION_PUBLICATION,
                (int) $destinationId,
                'view',
            ) === false
        ) {
            return 'Página Meta';
        }

        $destination = PublicationDestination::query()->find((int) $destinationId);

        return $destination?->name
            ? $destination->name.' · Página Meta'
            : 'Página Meta #'.$destinationId;
    }

    private function canManageExecutions(): bool
    {
        return $this->currentWorkspaceMembership()?->hasPermission('executions.manage') ?? false;
    }

    private function currentWorkspaceMembership()
    {
        return auth()->user()?->activeWorkspaceMemberships()
            ->where('workspace_id', app(WorkspaceContext::class)->id())
            ->first();
    }
}
