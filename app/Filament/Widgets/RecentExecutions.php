<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Publications\PublicationResource;
use App\Models\BotCommand;
use App\Models\PublicationExecution;
use App\Services\BotCommandService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentExecutions extends TableWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Actividad reciente';

    protected static ?string $description = 'Últimas ejecuciones registradas por el sistema de publicaciones.';

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
                    ->wrap(),

                TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'whatsapp' => 'WhatsApp',
                            'facebook' => 'Facebook',
                            default => ucfirst($state ?? '—'),
                        }
                    ),

                TextColumn::make('target_key')
                    ->label('Destino')
                    ->limit(35)
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'succeeded' => 'success',
                            'failed' => 'danger',
                            'pending' => 'warning',
                            'running' => 'info',
                            default => 'gray',
                        }
                    )
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'succeeded' => 'Exitosa',
                            'failed' => 'Fallida',
                            'pending' => 'Pendiente',
                            'running' => 'Ejecutando',
                            default => ucfirst($state ?? '—'),
                        }
                    ),

                TextColumn::make('completed_at')
                    ->label('Completada')
                    ->dateTime('d/m/Y H:i:s')
                    ->placeholder('—')
                    ->sortable(),

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
                    ]),

                SelectFilter::make('channel')
                    ->label('Canal')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'facebook' => 'Facebook',
                    ]),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Reintentar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(
                        fn (PublicationExecution $record): bool => in_array(
                            $record->status,
                            ['failed', 'pending'],
                            true
                        )
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
}
