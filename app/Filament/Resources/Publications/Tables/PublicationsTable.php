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
            ->paginated([10, 25, 50])
            ->searchPlaceholder('Buscar publicaciones...')
            ->emptyStateHeading('No hay publicaciones')
            ->emptyStateDescription('Crea una publicación para comenzar a configurar tu cronograma.')
            ->emptyStateIcon('heroicon-o-megaphone')
            ->columns([
                TextColumn::make('time')
                    ->label('Hora')
                    ->time('H:i')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->icon('heroicon-o-clock')
                    ->weight('bold')
                    ->size('lg')
                    ->alignCenter(),

                ImageColumn::make('image')
                    ->label('Imagen')
                    ->disk('public')
                    ->square()
                    ->size(70)
                    ->alignCenter(),

                TextColumn::make('text')
                    ->label('Contenido')
                    ->searchable()
                    ->wrap()
                    ->limit(180)
                    ->lineClamp(3)
                    ->weight('medium')
                    ->color('gray')
                    ->tooltip(fn(TextColumn $column): ?string => filled($column->getState()) ? $column->getState() : null)
                    ->grow(),

                TextColumn::make('destination')
                    ->label('Destino')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'whatsapp' => 'WhatsApp',
                        'facebook' => 'Facebook',
                        'both' => 'WhatsApp + Facebook',
                        default => ucfirst($state),
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'whatsapp' => 'success',
                        'facebook' => 'info',
                        'both' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),

                IconColumn::make('active')
                    ->label('Estado')
                    ->boolean()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color('gray')
                    ->icon('heroicon-o-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()->label('Editar')->icon('heroicon-o-pencil-square'),
                DeleteAction::make()->label('Eliminar')->icon('heroicon-o-trash'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->icon('heroicon-o-trash'),
                ]),
            ]);
    }
}
