<?php

namespace App\Filament\Resources\Publications\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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

                ImageColumn::make('image')
                    ->label('Vista previa')
                    ->disk('public')
                    ->square()
                    ->size(64)
                    ->alignCenter()
                    ->extraAttributes([
                        'class' => 'rounded-xl overflow-hidden shadow-sm',
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

                IconColumn::make('active')
                    ->label('Estado')
                    ->boolean()
                    ->sortable()
                    ->alignCenter()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-pause-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(
                        fn (bool $state): string => $state
                            ? 'Publicación activa'
                            : 'Publicación inactiva'
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

