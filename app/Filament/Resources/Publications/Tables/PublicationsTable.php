<?php

namespace App\Filament\Resources\Publications\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PublicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('time', 'asc')
            ->striped()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->searchPlaceholder('Buscar publicaciones...')
            ->searchDebounce('500ms')
            ->emptyStateHeading('No hay publicaciones')
            ->emptyStateDescription(
                'Crea una publicación para comenzar a configurar tu cronograma.'
            )
            ->emptyStateIcon('heroicon-o-megaphone')
            ->columns([
                TextColumn::make('time')
                    ->label('Hora')
                    ->time('H:i')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->icon('heroicon-m-clock')
                    ->weight('bold')
                    ->size('lg')
                    ->color('primary')
                    ->alignCenter()
                    ->width('110px')
                    ->extraAttributes([
                        'class' => 'whitespace-nowrap',
                    ]),

                TextColumn::make('next_execution_at')
                    ->label('Próxima ejecución')
                    ->getStateUsing(fn ($record) => $record->schedule?->next_run_at ?? $record->nextExecutionAt())
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->alignCenter()
                    ->placeholder('—')
                    ->icon('heroicon-o-clock')
                    ->color('primary')
                    ->weight('semibold')
                    ->width('155px'),

                TextColumn::make('recurrence_type')
                    ->label('Recurrencia')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'daily' => 'Diaria',
                            'weekly' => 'Semanal',
                            'hourly' => 'Cada X minutos',
                            default => 'Una vez',
                        }
                    )
                    ->color(
                        fn (string $state): string => match ($state) {
                            'daily' => 'success',
                            'weekly' => 'warning',
                            'hourly' => 'info',
                            default => 'gray',
                        }
                    )
                    ->icon(
                        fn (string $state): string => match ($state) {
                            'daily' => 'heroicon-o-calendar-days',
                            'weekly' => 'heroicon-o-calendar',
                            'hourly' => 'heroicon-o-arrow-path',
                            default => 'heroicon-o-minus-circle',
                        }
                    )
                    ->sortable()
                    ->alignCenter()
                    ->width('150px'),

                ImageColumn::make('image')
                    ->label('Vista previa')
                    ->getStateUsing(function ($record): ?string {
                        $image = $record->image;

                        if (blank($image)) {
                            return null;
                        }

                        if (filter_var($image, FILTER_VALIDATE_URL)) {
                            return $image;
                        }

                        return Storage::disk('public')->url(ltrim($image, '/'));
                    })
                    ->square()
                    ->size(64)
                    ->alignCenter()
                    ->extraAttributes([
                        'class' => 'rounded-xl overflow-hidden shadow-sm',
                    ])
                    ->extraImgAttributes([
                        'loading' => 'lazy',
                        'alt' => 'Vista previa de la publicación',
                        'class' => 'rounded-xl object-cover',
                    ])
                    ->placeholder('heroicon-o-photo'),

                TextColumn::make('text')
                    ->label('Contenido')
                    ->searchable()
                    ->wrap()
                    ->limit(160)
                    ->lineClamp(3)
                    ->weight('medium')
                    ->color('gray')
                    ->tooltip(
                        fn (TextColumn $column): ?string => filled($column->getState())
                            ? $column->getState()
                            : null
                    )
                    ->grow()
                    ->extraAttributes([
                        'class' => 'min-w-[220px]',
                    ]),

                TextColumn::make('destination')
                    ->label('Destino')
                    ->badge()
                    ->icon(
                        fn (string $state): string => match ($state) {
                            'whatsapp' => 'heroicon-o-chat-bubble-left-right',
                            'facebook' => 'heroicon-o-globe-alt',
                            'both' => 'heroicon-o-share',
                            default => 'heroicon-o-paper-airplane',
                        }
                    )
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'whatsapp' => 'WhatsApp',
                            'facebook' => 'Facebook',
                            'both' => 'WhatsApp + Facebook',
                            default => ucfirst($state),
                        }
                    )
                    ->color(
                        fn (string $state): string => match ($state) {
                            'whatsapp' => 'success',
                            'facebook' => 'info',
                            'both' => 'warning',
                            default => 'gray',
                        }
                    )
                    ->sortable()
                    ->searchable()
                    ->alignCenter()
                    ->width('170px'),

                ToggleColumn::make('active')
                    ->label('Activa')
                    ->sortable()
                    ->alignCenter()
                    ->onColor('success')
                    ->offColor('gray')
                    ->onIcon('heroicon-o-check-circle')
                    ->offIcon('heroicon-o-pause-circle')
                    ->tooltip(
                        fn (bool $state): string => $state
                            ? 'Desactivar publicación'
                            : 'Activar publicación'
                    )
                    ->width('90px'),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color('gray')
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil-square')
                        ->color('gray'),

                    DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation(),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Acciones')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation(),
                ]),
            ])
            ->recordUrl(null)
            ->recordAction(null);
    }
}
